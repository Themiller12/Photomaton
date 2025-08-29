<?php
/**
 * PHOTOMATON LINUX - Endpoint de capture photo
 * Utilise gPhoto2 pour les appareils photo DSLR
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
define('CAPTURE_SCRIPT', __DIR__ . '/../linux_capture.sh');
define('LOG_FILE', __DIR__ . '/../capture_log.txt');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] PHP_LINUX_CAPTURE: $message\n", FILE_APPEND);
}

function executeCapture() {
    logMessage("Début capture Linux avec gPhoto2");
    
    // Vérifier que le script existe et est exécutable
    if (!file_exists(CAPTURE_SCRIPT)) {
        throw new Exception("Script de capture non trouvé: " . CAPTURE_SCRIPT);
    }
    
    if (!is_executable(CAPTURE_SCRIPT)) {
        // Essayer de rendre le script exécutable
        chmod(CAPTURE_SCRIPT, 0755);
        if (!is_executable(CAPTURE_SCRIPT)) {
            throw new Exception("Script de capture non exécutable");
        }
    }
    
    // Exécuter le script de capture
    $verbose = isset($_GET['verbose']);
    $command = escapeshellcmd(CAPTURE_SCRIPT) . ' capture 2>&1';
    logMessage("Commande: $command");
    
    $output = [];
    $returnCode = null;
    
    exec($command, $output, $returnCode);
    
    $outputString = implode("\n", $output);
    logMessage("Code retour: $returnCode");
    logMessage("Sortie: $outputString");
    
    if ($returnCode === 0) {
        // Extraire le nom de fichier de la sortie
        $lines = array_filter($output, function($line) {
            return strpos($line, 'captures/') !== false && strpos($line, '.jpg') !== false;
        });
        
        if (!empty($lines)) {
            $capturedFile = trim(end($lines));
            logMessage("Photo capturée: $capturedFile");
            
            // Vérifier que le fichier existe
            $fullPath = __DIR__ . '/../' . $capturedFile;
            if (file_exists($fullPath)) {
                return [
                    'success' => true,
                    'filename' => $capturedFile,
                    'fullPath' => $fullPath,
                    'fileSize' => filesize($fullPath),
                    'method' => 'gPhoto2',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                throw new Exception("Fichier capturé introuvable: $fullPath");
            }
        } else {
            throw new Exception("Nom de fichier non trouvé dans la sortie");
        }
    } else {
        // Chercher messages d'erreur fréquents
        $hint = '';
        if (stripos($outputString, 'Device busy') !== false || stripos($outputString, 'occupied') !== false) {
            $hint = 'Caméra occupée: débranchez/rebranchez, fermez autres processus (gvfsd-gphoto2)';
        } elseif (stripos($outputString, 'read-only file system') !== false || stripos($outputString, 'chmod') !== false) {
            $hint = 'Permissions dossier captures: chown www-data:www-data captures && chmod 775 captures';
        }
        throw new Exception("Échec capture (code $returnCode): $outputString" . ($hint? " | Astuce: $hint" : ''));
    }
}

function testSystem() {
    logMessage("Test du système Linux");
    
    $command = escapeshellcmd(CAPTURE_SCRIPT) . ' test 2>&1';
    exec($command, $output, $returnCode);
    
    return [
        'cupsInstalled' => command_exists('lp'),
        'gphoto2Installed' => command_exists('gphoto2'),
        'imageMagickInstalled' => command_exists('identify'),
        'scriptExecutable' => is_executable(CAPTURE_SCRIPT),
        'testOutput' => implode("\n", $output),
        'testResult' => $returnCode === 0
    ];
}

function command_exists($cmd) {
    $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
    return !empty($return);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'capture';
    
    switch ($action) {
        case 'capture':
            $result = executeCapture();
            logMessage("SUCCÈS capture: " . $result['filename']);
            echo json_encode($result);
            break;
            
        case 'test':
            $result = testSystem();
            logMessage("Test système effectué");
            echo json_encode([
                'success' => true,
                'system' => $result
            ]);
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
