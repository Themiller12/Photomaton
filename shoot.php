<?php
// Session de prise de vues
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Prise de vue - Photomaton</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="src/css/fonts.css" />
<link rel="stylesheet" href="src/css/all.min.css" />
<link rel="stylesheet" href="src/css/style.css" />
<style>
/* Style pour la modale d'aperçu impression double */
#print2up-preview-modal .modal-content {
  max-width: 600px;
  width: 90%;
}

#print2up-preview-modal .modal-body {
  padding: 1.5rem;
}

#print2up-preview-modal #preview2up-image {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  margin: 1rem 0;
}

#print2up-preview-modal .modal-footer {
  gap: 1rem;
}
</style>
</head>
<body>
  <div class="screen" id="capture-screen">
    <h1><i class="fa-solid fa-spray-can-sparkles"></i> <span id="prepare-title">Préparez-vous !</span></h1>
    <p id="prepare-subtitle" style="font-size: 1.2rem; color: var(--charcoal); margin-bottom: 2rem;">
      Souriez, prenez votre plus belle pose et appuyez quand vous êtes prêts !
    </p>
    <video id="live-view" autoplay playsinline></video>
    <div id="countdown"></div>
    <div class="controls">
      <button id="start-sequence" class="btn"><i class="fas fa-camera"></i> Prendre <span id="photo-count">3</span> photos</button>
      <button id="single-photo" class="btn"><i class="fas fa-camera-retro"></i> Prendre 1 photo</button>
      <button class="btn secondary" onclick="window.location='index.php'"><i class="fas fa-times"></i> Annuler</button>
    </div>
  </div>
  <div class="screen hidden" id="selection-screen">
    <h2><i class="fas fa-star"></i> Choisissez votre photo préférée <i class="fas fa-star"></i></h2>
    <div id="thumbnails" class="thumbs"></div>
    <div class="controls" style="align-items: center;">
      <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; width: 100%;">
        <button id="retake-photo" class="btn secondary" style="display: none;">
          <i class="fas fa-redo"></i> Reprendre une photo
        </button>
        <label for="copies"><i class="fas fa-heart"></i> Nombre de copies :</label>
        <select id="copies">
          <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
        </select>
      </div>
      <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; width: 100%;">
        <button id="print-selected" class="btn"><i class="fas fa-print"></i> Imprimer</button>
        <button id="print-double" class="btn" style="background: linear-gradient(135deg, #9CAF88 0%, #E8B4B8 100%);">
          <i class="fas fa-clone"></i> 2 photos / page
        </button>
        <button class="btn secondary" onclick="window.location='index.php'"><i class="fas fa-home"></i> Terminer</button>
      </div>
    </div>
  </div>

  
  <!-- Modale d'impression -->
  <div id="print-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <i class="fas fa-print modal-icon"></i>
        <h3 id="modal-title">Impression en cours...</h3>
      </div>
      <div class="modal-body">
        <div id="print-spinner" class="spinner">
          <div class="spinner-ring"></div>
          <div class="spinner-ring"></div>
          <div class="spinner-ring"></div>
        </div>
        <p id="modal-message">Votre photo est en cours d'impression, veuillez patienter...</p>
        <div id="print-success" class="success-animation" style="display: none;">
          <i class="fas fa-check-circle"></i>
          <span>Impression réussie !</span>
        </div>
        <div id="print-error" class="error-message" style="display: none;">
          <i class="fas fa-exclamation-triangle"></i>
          <span id="error-text">Une erreur s'est produite</span>
        </div>
      </div>
      <div class="modal-footer">
        <button id="modal-close" class="btn secondary" style="display: none;">
          <i class="fas fa-times"></i> Fermer
        </button>
        <button id="modal-retry" class="btn" style="display: none;">
          <i class="fas fa-redo"></i> Réessayer
        </button>
        <button id="modal-home" class="btn" style="display: none;">
          <i class="fas fa-home"></i> Terminer
        </button>
      </div>
    </div>
  </div>

  
<script src="src/js/config.js"></script>
<script>
// Pour compatibilité avec l'ancien système
window.PHOTOMATON_MODE = getCameraMode();

// Mettre à jour les textes avec la configuration
document.addEventListener('DOMContentLoaded', function() {
  // Mettre à jour les titres et sous-titres
  const prepareTitle = document.getElementById('prepare-title');
  const prepareSubtitle = document.getElementById('prepare-subtitle');
  const photoCount = document.getElementById('photo-count');
  
  if (prepareTitle) prepareTitle.textContent = getMessage('prepareTitle');
  if (prepareSubtitle) prepareSubtitle.textContent = getMessage('prepareSubtitle');
  if (photoCount) photoCount.textContent = window.PHOTOMATON_CONFIG.photoCount;
});
</script>
<script src="src/js/effects.js"></script>
<script src="src/js/capture.js"></script>
</body>
</html>
