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
    <h2>✨ Préparez-vous ! ✨</h2>
    <p style="font-size: 1.2rem; color: var(--charcoal); margin-bottom: 2rem;">
      Souriez, 3 magnifiques photos vous attendent
    </p>
    <video id="live-view" autoplay playsinline></video>
    <div id="countdown"></div>
    <div class="controls">
      <button id="start-sequence" class="btn">📸 Prendre Photo</button>
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
<script>
// Expose mode côté front (config surveillée par capture.js)
// Valeurs possibles: 'dslr_win', 'webcam', 'sony_wifi'
window.PHOTOMATON_MODE = 'sony_sdk'; // changer en 'sony_wifi' si utilisation WiFi API
</script>
<script src="src/js/effects.js"></script>
<script src="src/js/capture.js"></script>
</body>
</html>
