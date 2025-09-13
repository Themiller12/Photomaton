@echo off
REM Script d'impression Windows pour 2 photos par page depuis la galerie
REM Usage: windows_print_double.bat "chemin_image" nombre_copies

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

echo Impression de %COPIES% copie(s) de "%IMAGE_PATH%" en format 2 photos par page...

REM Créer l'image double via PHP
set "PHP_SCRIPT=%~dp0..\create_double_image.php"
set "TEMP_IMAGE="

for /f "tokens=*" %%i in ('php "%PHP_SCRIPT%" "%IMAGE_PATH%"') do (
    set "TEMP_IMAGE=%%i"
)

if "%TEMP_IMAGE%"=="" (
    echo Erreur: Impossible de créer l'image double
    exit /b 1
)

if not exist "%TEMP_IMAGE%" (
    echo Erreur: Image double non créée: %TEMP_IMAGE%
    exit /b 1
)

echo Image double créée: %TEMP_IMAGE%

REM Imprimer l'image double
for /l %%i in (1,1,%COPIES%) do (
    echo Impression copie %%i/%COPIES%...
    mspaint /pt "%TEMP_IMAGE%" > nul 2>&1
    timeout /t 2 /nobreak > nul
)

REM Nettoyer le fichier temporaire
if exist "%TEMP_IMAGE%" (
    del "%TEMP_IMAGE%" > nul 2>&1
)

echo Impression terminée: %COPIES% copie(s) de 2 photos par page

exit /b 0