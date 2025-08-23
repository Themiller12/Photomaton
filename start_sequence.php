<?php
// DSLR capture sequence using gphoto2 (Linux/Raspberry Pi). Placeholder.
// Returns JSON: { files: ["captures/xxx.jpg", ...] }
// Fallback: error if gphoto2 not available.

header('Content-Type: application/json; charset=utf-8');

function hasGphoto2(): bool {
    // Try multiple detection strategies
    $commands = [
        'command -v gphoto2 2>/dev/null || which gphoto2 2>/dev/null', // POSIX
        'which gphoto2 2>/dev/null'
    ];
    foreach($commands as $cmd){
        $out = @shell_exec($cmd);
        if($out && strpos($out, 'gphoto2') !== false) return true;
    }
    return false;
}

if(strtoupper(substr(PHP_OS,0,3)) === 'WIN') {
    http_response_code(400);
    echo json_encode(['error'=>'Capture DSLR non implémentée sous Windows – utilisez webcam ou migrez vers Linux avec gphoto2.']);
    exit;
}

if(!hasGphoto2()){
    http_response_code(500);
    echo json_encode(['error'=>'gphoto2 introuvable']);
    exit;
}

$files = [];
$captureDir = __DIR__ . '/captures';
if(!is_dir($captureDir)) mkdir($captureDir,0775,true);

for($i=1;$i<=3;$i++) {
    $filename = 'capture_'.date('Ymd_His')."_{$i}_".bin2hex(random_bytes(2)).'.jpg';
    $target = $captureDir . '/' . $filename;
    $cmd = 'gphoto2 --capture-image-and-download --force-overwrite --filename '.escapeshellarg($target).' 2>&1';
    exec($cmd, $outLines, $code);
    if($code !== 0 || !file_exists($target)) {
        http_response_code(500);
        echo json_encode(['error'=>'Echec capture '. $i, 'details'=>$outLines]);
        // Optional: cleanup previous captures on failure
        foreach($files as $f) @unlink(__DIR__.'/'.$f);
        exit;
    }
    $files[] = 'captures/'.$filename;
    if($i<3) sleep(3); // pause 3s
}

echo json_encode(['files'=>$files]);
?>
