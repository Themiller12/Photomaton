<?php
/**
 * Script d'impression pour la galerie Photomaton
 * Gère l'impression de photos depuis la galerie avec options de format et copies
 */

header('Content-Type: application/json');

// Vérifier que c'est bien une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit;
}

// Valider les données reçues
$imagePath = $data['image'] ?? '';
$copies = intval($data['copies'] ?? 1);
$format = intval($data['format'] ?? 1);
$source = $data['source'] ?? 'gallery';

// Validation
if (empty($imagePath)) {
    echo json_encode(['success' => false, 'error' => 'Chemin d\'image manquant']);
    exit;
}

if ($copies < 1 || $copies > 10) {
    echo json_encode(['success' => false, 'error' => 'Nombre de copies invalide (1-10)']);
    exit;
}

if (!in_array($format, [1, 2])) {
    echo json_encode(['success' => false, 'error' => 'Format invalide (1 ou 2)']);
    exit;
}

// Nettoyer le chemin de l'image et vérifier qu'elle existe
$imagePath = str_replace(['../', './'], '', $imagePath);
$fullImagePath = __DIR__ . '/' . $imagePath;

if (!file_exists($fullImagePath)) {
    echo json_encode(['success' => false, 'error' => 'Image non trouvée: ' . $imagePath]);
    exit;
}

// Log de l'impression
$logMessage = "[" . date('Y-m-d H:i:s') . "] GALERIE PRINT: {$imagePath} - {$copies} copies - format {$format} photo(s)/page\n";
file_put_contents(__DIR__ . '/print.log', $logMessage, FILE_APPEND | LOCK_EX);

try {
    // Déterminer le système d'exploitation
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($isWindows) {
        // Impression Windows
        $success = printOnWindows($fullImagePath, $copies, $format);
    } else {
        // Impression Linux (Raspberry Pi)
        $success = printOnLinux($fullImagePath, $copies, $format);
    }
    
    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => "Impression lancée: {$copies} copie(s) en format {$format} photo(s) par page"
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Échec de l\'impression']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur d\'impression: ' . $e->getMessage()]);
}

/**
 * Impression sur Windows via les scripts batch
 */
function printOnWindows($imagePath, $copies, $format) {
    $scriptDir = __DIR__ . '/scripts';
    
    if ($format == 1) {
        // 1 photo par page - impression simple
        $command = "cd /d \"{$scriptDir}\" && windows_print.bat \"" . addslashes($imagePath) . "\" {$copies}";
    } else {
        // 2 photos par page - utiliser le script de double impression
        $command = "cd /d \"{$scriptDir}\" && windows_print_double.bat \"" . addslashes($imagePath) . "\" {$copies}";
    }
    
    // Exécuter la commande en arrière-plan
    $output = [];
    $returnVar = 0;
    exec($command . " 2>&1", $output, $returnVar);
    
    // Log du résultat
    $logMessage = "[" . date('Y-m-d H:i:s') . "] WINDOWS PRINT: " . implode("\n", $output) . " (code: {$returnVar})\n";
    file_put_contents(__DIR__ . '/print.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    return $returnVar === 0;
}

/**
 * Impression sur Linux via les scripts shell
 */
function printOnLinux($imagePath, $copies, $format) {
    $scriptDir = __DIR__ . '/scripts';
    
    if ($format == 2) {
        // 2 photos par page - créer l'image double d'abord
        $doubleImagePath = create2upImage($imagePath);
        if (!$doubleImagePath) {
            throw new Exception('Impossible de créer l\'image double');
        }
        $finalImagePath = $doubleImagePath;
    } else {
        // 1 photo par page - utiliser l'image originale
        $finalImagePath = $imagePath;
    }
    
    // Préparer la commande d'impression
    $command = "cd \"{$scriptDir}\" && ./linux_print.sh \"" . addslashes($finalImagePath) . "\" {$copies}";
    
    // Exécuter la commande
    $output = [];
    $returnVar = 0;
    exec($command . " 2>&1", $output, $returnVar);
    
    // Log du résultat
    $logMessage = "[" . date('Y-m-d H:i:s') . "] LINUX PRINT: " . implode("\n", $output) . " (code: {$returnVar})\n";
    file_put_contents(__DIR__ . '/print.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    return $returnVar === 0;
}

/**
 * Créer une image avec 2 photos côte à côte (pour Linux)
 * Utilise la même logique que dans linux_print.php
 */
function create2upImage($imagePath) {
    try {
        // Charger l'image source
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception('Impossible de lire l\'image');
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
                throw new Exception('Format d\'image non supporté: ' . $mimeType);
        }
        
        if (!$sourceImage) {
            throw new Exception('Impossible de créer l\'image source');
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
        
        // Sauvegarder l'image double
        $filename = basename($imagePath, '.' . pathinfo($imagePath, PATHINFO_EXTENSION));
        $doubleImagePath = __DIR__ . '/temp/temp_2up_' . $filename . '_' . time() . '.jpg';
        
        // Créer le dossier temp s'il n'existe pas
        $tempDir = dirname($doubleImagePath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        if (imagejpeg($pageImage, $doubleImagePath, 95)) {
            // Nettoyer la mémoire
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            imagedestroy($pageImage);
            
            return $doubleImagePath;
        } else {
            throw new Exception('Impossible de sauvegarder l\'image double');
        }
        
    } catch (Exception $e) {
        // Nettoyer la mémoire en cas d'erreur
        if (isset($sourceImage)) imagedestroy($sourceImage);
        if (isset($resizedImage)) imagedestroy($resizedImage);
        if (isset($pageImage)) imagedestroy($pageImage);
        
        error_log("Erreur create2upImage: " . $e->getMessage());
        return false;
    }
}
?>