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
<link rel="stylesheet" href="src/css/fonts.css" />
<link rel="stylesheet" href="src/css/all.min.css" />
<link rel="stylesheet" href="src/css/style.css" />
<style>
  /* Gallery styles pour écran tactile avec grosse barre de défilement */
  html, body { height: 100%; }
  
  /* Barre de défilement épaisse et visible */
  ::-webkit-scrollbar {
    width: 16px;
    height: 16px;
  }
  
  ::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
  }
  
  ::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 8px;
    border: 2px solid #f1f1f1;
  }
  
  ::-webkit-scrollbar-thumb:hover {
    background: #555;
  }
  
  ::-webkit-scrollbar-corner {
    background: #f1f1f1;
  }
  
  /* Pour Firefox */
  * {
    scrollbar-width: thick;
    scrollbar-color: #888 #f1f1f1;
  }
  
  #gallery-shell { 
    padding: 0.8rem 1.2rem 2rem; 
  }
  
  #gallery-head { 
    margin-bottom: 0.8rem; 
  }
  
  #gallery-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); 
    gap: 0.7rem; 
    align-content: start; 
  }
  
  #gallery-grid .item img { 
    width: 100%; 
    height: 140px; 
    object-fit: cover; 
    border-radius: 8px; 
    border: 2px solid rgba(255,255,255,0.6); 
    background: #eee; 
    cursor: pointer; 
    transition: transform 0.2s; 
  }
  
  #gallery-grid .item img:hover { 
    transform: scale(1.02); 
  }
  
  #loadMore { 
    margin: 1.1rem auto 0; 
  }
  
  #photoModal.modal { 
    align-items: center; 
    justify-content: center; 
  }
  
  #photoModal img { 
    max-width: 92vw; 
    max-height: 92vh; 
  }
  
  .gallery-actions { 
    text-align: center; 
    margin-top: 1rem; 
  }

  /* Styles pour les modales */
  .modal-actions {
    padding: 1rem 0 0;
    border-top: 1px solid rgba(255,255,255,0.2);
    margin-top: 1rem;
  }

  .print-format-btn {
    transition: all 0.3s ease;
  }

  .print-format-btn.active {
    background-color: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
  }

  .print-format-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,123,255,0.3);
  }

  #printModal .modal-content {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  }
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

  <!-- Modal Photo -->
  <div id="photoModal" class="modal" style="display:none;">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">&times;</button>
      <img id="modalImage" src="" alt="Photo en grand" />
      <div class="modal-actions" style="text-align: center; margin-top: 1rem;">
        <button class="btn primary" onclick="openPrintModal()" style="padding: 0.8rem 1.5rem; font-size: 1rem;">
          <i class="fas fa-print"></i> Imprimer
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Impression -->
  <div id="printModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px; padding: 2rem;">
      <button class="modal-close" onclick="closePrintModal()">&times;</button>
      <h2 style="text-align: center; margin-bottom: 1.5rem;">
        <i class="fas fa-print"></i> Options d'impression
      </h2>
      
      <!-- Nombre d'exemplaires -->
      <div style="margin-bottom: 1.5rem;">
        <label for="copyCount" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
          Nombre d'exemplaires :
        </label>
        <div style="display: flex; align-items: center; gap: 1rem;">
          <button type="button" onclick="decreaseCopies()" class="btn secondary" style="width: 40px; height: 40px; padding: 0;">
            <i class="fas fa-minus"></i>
          </button>
          <input type="number" id="copyCount" value="1" min="1" max="10" 
                 style="width: 80px; text-align: center; font-size: 1.2rem; padding: 0.5rem; border: 2px solid #ddd; border-radius: 8px;">
          <button type="button" onclick="increaseCopies()" class="btn secondary" style="width: 40px; height: 40px; padding: 0;">
            <i class="fas fa-plus"></i>
          </button>
        </div>
      </div>

      <!-- Format d'impression -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; margin-bottom: 1rem; font-weight: 600;">
          Format d'impression :
        </label>
        <div style="display: flex; gap: 1rem;">
          <button type="button" id="print1" onclick="selectPrintFormat(1)" 
                  class="btn secondary print-format-btn active" 
                  style="flex: 1; padding: 1rem; text-align: center; border: 2px solid #007bff;">
            <i class="fas fa-image" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
            <strong>1 photo / page</strong><br>
            <small>Pleine page</small>
          </button>
          <button type="button" id="print2" onclick="selectPrintFormat(2)" 
                  class="btn secondary print-format-btn" 
                  style="flex: 1; padding: 1rem; text-align: center;">
            <i class="fas fa-images" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
            <strong>2 photos / page</strong><br>
            <small>Format économique</small>
          </button>
        </div>
      </div>

      <!-- Boutons d'action -->
      <div style="display: flex; gap: 1rem;">
        <button class="btn secondary" onclick="closePrintModal()" style="flex: 1; padding: 0.8rem;">
          <i class="fas fa-times"></i> Annuler
        </button>
        <button class="btn primary" onclick="startPrint()" style="flex: 1; padding: 0.8rem;">
          <i class="fas fa-print"></i> Imprimer
        </button>
      </div>
    </div>
  </div>

<script src="src/js/config.js"></script>
<script>
// Variables de configuration
let loadedCount = <?= count($displayFiles) ?>;
const totalCount = <?= count($files) ?>;
const allFiles = <?= json_encode(array_map('basename', $files)) ?>;
const photosFolder = getPhotosFolder();

// Variables pour l'impression
let currentImageForPrint = '';
let selectedPrintFormat = 1; // 1 ou 2 photos par page

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
  currentImageForPrint = src; // Sauvegarder l'image pour l'impression
  modal.style.display = 'flex';
  requestAnimationFrame(()=> modal.classList.add('show'));
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  const modal = document.getElementById('photoModal');
  modal.classList.remove('show');
  setTimeout(()=>{ modal.style.display='none'; document.body.style.overflow='auto'; }, 200);
}

// Fonctions pour la modale d'impression
function openPrintModal() {
  const printModal = document.getElementById('printModal');
  printModal.style.display = 'flex';
  requestAnimationFrame(()=> printModal.classList.add('show'));
  
  // Réinitialiser les valeurs par défaut
  document.getElementById('copyCount').value = 1;
  selectPrintFormat(1);
}

function closePrintModal() {
  const printModal = document.getElementById('printModal');
  printModal.classList.remove('show');
  setTimeout(()=>{ printModal.style.display='none'; }, 200);
}

function increaseCopies() {
  const input = document.getElementById('copyCount');
  const currentValue = parseInt(input.value);
  if (currentValue < 10) {
    input.value = currentValue + 1;
  }
}

function decreaseCopies() {
  const input = document.getElementById('copyCount');
  const currentValue = parseInt(input.value);
  if (currentValue > 1) {
    input.value = currentValue - 1;
  }
}

function selectPrintFormat(format) {
  selectedPrintFormat = format;
  
  // Mettre à jour l'interface
  document.getElementById('print1').classList.toggle('active', format === 1);
  document.getElementById('print2').classList.toggle('active', format === 2);
  
  // Mettre à jour les styles
  const btn1 = document.getElementById('print1');
  const btn2 = document.getElementById('print2');
  
  if (format === 1) {
    btn1.style.borderColor = '#007bff';
    btn1.style.backgroundColor = '#007bff';
    btn1.style.color = 'white';
    btn2.style.borderColor = '#ddd';
    btn2.style.backgroundColor = '';
    btn2.style.color = '';
  } else {
    btn2.style.borderColor = '#007bff';
    btn2.style.backgroundColor = '#007bff';
    btn2.style.color = 'white';
    btn1.style.borderColor = '#ddd';
    btn1.style.backgroundColor = '';
    btn1.style.color = '';
  }
}

function startPrint() {
  const copies = document.getElementById('copyCount').value;
  const format = selectedPrintFormat;
  
  if (!currentImageForPrint) {
    alert('Aucune image sélectionnée pour l\'impression');
    return;
  }
  
  // Afficher un indicateur de chargement
  const printBtn = document.querySelector('#printModal .btn.primary');
  const originalText = printBtn.innerHTML;
  printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Impression...';
  printBtn.disabled = true;
  
  // Préparer les données pour l'impression
  const printData = {
    image: currentImageForPrint,
    copies: copies,
    format: format,
    source: 'gallery'
  };
  
  // Envoyer la demande d'impression
  fetch('print_photo.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(printData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(`Impression lancée avec succès !\n${copies} exemplaire(s) en format ${format} photo(s) par page`);
      closePrintModal();
      closeModal();
    } else {
      alert('Erreur lors de l\'impression: ' + (data.error || 'Erreur inconnue'));
    }
  })
  .catch(error => {
    console.error('Erreur:', error);
    alert('Erreur lors de l\'impression: ' + error.message);
  })
  .finally(() => {
    // Restaurer le bouton
    printBtn.innerHTML = originalText;
    printBtn.disabled = false;
  });
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
document.addEventListener('keydown', e => { 
  if(e.key==='Escape') {
    // Fermer la modale d'impression en priorité si elle est ouverte
    const printModal = document.getElementById('printModal');
    if (printModal.style.display === 'flex') {
      closePrintModal();
    } else {
      closeModal();
    }
  }
});

// Délégation clic sur images (meilleur perf)
document.getElementById('gallery-grid')?.addEventListener('click', e => { if(e.target.tagName==='IMG') openModal(e.target.dataset.full); });
</script>
</body>
</html>
