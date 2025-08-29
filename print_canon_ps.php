<?php
// Impression Canon CP1500 via PowerShell
// Version avancée avec gestion spécifique de l'imprimante

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

$file = $input['file'] ?? '';
$copies = max(1, intval($input['copies'] ?? 1));

if (empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier non spécifié']);
    exit;
}

// Construire le chemin complet du fichier
$filePath = __DIR__ . '/' . ltrim($file, '/');

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier non trouvé: ' . $file]);
    exit;
}

// Log de l'impression
$logFile = __DIR__ . '/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Tentative impression: $file (x$copies copies)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Convertir le chemin pour PowerShell
    $psFilePath = str_replace('/', '\\', $filePath);
    
    // Script PowerShell pour imprimer
    $psScript = "
        # Obtenir l'imprimante par défaut ou Canon CP1500
        \$printer = Get-WmiObject -Class Win32_Printer | Where-Object { \$_.Default -eq \$true -or \$_.Name -like '*CP1500*' -or \$_.Name -like '*Canon*' } | Select-Object -First 1
        
        if (\$printer) {
            Write-Host \"Imprimante trouvée: \" \$printer.Name
            
            # Imprimer le fichier
            for (\$i = 1; \$i -le $copies; \$i++) {
                Write-Host \"Impression copie \$i sur \" \$printer.Name
                Start-Process -FilePath '$psFilePath' -Verb Print -WindowStyle Hidden
                if (\$i -lt $copies) {
                    Start-Sleep -Seconds 3
                }
            }
            Write-Host \"Impression terminée\"
        } else {
            Write-Error \"Aucune imprimante trouvée\"
            exit 1
        }
    ";
    
    // Écrire le script dans un fichier temporaire
    $tempScript = tempnam(sys_get_temp_dir(), 'print_') . '.ps1';
    file_put_contents($tempScript, $psScript);
    
    // Exécuter le script PowerShell
    $command = 'powershell.exe -ExecutionPolicy Bypass -File "' . $tempScript . '"';
    $output = [];
    $returnCode = 0;
    
    exec($command . ' 2>&1', $output, $returnCode);
    
    // Nettoyer le fichier temporaire
    unlink($tempScript);
    
    if ($returnCode === 0) {
        // Log de succès
        $logEntry = date('Y-m-d H:i:s') . " - Succès impression PowerShell: $file (x$copies copies)\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true, 
            'message' => "Impression lancée sur Canon CP1500 ($copies copie(s))",
            'file' => $file,
            'copies' => $copies,
            'printer_output' => implode("\n", $output)
        ]);
    } else {
        throw new Exception('Erreur PowerShell: ' . implode("\n", $output));
    }
    
} catch (Exception $e) {
    // Log d'erreur
    $logEntry = date('Y-m-d H:i:s') . " - Erreur impression PowerShell: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'impression: ' . $e->getMessage()]);
}
?>
