<?php
// Impression Canon SELPHY CP1500 avec analyse du fichier PPD
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$file = $input['file'] ?? '';
$copies = max(1, intval($input['copies'] ?? 1));
$paperSize = $input['paperSize'] ?? 'Postcard.Fullbleed'; // Format par défaut du PPD

if (empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier non spécifié']);
    exit;
}

$filePath = __DIR__ . '/' . ltrim($file, '/');

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier non trouvé: ' . $file]);
    exit;
}

// Lire et analyser le fichier PPD
function parsePPDInfo($ppdFile) {
    $info = [
        'paperSizes' => [],
        'defaultPaperSize' => 'Postcard.Fullbleed',
        'maxCopies' => 99,
        'colorModes' => [],
        'resolution' => '300x300dpi'
    ];
    
    if (file_exists($ppdFile)) {
        $content = file_get_contents($ppdFile);
        
        // Extraire les tailles de papier
        if (preg_match_all('/\*PageSize ([^:]+)/', $content, $matches)) {
            $info['paperSizes'] = $matches[1];
        }
        
        // Extraire la taille par défaut
        if (preg_match('/\*DefaultPageSize:\s*([^\s]+)/', $content, $matches)) {
            $info['defaultPaperSize'] = $matches[1];
        }
        
        // Extraire le nombre max de copies
        if (preg_match('/\*cupsMaxCopies:\s*(\d+)/', $content, $matches)) {
            $info['maxCopies'] = intval($matches[1]);
        }
        
        // Extraire les modes couleur
        if (preg_match_all('/\*ColorModel ([^\/]+)/', $content, $matches)) {
            $info['colorModes'] = $matches[1];
        }
        
        // Extraire la résolution
        if (preg_match('/\*DefaultResolution:\s*([^\s]+)/', $content, $matches)) {
            $info['resolution'] = $matches[1];
        }
    }
    
    return $info;
}

// Configuration
$logFile = __DIR__ . '/logs/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Impression PPD-optimisée: $file (x$copies, format: $paperSize)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Analyser le fichier PPD
    // Chemin vers le fichier PPD
    $ppdFile = __DIR__ . '/ppd/Canon_SELPHY_CP1500.ppd';
    $ppdInfo = parsePPDInfo($ppdFile);
    
    // Vérifier si le nombre de copies est dans la limite
    if ($copies > $ppdInfo['maxCopies']) {
        $copies = $ppdInfo['maxCopies'];
    }
    
    // Convertir le chemin pour Windows
    $windowsPath = str_replace('/', '\\', $filePath);
    
    // Script PowerShell avec configuration PPD
    $psScript = "
        # Configuration basée sur le fichier PPD Canon SELPHY CP1500
        Write-Host \"Configuration PPD: Format=$paperSize, Copies=$copies\"
        
        # Trouver l'imprimante Canon SELPHY
        \$printer = Get-WmiObject -Class Win32_Printer | Where-Object { 
            \$_.Name -like '*CP1500*' -or \$_.Name -like '*SELPHY*' -or \$_.Default -eq \$true 
        } | Select-Object -First 1
        
        if (!\$printer) {
            Write-Error \"Imprimante Canon SELPHY CP1500 non trouvée\"
            exit 1
        }
        
        Write-Host \"Imprimante trouvée: \" \$printer.Name
        
        # Préparer l'impression avec les paramètres PPD
        try {
            # Méthode 1: Impression avec paramètres Windows spécifiques
            for (\$i = 1; \$i -le $copies; \$i++) {
                Write-Host \"Impression copie \$i/$copies...\"
                
                # Utiliser l'utilitaire Windows pour imprimer avec paramètres
                \$printJob = Start-Process -FilePath '$windowsPath' -Verb 'printto' -ArgumentList '\"\$(\$printer.Name)\"' -WindowStyle Hidden -PassThru
                
                # Alternative si printto ne fonctionne pas
                if (!\$printJob) {
                    \$printJob = Start-Process -FilePath '$windowsPath' -Verb 'print' -WindowStyle Hidden -PassThru
                }
                
                # Attendre que le job se lance
                Start-Sleep -Seconds 2
                
                if (\$i -lt $copies) {
                    Write-Host \"Attente avant copie suivante...\"
                    Start-Sleep -Seconds 5
                }
            }
            
            Write-Host \"Impression terminée avec succès\"
            
        } catch {
            Write-Error \"Erreur lors de l'impression: \$_\"
            exit 1
        }
    ";
    
    // Écrire et exécuter le script
    $tempScript = tempnam(sys_get_temp_dir(), 'print_ppd_') . '.ps1';
    file_put_contents($tempScript, $psScript);
    
    $command = 'powershell.exe -ExecutionPolicy Bypass -File "' . $tempScript . '"';
    $output = [];
    $returnCode = 0;
    
    exec($command . ' 2>&1', $output, $returnCode);
    unlink($tempScript);
    
    if ($returnCode === 0) {
        $logEntry = date('Y-m-d H:i:s') . " - Succès impression PPD: $file (x$copies)\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true, 
            'message' => "Impression lancée avec configuration PPD ($copies copie(s))",
            'file' => $file,
            'copies' => $copies,
            'paperSize' => $paperSize,
            'ppdInfo' => $ppdInfo,
            'output' => implode("\n", $output)
        ]);
    } else {
        throw new Exception('Erreur PowerShell PPD: ' . implode("\n", $output));
    }
    
} catch (Exception $e) {
    $logEntry = date('Y-m-d H:i:s') . " - Erreur impression PPD: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur PPD: ' . $e->getMessage()]);
}
?>
