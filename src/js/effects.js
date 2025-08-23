// Effets visuels pour le photomaton
class PhotoEffects {
  
  static createFlashEffect() {
    const flash = document.createElement('div');
    flash.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(255, 255, 255, 0.9);
      z-index: 9999;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.15s ease;
    `;
    document.body.appendChild(flash);
    
    // Animation flash
    setTimeout(() => flash.style.opacity = '1', 10);
    setTimeout(() => flash.style.opacity = '0', 150);
    setTimeout(() => document.body.removeChild(flash), 300);
  }
  
  static createSparkles(element) {
    const sparkles = [];
    for(let i = 0; i < 15; i++) {
      const sparkle = document.createElement('div');
      sparkle.innerHTML = '✨';
      sparkle.style.cssText = `
        position: absolute;
        font-size: ${Math.random() * 20 + 10}px;
        pointer-events: none;
        z-index: 100;
        animation: sparkleFloat 2s ease-out forwards;
        left: ${Math.random() * 100}%;
        top: ${Math.random() * 100}%;
      `;
      element.appendChild(sparkle);
      sparkles.push(sparkle);
    }
    
    // Nettoyage
    setTimeout(() => {
      sparkles.forEach(s => s.remove());
    }, 2000);
  }
  
  static addPhotoFrame(canvas) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    
    // Gradient frame
    const gradient = ctx.createLinearGradient(0, 0, w, h);
    gradient.addColorStop(0, 'rgba(232, 180, 184, 0.3)');
    gradient.addColorStop(1, 'rgba(156, 175, 136, 0.3)');
    
    // Border
    ctx.strokeStyle = gradient;
    ctx.lineWidth = 20;
    ctx.strokeRect(10, 10, w - 20, h - 20);
    
    // Corner decorations
    ctx.fillStyle = 'rgba(232, 180, 184, 0.8)';
    ctx.font = '30px serif';
    ctx.textAlign = 'center';
    ctx.fillText('💕', 50, 50);
    ctx.fillText('💕', w - 50, 50);
    ctx.fillText('💕', 50, h - 30);
    ctx.fillText('💕', w - 50, h - 30);
    
    return canvas;
  }
}

// Ajouter styles CSS pour les animations
const styleSheet = document.createElement('style');
styleSheet.textContent = `
  @keyframes sparkleFloat {
    0% {
      opacity: 1;
      transform: translateY(0) rotate(0deg);
    }
    100% {
      opacity: 0;
      transform: translateY(-100px) rotate(360deg);
    }
  }
  
  .photo-taking {
    animation: photoGlow 0.5s ease-in-out;
  }
  
  @keyframes photoGlow {
    0%, 100% { box-shadow: 0 20px 40px var(--shadow-strong); }
    50% { box-shadow: 0 20px 40px var(--rose-gold), 0 0 30px var(--rose-gold); }
  }
`;
document.head.appendChild(styleSheet);

window.PhotoEffects = PhotoEffects;
