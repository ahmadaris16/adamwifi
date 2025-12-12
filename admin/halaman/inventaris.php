<?php
// Inventaris (placeholder) - memakai layout yang sama dengan Dashboard
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/../config/persiapan_admin.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
  catch (Throwable $e) { $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$pageTitle = 'Inventaris';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=h($pageTitle)?> - AdamWifi Admin</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

* { margin:0; padding:0; box-sizing:border-box; }
:root {
  --primary:#fbbf24; --primary-light:#fde047; --primary-dark:#f59e0b;
  --secondary:#06b6d4; --success:#10b981; --danger:#ef4444; --warning:#f97316;
  --dark:#0f172a; --dark-light:#1e293b; --gray:#64748b; --light:#f8fafc; --white:#fff;
  --gradient: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #dc2626 100%);
  --gradient-soft: linear-gradient(135deg, rgba(251,191,36,0.1) 0%, rgba(245,158,11,0.05) 100%);
  --shadow-sm:0 2px 4px rgba(0,0,0,0.05); --shadow-md:0 4px 12px rgba(0,0,0,0.1);
  --shadow-lg:0 8px 24px rgba(0,0,0,0.15); --shadow-xl:0 12px 48px rgba(0,0,0,0.2); --shadow-glow:0 0 40px rgba(251,191,36,0.3);
}
body{
  font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  background: var(--dark);
  color: var(--light);
  min-height:100vh;
}
.wrap{ display:flex; min-height:100vh; position:relative; }
.sidebar{
  width:260px; background: linear-gradient(180deg, rgba(15,23,42,0.95), rgba(30,41,59,0.95));
  backdrop-filter: blur(20px);
  border-right:1px solid rgba(251,191,36,0.2);
  display:flex; flex-direction:column;
  position:sticky; top:0; left:0; height:100vh; z-index:1001;
  flex-shrink:0; animation: slideInLeft 0.5s ease-out;
}
@keyframes slideInLeft { from{transform:translateX(-100%);} to{transform:translateX(0);} }
.sidebar-header{ padding:24px; border-bottom:1px solid rgba(251,191,36,0.1); display:flex; align-items:center; gap:12px; }
.sidebar-header .logo{ font-size:32px; animation:pulse 2s ease-in-out infinite; }
.sidebar-header h1{ margin:0; font-size:20px; font-weight:800; background:linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.sidebar-menu{ flex:1; padding:20px 12px; overflow-y:auto; }
.menu-item{ display:flex; align-items:center; gap:12px; padding:14px 16px; margin-bottom:8px; color:var(--light); text-decoration:none; border-radius:12px; transition:all .3s ease; font-weight:600; font-size:14px; position:relative; overflow:hidden; }
.menu-item:hover{ background:linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05)); transform:translateX(4px); color:var(--primary); }
.menu-item::before{ content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:3px; height:0; background:var(--primary); transition:height .3s ease; }
.menu-item:hover::before{ height:70%; }
.menu-icon{ font-size:16px; color:var(--primary); }
.menu-text{ flex:1; }
.sidebar-footer{ padding:12px; border-top:1px solid rgba(251,191,36,0.1); }
.menu-item.logout{ color:#fecaca; }
.menu-item.logout .menu-icon{ color:#fecaca; }
.menu-item.logout:hover{ background: rgba(239,68,68,0.08); color:#fff; }
#sbMask{ display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1000; }
.main-wrapper{ flex:1; min-height:100vh; background: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.08), transparent 30%), radial-gradient(circle at 80% 10%, rgba(14,165,233,0.06), transparent 30%), var(--dark); }
.main-content{ padding:24px; max-width:1200px; margin:0 auto; }
.page-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.page-title-wrap{ display:flex; align-items:center; gap:12px; }
.page-title{ font-size:22px; font-weight:800; margin:0; }
.page-menu-toggle{ display:none; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.08); color:var(--light); padding:8px; border-radius:10px; }
.time{ color:var(--gray); font-size:14px; }
.card{ background: var(--dark-light); border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:20px; box-shadow: var(--shadow-md); }
.placeholder{ color:var(--gray); font-size:16px; display:flex; align-items:center; gap:12px; }
@media (max-width: 1024px){ .sidebar{ position:fixed; transform:translateX(-100%); width:240px; transition:transform .28s ease; } .sidebar.active{ transform:translateX(0); } .main-wrapper{ margin-left:0; } #sbMask{ display:none; } .page-menu-toggle{ display:inline-flex; } }
@media (max-width:640px){ .main-content{ padding:16px; } }
</style>
</head>
<body>
<div class="wrap">
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo" aria-hidden="true">⚡</span>
      <h1>Adam Wifi</h1>
    </div>

    <nav class="sidebar-menu">
      <a class="menu-item" href="index.php">
        <span class="menu-icon">⌂</span>
        <span class="menu-text">Dashboard</span>
      </a>
      <a class="menu-item" href="inventaris.php">
        <span class="menu-icon">⧉</span>
        <span class="menu-text">Inventaris</span>
      </a>
      <a class="menu-item" href="halaman_job_teknisi.php">
        <span class="menu-icon">✓</span>
        <span class="menu-text">Job Teknisi</span>
      </a>
      <a class="menu-item" href="halaman_voucher.php">
        <span class="menu-icon">✦</span>
        <span class="menu-text">Kelola Voucher</span>
      </a>
      <a class="menu-item" href="#" onclick="document.getElementById('syncForm').submit();return false;">
        <span class="menu-icon">↻</span>
        <span class="menu-text">Sinkronkan Pelanggan</span>
      </a>
      <a class="menu-item" href="auto_link_pppoe.php">
        <span class="menu-icon">⚙</span>
        <span class="menu-text">Auto-Link PPPoE</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <a class="menu-item logout" href="logout.php">
        <span class="menu-icon">⇦</span>
        <span class="menu-text">Keluar</span>
      </a>
    </div>
  </aside>

  <div id="sbMask"></div>

  <div class="main-wrapper">
    <form id="syncForm" method="post" style="display:none">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf']??'')?>">
      <input type="hidden" name="sync_customers" value="1">
    </form>

    <div class="main-content">
      <div class="page-header">
        <div class="page-title-wrap">
          <button type="button" class="page-menu-toggle" aria-label="Buka menu" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <h1 class="page-title"><?=h($pageTitle)?></h1>
        </div>
        <span class="time" id="clock"></span>
      </div>

      <div class="card">
        <div class="placeholder">
          <span>📦</span>
          <div>
            <strong>Inventaris</strong><br>
            Fitur akan datang.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const btn = document.querySelector('.page-menu-toggle');
  const sidebar = document.querySelector('.sidebar');
  const mask = document.getElementById('sbMask');
  if (!btn || !sidebar || !mask) return;

  const isMobile = () => window.matchMedia('(max-width:768px)').matches;

  function open(){
    if(!isMobile()) return;
    sidebar.classList.add('active');
    btn.classList.add('open');
    btn.setAttribute('aria-expanded','true');
    mask.style.display='block';
  }
  function close(){
    sidebar.classList.remove('active');
    btn.classList.remove('open');
    btn.setAttribute('aria-expanded','false');
    mask.style.display='none';
  }
  function toggle(){ (sidebar.classList.contains('active') ? close : open)(); }

  btn.addEventListener('click', toggle);
  mask.addEventListener('click', close);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') close(); });

  const mq = window.matchMedia('(min-width:769px)');
  const handleMQ = e => { if (e.matches) close(); };
  if (mq.addEventListener) mq.addEventListener('change', handleMQ);
  else mq.addListener(handleMQ);
})();

function updateClock() {
  const now = new Date();
  const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  const date = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  const clock = document.getElementById('clock');
  if (clock) clock.innerHTML = `${time} · ${date}`;
}
setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>
