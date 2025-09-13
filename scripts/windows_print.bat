@echo off
REM Script d'impression Windows pour 1 photo par page depuis la galerie
REM Usage: windows_print.bat "chemin_image" nombre_copies

setlocal enabledelayedexpansion

set "IMAGE_PATH=%~1"
set "COPIES=%2"

REM Valeurs par défaut
if "%IMAGE_PATH%"=="" (
    echo Erreur: Chemin d'image manquant
    exit /b 1
)

if "%COPIES%"=="" set "COPIES=1"

REM Vérifier que l'image existe
if not exist "%IMAGE_PATH%" (
    echo Erreur: Image non trouvée: %IMAGE_PATH%
    exit /b 1
)

echo Impression de %COPIES% copie(s) de "%IMAGE_PATH%" en format 1 photo par page...

REM Imprimer l'image le nombre de fois demandé
for /l %%i in (1,1,%COPIES%) do (
    echo Impression copie %%i/%COPIES%...
    
    REM Utiliser mspaint pour imprimer (disponible sur tous les Windows)
    mspaint /pt "%IMAGE_PATH%" > nul 2>&1
    
    REM Attendre un peu entre les impressions
    timeout /t 2 /nobreak > nul
)

echo Impression terminée: %COPIES% copie(s) de 1 photo par page

exit /b 0