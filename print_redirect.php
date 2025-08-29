<?php
// Redirection automatique vers l'impression navigateur
// Ce fichier remplace temporairement les méthodes d'impression problématiques

header('Content-Type: application/json');

// Récupérer les paramètres
$imagePath = $_POST['imagePath'] ?? $_GET['imagePath'] ?? '';
$copies = intval($_POST['copies'] ?? $_GET['copies'] ?? 1);

// Log de la redirection
$logFile = 'print_log.txt';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$timestamp] REDIRECT_TO_BROWSER: $imagePath (x$copies copies)\n", FILE_APPEND);

if (empty($imagePath)) {
    echo json_encode(['success' => false, 'error' => 'Fichier non spécifié']);
    exit;
}

// Construire l'URL pour l'impression navigateur
$printUrl = 'src/print_page.html?' . http_build_query([
    'image' => $imagePath,
    'copies' => $copies,
    'format' => 'Postcard.Fullbleed',
    'auto' => '1'
]);

// Si c'est une requête AJAX, retourner JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode([
        'success' => true,
        'printUrl' => $printUrl,
        'method' => 'browser_redirect',
        'message' => "Redirection vers impression navigateur pour $copies copie(s)"
    ]);
} else {
    // Sinon, redirection directe
    header("Location: $printUrl");
    exit;
}
?>
