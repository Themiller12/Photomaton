<?php
// Récupérer les fichiers JPG et JPEG (majuscules et minuscules)
$photosFolder = 'captures'; // Valeur par défaut, sera remplacée par JavaScript
$files = array_merge(
  glob(__DIR__.'/'.$photosFolder.'/*.jpg'),
  glob(__DIR__.'/'.$photosFolder.'/*.JPG'),
  glob(__DIR__.'/'.$photosFolder.'/*.jpeg'),
  glob(__DIR__.'/'.$photosFolder.'/*.JPEG')
);
// Trier par date de modification (plus récent en premier)
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Galerie - Photomaton</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link rel="stylesheet" href="src/css/style.css" />
</head>
<body>
  <div class="screen" id="gallery">
    <h1 id="gallery-title"><i class="fas fa-camera"></i> Galerie <i class="fas fa-camera"></i></h1>
    <p id="gallery-subtitle" style="font-size: 1.3rem; color: var(--charcoal); margin-bottom: 2rem; font-weight: 300;">
      Tous vos souvenirs capturés (<span id="photo-count"><?= count($files) ?></span> photos)
    </p>
    <div class="grid" id="photoGrid">
      <?php 
      // Utiliser la configuration pour le nombre initial
      $initialLoad = 20; // Valeur par défaut, sera remplacée par JavaScript
      $displayFiles = array_slice($files, 0, $initialLoad);
      foreach($displayFiles as $f): 
        $base = basename($f); 
      ?>
        <div class="item">
          <img loading="lazy" src="<?= $photosFolder ?>/<?= $base ?>" alt="photo" onclick="openModal(this.src)" style="cursor: pointer;" />
        </div>
      <?php endforeach; ?>
      <?php if(empty($files)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem;">
          <p id="no-photos-msg" style="font-size: 1.5rem; color: var(--charcoal);">
            <i class="fas fa-sparkles"></i> Aucune photo pour le moment <i class="fas fa-sparkles"></i><br>
            <span id="no-photos-sub" style="font-size: 1.1rem; opacity: 0.7;">Commencez à créer de beaux souvenirs !</span>
          </p>
        </div>
      <?php endif; ?>
    </div>
    
    <?php if(count($files) > $initialLoad): ?>
    <div style="margin: 2rem 0;">
      <button id="loadMore" class="btn secondary" onclick="loadMorePhotos()">
        <i class="fas fa-camera"></i> Voir plus de photos (<span id="remaining-count"><?= count($files) - $initialLoad ?></span> restantes)
      </button>
    </div>
    <?php endif; ?>
    
    <div class="controls">
      <button class="btn" onclick="window.location='index.php'"><i class="fas fa-home"></i> Accueil</button>
    </div>
  </div>
  
  <!-- Modal pour affichage en grand -->
  <div id="photoModal" class="modal" onclick="closeModal()">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">&times;</button>
      <img id="modalImage" src="" alt="Photo en grand">
    </div>
  </div>

<script src="src/js/config.js"></script>
<script>
// Variables de configuration
let loadedCount = <?= count($displayFiles) ?>;
const totalCount = <?= count($files) ?>;
const allFiles = <?= json_encode(array_map('basename', $files)) ?>;
const photosFolder = getPhotosFolder();

// Mettre à jour les textes avec la configuration
document.addEventListener('DOMContentLoaded', function() {
  const galleryTitle = document.getElementById('gallery-title');
  const gallerySubtitle = document.getElementById('gallery-subtitle');
  const noPhotosMsg = document.getElementById('no-photos-msg');
  const noPhotosSub = document.getElementById('no-photos-sub');
  
  if (galleryTitle) galleryTitle.textContent = getMessage('galleryTitle');
  if (gallerySubtitle) {
    const photoCount = document.getElementById('photo-count');
    gallerySubtitle.innerHTML = getMessage('gallerySubtitle') + ' (<span id="photo-count">' + totalCount + '</span> photos)';
  }
  if (noPhotosMsg) noPhotosMsg.innerHTML = getMessage('noPhotosMessage') + '<br><span id="no-photos-sub" style="font-size: 1.1rem; opacity: 0.7;">' + getMessage('noPhotosSubMessage') + '</span>';
});

function openModal(src) {
  const modal = document.getElementById('photoModal');
  const modalImg = document.getElementById('modalImage');
  modalImg.src = src;
  modal.classList.add('show');
  document.body.style.overflow = 'hidden'; // Empêcher le scroll
}

function closeModal() {
  const modal = document.getElementById('photoModal');
  modal.classList.remove('show');
  document.body.style.overflow = 'auto'; // Réactiver le scroll
}

function loadMorePhotos() {
  const grid = document.getElementById('photoGrid');
  const loadMoreBtn = document.getElementById('loadMore');
  const loadMoreCount = window.PHOTOMATON_CONFIG.galleryLoadMore;
  
  // Charger le nombre de photos configuré
  const nextBatch = allFiles.slice(loadedCount, loadedCount + loadMoreCount);
  
  nextBatch.forEach(filename => {
    const item = document.createElement('div');
    item.className = 'item';
    item.innerHTML = `<img loading="lazy" src="${photosFolder}/${filename}" alt="photo" onclick="openModal(this.src)" style="cursor: pointer;" />`;
    grid.appendChild(item);
  });
  
  loadedCount += nextBatch.length;
  
  // Mettre à jour ou cacher le bouton
  if (loadedCount >= totalCount) {
    loadMoreBtn.style.display = 'none';
  } else {
    const remainingCount = document.getElementById('remaining-count');
    if (remainingCount) remainingCount.textContent = totalCount - loadedCount;
  }
}

// Fermer avec la touche Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal();
  }
});
</script>
</body>
</html>
