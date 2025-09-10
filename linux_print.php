<?php
/**
 * PHOTOMATOfunction create2upImage($originalPath) {
    // Créer une image avec 2 copies l'une au-dessus de l'autre
    logMessage("create2upImage: Traitement de $originalPath");
    
    $source = imagecreatefromjpeg($originalPath);
    if (!$source) {
        throw new Exception("Impossible de charger l'image source: $originalPath");
    }X - Endpoint d'impression CUPS
 * Utilise le système CUPS pour impression  */


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

 
function setupPrinter() {
    logMessage("Configuration imprimante Linux");
    
    $command = escapeshellcmd(PRINT_SCRIPT) . ' setup 2>&1';
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output)
    ];
}

function create2upImage($originalPath) {
    // Créer une image avec 2 copies côte à côte
    $source = imagecreatefromjpeg($originalPath);
    if (!$source) {
        throw new Exception("Impossible de charger l'image source");
    }
    
    $srcWidth = imagesx($source);
    $srcHeight = imagesy($source);
    
    // Calculer dimensions pour 2 images paysage l'une au-dessus de l'autre
    // Format final : 10x15 cm (portrait) avec 2 photos paysage de même taille + espacement
    
    // Garder les proportions originales des photos (format paysage)
    $photoWidth = $srcWidth;
    $photoHeight = $srcHeight;
    
    // Espacement entre les photos (équivalent à ~1.5cm sur papier)
    $spacing = intval($photoHeight * 0.3); // 30% de la hauteur comme espacement
    
    // Dimensions de l'image finale (portrait)
    $finalWidth = $photoWidth;
    $finalHeight = ($photoHeight * 2) + $spacing;
    
    // Créer l'image de destination
    $dest = imagecreatetruecolor($finalWidth, $finalHeight);
    $white = imagecolorallocate($dest, 255, 255, 255);
    imagefill($dest, 0, 0, $white);
    
    // Copier les 2 photos identiques sans déformation (format paysage conservé)
    imagecopy($dest, $source, 0, 0, 0, 0, $photoWidth, $photoHeight); // Photo du haut
    imagecopy($dest, $source, 0, $photoHeight + $spacing, 0, 0, $photoWidth, $photoHeight); // Photo du bas
    
    // Ajouter des lignes de découpe (optionnel, en pointillés légers)
    $lightGray = imagecolorallocate($dest, 200, 200, 200);
    $lineY = intval($photoHeight + ($spacing / 2));
    
    // Ligne de découpe horizontale en pointillés
    for ($x = 0; $x < $finalWidth; $x += 15) {
        imageline($dest, $x, $lineY, min($x + 8, $finalWidth), $lineY, $lightGray);
    }
    
    // Lignes de découpe verticales sur les côtés (optionnel)
    for ($y = 0; $y < $finalHeight; $y += 15) {
        // Ligne gauche
        imageline($dest, 5, $y, 5, min($y + 8, $finalHeight), $lightGray);
        // Ligne droite  
        imageline($dest, $finalWidth - 6, $y, $finalWidth - 6, min($y + 8, $finalHeight), $lightGray);
    }
    
    // Sauvegarder
    $outputPath = __DIR__ . '/temp_2up_' . basename($originalPath);
    if (!imagejpeg($dest, $outputPath, 95)) {
        throw new Exception("Impossible de sauvegarder l'image 2up");
    }
    
    // Nettoyer
    imagedestroy($source);
    imagedestroy($dest);
    
    return $outputPath;
}

// Gestion CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
$script_path = __DIR__ . '/scripts/linux_print.sh';
// Fallback pour environnement de production Linux
if (!file_exists($script_path) && file_exists('/var/www/html/Photomaton/scripts/linux_print.sh')) {
    $script_path = '/var/www/html/Photomaton/scripts/linux_print.sh';
}
define('PRINT_SCRIPT', $script_path);
define('LOG_FILE', __DIR__ . '/logs/print_log.txt');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] PHP_LINUX_PRINT: $message\n", FILE_APPEND);
}

function printImage($imagePath, $copies = 1, $media = '4x6', $options = []) {
    logMessage("Impression Linux: $imagePath (x$copies, $media)");
    
    // Validation des paramètres
    if (empty($imagePath)) {
        throw new Exception("Chemin image requis");
    }
    
    // Convertir le chemin relatif en chemin absolu
    if (!file_exists($imagePath)) {
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($fullPath)) {
            $imagePath = $fullPath;
        } else {
            throw new Exception("Fichier image introuvable: $imagePath");
        }
    }
    
    // Traitement spécial pour impression double (2up)
    if (isset($options['layout']) && $options['layout'] === '2up') {
        $imagePath = create2upImage($imagePath);
        logMessage("Image 2up créée: $imagePath");
    }
    
    // Vérifier le script d'impression
    $resolved_path = realpath(PRINT_SCRIPT);
    logMessage("Chemin script attendu: " . PRINT_SCRIPT);
    logMessage("Chemin script résolu: " . ($resolved_path ? $resolved_path : "NON TROUVÉ"));
    logMessage("Dossier courant: " . getcwd());
    logMessage("__DIR__: " . __DIR__);
    
    if (!file_exists(PRINT_SCRIPT)) {
        throw new Exception("Script d'impression non trouvé: " . PRINT_SCRIPT);
    }
    
    if (!is_executable(PRINT_SCRIPT)) {
        chmod(PRINT_SCRIPT, 0755);
        if (!is_executable(PRINT_SCRIPT)) {
            throw new Exception("Script d'impression non exécutable");
        }
    }
    
    // Construire la commande
    $command = sprintf(
        '%s print %s %d %s 2>&1',
        escapeshellcmd(PRINT_SCRIPT),
        escapeshellarg($imagePath),
        intval($copies),
        escapeshellarg($media)
    );
    
    logMessage("Commande: $command");
    
    // Exécuter
    $output = [];
    $returnCode = null;
    
    exec($command, $output, $returnCode);
    
    $outputString = implode("\n", $output);
    logMessage("Code retour: $returnCode");
    logMessage("Sortie: $outputString");
    
    if ($returnCode === 0) {
        // Extraire l'ID du job d'impression
        $jobId = null;
        foreach ($output as $line) {
            if (strpos($line, 'SUCCESS:') === 0) {
                $jobId = trim(str_replace('SUCCESS:', '', $line));
                break;
            }
        }
        
        return [
            'success' => true,
            'jobId' => $jobId,
            'message' => "Impression envoyée ($copies copie(s))",
            'method' => 'CUPS',
            'output' => $outputString
        ];
    } else {
        throw new Exception("Échec impression: $outputString");
    }
}


function checkPrintStatus($jobId = null) {
    logMessage("Vérification statut impression: $jobId");
    
    $command = escapeshellcmd(PRINT_SCRIPT) . ' status';
    if ($jobId) {
        $command .= ' ' . escapeshellarg($jobId);
    }
    $command .= ' 2>&1';
    
    exec($command, $output, $returnCode);
    
    return [
        'success' => true,
        'status' => implode("\n", $output)
    ];
}

function listPrinters() {
    logMessage("Liste des imprimantes");
    
    $command = escapeshellcmd(PRINT_SCRIPT) . ' list 2>&1';
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'printers' => implode("\n", $output)
    ];
}

function testPrintSystem() {
    logMessage("Test système impression");
    
    $command = escapeshellcmd(PRINT_SCRIPT) . ' test 2>&1';
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'cupsRunning' => shell_exec('systemctl is-active cups 2>/dev/null') === "active\n"
    ];
}

try {
    // Récupérer les paramètres
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'print';
    
    switch ($action) {
        case 'print':
            // Compatibilité avec les deux formats
            $imagePath = $input['imagePath'] ?? $input['file'] ?? $_POST['imagePath'] ?? $_POST['file'] ?? $_GET['imagePath'] ?? $_GET['file'] ?? '';
            $copies = intval($input['copies'] ?? $_POST['copies'] ?? $_GET['copies'] ?? 1);
            $media = $input['media'] ?? $input['paperSize'] ?? $_POST['media'] ?? $_POST['paperSize'] ?? $_GET['media'] ?? $_GET['paperSize'] ?? '4x6';
            
            // Options spéciales (layout 2up, etc.)
            $options = [];
            if (isset($input['layout'])) $options['layout'] = $input['layout'];
            if (isset($input['doublePhoto'])) $options['doublePhoto'] = $input['doublePhoto'];
            
            $result = printImage($imagePath, $copies, $media, $options);
            logMessage("SUCCÈS impression: Job " . ($result['jobId'] ?? 'N/A'));
            echo json_encode($result);
            break;
            
        case 'preview2up':
            // Créer un aperçu de l'image double
            $imagePath = $input['imagePath'] ?? $input['file'] ?? $_POST['imagePath'] ?? $_POST['file'] ?? $_GET['imagePath'] ?? $_GET['file'] ?? '';
            
            logMessage("DEMANDE aperçu 2up: $imagePath");
            
            if (empty($imagePath)) {
                throw new Exception("Fichier non spécifié pour l'aperçu");
            }
            
            // Vérifier que le fichier existe
            $fullPath = __DIR__ . '/' . $imagePath;
            if (!file_exists($fullPath)) {
                // Essayer sans le préfixe captures/
                $imagePath = 'captures/' . basename($imagePath);
                $fullPath = __DIR__ . '/' . $imagePath;
                
                if (!file_exists($fullPath)) {
                    throw new Exception("Fichier non trouvé: $imagePath (testé: $fullPath)");
                }
            }
            
            logMessage("Fichier trouvé: $fullPath");
            
            // Créer l'image 2up temporaire
            $previewPath = create2upImage($fullPath);
            
            // Créer une URL relative accessible depuis le navigateur
            $fileName = basename($previewPath);
            $previewUrl = $fileName; // Juste le nom du fichier car il sera dans le même répertoire
            
            logMessage("SUCCÈS création aperçu: $previewPath -> $previewUrl");
            echo json_encode([
                'success' => true,
                'previewPath' => $previewPath,
                'previewUrl' => $previewUrl,
                'originalImage' => $imagePath
            ]);
            break;
            
        case 'setup':
            $result = setupPrinter();
            logMessage("Configuration imprimante: " . ($result['success'] ? 'OK' : 'ÉCHEC'));
            echo json_encode($result);
            break;
            
        case 'status':
            $jobId = $input['jobId'] ?? $_POST['jobId'] ?? $_GET['jobId'] ?? null;
            $result = checkPrintStatus($jobId);
            echo json_encode($result);
            break;
            
        case 'list':
            $result = listPrinters();
            echo json_encode($result);
            break;
            
        case 'test':
            $result = testPrintSystem();
            logMessage("Test système: " . ($result['success'] ? 'OK' : 'ÉCHEC'));
            echo json_encode($result);
            break;
            
        default:
            throw new Exception("Action non supportée: $action");
    }
    
} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
