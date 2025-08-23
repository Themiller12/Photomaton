<?php
// Endpoint to save chosen photo and trigger printing (placeholder implementation for Windows environment)
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
if(!$input || empty($input['image'])) { http_response_code(400); echo json_encode(['error'=>'No image']); exit; }

$copies = isset($input['copies']) ? intval($input['copies']) : 1;
$copies = max(1, min(10, $copies));

$imgData = $input['image'];
if(preg_match('/^data:image\/(png|jpeg);base64,/', $imgData, $m)) {
    $imgData = substr($imgData, strpos($imgData, ',')+1);
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
} else {
    http_response_code(400); echo json_encode(['error'=>'Invalid image data']); exit;
}
$binary = base64_decode($imgData);
if($binary===false){ http_response_code(400); echo json_encode(['error'=>'Decode failed']); exit; }

$filename = 'capture_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$ext;
$fullPath = __DIR__.'/captures/'.$filename;
file_put_contents($fullPath, $binary);

// Placeholder: printing logic. On Windows you might use a command line tool or invoke a printer share.
// For now we just duplicate file into prints directory copies times.
for($i=0; $i<$copies; $i++) {
    copy($fullPath, __DIR__.'/prints/'.($i+1).'_'.$filename);
}

echo json_encode(['status'=>'ok','file'=>$filename]);
