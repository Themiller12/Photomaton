/**
 * Configuration globale du Photomaton
 * Modifiez ces valeurs selon vos besoins
 */

// Configuration principale
// Détection avancée de l'OS avec possibilités d'override (paramètre URL ?os=linux ou localStorage)
function detectOperatingSystem() {
  try {
    const params = new URLSearchParams(location.search);
    const forced = params.get('os') || localStorage.getItem('PHOTOMATON_OS_OVERRIDE');
    if (forced) {
      console.log('[Photomaton] OS forcé via override =', forced);
      return forced;
    }
    const ua = navigator.userAgent.toLowerCase();
    const platform = (navigator.platform || '').toLowerCase();
    if (/linux|x11/.test(ua) || /linux|arm|raspbian/.test(platform)) return 'linux';
    if (ua.includes('mac') || platform.includes('mac')) return 'macos';
    return 'windows';
  } catch(e) {
    console.warn('OS detection error:', e);
    return 'windows';
  }
}

const DETECTED_OS = detectOperatingSystem();

// Camera mode dynamique: si Linux par défaut on passe en dslr_linux (sauf override explicite)
function initialCameraMode() {
  const paramMode = new URLSearchParams(location.search).get('mode');
  if (paramMode) return paramMode;
  const stored = localStorage.getItem('PHOTOMATON_CAMERA_MODE');
  if (stored) return stored;
  return DETECTED_OS === 'linux' ? 'dslr_linux' : 'dslr_win';
}

// Printer type dynamique: Linux utilise CUPS par défaut
function initialPrinterType() {
  const paramType = new URLSearchParams(location.search).get('printer');
  if (paramType) return paramType;
  const stored = localStorage.getItem('PHOTOMATON_PRINTER_TYPE');
  if (stored) return stored;
  return DETECTED_OS === 'linux' ? 'linux_cups' : 'browser';
}

window.PHOTOMATON_CONFIG = {
  
  // === CONFIGURATION SYSTÈME ===
  // OS détecté automatiquement: 'windows', 'linux', 'macos' (override possible)
  operatingSystem: DETECTED_OS,
  
  // === CONFIGURATION APPAREIL PHOTO ===
  // Modes disponibles: 'dslr_win', 'dslr_linux', 'webcam', 'sony_wifi', 'sony_sdk', 'folder_watch'
  cameraMode: initialCameraMode(),
  
  // === CONFIGURATION MARIAGE ===
  // Nom du mariage affiché dans l'interface
  marriageName: 'Mariage de Léa & Corentin',
  
  // === CONFIGURATION IMPRESSION ===
  // 1 = Proposer l'impression, 0 = Masquer l'impression
  enablePrinting: 1,
  
  // Type d'impression: 'browser', 'simple', 'linux_cups', 'selphy_optimized', 'canon_cp1500', 'default_printer', 'mspaint'
  printerType: initialPrinterType(),
  
  // Nom de l'imprimante (optionnel, pour identification)
  printerName: 'Canon SELPHY CP1500',
  
  // Format papier par défaut (basé sur le fichier PPD)
  defaultPaperSize: 'Postcard.Fullbleed', // 10x15cm format photo standard
  
  // Impression automatique ou manuelle
  autoPrint: true, // true = impression automatique après 3 secondes, false = clic manuel
  
  // === CONFIGURATION STOCKAGE ===
  // Nom du dossier où sont stockées les photos (sans slash)
  photosFolder: 'captures',
  
  // === CONFIGURATION CAPTURE ===
  // Nombre de photos dans une séquence complète
  photoCount: 3,
  
  // Délai entre les photos en mode séquence (ms)
  delayBetweenPhotos: 3000,
  
  // === CONFIGURATION AFFICHAGE ===
  // Nombre de photos à afficher initialement dans la galerie
  galleryInitialLoad: 20,
  
  // Nombre de photos à charger à chaque "Voir plus"
  galleryLoadMore: 20,
  
  // === MESSAGES PERSONNALISABLES ===
  messages: {
    welcomeTitle: 'Photomaton',
    welcomeSubtitle: "Amusez-vous, faites des grimaces ou des poses originales !<br>Les photos seront ensuite envoyées aux mariés pour des souvenirs inoubliables.",
    prepareTitle: 'Préparez-vous !',
    prepareSubtitle: 'Souriez et prenez votre plus belle pose ! Le décompte dure 3 secondes.',
    galleryTitle: 'Galerie',
    gallerySubtitle: 'Tous vos souvenirs capturés',
    singlePhotoTitle: 'Votre magnifique photo !',
    multiPhotoTitle: 'Choisissez votre photo préférée',
    countdownMessage: 'On ne bouge plus ! 😁',
    noPhotosMessage: 'Aucune photo pour le moment',
    noPhotosSubMessage: 'Commencez à créer de beaux souvenirs !'
  }
};

// === FONCTIONS UTILITAIRES ===

// Obtenir le mode de l'appareil photo
window.getCameraMode = function() {
  return window.PHOTOMATON_CONFIG.cameraMode;
};

// Vérifier si l'impression est activée
window.isPrintingEnabled = function() {
  return window.PHOTOMATON_CONFIG.enablePrinting === 1;
};

// Obtenir le chemin du dossier photos
window.getPhotosFolder = function() {
  return window.PHOTOMATON_CONFIG.photosFolder;
};

// Obtenir un message personnalisé
window.getMessage = function(key) {
  return window.PHOTOMATON_CONFIG.messages[key] || '';
};

// Pour compatibilité avec l'ancien système
window.PHOTOMATON_MODE = window.PHOTOMATON_CONFIG.cameraMode;

console.log('🎉 Configuration Photomaton chargée:', window.PHOTOMATON_CONFIG);

// Fonctions d'override à chaud (pour débogage depuis la console navigateur)
window.overridePhotomatonOS = function(newOS){
  localStorage.setItem('PHOTOMATON_OS_OVERRIDE', newOS);
  console.log('[Photomaton] OS override enregistré =', newOS, ' -> recharger la page.');
};
window.overridePhotomatonMode = function(newMode){
  localStorage.setItem('PHOTOMATON_CAMERA_MODE', newMode);
  console.log('[Photomaton] Camera mode override =', newMode, ' -> recharger la page.');
};
window.overridePhotomatonPrinter = function(newType){
  localStorage.setItem('PHOTOMATON_PRINTER_TYPE', newType);
  console.log('[Photomaton] Printer type override =', newType, ' -> recharger la page.');
};
