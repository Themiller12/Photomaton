<?php
/**
 * Impression directe d'un fichier déjà présent dans captures/.
 * Entrée JSON: { "file": "captures/xxx.jpg", "copies": 1 }
 * Réponse: { status: 'ok' } ou { error: '...' }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);
if(!$input || empty($input['file'])) { http_response_code(400); echo json_encode(['error'=>'Paramètre file manquant']); exit; }

$copies = isset($input['copies']) ? (int)$input['copies'] : 1;
$copies = max(1, min(20, $copies));

$rel = str_replace(['\\','..'], ['/',''], $input['file']);
if(strpos($rel, 'captures/') !== 0) { http_response_code(400); echo json_encode(['error'=>'Chemin non autorisé']); exit; }

$abs = realpath(__DIR__ . '/' . $rel);
if(!$abs || !is_file($abs)) { http_response_code(404); echo json_encode(['error'=>'Fichier introuvable']); exit; }

switch($PRINT_MODE){
    case 'copy':
        for($i=0;$i<$copies;$i++) {
            $target = $PRINT_OUTPUT_DIR . '/' . ($i+1) . '_' . basename($abs);
            @copy($abs, $target);
        }
        echo json_encode(['status'=>'ok','mode'=>'copy']);
        break;
    case 'command':
        $errors = [];
        for($i=0;$i<$copies;$i++) {
            $cmd = str_replace('%file%', escapeshellarg($abs), $PRINT_CMD);
            exec($cmd.' 2>&1', $out, $code);
            if($code !== 0) $errors[] = ['copy'=>$i+1,'output'=>$out,'code'=>$code];
        }
        if($errors){ http_response_code(500); echo json_encode(['error'=>'Erreur impression partielle','details'=>$errors]); }
        else echo json_encode(['status'=>'ok','mode'=>'command']);
        break;
    default:
        http_response_code(500); echo json_encode(['error'=>'PRINT_MODE inconnu']);
}
?>
