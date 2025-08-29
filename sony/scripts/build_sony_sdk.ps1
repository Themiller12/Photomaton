<#!
.SYNOPSIS
  Script d'automatisation build Sony RemoteCli.
.DESCRIPTION
  - Vérifie présence CMake, Ninja, vswhere
  - Tente de localiser Visual Studio (cl.exe)
  - Choix générateur: Ninja si dispo, sinon Visual Studio
  - Génère et compile RemoteCli
  - Affiche chemin binaire final
#>
param(
  [switch]$ForceClean,
  [ValidateSet('Auto','Ninja','VS')][string]$Generator = 'Auto',
  [string]$Config = 'Release'
)

$ErrorActionPreference = 'Stop'

function Info($m){ Write-Host "[INFO] $m" -ForegroundColor Cyan }
function Warn($m){ Write-Host "[WARN] $m" -ForegroundColor Yellow }
function Fail($m){ Write-Host "[ERREUR] $m" -ForegroundColor Red }

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path | Split-Path -Parent
$SonyDir = Join-Path $Root 'sony'
$BuildDir = Join-Path $SonyDir 'build'
if(!(Test-Path (Join-Path $SonyDir 'CMakeLists.txt'))){ Fail 'CMakeLists.txt introuvable dans sony/'; exit 1 }

# Outils
$cmake = Get-Command cmake -ErrorAction SilentlyContinue
if(-not $cmake){
  # Fallback chemin standard communiqué par l'utilisateur
  $cmakeCandidate = 'C:\Program Files\CMake\bin\cmake.exe'
  if(Test-Path $cmakeCandidate){
    Info "CMake trouvé via chemin implicite: $cmakeCandidate"
    $cmake = Get-Item $cmakeCandidate
  } else {
    Fail 'CMake non trouvé. Ajoute CMake\\bin au PATH ou installe: winget install Kitware.CMake'
    exit 1
  }
}
${cmakeExe} = $cmake.Path

$vswhere = Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio\Installer\vswhere.exe'
if(Test-Path $vswhere){ $vsPath = & $vswhere -latest -products * -requires Microsoft.Component.MSBuild -property installationPath }

$ninjaCmd = Get-Command ninja -ErrorAction SilentlyContinue
$haveNinja = $ninjaCmd -ne $null

# Déterminer générateur
$chosenGen = $null
if($Generator -eq 'Ninja'){ if(!$haveNinja){ Fail 'Ninja demandé mais indisponible.'; exit 1 } $chosenGen = 'Ninja' }
elseif($Generator -eq 'VS'){ if(!$vsPath){ Fail 'Visual Studio non détecté.'; exit 1 } $chosenGen = 'Visual Studio 17 2022' }
else {
  if($haveNinja){ $chosenGen = 'Ninja' } elseif($vsPath){ $chosenGen = 'Visual Studio 17 2022' } else { Fail 'Ni Ninja ni Visual Studio détecté.'; exit 1 }
}

Info "Générateur sélectionné: $chosenGen"

if($ForceClean -and (Test-Path $BuildDir)){
  Info 'Nettoyage dossier build...'
  Remove-Item -Recurse -Force $BuildDir
}

if(!(Test-Path $BuildDir)){ New-Item -ItemType Directory -Path $BuildDir | Out-Null }

# Vérifier compilateur (cl.exe) si VS
if($chosenGen -like 'Visual Studio*'){
  $cl = Get-Command cl.exe -ErrorAction SilentlyContinue
  if(-not $cl){
    Warn 'cl.exe non dans le PATH. Ouvre un Developer PowerShell VS ou lance ce script depuis celui-ci.'
  }
}

# Génération
function Resolve-CxxCompiler {
  # Try cl.exe in common VS BuildTools locations if not in PATH
  $cl = Get-Command cl.exe -ErrorAction SilentlyContinue
  if($cl){ return $cl.Path }
  $vsRoot = Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio'
  if(Test-Path $vsRoot){
    $clCandidates = Get-ChildItem -Path $vsRoot -Recurse -Filter cl.exe -ErrorAction SilentlyContinue | Where-Object { $_.FullName -match 'Hostx64\\x64' }
    $best = $clCandidates | Sort-Object FullName | Select-Object -Last 1
    if($best){ return $best.FullName }
  }
  # Try clang++
  $clangpp = Get-Command clang++ -ErrorAction SilentlyContinue
  if($clangpp){ return $clangpp.Path }
  return $null
}

$cxxPath = Resolve-CxxCompiler
if(-not $cxxPath){
  Warn 'Aucun compilateur détecté (cl.exe ou clang++). Installe VS Build Tools OU LLVM, puis relance.'
}

$cmakeArgs = @('-S', $SonyDir, '-B', $BuildDir, '-DCMAKE_BUILD_TYPE='+$Config)
if($chosenGen -like 'Visual Studio*'){ $cmakeArgs += @('-G', $chosenGen, '-A','x64') } else { $cmakeArgs += @('-G',$chosenGen) }
if($cxxPath -and -not ($chosenGen -like 'Visual Studio*')) {
  # Pour Ninja ou autre, forcer le compilateur si trouvé
  $cmakeArgs += ('-DCMAKE_CXX_COMPILER='+$cxxPath)
}

Info "${cmakeExe} $($cmakeArgs -join ' ')"
& "$cmakeExe" @cmakeArgs
if($LASTEXITCODE -ne 0){ Fail 'Échec génération CMake'; exit 1 }

# Build
if($chosenGen -like 'Visual Studio*'){
  & "$cmakeExe" --build $BuildDir --config $Config --target RemoteCli
} else {
  & "$cmakeExe" --build $BuildDir --target RemoteCli --config $Config
}
if($LASTEXITCODE -ne 0){ Fail 'Échec compilation'; exit 1 }

# Localiser binaire
$candidates = @()
$candidates += Get-ChildItem -Recurse -Filter RemoteCli.exe -Path $BuildDir -ErrorAction SilentlyContinue
if(!$candidates){ Fail 'RemoteCli.exe introuvable après build.'; exit 1 }
$exe = $candidates | Sort-Object FullName | Select-Object -First 1

Info "Binaire: $($exe.FullName)"
Write-Host "Ajoute ce chemin dans config.php si différent:"
Write-Host "$`SONY_SDK_CLI = __DIR__ . '/sony/build/RemoteCli.exe';" -ForegroundColor Green
