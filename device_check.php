<?php
// Endpoint de diagnostic pour lister les appareils vus par digiCamControl
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';

if(strtolower(substr(PHP_OS,0,3)) !== 'win') { http_response_code(400); echo json_encode(['error'=>'Windows uniquement']); exit; }
if(!file_exists($CAMERA_CMD)) { http_response_code(500); echo json_encode(['error'=>'CameraControlCmd introuvable','path'=>$CAMERA_CMD]); exit; }

function win_quote($s){ if(stripos(PHP_OS,'WIN')===0){ return '"'.str_replace('"','""',$s).'"'; } return escapeshellarg($s);} 

$cmd = win_quote($CAMERA_CMD).' /list';
exec($cmd.' 2>&1', $out, $code);
$devices = [];
foreach($out as $line){
  if(preg_match('/(canon|nikon|sony|fujifilm|pentax|olympus|panasonic)/i',$line)){
    $devices[] = trim($line);
  }
}
echo json_encode([
  'command'=>$cmd,
  'exit'=>$code,
  'raw_output'=>$out,
  'devices'=>$devices,
  'detected'=>!empty($devices)
]);
?>
