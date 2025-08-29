<?php
// Impression sur Canon CP1500 via USB
// Ce script utilise l'imprimante par défaut Windows

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

$file = $input['file'] ?? '';
$copies = max(1, intval($input['copies'] ?? 1));

if (empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier non spécifié']);
    exit;
}

// Construire le chemin complet du fichier
$filePath = __DIR__ . '/' . ltrim($file, '/');

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier non trouvé: ' . $file]);
    exit;
}

// Log de l'impression
$logFile = __DIR__ . '/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Impression: $file (x$copies copies)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Méthode 1: Utiliser mspaint pour imprimer (fonctionne bien avec Canon CP1500)
    for ($i = 0; $i < $copies; $i++) {
        $command = 'mspaint /pt "' . $filePath . '"';
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        // Petit délai entre les copies pour éviter les problèmes
        if ($i < $copies - 1) {
            sleep(2);
        }
    }
    
    // Log de succès
    $logEntry = date('Y-m-d H:i:s') . " - Succès impression: $file (x$copies copies)\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'success' => true, 
        'message' => "Impression lancée pour $copies copie(s)",
        'file' => $file,
        'copies' => $copies
    ]);
    
} catch (Exception $e) {
    // Log d'erreur
    $logEntry = date('Y-m-d H:i:s') . " - Erreur impression: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'impression: ' . $e->getMessage()]);
}
?>
