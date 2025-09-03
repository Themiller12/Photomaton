<?php
// Impression Canon SELPHY CP1500 - Méthode simple et fiable
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$file = $input['file'] ?? '';
$copies = max(1, intval($input['copies'] ?? 1));

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

// Log pour debug
$logFile = __DIR__ . '/logs/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Impression simple Canon SELPHY: $file (x$copies copies)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    $windowsPath = str_replace('/', '\\', $filePath);
    $success = false;
    $method = '';
    $errors = [];
    
    // === MÉTHODE 1: Impression directe avec cmd et rundll32 (plus compatible) ===
    try {
        for ($i = 0; $i < $copies; $i++) {
            // Utiliser rundll32 avec shimgvw.dll (plus universel)
            $command = 'rundll32.exe shimgvw.dll,ImageView_PrintTo "' . $windowsPath . '"';
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($i < $copies - 1) {
                sleep(3);
            }
        }
        $success = true;
        $method = "rundll32 shimgvw.dll";
    } catch (Exception $e) {
        $errors[] = "shimgvw.dll: " . $e->getMessage();
    }
    
    // === MÉTHODE 2: Impression via l'application Photos de Windows 10/11 ===
    if (!$success) {
        try {
            for ($i = 0; $i < $copies; $i++) {
                // Lancer l'application Photos avec le fichier
                $command = 'cmd /c start "" ms-photos:viewer?fileName="' . $windowsPath . '"';
                exec($command);
                
                // Attendre que l'app se lance
                sleep(2);
                
                // Envoyer Ctrl+P pour imprimer
                $psScript = 'powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; Start-Sleep -Seconds 1; [System.Windows.Forms.SendKeys]::SendWait(\'^p\'); Start-Sleep -Seconds 1; [System.Windows.Forms.SendKeys]::SendWait(\'{ENTER}\')"';
                exec($psScript);
                
                if ($i < $copies - 1) {
                    sleep(5); // Plus de temps entre les copies
                }
            }
            $success = true;
            $method = "Application Photos Windows + Ctrl+P";
        } catch (Exception $e) {
            $errors[] = "Photos Windows: " . $e->getMessage();
        }
    }
    
    // === MÉTHODE 3: PowerShell simple avec Get-Printer ===
    if (!$success) {
        try {
            $psScript = "
                # Trouver l'imprimante Canon SELPHY
                \$printers = Get-Printer | Where-Object { \$_.Name -like '*CP1500*' -or \$_.Name -like '*SELPHY*' }
                if (\$printers.Count -eq 0) {
                    \$printers = Get-Printer | Where-Object { \$_.Default -eq \$true }
                }
                
                if (\$printers.Count -gt 0) {
                    \$printer = \$printers[0]
                    Write-Host \"Utilisation de l'imprimante: \" \$printer.Name
                    
                    for (\$i = 1; \$i -le $copies; \$i++) {
                        Write-Host \"Impression copie \$i\"
                        
                        # Ouvrir le fichier avec l'application par défaut et imprimer
                        \$process = Start-Process -FilePath '$windowsPath' -Verb Print -PassThru -WindowStyle Hidden
                        
                        # Attendre un peu
                        Start-Sleep -Seconds 3
                        
                        if (\$i -lt $copies) {
                            Start-Sleep -Seconds 4
                        }
                    }
                    Write-Host \"Impression terminée\"
                } else {
                    Write-Error \"Aucune imprimante trouvée\"
                    exit 1
                }
            ";
            
            $tempScript = tempnam(sys_get_temp_dir(), 'print_simple_') . '.ps1';
            file_put_contents($tempScript, $psScript);
            
            $command = 'powershell.exe -ExecutionPolicy Bypass -File "' . $tempScript . '"';
            $output = [];
            $returnCode = 0;
            
            exec($command . ' 2>&1', $output, $returnCode);
            unlink($tempScript);
            
            if ($returnCode === 0) {
                $success = true;
                $method = "PowerShell Get-Printer";
            } else {
                $errors[] = "PowerShell Get-Printer: " . implode("\n", $output);
            }
        } catch (Exception $e) {
            $errors[] = "PowerShell simple: " . $e->getMessage();
        }
    }
    
    // === MÉTHODE 4: MSPaint (fallback final) ===
    if (!$success) {
        try {
            for ($i = 0; $i < $copies; $i++) {
                $command = 'mspaint /pt "' . $windowsPath . '"';
                exec($command);
                
                if ($i < $copies - 1) {
                    sleep(2);
                }
            }
            $success = true;
            $method = "MSPaint (fallback)";
        } catch (Exception $e) {
            $errors[] = "MSPaint: " . $e->getMessage();
        }
    }
    
    if ($success) {
        $logEntry = date('Y-m-d H:i:s') . " - Succès impression simple ($method): $file (x$copies copies)\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true, 
            'message' => "Impression lancée avec $method ($copies copie(s))",
            'file' => $file,
            'copies' => $copies,
            'method' => $method
        ]);
    } else {
        throw new Exception("Toutes les méthodes d'impression ont échoué:\n" . implode("\n", $errors));
    }
    
} catch (Exception $e) {
    $logEntry = date('Y-m-d H:i:s') . " - Erreur impression simple: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'impression: ' . $e->getMessage()]);
}
?>
