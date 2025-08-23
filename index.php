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
    <h1>💕 Photomaton 💕</h1>
    <p style="font-size: 1.5rem; color: var(--charcoal); margin-bottom: 3rem; font-weight: 300;">
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
</body>
</html>
