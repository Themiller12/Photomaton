// Front-end capture sequence.
// Configuration centralisée dans config.js

const video = document.getElementById('live-view');
const startBtn = document.getElementById('start-sequence');
const singlePhotoBtn = document.getElementById('single-photo');
const retakeBtn = document.getElementById('retake-photo');
const countdownEl = document.getElementById('countdown');
const selectionScreen = document.getElementById('selection-screen');
const selectionTitle = document.querySelector('#selection-screen h2');
const captureScreen = document.getElementById('capture-screen');
const thumbsDiv = document.getElementById('thumbnails');
const printBtn = document.getElementById('print-selected');
const printDoubleBtn = document.getElementById('print-double');
const copiesSelect = document.getElementById('copies');

// Variables de configuration (depuis config.js)
const MODE = getCameraMode();
console.log('[Photomaton] MODE détecté =', MODE, 'OS =', window.PHOTOMATON_CONFIG.operatingSystem);
const PHOTO_COUNT = window.PHOTOMATON_CONFIG.photoCount;
const DELAY_BETWEEN_PHOTOS = window.PHOTOMATON_CONFIG.delayBetweenPhotos;

let captured = [];
let selectedIndex = 0;
let isSinglePhotoMode = false;
let lastPrintType = 'single'; // 'single' ou 'double'

function sleep(ms){ return new Promise(r=>setTimeout(r,ms)); }

async function initWebcamIfNeeded(){
  if(MODE !== 'webcam') { if(video) video.style.display='none'; return; }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width:1920, height:1080 }, audio:false });
    video.srcObject = stream;
  } catch(e) { alert('Impossible d\'accéder à la caméra: '+e.message); }
}

async function runSequence(){
  singlePhotoBtn.disabled = true;
  startBtn.disabled = true;
  captured = [];
  isSinglePhotoMode = false;
  
  for(let i=0;i<PHOTO_COUNT;i++){
    if(MODE === 'webcam') {
      await runCountdown(3);
      captured.push(takeSnapshot());
  } else if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
      // Pour DSLR: lancer capture pendant le décompte (latence 3-4s)
      try {
        const capturePromise = runCountdownWithCapture();
        const f = await capturePromise;
        captured.push(f);
        
        // Afficher la photo capturée en arrière-plan
        showCapturedPhoto(f, i + 1);
        
      } catch(err){
        alert('Erreur capture: '+err.message);
        startBtn.disabled = false;
        return;
      }
    }
    if(i < PHOTO_COUNT-1) await sleep(DELAY_BETWEEN_PHOTOS); // pause entre les prises
  }
  showSelection();
}

async function runSinglePhoto(){
  singlePhotoBtn.disabled = true;
  startBtn.disabled = true;
  captured = [];
  isSinglePhotoMode = true;
  
  if(MODE === 'webcam') {
    await runCountdown(3);
    captured.push(takeSnapshot());
  } else if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    // Pour DSLR: lancer capture pendant le décompte (latence 3-4s)
    try {
      const capturePromise = runCountdownWithCapture();
      const f = await capturePromise;
      captured.push(f);
      
      // Afficher la photo capturée en arrière-plan
      showCapturedPhoto(f, 1);
      
    } catch(err){
      alert('Erreur capture: '+err.message);
      singlePhotoBtn.disabled = false;
      startBtn.disabled = false;
      return;
    }
  }
  showSelection();
}

async function retakePhotos(){
  // Retourner à l'écran de capture
  selectionScreen.classList.add('hidden');
  captureScreen.classList.remove('hidden');
  
  // Réactiver les boutons temporairement
  singlePhotoBtn.disabled = false;
  startBtn.disabled = false;
  
  // Vider les photos précédentes
  captured = [];
  resetCaptureScreenBackground();
  
  // Attendre un court délai pour que l'utilisateur voie l'écran de capture
  await sleep(500);
  
  // Relancer automatiquement la capture selon le dernier mode utilisé
  if (isSinglePhotoMode) {
    console.log('[Retake] Relancement photo simple...');
    await runSinglePhoto();
  } else {
    console.log('[Retake] Relancement séquence...');
    await runSequence();
  }
}

async function runCountdown(from){
  for(let c=from; c>0; c--){
    countdownEl.textContent = c;
    countdownEl.classList.remove('countdown-pulse');
    countdownEl.classList.add('countdown-pulse');
    await sleep(1000);
  }
  countdownEl.textContent = '';
  countdownEl.classList.remove('countdown-pulse');
  if(window.PhotoEffects) {
    PhotoEffects.createFlashEffect();
    if(video) video.classList.add('photo-taking');
    setTimeout(() => video?.classList.remove('photo-taking'), 500);
  }
}

async function runCountdownWithCapture(){
  if (MODE === 'dslr_linux') {
    // Sur Linux (gphoto2) la capture est quasi instantanée: on fait le décompte AVANT puis on déclenche
    console.log('Décompte avant capture (linux rapide)');
    for(let c=3; c>0; c--){
      countdownEl.textContent = c;
      countdownEl.classList.remove('countdown-pulse');
      countdownEl.classList.add('countdown-pulse');
      await sleep(1000);
    }
    countdownEl.textContent = '';
    countdownEl.classList.remove('countdown-pulse');
    if(window.PhotoEffects) {
      PhotoEffects.createFlashEffect();
      if(video) video.classList.add('photo-taking');
      setTimeout(() => video?.classList.remove('photo-taking'), 500);
    }
    const start = Date.now();
    const file = await triggerServerFileCapture();
    console.log('Capture Linux effectuée en', Date.now()-start, 'ms');
    return file;
  } else {
    // Windows / autres DSLR: lancer la capture AVANT le décompte pour compenser la latence (digiCamControl etc.)
    console.log('Lancement capture anticipée (latence élevée)');
    const capturePromise = triggerServerFileCapture();
    await sleep(200); // laisser partir la commande
    for(let c=3; c>0; c--){
      countdownEl.textContent = c;
      countdownEl.classList.remove('countdown-pulse');
      countdownEl.classList.add('countdown-pulse');
      await sleep(1000);
    }
    countdownEl.textContent = '';
    countdownEl.classList.remove('countdown-pulse');
    const file = await capturePromise;
    return file;
  }
}

function takeSnapshot(){
  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video,0,0,canvas.width,canvas.height);
  return canvas.toDataURL('image/jpeg',0.9);
}

function showCapturedPhoto(filePath, photoNumber) {
  const captureScreen = document.getElementById('capture-screen');
  if (!captureScreen) return;
  
  // Ajouter la photo en arrière-plan avec transparence
  const imageUrl = `${filePath}?t=${Date.now()}`;
  captureScreen.style.backgroundImage = `url(${imageUrl})`;
  captureScreen.style.backgroundSize = 'cover';
  captureScreen.style.backgroundPosition = 'center';
  captureScreen.style.backgroundRepeat = 'no-repeat';
  
  // Ajouter un overlay semi-transparent pour garder la lisibilité du texte
  if (!captureScreen.querySelector('.bg-overlay')) {
    const overlay = document.createElement('div');
    overlay.className = 'bg-overlay';
    overlay.style.cssText = `
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.7);
      z-index: 1;
      pointer-events: none;
    `;
    captureScreen.style.position = 'relative';
    captureScreen.insertBefore(overlay, captureScreen.firstChild);
    
    // S'assurer que le contenu reste au-dessus
    const content = captureScreen.children;
    for (let i = 1; i < content.length; i++) {
      content[i].style.position = 'relative';
      content[i].style.zIndex = '2';
    }
  }
  
  // Si c'est la dernière photo, programmer le reset
  if (photoNumber === PHOTO_COUNT) {
    setTimeout(() => {
      resetCaptureScreenBackground();
    }, 3000); // Reset après 3 secondes
  }
}

function resetCaptureScreenBackground() {
  const captureScreen = document.getElementById('capture-screen');
  if (!captureScreen) return;
  
  // Supprimer le background
  captureScreen.style.backgroundImage = '';
  captureScreen.style.backgroundSize = '';
  captureScreen.style.backgroundPosition = '';
  captureScreen.style.backgroundRepeat = '';
  
  // Supprimer l'overlay
  const overlay = captureScreen.querySelector('.bg-overlay');
  if (overlay) {
    overlay.remove();
  }
}

async function triggerServerFileCapture(){
  // Détecter l'endpoint selon l'OS et le mode caméra
  let endpoint;
  
  if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux') {
    endpoint = 'linux_capture.php';
  } else {
    endpoint = MODE === 'sony_wifi' ? 'sony_wifi_capture.php' : 
               (MODE === 'sony_sdk' ? 'sony_sdk_capture.php' : 
               (MODE === 'folder_watch' ? 'folder_watch_capture.php' : 'win_capture.php'));
  }
  
  console.log(`Appel ${endpoint} (OS: ${window.PHOTOMATON_CONFIG.operatingSystem})...`);
  const startTime = Date.now();
  
  const res = await fetch(endpoint);
  const data = await res.json().catch(()=>({}));
  
  const elapsed = Date.now() - startTime;
  console.log(`Capture terminée en ${elapsed}ms`);
  
  if(!res.ok || !data.file && !data.filename) throw new Error(data.error || 'Echec capture');
  return data.file || data.filename; // chemin relatif
}

function showSelection(){
  // Reset le background de capture-screen avant d'afficher la sélection
  resetCaptureScreenBackground();
  
  captureScreen.classList.add('hidden');
  selectionScreen.classList.remove('hidden');
  
  // Adapter le titre selon le nombre de photos
  if (selectionTitle) {
    if (captured.length === 1) {
      selectionTitle.textContent = getMessage('singlePhotoTitle');
    } else {
      selectionTitle.textContent = getMessage('multiPhotoTitle');
    }
  }
  
  // Afficher/masquer et adapter le texte du bouton "Reprendre" 
  if (retakeBtn) {
    retakeBtn.style.display = 'inline-block';
    
    // Adapter le texte selon le mode
    if (isSinglePhotoMode) {
      retakeBtn.innerHTML = '<i class="fas fa-redo"></i> Reprendre la photo';
    } else {
      retakeBtn.innerHTML = '<i class="fas fa-redo"></i> Reprendre les photos';
    }
  }
  
  // Masquer ou afficher les contrôles d'impression selon la configuration
  const printControls = document.querySelector('#selection-screen .controls');
  if (printControls && !isPrintingEnabled()) {
    // Masquer les contrôles d'impression
    const copyLabel = printControls.querySelector('label[for="copies"]');
    const copySelect = printControls.querySelector('#copies');
    const printButton = printControls.querySelector('#print-selected');
    const printDoubleButton = printControls.querySelector('#print-double');
    
    if (copyLabel) copyLabel.style.display = 'none';
    if (copySelect) copySelect.style.display = 'none';
    if (printButton) printButton.style.display = 'none';
    if (printDoubleButton) printDoubleButton.style.display = 'none';
  }
  
  thumbsDiv.innerHTML='';
  if(window.PhotoEffects && selectionScreen) {
    PhotoEffects.createSparkles(selectionScreen);
  }
  captured.forEach((data,i)=>{
    const img = document.createElement('img');
    img.src = data.startsWith('captures/') ? data + '?t=' + Date.now() : data;
    if(i===0) img.classList.add('selected');
    img.addEventListener('click',()=>{
      document.querySelectorAll('.thumbs img').forEach(im=>im.classList.remove('selected'));
      img.classList.add('selected');
      selectedIndex = i;
    });
    thumbsDiv.appendChild(img);
  });
}

printBtn?.addEventListener('click', async () => {
  const copies = parseInt(copiesSelect.value,10) || 1;
  lastPrintType = 'single';
  
  // Afficher la modale d'impression
  showPrintModal();
  
  if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    const filePath = captured[selectedIndex];
    if(filePath.startsWith('captures/')){
      try {
        // Choisir l'endpoint d'impression selon l'OS et la configuration
        let printEndpoint = 'print_file.php'; // Fallback par défaut
        
        console.log('[Print Debug] OS:', window.PHOTOMATON_CONFIG.operatingSystem, 'PrinterType:', window.PHOTOMATON_CONFIG.printerType);
        
        // Support Linux prioritaire
        if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
          printEndpoint = 'linux_print.php';
        } else {
          // Endpoints Windows
          switch(window.PHOTOMATON_CONFIG.printerType) {
            case 'simple':
              printEndpoint = 'print_simple.php';
              break;
            case 'selphy_optimized':
              printEndpoint = 'print_selphy_optimized.php';
              break;
            case 'canon_cp1500':
              printEndpoint = 'print_canon_ps.php';
              break;
            case 'ppd_optimized':
              printEndpoint = 'print_ppd.php';
              break;
            case 'browser':
              printEndpoint = 'print_browser.php';
              break;
            default:
              printEndpoint = 'print_canon.php';
          }
        }
        
        const printData = {
          file: filePath, 
          imagePath: filePath, // Format Linux
          copies: copies
        };
        
        // Ajouter le format papier si configuré
        if (window.PHOTOMATON_CONFIG.defaultPaperSize) {
          printData.paperSize = window.PHOTOMATON_CONFIG.defaultPaperSize;
          printData.media = window.PHOTOMATON_CONFIG.defaultPaperSize; // Format Linux
        }
        
        console.log('[Print Debug] Endpoint:', printEndpoint, 'Data:', printData);
          
        const res = await fetch(printEndpoint, {
          method: 'POST', 
          headers: {'Content-Type': 'application/json'}, 
          body: JSON.stringify(printData)
        });
        
        const data = await res.json().catch(() => ({}));
        if(!res.ok) throw new Error(data.error || 'Erreur impression');
        
        // Afficher le succès dans la modale
        showPrintSuccess();
        
      } catch(e){ 
        showPrintError('Erreur impression: ' + e.message); 
      }
      return;
    }
  }
  await sendToSavePrint(captured[selectedIndex], copies);
});

// Gestionnaire impression double (2 photos par page)
printDoubleBtn?.addEventListener('click', async () => {
  const copies = parseInt(copiesSelect.value,10) || 1;
  lastPrintType = 'double';
  
  console.log('[Print Double] Début impression double');
  console.log('[Print Double] Captured:', captured);
  console.log('[Print Double] SelectedIndex:', selectedIndex);
  console.log('[Print Double] MODE:', MODE);
  
  if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    const filePath = captured[selectedIndex];
    console.log('[Print Double] FilePath:', filePath);
    
    if(!filePath) {
      showPrintError('Aucune photo sélectionnée pour l\'impression');
      return;
    }
    
    if(filePath.startsWith('captures/')){
      // D'abord créer l'aperçu
      await showPrint2upPreview(filePath, copies);
    } else {
      console.log('[Print Double] Chemin de fichier invalide:', filePath);
      showPrintError('Chemin de fichier invalide pour l\'impression double');
      return;
    }
  } else {
    console.log('[Print Double] Mode non supporté:', MODE);
    showPrintError('Mode de capture non supporté pour l\'impression double');
  }
        
        if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
          printEndpoint = 'linux_print.php';
        } else {
          // Windows endpoints
          switch(window.PHOTOMATON_CONFIG.printerType) {
            case 'simple': printEndpoint = 'print_simple.php'; break;
            case 'selphy_optimized': printEndpoint = 'print_selphy_optimized.php'; break;
            case 'canon_cp1500': printEndpoint = 'print_canon_ps.php'; break;
            case 'ppd_optimized': printEndpoint = 'print_ppd.php'; break;
            case 'browser': printEndpoint = 'print_browser.php'; break;
            default: printEndpoint = 'print_canon.php';
          }
        }
        
        const printData = {
          file: filePath, 
          imagePath: filePath,
          copies: copies,
          layout: '2up', // Indicateur spécial pour 2 photos par page
          doublePhoto: true
        };
        
        if (window.PHOTOMATON_CONFIG.defaultPaperSize) {
          printData.paperSize = window.PHOTOMATON_CONFIG.defaultPaperSize;
          printData.media = window.PHOTOMATON_CONFIG.defaultPaperSize;
        }
        
        console.log('[Print Double Debug] Endpoint:', printEndpoint, 'Data:', printData);
        
        console.log('[Print Double] Envoi de la requête...');
        const res = await fetch(printEndpoint, {
          method: 'POST', 
          headers: {'Content-Type': 'application/json'}, 
          body: JSON.stringify(printData)
        });
        
        console.log('[Print Double] Réponse reçue, status:', res.status, res.statusText);
});

// Fonction pour afficher l'aperçu de l'impression double
async function showPrint2upPreview(filePath, copies) {
  try {
    console.log('[Print Double Preview] filePath reçu:', filePath);
    console.log('[Print Double Preview] copies:', copies);
    
    // Créer l'aperçu via l'endpoint
    let printEndpoint = 'print_file.php';
    
    if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
      printEndpoint = 'linux_print.php';
    }
    
    // S'assurer que le chemin commence par 'captures/' pour l'endpoint PHP
    let imagePath = filePath;
    if (!imagePath.startsWith('captures/')) {
      imagePath = 'captures/' + imagePath.replace(/^.*[\/]/, '');
    }
    
    const previewData = {
      action: 'preview2up',
      imagePath: imagePath,
      file: imagePath
    };
    
    console.log('[Print Double Preview] Endpoint:', printEndpoint);
    console.log('[Print Double Preview] Data envoyée:', previewData);
    const res = await fetch(printEndpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(previewData)
    });
    
    const data = await res.json().catch(() => ({}));
    
    if (!res.ok) {
      throw new Error(data.error || 'Erreur création aperçu');
    }
    
    // Afficher la modale de prévisualisation
    showPrint2upModal(data.previewUrl, filePath, copies);
    
  } catch(e) {
    console.log('[Print Double Preview] Erreur:', e);
    showPrintError('Erreur création aperçu: ' + e.message);
  }
}

// Fonction pour afficher la modale d'aperçu
function showPrint2upModal(previewUrl, originalPath, copies) {
  // Créer la modale d'aperçu si elle n'existe pas
  let modal = document.getElementById('print2up-preview-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'print2up-preview-modal';
    modal.className = 'modal';
    modal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <i class="fas fa-eye modal-icon"></i>
          <h3>Aperçu impression double</h3>
        </div>
        <div class="modal-body" style="text-align: center;">
          <p>Vos 2 photos seront imprimées comme ceci :</p>
          <img id="preview2up-image" src="" alt="Aperçu 2 photos">
          <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
            <i class="fas fa-scissors"></i> Les lignes en pointillés indiquent où découper
          </p>
        </div>
        <div class="modal-footer">
          <button id="print2up-cancel" class="btn secondary">
            <i class="fas fa-times"></i> Annuler
          </button>
          <button id="print2up-confirm" class="btn">
            <i class="fas fa-print"></i> Confirmer l'impression
          </button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }
  
  // Mettre à jour l'image d'aperçu
  const previewImage = modal.querySelector('#preview2up-image');
  previewImage.src = previewUrl + '?t=' + Date.now();
  
  // Gérer les événements
  modal.querySelector('#print2up-cancel').onclick = () => {
    modal.style.display = 'none';
  };
  
  modal.querySelector('#print2up-confirm').onclick = () => {
    modal.style.display = 'none';
    // Lancer l'impression réelle
    confirmPrint2up(originalPath, copies);
  };
  
  // Afficher la modale
  modal.style.display = 'flex';
}

// Fonction pour confirmer et lancer l'impression double
async function confirmPrint2up(filePath, copies) {
  showPrintModal(); // Afficher la modale de progression
  
  try {
    let printEndpoint = 'print_file.php';
    
    if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
      printEndpoint = 'linux_print.php';
    } else {
      // Windows endpoints
      switch(window.PHOTOMATON_CONFIG.printerType) {
        case 'simple': printEndpoint = 'print_simple.php'; break;
        case 'selphy_optimized': printEndpoint = 'print_selphy_optimized.php'; break;
        case 'canon_cp1500': printEndpoint = 'print_canon_ps.php'; break;
        case 'ppd_optimized': printEndpoint = 'print_ppd.php'; break;
        case 'browser': printEndpoint = 'print_browser.php'; break;
        default: printEndpoint = 'print_canon.php';
      }
    }
    
    const printData = {
      file: filePath, 
      imagePath: filePath,
      copies: copies,
      layout: '2up',
      doublePhoto: true
    };
    
    if (window.PHOTOMATON_CONFIG.defaultPaperSize) {
      printData.paperSize = window.PHOTOMATON_CONFIG.defaultPaperSize;
      printData.media = window.PHOTOMATON_CONFIG.defaultPaperSize;
    }
    
    console.log('[Print Double] Impression confirmée, endpoint:', printEndpoint);
    const res = await fetch(printEndpoint, {
      method: 'POST', 
      headers: {'Content-Type': 'application/json'}, 
      body: JSON.stringify(printData)
    });
    
    const data = await res.json().catch(() => ({}));
    if(!res.ok) throw new Error(data.error || 'Erreur impression double');
    
    showPrintSuccess();
    
  } catch(e) {
    showPrintError('Erreur impression double: ' + e.message);
  }
}

async function toBase64FromUrl(url){
  const res = await fetch(url+'?raw='+Date.now());
  const blob = await res.blob();
  return await new Promise((resolve,reject)=>{
    const reader = new FileReader();
    reader.onload = ()=>resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

async function sendToSavePrint(imageData, copies){
  try {
    const res = await fetch('save_print.php',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ image:imageData, copies }) });
    if(!res.ok) throw new Error('Erreur serveur');
    await res.json();
    showPrintSuccess();
  } catch(e){ 
    showPrintError('Erreur impression: '+e.message); 
  }
}

// =======================
// GESTION MODALE D'IMPRESSION
// =======================

function showPrintModal() {
  const modal = document.getElementById('print-modal');
  const title = document.getElementById('modal-title');
  const message = document.getElementById('modal-message');
  const spinner = document.getElementById('print-spinner');
  const success = document.getElementById('print-success');
  const error = document.getElementById('print-error');
  const closeBtn = document.getElementById('modal-close');
  const retryBtn = document.getElementById('modal-retry');
  const homeBtn = document.getElementById('modal-home');

  // Reset modal state
  title.textContent = 'Impression en cours...';
  message.textContent = 'Votre photo est en cours d\'impression, veuillez patienter...';
  spinner.style.display = 'flex';
  success.style.display = 'none';
  error.style.display = 'none';
  closeBtn.style.display = 'none';
  retryBtn.style.display = 'none';
  homeBtn.style.display = 'none';

  modal.classList.add('show');
}

function showPrintSuccess() {
  const title = document.getElementById('modal-title');
  const message = document.getElementById('modal-message');
  const spinner = document.getElementById('print-spinner');
  const success = document.getElementById('print-success');
  const homeBtn = document.getElementById('modal-home');

  title.textContent = 'Impression réussie !';
  message.textContent = 'Votre photo a été envoyée à l\'imprimante avec succès.';
  spinner.style.display = 'none';
  success.style.display = 'flex';
  homeBtn.style.display = 'inline-block';

  // Auto-fermeture après 3 secondes
  setTimeout(() => {
    hidePrintModal();
    window.location = 'index.php';
  }, 3000);
}

function showPrintError(errorMessage) {
  const title = document.getElementById('modal-title');
  const message = document.getElementById('modal-message');
  const spinner = document.getElementById('print-spinner');
  const error = document.getElementById('print-error');
  const errorText = document.getElementById('error-text');
  const closeBtn = document.getElementById('modal-close');
  const retryBtn = document.getElementById('modal-retry');

  title.textContent = 'Erreur d\'impression';
  message.style.display = 'none';
  spinner.style.display = 'none';
  error.style.display = 'flex';
  errorText.textContent = errorMessage;
  closeBtn.style.display = 'inline-block';
  retryBtn.style.display = 'inline-block';
}

function hidePrintModal() {
  const modal = document.getElementById('print-modal');
  modal.classList.remove('show');
}

function retryPrint() {
  if (captured.length > 0) {
    hidePrintModal();
    setTimeout(() => {
      // Simuler le clic sur le bon bouton selon le type d'impression
      if (lastPrintType === 'double') {
        printDoubleBtn?.click();
      } else {
        printBtn?.click();
      }
    }, 200);
  }
}

startBtn?.addEventListener('click', runSequence);
singlePhotoBtn?.addEventListener('click', runSinglePhoto);
retakeBtn?.addEventListener('click', retakePhotos);

// Event listeners pour la modale d'impression
document.getElementById('modal-close')?.addEventListener('click', hidePrintModal);
document.getElementById('modal-retry')?.addEventListener('click', retryPrint);
document.getElementById('modal-home')?.addEventListener('click', () => {
  hidePrintModal();
  window.location = 'index.php';
});

initWebcamIfNeeded();
