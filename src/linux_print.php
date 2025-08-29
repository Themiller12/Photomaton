<?php
/**
 * PHOTOMATON LINUX - Endpoint d'impression CUPS
 * Utilise le système CUPS pour impression sur Canon SELPHY CP1500
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

function printImage($imagePath, $copies = 1, $media = '4x6') {
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
            $imagePath = $input['imagePath'] ?? $_POST['imagePath'] ?? $_GET['imagePath'] ?? '';
            $copies = intval($input['copies'] ?? $_POST['copies'] ?? $_GET['copies'] ?? 1);
            $media = $input['media'] ?? $_POST['media'] ?? $_GET['media'] ?? '4x6';
            
            $result = printImage($imagePath, $copies, $media);
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
