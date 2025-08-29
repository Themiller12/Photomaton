<?php
// Landing page: choose new photo session or gallery
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Photomaton Mariage</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="src/css/style.css" />
</head>
<body>
  <div class="screen" id="home">
    <h1 id="welcome-title">💕 Photomaton 💕</h1>
    <h2 id="marriage-name" style="font-size: 1.8rem; color: var(--rose-gold); margin-bottom: 1rem; font-weight: 400;"></h2>
    <p id="welcome-subtitle" style="font-size: 1.5rem; color: var(--charcoal); margin-bottom: 3rem; font-weight: 300;">
      Capturez vos plus beaux souvenirs
    </p>
    <div class="buttons">
      <button class="btn btn-lg" onclick="window.location='shoot.php'">
        📸 Nouvelle Photo
      </button>
      <button class="btn btn-lg" onclick="window.location='gallery.php'">
        🖼️ Galerie
      </button>
    </div>
  </div>

<script src="src/js/config.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Mettre à jour les textes avec la configuration
  const welcomeTitle = document.getElementById('welcome-title');
  const marriageName = document.getElementById('marriage-name');
  const welcomeSubtitle = document.getElementById('welcome-subtitle');
  
  if (welcomeTitle) welcomeTitle.textContent = getMessage('welcomeTitle');
  if (marriageName) marriageName.textContent = window.PHOTOMATON_CONFIG.marriageName;
  if (welcomeSubtitle) welcomeSubtitle.textContent = getMessage('welcomeSubtitle');
});
</script>
</body>
</html>
