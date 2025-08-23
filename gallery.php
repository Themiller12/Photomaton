<?php
$files = glob(__DIR__.'/captures/*.jpg');
$files = array_reverse($files);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Galerie - Photomaton</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="src/css/style.css" />
</head>
<body>
  <div class="screen" id="gallery">
    <h1>📷 Galerie 📷</h1>
    <p style="font-size: 1.3rem; color: var(--charcoal); margin-bottom: 2rem; font-weight: 300;">
      Tous vos souvenirs capturés
    </p>
    <div class="grid">
      <?php foreach($files as $f): $base = basename($f); ?>
        <div class="item"><img loading="lazy" src="captures/<?= $base ?>" alt="photo" /></div>
      <?php endforeach; ?>
      <?php if(empty($files)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem;">
          <p style="font-size: 1.5rem; color: var(--charcoal);">
            ✨ Aucune photo pour le moment ✨<br>
            <span style="font-size: 1.1rem; opacity: 0.7;">Commencez à créer de beaux souvenirs !</span>
          </p>
        </div>
      <?php endif; ?>
    </div>
    <div class="controls">
      <button class="btn" onclick="window.location='index.php'">🏠 Accueil</button>
    </div>
  </div>
</body>
</html>
