<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Fonction de log pour debug
function logPrint($message) {
        // Log
    $logFile = '../logs/print_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] BROWSER_PRINT: $message\n", FILE_APPEND);
}

try {
    // Récupérer les données JSON de la requête
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    logPrint("Données reçues: " . print_r($data, true));
    
    // Vérifier si les données sont valides
    if (!$data) {
        throw new Exception("Aucune donnée JSON reçue");
    }
    
    // Récupérer les paramètres
    $imagePath = $data['imagePath'] ?? '';
    $copies = intval($data['copies'] ?? 1);
    $format = $data['format'] ?? 'Postcard.Fullbleed';
    $autoPrint = $data['autoPrint'] ?? true;
    
    logPrint("ImagePath: $imagePath, Copies: $copies, Format: $format, AutoPrint: " . ($autoPrint ? 'true' : 'false'));
    
    // Vérifier si le fichier image est spécifié
    if (empty($imagePath)) {
        throw new Exception("Fichier non spécifié");
    }
    
    // Si c'est une URL complète, extraire juste le chemin relatif
    if (strpos($imagePath, 'http') === 0) {
        $parsed = parse_url($imagePath);
        $imagePath = ltrim($parsed['path'], '/');
        
        // Enlever "Photomaton/" du début si présent
        if (strpos($imagePath, 'Photomaton/') === 0) {
            $imagePath = substr($imagePath, 11);
        }
    }
    
    logPrint("Chemin image traité: $imagePath");
    
    // Vérifier si le fichier existe
    $fullPath = '../' . $imagePath;
    if (!file_exists($fullPath)) {
        throw new Exception("Fichier image non trouvé: $fullPath");
    }
    
    // Construire l'URL de la page d'impression
    $printUrl = 'print_page.html?' . http_build_query([
        'image' => $imagePath,
        'copies' => $copies,
        'format' => $format,
        'auto' => $autoPrint ? '1' : '0'
    ]);
    
    logPrint("URL d'impression générée: $printUrl");
    
    // Retourner la réponse de succès
    echo json_encode([
        'success' => true,
        'printUrl' => $printUrl,
        'message' => "Page d'impression préparée pour $copies copie(s)",
        'imagePath' => $imagePath,
        'copies' => $copies,
        'format' => $format
    ]);
    
} catch (Exception $e) {
    logPrint("Erreur: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
