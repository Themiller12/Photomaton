<?php
// Impression via navigateur - Interface pour JavaScript
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$file = $input['file'] ?? '';
$copies = max(1, intval($input['copies'] ?? 1));

if (empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier non spécifié']);
    exit;
}

$filePath = __DIR__ . '/' . ltrim($file, '/');

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier non trouvé: ' . $file]);
    exit;
}

// Log de la demande d'impression
$logFile = __DIR__ . '/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Demande impression navigateur: $file (x$copies copies)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Retourner les informations pour l'impression JavaScript
echo json_encode([
    'success' => true,
    'action' => 'print_browser',
    'file' => $file,
    'copies' => $copies,
    'fullPath' => $filePath,
    'url' => $file . '?t=' . time(), // Cache busting
    'message' => "Prêt pour impression navigateur ($copies copie(s))"
]);
?>
