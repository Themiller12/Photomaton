<?php
// Capture via Sony SDK CLI (mode 'sony_sdk') en USB ou IP selon support.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';

if($CAPTURE_MODE !== 'sony_sdk') { http_response_code(400); echo json_encode(['error'=>'Mode sony_sdk non actif']); exit; }
if(!file_exists($SONY_SDK_CLI)) { http_response_code(500); echo json_encode(['error'=>'Binaire SDK introuvable','path'=>$SONY_SDK_CLI]); exit; }

// Lister fichiers avant
$before = glob($CAPTURE_DIR.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [];
$beforeMap = [];
foreach($before as $b) $beforeMap[realpath($b)] = true;

$cmd = '"'.str_replace('"','""',$SONY_SDK_CLI).'" --auto --capture-once --wait=3000';
$start = microtime(true);
exec($cmd.' 2>&1', $out, $code);

if($code !== 0){
    http_response_code(500);
    echo json_encode(['error'=>'Echec exécution RemoteCli','exit'=>$code,'output'=>$out,'command'=>$cmd]);
    exit;
}

// Attendre nouveau fichier (jusqu'à 12s)
$timeout = isset($CAPTURE_TIMEOUT)?$CAPTURE_TIMEOUT:12;
$newFile = null;
do {
    usleep(300000);
    $now = glob($CAPTURE_DIR.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [];
    foreach($now as $f){ $rp=realpath($f); if(!isset($beforeMap[$rp])){ $newFile=$f; break 2; } }
} while(microtime(true)-$start < $timeout);

if(!$newFile){
    http_response_code(504);
    echo json_encode(['error'=>'Timeout: aucun nouveau fichier','output'=>$out,'command'=>$cmd]);
    exit;
}

echo json_encode(['file'=>'captures/'.basename($newFile),'output'=>$out,'command'=>$cmd]);
?>
