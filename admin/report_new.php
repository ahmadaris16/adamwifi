<?php
// report_new.php — Form input Job Teknisi (AJAX) — UI modern seragam
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// dropdown teknisi
$techs = $pdo->query("SELECT id, username FROM technicians ORDER BY username ASC")->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tambah Job Teknisi — AdamWifi Admin</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --primary: #fbbf24;
  --primary-light: #fde047;
  --primary-dark: #f59e0b;
  --secondary: #06b6d4;
  --success: #10b981;
  --danger: #ef4444;
  --warning: #f97316;
  --dark: #0f172a;
  --dark-light: #1e293b;
  --gray: #64748b;
  --light: #f8fafc;
  --white: #ffffff;
  --gradient: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #dc2626 100%);
  --gradient-soft: linear-gradient(135deg, rgba(251,191,36,0.1) 0%, rgba(245,158,11,0.05) 100%);
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
  --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
  --shadow-xl: 0 12px 48px rgba(0,0,0,0.2);
}

body {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  background: var(--dark);
  color: var(--light);
  min-height: 100vh;
  line-height: 1.6;
  position: relative;
  overflow-x: hidden;
}

/* Animated Background - sama dengan dashboard */
body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 50%, rgba(251,191,36,0.15) 0%, transparent 50%),
    radial-gradient(circle at 80% 80%, rgba(245,158,11,0.1) 0%, transparent 50%),
    radial-gradient(circle at 40% 20%, rgba(6,182,212,0.08) 0%, transparent 50%);
  z-index: -1;
  animation: bgMove 20s ease-in-out infinite;
}

@keyframes bgMove {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(-20px, -20px) scale(1.05); }
  66% { transform: translate(20px, -10px) scale(0.95); }
}

/* Grid Pattern */
body::after {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: 
    linear-gradient(rgba(251,191,36,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(251,191,36,0.03) 1px, transparent 1px);
  background-size: 50px 50px;
  z-index: -1;
  animation: gridMove 10s linear infinite;
  pointer-events: none;
}

@keyframes gridMove {
  0% { transform: translate(0, 0); }
  100% { transform: translate(50px, 50px); }
}

/* Layout wrapper */
.wrap {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  margin: 0 auto;
  min-height: 100vh;
  padding: 24px;
  padding-top: 90px;
}

/* Topbar dengan style dashboard */
.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: rgba(15,23,42,0.95);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(251,191,36,0.2);
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
  animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
  from {
    transform: translateY(-100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.topbar-content {
  max-width: 1100px;
  margin: 0 auto;
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.topbar .title {
  font-size: 24px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar .title::before {
  content: '➕';
  font-size: 28px;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.topbar .actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

/* Buttons - sama dengan dashboard */
.btn {
  padding: 10px 20px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: var(--dark);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 14px;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(251,191,36,0.3);
  position: relative;
  overflow: hidden;
  height: 46px;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: rgba(255,255,255,0.3);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  transition: width 0.4s, height 0.4s;
}

.btn:hover::before {
  width: 300px;
  height: 300px;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(251,191,36,0.5);
}

.btn:active {
  transform: translateY(0);
}

.btn.secondary {
  background: rgba(30,41,59,0.9);
  color: var(--light);
  border: 1px solid rgba(251,191,36,0.18);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Card - sama dengan dashboard */
.card {
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border: 1px solid rgba(251,191,36,0.1);
  border-radius: 20px;
  padding: 32px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  animation: fadeInUp 0.6s ease-out;
  max-width: 720px;
  margin: 20px auto;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--gradient);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s ease;
}

.card:hover::before {
  transform: scaleX(1);
}

.card .head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(251,191,36,0.1);
}

.card .head .title {
  font-weight: 800;
  font-size: 20px;
  color: var(--light);
}

.card .head .sub {
  color: var(--gray);
  font-size: 14px;
  margin-top: 4px;
}

/* Form styling */
form#jobForm {
  display: grid;
  gap: 20px;
  margin-top: 20px;
}

.row label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--gray);
  margin: 0 0 8px 2px;
}

.row input[type="date"],
.row input[type="number"],
.row select,
.row textarea {
  width: 100%;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid rgba(251,191,36,0.18);
  background: rgba(30,41,59,0.5);
  color: var(--light);
  outline: none;
  font-family: inherit;
  font-size: 14px;
  transition: all 0.3s ease;
}

.row textarea {
  min-height: 120px;
  resize: vertical;
}

.row input:focus,
.row select:focus,
.row textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(251,191,36,0.1);
}

.row input::placeholder,
.row textarea::placeholder {
  color: var(--gray);
  opacity: 0.6;
}

/* Notice/Toast */
.notice {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 200;
  padding: 14px 20px;
  border-radius: 12px;
  border: 1px solid transparent;
  box-shadow: 0 8px 24px rgba(0,0,0,0.25);
  display: none;
  max-width: 400px;
  animation: toastIn 0.3s ease-out both;
  backdrop-filter: blur(10px);
}

@keyframes toastIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.notice .close {
  margin-left: 16px;
  background: transparent;
  border: 0;
  color: inherit;
  font-weight: 800;
  font-size: 16px;
  cursor: pointer;
  opacity: 0.8;
}

.notice .close:hover {
  opacity: 1;
}

/* Button group */
.button-group {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 8px;
}

/* Responsive */
@media (max-width: 768px) {
  .topbar {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .wrap {
    padding: 16px;
    padding-top: 120px;
  }
  
  .card {
    padding: 20px;
  }
  
  .button-group {
    flex-direction: column;
  }
  
  .button-group .btn {
    width: 100%;
  }
}
</style>
</head>
<body>
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="title">Tambah Job Teknisi</div>
      <div class="actions">
        <a class="btn secondary" href="reports.php">← Kembali</a>
        <a class="btn secondary" href="index.php">Dashboard</a>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="head">
        <div>
          <div class="title">Form Input Job</div>
          <div class="sub">Isi detail pekerjaan dan fee untuk teknisi</div>
        </div>
      </div>

      <!-- Notifikasi -->
      <div class="notice" id="msg"></div>

      <!-- FORM -->
      <form method="post" id="jobForm">
        <?php csrf_field(); ?>

        <div class="row">
          <label>Pilih Teknisi</label>
          <select name="technician_id" required>
            <option value="">-- Pilih Teknisi --</option>
            <?php foreach($techs as $t): ?>
              <option value="<?=$t['id']?>"><?=h($t['username'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <label>Tanggal Pekerjaan</label>
          <input type="date" name="job_date" value="<?=h(date('Y-m-d'))?>" required>
        </div>

        <div class="row">
          <label>Jumlah Fee (Rp)</label>
          <input type="number" step="100" min="0" name="fee_amount" placeholder="Contoh: 50000" required>
        </div>

        <div class="row">
          <label>Keterangan Pekerjaan</label>
          <textarea name="description" placeholder="Contoh: Instalasi router di lokasi pelanggan baru, setting mikrotik, dll"></textarea>
        </div>

        <div class="button-group">
          <button class="btn" type="submit" id="btnSave">Simpan Job</button>
          <a class="btn secondary" href="reports.php">Batal</a>
        </div>
      </form>
    </div>
  </div>

<script>
// Toast helper
(function(){
  var box = document.getElementById('msg');
  var timer;

  function show(msg, ok){
    if(!box) return;
    clearTimeout(timer);
    box.style.display = 'inline-flex';
    box.style.alignItems = 'center';
    box.style.gap = '10px';
    
    if(ok){
      box.style.borderColor = 'var(--success)';
      box.style.background = 'linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.1))';
      box.style.color = 'var(--success)';
    } else {
      box.style.borderColor = 'var(--danger)';
      box.style.background = 'linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1))';
      box.style.color = 'var(--danger)';
    }
    
    box.innerHTML = '<span>' + msg + '</span><button class="close" aria-label="Tutup">×</button>';
    var closer = box.querySelector('.close');
    if(closer){ closer.onclick = hide; }
    timer = setTimeout(hide, 6000);
  }
  
  function hide(){
    if(!box) return;
    box.style.transition = 'opacity .25s ease, transform .25s ease';
    box.style.opacity = '0';
    box.style.transform = 'translateY(-6px)';
    setTimeout(function(){
      if(box){
        box.style.display = 'none';
        box.style.opacity = '';
        box.style.transform = '';
      }
    }, 260);
  }
  
  window.__toast = { show: show, hide: hide };
})();

// AJAX submit
(function(){
  var form = document.getElementById('jobForm');
  var btn = document.getElementById('btnSave');

  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    if(btn){
      btn.disabled = true;
      btn.textContent = 'Menyimpan...';
    }
    
    try{
      var fd = new FormData(form);
      const res = await fetch('api_job_create.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const json = await res.json();
      
      if(json.ok){
        window.__toast.show('Job berhasil disimpan! Mengalihkan...', true);
        setTimeout(function(){
          location.href = json.redirect || 'reports.php';
        }, 1000);
      } else {
        window.__toast.show(json.error || 'Gagal menyimpan job.', false);
      }
    } catch(e) {
      window.__toast.show('Koneksi bermasalah. Silakan coba lagi.', false);
    } finally {
      if(btn){
        btn.disabled = false;
        btn.textContent = 'Simpan Job';
      }
    }
  });
})();
</script>
</body>
</html>