<?php
/**
 * Script PHP pour créer une image double (2 photos par page) sur Windows
 * Appelé par windows_print_double.bat
 */

// Récupérer le chemin de l'image depuis les arguments
if ($argc < 2) {
    exit("Erreur: Chemin d'image manquant\n");
}

$imagePath = $argv[1];

// Vérifier que l'image existe
if (!file_exists($imagePath)) {
    exit("Erreur: Image non trouvée: $imagePath\n");
}

try {
    // Charger l'image source
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        exit("Erreur: Impossible de lire l'image\n");
    }
    
    $mimeType = $imageInfo['mime'];
    
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        default:
            exit("Erreur: Format d'image non supporté: $mimeType\n");
    }
    
    if (!$sourceImage) {
        exit("Erreur: Impossible de créer l'image source\n");
    }
    
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    
    // Dimensions pour format 10x15 cm à 300 DPI
    $photoWidth = 1181;  // 10cm à 300 DPI
    $photoHeight = 1772; // 15cm à 300 DPI
    $spacing = 59;       // 5mm d'espacement
    $pageWidth = ($photoWidth * 2) + ($spacing * 3); // 2 photos + 3 espacements
    $pageHeight = $photoHeight + ($spacing * 2);     // 1 photo + 2 espacements
    
    // Créer l'image de la page
    $pageImage = imagecreatetruecolor($pageWidth, $pageHeight);
    $white = imagecolorallocate($pageImage, 255, 255, 255);
    imagefill($pageImage, 0, 0, $white);
    
    // Redimensionner l'image source au format photo
    $resizedImage = imagecreatetruecolor($photoWidth, $photoHeight);
    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $photoWidth, $photoHeight, $sourceWidth, $sourceHeight);
    
    // Coller les deux photos
    imagecopy($pageImage, $resizedImage, $spacing, $spacing, 0, 0, $photoWidth, $photoHeight);
    imagecopy($pageImage, $resizedImage, $spacing * 2 + $photoWidth, $spacing, 0, 0, $photoWidth, $photoHeight);
    
    // Ajouter une ligne de coupe au milieu
    $gray = imagecolorallocate($pageImage, 200, 200, 200);
    $cutLineX = $spacing + $photoWidth + ($spacing / 2);
    imageline($pageImage, $cutLineX, 0, $cutLineX, $pageHeight, $gray);
    
    // Créer le nom du fichier temporaire
    $filename = basename($imagePath, '.' . pathinfo($imagePath, PATHINFO_EXTENSION));
    $tempDir = __DIR__ . '/../temp';
    
    // Créer le dossier temp s'il n'existe pas
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $doubleImagePath = $tempDir . '/temp_2up_' . $filename . '_' . time() . '.jpg';
    
    if (imagejpeg($pageImage, $doubleImagePath, 95)) {
        // Nettoyer la mémoire
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        imagedestroy($pageImage);
        
        // Retourner le chemin du fichier créé
        echo $doubleImagePath;
    } else {
        exit("Erreur: Impossible de sauvegarder l'image double\n");
    }
    
} catch (Exception $e) {
    // Nettoyer la mémoire en cas d'erreur
    if (isset($sourceImage)) imagedestroy($sourceImage);
    if (isset($resizedImage)) imagedestroy($resizedImage);
    if (isset($pageImage)) imagedestroy($pageImage);
    
    exit("Erreur: " . $e->getMessage() . "\n");
}
?>