<?php
// Mode folder_watch: attend un nouveau fichier déposé dans captures/ (par ex. par Imaging Edge Desktop configuré en dossier de destination)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
if($CAPTURE_MODE !== 'folder_watch') { http_response_code(400); echo json_encode(['error'=>'Mode folder_watch non actif']); exit; }
if(!is_dir($CAPTURE_DIR)) { http_response_code(500); echo json_encode(['error'=>'Dossier captures manquant']); exit; }

$before = glob($CAPTURE_DIR.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [];
$map = [];
foreach($before as $b) $map[realpath($b)] = true;

$timeout = isset($CAPTURE_TIMEOUT)?$CAPTURE_TIMEOUT:12;
$start = microtime(true);
$newFile = null;

do {
    usleep(250000);
    $cur = glob($CAPTURE_DIR.'/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [];
    foreach($cur as $f){ $rp = realpath($f); if(!isset($map[$rp])){ $newFile = $f; break 2; } }
} while(microtime(true)-$start < $timeout);

if(!$newFile){ http_response_code(504); echo json_encode(['error'=>'Timeout: aucun nouveau fichier','hint'=>'Déclenche sur la caméra / Imaging Edge doit enregistrer dans captures/']); exit; }

echo json_encode(['file'=>'captures/'.basename($newFile),'mode'=>'folder_watch']);
?>