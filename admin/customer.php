<?php
// customer.php — Detail & edit 1 pelanggan (tema seragam)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// --- Ambil ID ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { exit('ID tidak valid'); }

// --- Ambil data pelanggan + status PPPoE ---
$sql = "SELECT c.id, c.name, c.phone, COALESCE(c.billable,1) AS billable, COALESCE(c.pppoe_username,'') AS pppoe_username,
               s.ip, s.status
        FROM customers c
        LEFT JOIN pppoe_status s ON s.username = c.pppoe_username
        WHERE c.id = ?";
$st = $pdo->prepare($sql); $st->execute([$id]);
$cus = $st->fetch(PDO::FETCH_ASSOC);
if (!$cus) { exit('Pelanggan tidak ditemukan'); }

// --- Simpan (POST) ---
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();

  $phone    = trim($_POST['phone'] ?? '');
  $is_free  = isset($_POST['is_free']) ? 1 : 0;   // centang = gratis (billable=0)
  $billable = $is_free ? 0 : 1;

  $up = $pdo->prepare("UPDATE customers SET phone=?, billable=? WHERE id=?");
  $up->execute([$phone!=='' ? $phone : null, $billable, $id]);

  $_SESSION['flash'] = 'Perubahan disimpan.';
  if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

  $to = 'customer.php?id='.$id;
  header('Location: '.$to, true, 303);
  echo '<!doctype html><meta charset="utf-8">';
  echo '<meta http-equiv="refresh" content="0;url='.$to.'">';
  echo '<script>location.replace("'.$to.'")</script>';
  echo '<p>Perubahan disimpan. <a href="'.$to.'">Kembali</a></p>';
  exit;
}

$isOnline = in_array(strtolower((string)($cus['status'] ?? '')), ['online','connected','up','1','true']);
$isFree   = ((int)$cus['billable']===0);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Detail Pelanggan — AdamWifi Admin</title>
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

/* Animated Background - sama dengan reports.php */
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
  max-width: 1400px;
  margin: 0 auto;
  min-height: 100vh;
}

/* Topbar dengan style seragam */
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
  max-width: 1400px;
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
  content: '👤';
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

/* Main content */
.main {
  padding: 24px;
  padding-top: 90px;
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 24px;
}

/* Buttons - sama dengan reports.php */
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

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn.secondary {
  background: rgba(30,41,59,0.9);
  color: var(--light);
  border: 1px solid rgba(251,191,36,0.18);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.xbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 42px;
  padding: 0 16px;
  border-radius: 12px;
  text-decoration: none;
  color: var(--light);
  background: rgba(30,41,59,0.9);
  border: 1px solid rgba(251,191,36,0.18);
  transition: all 0.3s ease;
}

.xbtn:hover {
  background: rgba(251,191,36,0.1);
  border-color: var(--primary);
}

/* Flash Message */
.info {
  background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.1));
  border: 1px solid var(--success);
  color: var(--success);
  padding: 16px 24px;
  border-radius: 16px;
  margin: 90px 24px 0 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  animation: slideIn 0.5s ease-out;
  box-shadow: 0 4px 12px rgba(16,185,129,0.2);
  position: relative;
}

.info::before {
  content: '✓';
  width: 24px;
  height: 24px;
  background: var(--success);
  color: var(--white);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

@keyframes slideIn {
  from {
    transform: translateX(-100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Cards - sama dengan reports.php */
.card {
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border: 1px solid rgba(251,191,36,0.1);
  border-radius: 20px;
  padding: 24px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  animation: fadeInUp 0.6s ease-out;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.card:nth-of-type(1) { animation-delay: 0.1s; }
.card:nth-of-type(2) { animation-delay: 0.2s; }

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

/* Header detail */
.header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid rgba(251,191,36,0.12);
  padding-bottom: 16px;
}

.header .title-name {
  font-size: 24px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--light) 0%, rgba(248,250,252,0.8) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.header .muted {
  color: var(--gray);
  font-size: 13px;
  margin-top: 4px;
}

.header .badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* Status badges dengan animasi */
.badge {
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}

.badge.on {
  background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(34,197,94,0.2));
  color: var(--success);
  border: 1px solid rgba(16,185,129,0.35);
  animation: pulseGreen 2s ease-in-out infinite;
}

@keyframes pulseGreen {
  0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
  50% { box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }
}

.badge.off {
  background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.2));
  color: var(--danger);
  border: 1px solid rgba(239,68,68,0.35);
}

.badge.free {
  background: linear-gradient(135deg, rgba(14,165,233,0.2), rgba(2,132,199,0.2));
  color: #7dd3fc;
  border: 1px solid rgba(59,130,246,0.35);
}

.badge.bill {
  background: linear-gradient(135deg, rgba(251,191,36,0.25), rgba(245,158,11,0.15));
  color: #fde68a;
  border: 1px solid rgba(251,191,36,0.35);
}

/* Grid detail kiri */
.detail-grid {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 12px;
  margin-top: 8px;
}

.detail-grid .label {
  color: var(--gray);
  font-weight: 700;
  font-size: 13px;
  padding-top: 8px;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Courier New", monospace;
  font-size: 13px;
  background: rgba(30,41,59,0.5);
  border-radius: 8px;
  padding: 8px 12px;
  display: inline-block;
  border: 1px solid rgba(251,191,36,0.1);
}

/* Mini ribbon di kartu kiri */
.ribbon {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.pill {
  background: var(--gradient-soft);
  border: 1px solid rgba(251,191,36,0.25);
  padding: 8px 12px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 12px;
  transition: all 0.3s ease;
  animation: fadeInUp 0.5s ease-out backwards;
}

.pill:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(251,191,36,0.2);
}

.pill .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  animation: dotPulse 2s ease-in-out infinite;
}

@keyframes dotPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.2); }
}

.dot-on { background: var(--success); }
.dot-off { background: var(--warning); }

/* Form kanan */
.form-grid {
  display: grid;
  grid-template-columns: 160px 1fr;
  gap: 12px;
  margin-top: 8px;
}

.form-grid .label {
  color: var(--gray);
  font-weight: 700;
  padding-top: 12px;
  font-size: 13px;
}

.input, .select {
  width: 100%;
  height: 42px;
  border-radius: 12px;
  border: 1px solid rgba(251,191,36,0.18);
  background: rgba(30,41,59,0.5);
  color: var(--light);
  padding: 0 12px;
  outline: none;
  font-family: inherit;
  font-size: 14px;
  transition: all 0.3s ease;
}

.input:focus, .select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(251,191,36,0.1);
}

.checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--primary);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 8px 0;
  transition: all 0.3s ease;
}

.checkbox-label:hover {
  color: var(--primary);
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(251,191,36,0.1);
}

/* Responsive */
@media (max-width: 900px) {
  .main {
    grid-template-columns: 1fr;
  }
  
  .detail-grid {
    grid-template-columns: 140px 1fr;
    gap: 8px;
  }
  
  .form-grid {
    grid-template-columns: 120px 1fr;
  }
}

@media (max-width: 768px) {
  .topbar {
    position: static;
    flex-direction: column;
    align-items: flex-start;
  }
  
  .main {
    padding: 16px;
    padding-top: 16px;
  }
  
  .info {
    margin: 16px 16px 0 16px;
  }
  
  .card {
    padding: 16px;
  }
  
  .header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .detail-grid,
  .form-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .form-grid .label {
    padding-top: 0;
    margin-bottom: 4px;
  }
}
</style>
</head>
<body>
  <!-- Topbar di luar wrap -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="title">Detail Pelanggan</div>
      <div class="actions">
        <a class="btn secondary" href="customers.php">← Kembali ke Pelanggan</a>
        <a class="xbtn" href="index.php">Dashboard</a>
      </div>
    </div>
  </div>

  <?php if($flash): ?>
    <div class="info" id="flash">
      <?=h($flash)?>
    </div>
  <?php endif; ?>

  <div class="wrap">
    <div class="main">
      <!-- Kartu Kiri: Info PPPoE & Profil -->
      <div class="card">
        <div class="header">
          <div>
            <div class="title-name"><?=h($cus['name'] ?? '—')?></div>
            <div class="muted">ID: #<?= (int)$cus['id'] ?></div>
          </div>
          <div class="badges">
            <?php if($isOnline): ?>
              <span class="badge on">Online</span>
            <?php else: ?>
              <span class="badge off">Offline</span>
            <?php endif; ?>
            <?php if($isFree): ?>
              <span class="badge free">Gratis</span>
            <?php else: ?>
              <span class="badge bill">Ditagih</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="detail-grid">
          <div class="label">PPPoE Username</div>
          <div>
            <?= $cus['pppoe_username'] ? '<span class="mono">'.h($cus['pppoe_username']).'</span>' : '—'; ?>
            <small class="muted" style="margin-left:8px">(read-only)</small>
          </div>

          <div class="label">IP Address</div>
          <div><?= $cus['ip'] ? '<span class="mono">'.h($cus['ip']).'</span>' : '—'; ?></div>

          <div class="label">Status Koneksi</div>
          <div><?= $isOnline ? '<span class="badge on">Online</span>' : '<span class="badge off">Offline</span>' ?></div>
        </div>

        <div class="ribbon">
          <div class="pill"><span class="dot <?= $isOnline?'dot-on':'dot-off' ?>"></span> PPPoE Status</div>
          <?php if($cus['pppoe_username']===''): ?>
            <div class="pill" title="Belum tertaut PPPoE" style="border-color:var(--warning);background:rgba(249,115,22,0.1)">⚠️ Unlinked</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Kartu Kanan: Edit Kontak & Tagihan -->
      <div class="card">
        <div class="header" style="border-bottom:0; padding-bottom:0; margin-bottom:8px">
          <div class="title-name" style="font-size:20px">Edit Kontak & Tagihan</div>
        </div>

        <form id="f" method="post" novalidate>
          <?php csrf_field(); ?>

          <div class="form-grid">
            <div class="label"><label for="phone">Nomor WhatsApp</label></div>
            <div><input id="phone" class="input" name="phone" placeholder="08xxxxxxxxx" value="<?=h($cus['phone'] ?? '')?>"></div>

            <div class="label">Status Tagihan</div>
            <div>
              <label class="checkbox-label">
                <input class="checkbox" type="checkbox" name="is_free" <?= $isFree?'checked':''; ?>>
                <span>Centang jika pelanggan gratis (tidak ditagih)</span>
              </label>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" id="saveBtn" type="submit">Simpan Perubahan</button>
            <a class="xbtn" href="customers.php">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
// UX: saat submit, disable tombol agar terasa responsif
document.addEventListener('DOMContentLoaded', function(){
  var f = document.getElementById('f'), b = document.getElementById('saveBtn');
  if(f && b){
    f.addEventListener('submit', function(){
      b.disabled = true; 
      b.textContent = 'Menyimpan...';
      b.style.opacity = '0.6';
    });
  }
  
  // Auto-hide flash message setelah 6 detik
  setTimeout(function(){ 
    var x = document.getElementById('flash'); 
    if(x) {
      x.style.opacity = '0';
      x.style.transform = 'translateY(-10px)';
      setTimeout(function(){ x.style.display = 'none'; }, 300);
    }
  }, 6000);
  
  // Animasi stagger untuk ribbon pills
  var pills = document.querySelectorAll('.pill');
  pills.forEach(function(pill, i){
    pill.style.animationDelay = (0.1 + i * 0.1) + 's';
  });
});
</script>
</body>
</html>