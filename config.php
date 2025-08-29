<?php
// Configuration générale Photomaton
// Mode capture : 'webcam', 'dslr_win', 'sony_wifi', 'sony_sdk', 'folder_watch'
// folder_watch : on surveille simplement l'arrivée d'un nouveau fichier dans captures/ (utiliser Imaging Edge Desktop configuré pour enregistrer là)
$CAPTURE_MODE = 'dslr_win'; // valeurs possibles: 'dslr_win', 'webcam', 'sony_wifi', 'sony_sdk', 'folder_watch'

// Emplacement de CameraControlCmd.exe (digiCamControl) sous Windows
// Adapter si installé ailleurs.
$CAMERA_CMD = 'C:\\Program Files (x86)\\digiCamControl\\CameraControlCmd.exe';

// Pattern de nom de fichier utilisé par la commande /filename (personnalisable si besoin)
// Placeholders supportés par digiCamControl: %Y %m %d %H %M %S %i etc.
$CAPTURE_FILENAME_PATTERN = 'capture_%Y%m%d_%H%M%S_%i.jpg';

// Extensions surveillées après une capture (ordre de priorité). Ajouter 'ARW' si RAW+JPEG et que JPEG absent.
$CAPTURE_EXTS = ['jpg','jpeg','JPG','JPEG'];

// Chemin vers le binaire Sony SDK (RemoteCli compilé) si utilisation mode 'sony_sdk'
// Après build Release : sony/build/Release/RemoteCli.exe
$SONY_SDK_CLI = __DIR__ . '/sony/build/Release/RemoteCli.exe';

// --- Configuration Sony WiFi Remote API ---
// Si vous utilisez le mode 'sony_wifi', renseignez l'adresse IP de l'appareil photo (trouvée après appairage WiFi ou via routeur)
// Exemple: 192.168.122.1 (souvent l'API écoute sur ce genre d'IP ou celle donnée par la caméra)
$SONY_WIFI_IP = '192.168.122.1';
// Timeout HTTP (secondes) pour les requêtes vers la caméra
$SONY_HTTP_TIMEOUT = 8;
// Version API (1.0 ou 1.3 selon modèle). On utilisera 1.0 par défaut.
$SONY_API_VERSION = '1.0';

// Polling WiFi (utile si actTakePicture ne renvoie pas immédiatement l'URL)
// Nombre max de polls getEvent après actTakePicture si aucune URL reçue
$SONY_WIFI_MAX_EVENT_POLLS = 15; // ~15 * 300ms = 4.5s par défaut
// Intervalle en millisecondes entre deux polls
$SONY_WIFI_EVENT_POLL_INTERVAL_MS = 300;

// Dossier de capture (chemin absolu)
$CAPTURE_DIR = __DIR__ . '/captures';
if(!is_dir($CAPTURE_DIR)) @mkdir($CAPTURE_DIR, 0775, true);

// Timeout (secondes) attente écriture fichier après déclenchement
$CAPTURE_TIMEOUT = 12;

// Nombre de photos par séquence (le front en déclenche 3)
$SEQUENCE_COUNT = 3;

// Autoriser exécution concurrente ? false = une session à la fois
$ALLOW_CONCURRENT = false;

// Impression : modes possibles
// 'copy'  -> copie le fichier vers prints/ (placeholder)
// 'command' -> exécute une commande système (ex: PowerShell impression silencieuse) une fois par copie
// Vous pouvez plus tard ajouter un mode 'lp' sous Linux.
$PRINT_MODE = 'copy';

// Dossier de sortie des impressions (traces / logs ou spool local)
$PRINT_OUTPUT_DIR = __DIR__ . '/prints';
if(!is_dir($PRINT_OUTPUT_DIR)) @mkdir($PRINT_OUTPUT_DIR, 0775, true);

// Commande d'impression Windows (utilisée si $PRINT_MODE === 'command')
// %file% sera remplacé par le chemin absolu du fichier.
// Exemple basique (peut ouvrir une fenêtre selon l'assoc. d'extension) :
//   powershell -NoProfile -Command Start-Process -FilePath %file% -Verb Print -WindowStyle Hidden
// Pour certaines imprimantes on peut utiliser un utilitaire spécifique en ligne de commande.
$PRINT_CMD = 'powershell -NoProfile -Command Start-Process -FilePath %file% -Verb Print';

// Debug : si true, écrit un journal détaillé des captures (commande, code retour, nouveaux fichiers)
$DEBUG_CAPTURE = true;
// Fichier log
$CAPTURE_LOG_FILE = __DIR__ . '/capture_log.txt';
?>
