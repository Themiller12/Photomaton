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
<style>
  /* Gallery performance optimizations for Raspberry Pi */
  html, body { height:auto; min-height:100%; overflow-y:auto; touch-action:pan-y; }
  #gallery-shell { padding:0.8rem 1.2rem 2rem; }
  #gallery-head { margin-bottom:0.8rem; }
  #gallery-grid { 
    display:grid; 
    grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); 
    gap:0.7rem; 
    align-content:start; 
  }
  #gallery-grid .item img { 
    width:100%; height:140px; object-fit:cover; border-radius:8px; 
    border:2px solid rgba(255,255,255,0.6); 
    background:#eee; 
    cursor:pointer; 
    transition:opacity .15s; 
  }
  #gallery-grid .item img:active { opacity:0.75; }
  #loadMore { margin:1.1rem auto 0; }
  #photoModal.modal { align-items:center; justify-content:center; }
  #photoModal img { max-width:92vw; max-height:92vh; }
  /* Hide scrollbars visually (optional) */
  #gallery-grid::-webkit-scrollbar { width:0; height:0; }
  /* Pin action buttons */
  .gallery-actions { text-align:center; margin-top:1rem; }
</style>
</head>
<body>
  <div id="gallery-shell">
    <div id="gallery-head">
      <h1 id="gallery-title"><i class="fas fa-images"></i> Galerie</h1>
      <p id="gallery-subtitle" style="font-size:1.05rem; margin:0 0 1rem; font-weight:400;">
        Tous vos souvenirs capturés (<span id="photo-count"><?= count($files) ?></span> photos)
      </p>
    </div>
    <div id="gallery-grid">
        <?php 
        $initialLoad = 20; // Valeur par défaut
        $displayFiles = array_slice($files, 0, $initialLoad);
        foreach($displayFiles as $f): $base=basename($f); ?>
          <div class="item"><img loading="lazy" src="<?= $photosFolder ?>/<?= $base ?>" data-full="<?= $photosFolder ?>/<?= $base ?>" alt="photo" /></div>
        <?php endforeach; ?>
        <?php if(empty($files)): ?>
          <div style="grid-column:1/-1; text-align:center; padding:3rem 0;">
            <p id="no-photos-msg" style="font-size:1.2rem; opacity:.8;">
              Aucune photo pour le moment<br><span id="no-photos-sub" style="font-size:0.95rem;">Commencez à créer de beaux souvenirs !</span>
            </p>
          </div>
        <?php endif; ?>
    </div>
    <?php if(count($files) > $initialLoad): ?>
      <div class="gallery-actions">
        <button id="loadMore" class="btn secondary" style="padding:0.8rem 2rem; font-size:1rem;" onclick="loadMorePhotos()">
          <i class="fas fa-plus"></i> Voir plus (<span id="remaining-count"><?= count($files) - $initialLoad ?></span>)
        </button>
      </div>
    <?php endif; ?>
    <div class="gallery-actions">
      <button class="btn" style="padding:0.8rem 2rem; font-size:1rem;" onclick="window.location='index.php'"><i class="fas fa-home"></i> Accueil</button>
    </div>
    </div>
  </div>

  <!-- Modal -->
  <div id="photoModal" class="modal" style="display:none;">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">&times;</button>
      <img id="modalImage" src="" alt="Photo en grand" />
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
  modal.style.display = 'flex';
  requestAnimationFrame(()=> modal.classList.add('show'));
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  const modal = document.getElementById('photoModal');
  modal.classList.remove('show');
  setTimeout(()=>{ modal.style.display='none'; document.body.style.overflow='auto'; }, 200);
}

function loadMorePhotos() {
  const grid = document.getElementById('gallery-grid');
  const loadMoreBtn = document.getElementById('loadMore');
  const batchSize = window.PHOTOMATON_CONFIG.galleryLoadMore;
  const nextBatch = allFiles.slice(loadedCount, loadedCount + batchSize);
  nextBatch.forEach(fn => {
    const wrap = document.createElement('div');
    wrap.className='item';
    wrap.innerHTML = `<img loading="lazy" src="${photosFolder}/${fn}" data-full="${photosFolder}/${fn}" alt="photo" />`;
    grid.appendChild(wrap);
  });
  loadedCount += nextBatch.length;
  if (loadedCount >= totalCount) loadMoreBtn?.style && (loadMoreBtn.style.display='none');
  else {
    const r = document.getElementById('remaining-count');
    if (r) r.textContent = totalCount - loadedCount;
  }
}

// Fermer avec la touche Escape
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

// Délégation clic sur images (meilleur perf)
document.getElementById('gallery-grid')?.addEventListener('click', e => { if(e.target.tagName==='IMG') openModal(e.target.dataset.full); });
</script>
</body>
</html>
