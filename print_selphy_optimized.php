<?php
// Impression Canon SELPHY CP1500 avec configuration PPD optimisée
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

// Log pour debug
$logFile = __DIR__ . '/logs/print_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Tentative impression optimisée CP1500: $file (x$copies copies)\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Méthode 1: Impression directe avec paramètres spécifiques Canon SELPHY
    $success = false;
    $errorMessages = [];
    
    // Convertir le chemin pour Windows
    $windowsPath = str_replace('/', '\\', $filePath);
    
    // === MÉTHODE 1: Impression directe via association de fichier Windows ===
    try {
        for ($i = 0; $i < $copies; $i++) {
            // Méthode la plus fiable : utiliser l'association de fichier par défaut
            $command = 'cmd /c start /min "" "' . $windowsPath . '"';
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            // Attendre que le programme se lance puis simuler Ctrl+P
            sleep(3);
            
            // Envoyer Ctrl+P pour ouvrir la boîte d'impression
            $psCommand = 'powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.SendKeys]::SendWait(\'^p\')"';
            exec($psCommand);
            
            if ($i < $copies - 1) {
                sleep(5); // Délai plus long entre les copies
            }
        }
        $success = true;
        $method = "Association fichier Windows + Ctrl+P";
    } catch (Exception $e) {
        $errorMessages[] = "Association fichier: " . $e->getMessage();
    }
    
    // === MÉTHODE 2: PowerShell avec impression directe sur imprimante ===
    if (!$success) {
        try {
            $psScript = "
                # Trouver l'imprimante Canon SELPHY CP1500
                \$printer = Get-WmiObject -Class Win32_Printer | Where-Object { 
                    \$_.Name -like '*CP1500*' -or \$_.Name -like '*SELPHY*' -or \$_.Default -eq \$true 
                } | Select-Object -First 1
                
                if (\$printer) {
                    Write-Host \"Imprimante trouvée: \" \$printer.Name
                    
                    # Méthode PowerShell robuste pour impression
                    for (\$i = 1; \$i -le $copies; \$i++) {
                        Write-Host \"Impression copie \$i\"
                        
                        try {
                            # Méthode 1: Utiliser System.Drawing pour imprimer
                            Add-Type -AssemblyName System.Drawing
                            Add-Type -AssemblyName System.Drawing.Printing
                            
                            \$image = [System.Drawing.Image]::FromFile('$windowsPath')
                            \$printDoc = New-Object System.Drawing.Printing.PrintDocument
                            \$printDoc.PrinterSettings.PrinterName = \$printer.Name
                            
                            # Événement pour dessiner l'image
                            \$printPage = {
                                param(\$sender, \$e)
                                \$e.Graphics.DrawImage(\$image, \$e.MarginBounds)
                            }
                            
                            \$printDoc.add_PrintPage(\$printPage)
                            \$printDoc.Print()
                            \$image.Dispose()
                            
                            Write-Host \"Copie \$i envoyée à l'imprimante\"
                            
                        } catch {
                            # Fallback: utiliser start avec printto
                            Write-Host \"Fallback: utilisation de printto\"
                            Start-Process -FilePath '$windowsPath' -Verb 'printto' -ArgumentList '\"\$(\$printer.Name)\"' -WindowStyle Hidden
                        }
                        
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
            
            $tempScript = tempnam(sys_get_temp_dir(), 'print_cp1500_') . '.ps1';
            file_put_contents($tempScript, $psScript);
            
            $command = 'powershell.exe -ExecutionPolicy Bypass -File "' . $tempScript . '"';
            $output = [];
            $returnCode = 0;
            
            exec($command . ' 2>&1', $output, $returnCode);
            unlink($tempScript);
            
            if ($returnCode === 0) {
                $success = true;
                $method = "PowerShell System.Drawing";
            } else {
                $errorMessages[] = "PowerShell: " . implode("\n", $output);
            }
        } catch (Exception $e) {
            $errorMessages[] = "PowerShell amélioré: " . $e->getMessage();
        }
    }
    
    // === MÉTHODE 3: Fallback avec MSPaint ===
    if (!$success) {
        try {
            for ($i = 0; $i < $copies; $i++) {
                $command = 'mspaint /pt "' . $windowsPath . '"';
                $output = [];
                $returnCode = 0;
                exec($command, $output, $returnCode);
                
                if ($i < $copies - 1) {
                    sleep(2);
                }
            }
            $success = true;
            $method = "MSPaint (fallback)";
        } catch (Exception $e) {
            $errorMessages[] = "MSPaint: " . $e->getMessage();
        }
    }
    
    if ($success) {
        // Log de succès
        $logEntry = date('Y-m-d H:i:s') . " - Succès impression CP1500 ($method): $file (x$copies copies)\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true, 
            'message' => "Impression lancée sur Canon SELPHY CP1500 ($copies copie(s))",
            'file' => $file,
            'copies' => $copies,
            'method' => $method
        ]);
    } else {
        throw new Exception("Toutes les méthodes d'impression ont échoué:\n" . implode("\n", $errorMessages));
    }
    
} catch (Exception $e) {
    // Log d'erreur
    $logEntry = date('Y-m-d H:i:s') . " - Erreur impression CP1500: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'impression: ' . $e->getMessage()]);
}
?>
