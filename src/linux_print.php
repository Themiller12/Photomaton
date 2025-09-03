<?php
/**
 * PHOTOMATON LINUX - Endpoint d'impression CUPS
 * Utilise le système CUPS pour impression function setupPrinter() {
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
    
    // Calculer dimensions pour 2 images côte à côte sur format 10x15
    // Format final : 15x10 cm (landscape) avec 2 photos de ~7x10 chacune
    $finalWidth = $srcWidth * 2;
    $finalHeight = $srcHeight;
    
    // Créer l'image de destination
    $dest = imagecreatetruecolor($finalWidth, $finalHeight);
    $white = imagecolorallocate($dest, 255, 255, 255);
    imagefill($dest, 0, 0, $white);
    
    // Copier l'image 2 fois
    imagecopy($dest, $source, 0, 0, 0, 0, $srcWidth, $srcHeight); // Gauche
    imagecopy($dest, $source, $srcWidth, 0, 0, 0, $srcWidth, $srcHeight); // Droite
    
    // Sauvegarder
    $outputPath = __DIR__ . '/../temp_2up_' . basename($originalPath);
    if (!imagejpeg($dest, $outputPath, 95)) {
        throw new Exception("Impossible de sauvegarder l'image 2up");
    }
    
    // Nettoyer
    imagedestroy($source);
    imagedestroy($dest);
    
    return $outputPath;
}ELPHY CP1500
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('PRINT_SCRIPT', __DIR__ . '/../linux_print.sh');
define('LOG_FILE', __DIR__ . '/../print_log.txt');

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

function setupPrinter() {
    logMessage("Configuration automatique imprimante");
    
    $command = escapeshellcmd(PRINT_SCRIPT) . ' setup 2>&1';
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output)
    ];
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
