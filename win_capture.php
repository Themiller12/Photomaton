<?php
/**
 * Capture une photo unique via digiCamControl (Windows CLI) et renvoie son chemin relatif.
 * Front-end l'appelle 3 fois avec un compte à rebours côté client.
 * Réponse JSON: { file: "captures/xxx.jpg" } ou { error: "message" }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

if(strtolower(substr(PHP_OS,0,3)) !== 'win') {
    http_response_code(400);
    echo json_encode(['error'=>'Endpoint Windows seulement']);
    exit;
}

if($CAPTURE_MODE !== 'dslr_win') {
    http_response_code(400);
    echo json_encode(['error'=>'Mode capture non configuré pour DSLR Windows']);
    exit;
}

if(!file_exists($CAMERA_CMD)) {
    http_response_code(500);
    echo json_encode(['error'=>'CameraControlCmd introuvable','path'=>$CAMERA_CMD]);
    exit;
}


// Lister les fichiers existants avant capture (toutes extensions surveillées)
// Inclure tous les fichiers JPEG sans restriction de nom car digiCamControl peut ignorer /filename
$extPattern = '{'.implode(',', array_map(function($e){return '*.'.preg_replace('/[^a-zA-Z0-9]/','',$e);}, $CAPTURE_EXTS)).'}';
$before = glob($CAPTURE_DIR.'/'.$extPattern, GLOB_BRACE) ?: [];
$beforeMap = [];
foreach($before as $b) { $beforeMap[realpath($b)] = filemtime($b); }

// Initialiser lockHandle (pour compatibilité avec code existant)
$lockHandle = null;

// Fonction de quoting adaptée Windows : utiliser des doubles quotes si sous Windows car les simples quotes ne sont pas traitées par cmd.exe
function win_quote($s){
    if(stripos(PHP_OS,'WIN') === 0){
        // Échapper les doubles quotes en les doublant
        return '"'.str_replace('"','""',$s).'"';
    }
    return escapeshellarg($s);
}

// Convertir le chemin Unix vers Windows et s'assurer qu'il est absolu
$winCaptureDir = str_replace('/', '\\', $CAPTURE_DIR);
if(!is_absolute_path($winCaptureDir)) {
    $winCaptureDir = realpath($winCaptureDir);
}

function is_absolute_path($path) {
    return preg_match('/^[A-Z]:\\\\/', $path) || preg_match('/^\//', $path);
}

// Simplifier la commande - digiCamControl ignore souvent /filename de toute façon
$cmd = win_quote($CAMERA_CMD).' /capture /folder '.win_quote($winCaptureDir);

$start = microtime(true);
// Exécuter (rediriger erreurs)
exec($cmd.' 2>&1', $outLines, $code);
if(isset($DEBUG_CAPTURE) && $DEBUG_CAPTURE){
    file_put_contents($CAPTURE_LOG_FILE, date('Y-m-d H:i:s')." CMD=$cmd\nWIN_DIR=$winCaptureDir\nEXIT=$code\nOUTPUT=".implode("\n", $outLines)."\n-- BEFORE COUNT=".count($beforeMap)."\n", FILE_APPEND);
}
// Détection appareil non trouvé (peut arriver avec EXIT=0)
$joined = strtolower(implode("\n", $outLines));
if(strpos($joined, 'no connected device was found') !== false){
    if($lockHandle) flock($lockHandle, LOCK_UN);
    http_response_code(500);
    echo json_encode([
        'error'=>'Aucun appareil détecté par digiCamControl',
        'hint'=>'Connectez l\'appareil via USB en mode PC Remote / Tethering. Le Wi-Fi Sony n\'est pas supporté par CameraControlCmd.',
        'command'=>$cmd,
        'output'=>$outLines
    ]);
    if(isset($DEBUG_CAPTURE) && $DEBUG_CAPTURE){
        file_put_contents($CAPTURE_LOG_FILE, date('Y-m-d H:i:s')." DEVICE_NOT_FOUND\n", FILE_APPEND);
    }
    exit;
}
if($code !== 0) {
    if($lockHandle) flock($lockHandle, LOCK_UN);
    http_response_code(500);
    echo json_encode(['error'=>'Echec commande capture','details'=>$outLines,'command'=>$cmd,'exit'=>$code]);
    exit;
}

// Attendre apparition d'un nouveau fichier image (extensions configurées)
// Note: digiCamControl peut ignorer /filename et utiliser ses propres noms (DSC_XXXX)
$newFile = null;
do {
    usleep(300000); // 0.3s
    $nowList = glob($CAPTURE_DIR.'/'.$extPattern, GLOB_BRACE) ?: [];
    foreach($nowList as $f) {
        $rp = realpath($f);
        $mtime = filemtime($f);
        // Nouveau fichier OU fichier modifié après le début de la capture
        if(!isset($beforeMap[$rp]) || $mtime > $start) {
            $newFile = $f;
            break 2;
        }
    }
} while((microtime(true)-$start) < $CAPTURE_TIMEOUT);

if(!$newFile) {
    if($lockHandle) flock($lockHandle, LOCK_UN);
    http_response_code(504);
    // Ajouter liste fichiers actuels pour debug (noms + mtime)
    $nowList = glob($CAPTURE_DIR.'/'.$extPattern, GLOB_BRACE) ?: [];
    $listing = [];
    foreach($nowList as $f){ $listing[] = basename($f).':'.filemtime($f); }
    echo json_encode([
        'error'=>'Timeout capture (fichier non détecté)',
        'command'=>$cmd,
        'output'=>$outLines,
        'files_after'=>$listing,
        'timeout_seconds'=>$CAPTURE_TIMEOUT
    ]);
    if(isset($DEBUG_CAPTURE) && $DEBUG_CAPTURE){
        file_put_contents($CAPTURE_LOG_FILE, date('Y-m-d H:i:s')." TIMEOUT files_after=".implode(',', $listing)."\n", FILE_APPEND);
    }
    exit;
}

$rel = 'captures/'.basename($newFile);

// Libérer lock (mais garder fichier lock pour réutilisation)
if($lockHandle) flock($lockHandle, LOCK_UN);

if(isset($DEBUG_CAPTURE) && $DEBUG_CAPTURE){
    file_put_contents($CAPTURE_LOG_FILE, date('Y-m-d H:i:s')." NEWFILE=".$rel."\n", FILE_APPEND);
}
echo json_encode(['file'=>$rel,'command'=>$cmd]);
?>
