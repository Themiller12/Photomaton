<?php
// Session de prise de vues
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Prise de vue - Photomaton</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="src/css/style.css" />
</head>
<body>
  <div class="screen" id="capture-screen">
    <h2 id="prepare-title">✨ Préparez-vous ! ✨</h2>
    <p id="prepare-subtitle" style="font-size: 1.2rem; color: var(--charcoal); margin-bottom: 2rem;">
      Souriez, prenez votre plus belle pose et appuyez quand vous êtes prêts !
    </p>
    <video id="live-view" autoplay playsinline></video>
    <div id="countdown"></div>
    <div class="controls">
      <button id="start-sequence" class="btn">📸 Prendre <span id="photo-count">3</span> photos</button>
      <button id="single-photo" class="btn">📷 Prendre 1 photo</button>
      <button class="btn secondary" onclick="window.location='index.php'">❌ Annuler</button>
    </div>
  </div>
  <div class="screen hidden" id="selection-screen">
    <h2>🌟 Choisissez votre photo préférée 🌟</h2>
    <div id="thumbnails" class="thumbs"></div>
    <div class="controls" style="align-items: center;">
      <label for="copies">💝 Nombre de copies :</label>
      <select id="copies">
        <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
      </select>
      <button id="print-selected" class="btn">🖨️ Imprimer</button>
      <button class="btn secondary" onclick="window.location='index.php'">🏠 Terminer</button>
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
