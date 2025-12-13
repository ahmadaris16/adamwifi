// Dashboard-specific behaviors (tanpa toggle sidebar)
// Animate numbers on load (versi dengan prefix)
function animateValue(element, start, end, duration, prefix = '') {
  if (!element) return;
  let startTimestamp = null;
  const step = (timestamp) => {
    if (!startTimestamp) startTimestamp = timestamp;
    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
    const value = Math.floor(progress * (end - start) + start);
    element.textContent = prefix + value.toLocaleString('id-ID');
    if (progress < 1) window.requestAnimationFrame(step);
  };
  window.requestAnimationFrame(step);
}

// Animate all card values on page load
window.addEventListener('DOMContentLoaded', () => {
  const cardValues = document.querySelectorAll('.card-value');
  cardValues.forEach(el => {
    const raw = el.textContent;
    const hasRp = /Rp/i.test(raw);
    const n = parseInt(raw.replace(/[^\d]/g, ''), 10);
    if (!isNaN(n) && n > 0) {
      animateValue(el, 0, n, 1500, hasRp ? 'Rp ' : '');
    }
  });
  
  // Add floating animation to icons
  const icons = document.querySelectorAll('.card-icon');
  icons.forEach((icon, index) => {
    icon.style.animation = `float 3s ease-in-out ${index * 0.5}s infinite`;
  });
  
  // Particle effect on hover for cards
  const cards = document.querySelectorAll('.card-link');
  cards.forEach(card => {
    card.addEventListener('mouseenter', function(e) {
      const rect = this.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      const ripple = document.createElement('div');
      ripple.style.cssText = `
        position: absolute;
        width: 20px;
        height: 20px;
        background: radial-gradient(circle, rgba(251,191,36,0.6) 0%, transparent 70%);
        border-radius: 50%;
        left: ${x}px;
        top: ${y}px;
        transform: translate(-50%, -50%);
        pointer-events: none;
        animation: rippleEffect 1s ease-out forwards;
      `;
      this.appendChild(ripple);
      
      setTimeout(() => ripple.remove(), 1000);
    });
  });
  
  // Add glow effect to badges
  const badges = document.querySelectorAll('.badge');
  badges.forEach(badge => {
    badge.addEventListener('mouseenter', function() {
      this.style.filter = 'brightness(1.2)';
      this.style.transform = 'scale(1.05)';
    });
    badge.addEventListener('mouseleave', function() {
      this.style.filter = '';
      this.style.transform = '';
    });
  });
});

// Add ripple animation style
(function(){
  const style = document.createElement('style');
  style.textContent = `
    @keyframes rippleEffect {
      0% { width: 20px; height: 20px; opacity: 1; }
      100% { width: 200px; height: 200px; opacity: 0; }
    }
  `;
  document.head.appendChild(style);
})();

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  // Alt + R for Job Teknisi
  if (e.altKey && e.key === 'r') {
    e.preventDefault();
    window.location.href = 'index.php?page=reports';
  }
  
  // Alt + P for PPPoE
  if (e.altKey && e.key === 'p') {
    e.preventDefault();
    window.location.href = 'index.php?page=pppoe&tab=all';
  }
  
  // Alt + C for customers
  if (e.altKey && e.key === 'c') {
    e.preventDefault();
    window.location.href = 'index.php?page=pelanggan';
  }
  
  // Alt + L for logout
  if (e.altKey && e.key === 'l') {
    e.preventDefault();
    if (confirm('Apakah Anda yakin ingin keluar?')) {
      window.location.href = 'logout.php';
    }
  }
});

// Show keyboard shortcuts hint
console.log('%c🎮 Keyboard Shortcuts:', 'font-size: 16px; font-weight: bold; color: #fbbf24');
console.log('%cAlt + R : Job Teknisi', 'color: #64748b');
console.log('%cAlt + P : PPPoE', 'color: #64748b');
console.log('%cAlt + C : Customers', 'color: #64748b');
console.log('%cAlt + L : Logout', 'color: #64748b');

// Add visual feedback for table rows
document.querySelectorAll('.tbl tbody tr').forEach(tr => {
  tr.addEventListener('click', function() {
    document.querySelectorAll('.tbl tbody tr').forEach(row => { row.style.background = ''; });
    this.style.background = 'rgba(251,191,36,0.05)';
  });
});

// Connection status monitor
function checkConnection() {
  const indicator = document.createElement('div');
  indicator.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    animation: slideIn 0.5s ease-out;
  `;
  
  function updateStatus() {
    if (navigator.onLine) {
      indicator.style.background = 'linear-gradient(135deg, rgba(16,185,129,0.9), rgba(34,197,94,0.9))';
      indicator.style.color = 'white';
      indicator.innerHTML = '<span style="width:8px;height:8px;background:white;border-radius:50%;animation:pulse 2s infinite"></span> Online';
      setTimeout(() => indicator.style.opacity = '0', 3000);
    } else {
      indicator.style.background = 'linear-gradient(135deg, rgba(239,68,68,0.9), rgba(220,38,38,0.9))';
      indicator.style.color = 'white';
      indicator.innerHTML = '<span style="width:8px;height:8px;background:white;border-radius:50%"></span> Offline';
      indicator.style.opacity = '1';
    }
  }
  
  document.body.appendChild(indicator);
  window.addEventListener('online', updateStatus);
  window.addEventListener('offline', updateStatus);
  updateStatus();
}
checkConnection();

// Theme time-based greeting
const hour = new Date().getHours();
let greeting = '';
if (hour < 12) greeting = '🌅 Selamat Pagi';
else if (hour < 15) greeting = '☀️ Selamat Siang';
else if (hour < 18) greeting = '🌤️ Selamat Sore';
else greeting = '🌙 Selamat Malam';
console.log(`%c${greeting}, Admin!`, 'font-size: 20px; font-weight: bold; color: #fbbf24; text-shadow: 2px 2px 4px rgba(0,0,0,0.3)');

// Performance monitoring log
if (window.performance) {
  window.addEventListener('load', () => {
    const perfData = window.performance.timing;
    const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
    console.log(`%c⚡ Page loaded in ${pageLoadTime}ms`, 'color: #10b981; font-weight: bold');
  });
}

// Flash message auto-hide
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.info').forEach(el => {
    const closeBtn = el.querySelector('.close');
    const hide = () => { el.classList.add('hide'); setTimeout(() => el.remove(), 300); };
    if (closeBtn) closeBtn.addEventListener('click', hide);
    setTimeout(hide, 6000);
  });
});

// Clock display
function updateClock() {
  const now = new Date();
  const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  const date = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  const clock = document.getElementById('clock');
  if (clock) clock.innerHTML = `${time} • ${date}`;
}
setInterval(updateClock, 1000);
updateClock();
