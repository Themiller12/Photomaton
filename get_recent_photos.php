<?php
// Récupérer les photos récentes du dossier captures
header('Content-Type: application/json');

$capturesDir = '../captures/';
$photos = [];

if (is_dir($capturesDir)) {
    $files = scandir($capturesDir);
    
    foreach ($files as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
            $filePath = $capturesDir . $file;
            $relativePath = 'captures/' . $file;
            $photos[] = [
                'path' => $relativePath,
                'name' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
    }
    
    // Trier par date de modification (plus récent en premier)
    usort($photos, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    // Limiter à 10 photos récentes
    $photos = array_slice($photos, 0, 10);
    
    // Retourner juste les chemins pour simplifier
    $photoPaths = array_map(function($photo) {
        return $photo['path'];
    }, $photos);
    
    echo json_encode($photoPaths);
} else {
    echo json_encode([]);
}
?>
