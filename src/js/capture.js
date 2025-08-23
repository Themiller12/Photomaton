// Front-end capture sequence.
// Deux modes :
// 1) Webcam (fallback) -> utilise getUserMedia et sauvegarde base64 via save_print.php
// 2) DSLR / Sony modes -> appels serveur pour obtenir un fichier disque

const video = document.getElementById('live-view');
const startBtn = document.getElementById('start-sequence');
const countdownEl = document.getElementById('countdown');
const selectionScreen = document.getElementById('selection-screen');
const captureScreen = document.getElementById('capture-screen');
const thumbsDiv = document.getElementById('thumbnails');
const printBtn = document.getElementById('print-selected');
const copiesSelect = document.getElementById('copies');

// Mode défini côté backend (shoot.php injecte window.PHOTOMATON_MODE)
const MODE = window.PHOTOMATON_MODE || 'dslr_win'; // 'webcam' | 'dslr_win' | 'sony_wifi' | 'sony_sdk' | 'folder_watch'
const PHOTO_COUNT = 3;

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
    await runCountdown(3);
    if(MODE === 'webcam') {
      captured.push(takeSnapshot());
  } else if(MODE === 'dslr_win' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
      try {
        const f = await triggerServerFileCapture();
        captured.push(f);
      } catch(err){
        alert('Erreur capture: '+err.message);
        startBtn.disabled = false;
        return;
      }
    }
    if(i < PHOTO_COUNT-1) await sleep(3000); // pause entre les prises
  }
  showSelection();
}

async function runCountdown(from){
  for(let c=from; c>0; c--){
    countdownEl.textContent = c;
    countdownEl.style.animation = 'none';
    setTimeout(() => countdownEl.style.animation = 'pulse 1s ease-in-out', 10);
    await sleep(1000);
  }
  countdownEl.textContent = '';
  if(window.PhotoEffects) {
    PhotoEffects.createFlashEffect();
    if(video) video.classList.add('photo-taking');
    setTimeout(() => video?.classList.remove('photo-taking'), 500);
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

async function triggerServerFileCapture(){
  const endpoint = MODE === 'sony_wifi' ? 'sony_wifi_capture.php' : (MODE === 'sony_sdk' ? 'sony_sdk_capture.php' : (MODE === 'folder_watch' ? 'folder_watch_capture.php' : 'win_capture.php'));
  const res = await fetch(endpoint);
  const data = await res.json().catch(()=>({}));
  if(!res.ok || !data.file) throw new Error(data.error || 'Echec capture');
  return data.file; // chemin relatif
}

function showSelection(){
  captureScreen.classList.add('hidden');
  selectionScreen.classList.remove('hidden');
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
  if(MODE === 'dslr_win' || MODE === 'sony_wifi' || MODE === 'sony_sdk' || MODE === 'folder_watch') {
    const filePath = captured[selectedIndex];
    if(filePath.startsWith('captures/')){
      try {
        const res = await fetch('print_file.php',{method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({file:filePath, copies})});
        const data = await res.json().catch(()=>({}));
        if(!res.ok) throw new Error(data.error||'Erreur impression');
        alert('Impression lancée');
        window.location='index.php';
      } catch(e){ alert(e.message); }
      return;
    }
  }
  await sendToSavePrint(captured[selectedIndex], copies);
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
initWebcamIfNeeded();
