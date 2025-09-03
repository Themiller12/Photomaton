// Front-end capture sequence.
// Configuration centralisée dans config.js

const video = document.getElementById('live-view');
const startBtn = document.getElementById('start-sequence');
const singlePhotoBtn = document.getElementById('single-photo');
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

function sleep(ms){ return new Promise(r=>setTimeout(r,ms)); }

async function initWebcamIfNeeded(){
  if(MODE !== 'webcam') { if(video) video.style.display='none'; return; }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width:1920, height:1080 }, audio:false });
    video.srcObject = stream;
  } catch(e) { alert('Impossible d\'accéder à la caméra: '+e.message); }
}

async function runSequence(){
  startBtn.disabled = true;
  captured = [];
  
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
    endpoint = 'src/linux_capture.php';
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
  if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    const filePath = captured[selectedIndex];
    if(filePath.startsWith('captures/')){
      try {
        // Choisir l'endpoint d'impression selon l'OS et la configuration
        let printEndpoint = 'print_file.php'; // Fallback par défaut
        
        console.log('[Print Debug] OS:', window.PHOTOMATON_CONFIG.operatingSystem, 'PrinterType:', window.PHOTOMATON_CONFIG.printerType);
        
        // Support Linux prioritaire
        if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
          printEndpoint = 'src/linux_print.php';
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
              printEndpoint = 'src/print_browser.php';
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
        
        // Message de succès personnalisé
        const printerName = window.PHOTOMATON_CONFIG.printerName || 'imprimante';
        const method = data.method ? ` (${data.method})` : '';
        alert(`✅ Impression lancée sur ${printerName}${method}\n📄 ${copies} copie(s) en cours...`);
        window.location='index.php';
      } catch(e){ 
        alert('❌ Erreur impression: ' + e.message); 
      }
      return;
    }
  }
  await sendToSavePrint(captured[selectedIndex], copies);
});

// Gestionnaire impression double (2 photos par page)
printDoubleBtn?.addEventListener('click', async () => {
  const copies = parseInt(copiesSelect.value,10) || 1;
  if(MODE === 'dslr_win' || MODE === 'dslr_linux' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    const filePath = captured[selectedIndex];
    if(filePath.startsWith('captures/')){
      try {
        // Utiliser même logique d'endpoint mais avec layout spécial
        let printEndpoint = 'print_file.php';
        
        console.log('[Print Double Debug] OS:', window.PHOTOMATON_CONFIG.operatingSystem, 'PrinterType:', window.PHOTOMATON_CONFIG.printerType);
        
        if (window.PHOTOMATON_CONFIG.operatingSystem === 'linux' && window.PHOTOMATON_CONFIG.printerType === 'linux_cups') {
          printEndpoint = 'src/linux_print.php';
        } else {
          // Windows endpoints
          switch(window.PHOTOMATON_CONFIG.printerType) {
            case 'simple': printEndpoint = 'print_simple.php'; break;
            case 'selphy_optimized': printEndpoint = 'print_selphy_optimized.php'; break;
            case 'canon_cp1500': printEndpoint = 'print_canon_ps.php'; break;
            case 'ppd_optimized': printEndpoint = 'print_ppd.php'; break;
            case 'browser': printEndpoint = 'src/print_browser.php'; break;
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
          
        const res = await fetch(printEndpoint, {
          method: 'POST', 
          headers: {'Content-Type': 'application/json'}, 
          body: JSON.stringify(printData)
        });
        
        const data = await res.json().catch(() => ({}));
        if(!res.ok) throw new Error(data.error || 'Erreur impression double');
        
        const printerName = window.PHOTOMATON_CONFIG.printerName || 'imprimante';
        const method = data.method ? ` (${data.method})` : '';
        alert(`✅ Impression double lancée sur ${printerName}${method}\n📄 ${copies} page(s) avec 2 photos chacune...`);
        window.location='index.php';
      } catch(e){ 
        alert('❌ Erreur impression double: ' + e.message); 
      }
      return;
    }
  }
});

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
    alert('Impression lancée');
    window.location='index.php';
  } catch(e){ alert('Erreur impression: '+e.message); }
}

startBtn?.addEventListener('click', runSequence);
singlePhotoBtn?.addEventListener('click', runSinglePhoto);
initWebcamIfNeeded();
