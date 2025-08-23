<?php
/**
 * Préflight USB: vérifie disponibilité de digiCamControl, liste appareils, tente (optionnel) une capture test.
 * GET params:
 *   test=1  -> réalise une capture test et supprime le fichier créé.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';

if(strtolower(substr(PHP_OS,0,3)) !== 'win') { http_response_code(400); echo json_encode(['error'=>'Windows uniquement']); exit; }
if(!file_exists($CAMERA_CMD)) { http_response_code(500); echo json_encode(['error'=>'CameraControlCmd introuvable','path'=>$CAMERA_CMD]); exit; }

function win_quote($s){ if(stripos(PHP_OS,'WIN')===0){ return '"'.str_replace('"','""',$s).'"'; } return escapeshellarg($s);} 

$responses = [];

// 1. Liste appareils
$listCmd = win_quote($CAMERA_CMD).' /list';
exec($listCmd.' 2>&1', $listOut, $listCode);
$devices = [];
foreach($listOut as $line){
  if(preg_match('/(canon|nikon|sony|fujifilm|pentax|olympus|panasonic)/i',$line)) $devices[] = trim($line);
}
$responses['list'] = [ 'command'=>$listCmd, 'exit'=>$listCode, 'raw'=>$listOut, 'devices'=>$devices ];

// 2. (Optionnel) capture test
if(!empty($_GET['test'])){
  $pattern = isset($CAPTURE_FILENAME_PATTERN) ? $CAPTURE_FILENAME_PATTERN : 'capture_%Y%m%d_%H%M%S_%i.jpg';
  $testPattern = 'preflight_%Y%m%d_%H%M%S_%i.jpg';
  $capCmd = win_quote($CAMERA_CMD).' /capture /folder '.win_quote($CAPTURE_DIR).' /filename '.win_quote($testPattern);
  $exts = isset($CAPTURE_EXTS)?$CAPTURE_EXTS:['jpg','jpeg','JPG','JPEG'];
  $pattern = '{'.implode(',', array_map(fn($e)=>'*.'.preg_replace('/[^a-zA-Z0-9]/','',$e), $exts)).'}';
  $before = glob($CAPTURE_DIR.'/'.$pattern, GLOB_BRACE) ?: [];
  $beforeMap = [];
  foreach($before as $b) $beforeMap[realpath($b)] = true;
  exec($capCmd.' 2>&1', $capOut, $capCode);
  $newFile = null;
  $start = microtime(true);
  do {
    usleep(250000);
    $now = glob($CAPTURE_DIR.'/'.$pattern, GLOB_BRACE) ?: [];
    foreach($now as $f){ $rp = realpath($f); if(!isset($beforeMap[$rp]) && strpos(basename($f),'preflight_')===0){ $newFile = $f; break 2; } }
  } while(microtime(true)-$start < 10);
  if($newFile){
    // supprimer après test
    @unlink($newFile);
  }
  $responses['test_capture'] = [ 'command'=>$capCmd, 'exit'=>$capCode, 'output'=>$capOut, 'created'=>basename($newFile ?? ''), 'success'=>(bool)$newFile ];
}

echo json_encode($responses);
?>
