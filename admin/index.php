<?php
// Dashboard ??? PPPoE + Pelanggan + Pembayaran (semua kartu bisa diklik)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

// Pakai koneksi DB yang sama dengan receiver.php
require_once __DIR__ . '/config/config.php'; // ini mendefinisikan $koneksi (PDO) yg dipakai receiver.php
if (isset($koneksi) && $koneksi instanceof PDO) {
  $pdo = $koneksi;           // jadikan $pdo = $koneksi (seragam)
} elseif (isset($pdo) && $pdo instanceof PDO) {
  $koneksi = $pdo;           // fallback dua arah
}


if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
  catch (Throwable $e) { $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}


if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function prev_period(){ return date('Y-m', strtotime('first day of last month')); }

function rupiah($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function period_label($ym){
  if(!$ym) return '-';
  [$y,$m] = explode('-', $ym);
  $bln = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  return ($bln[(int)$m] ?? $m).' '.$y;
}


$PPPOE_TABLE='pppoe_status'; $USER_COL='username'; $IP_COL='ip'; $STATUS_COL='status';
function table_exists(PDO $pdo,$t){ try{$pdo->query("SELECT 1 FROM `$t` LIMIT 1");return true;}catch(Throwable $e){return false;} }
function hascol(PDO $pdo,$t,$c){ $s=$pdo->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$c]); return (bool)$s->fetch(); }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard','inventaris','reports','voucher','pppoe','pelanggan','payments','keuangan'];
if (!in_array($page, $allowedPages, true)) { $page = 'dashboard'; }
$pageTitles = [
  'dashboard' => 'Dashboard',
  'inventaris'=> 'Inventaris',
  'reports'   => 'Job Teknisi',
  'voucher'   => 'Kelola Voucher',
  'payments'  => 'Pembayaran',
  'keuangan'  => 'Keuangan',
  'pppoe'     => 'Status PPPoE',
  'pelanggan' => 'Data Pelanggan',
];
$pageTitle = $pageTitles[$page] ?? 'Dashboard';

// helper aktif menu
function menu_active($current, $name) {
  return $current === $name ? ' active' : '';
}

// == Sinkronkan pelanggan dari PPPoE (hanya yang belum ada) ==
// --- Ringkasan PPPoE ---
$pppoe_online = 0; $pppoe_total = 0; $pppoe_offline = 0; $pppoe_last = null;
if (table_exists($pdo,$PPPOE_TABLE)) {
  $pppoe_total  = (int)$pdo->query("SELECT COUNT(*) FROM `$PPPOE_TABLE`")->fetchColumn();
  $pppoe_online = (int)$pdo->query("SELECT COUNT(*) FROM `$PPPOE_TABLE` WHERE LOWER(`$STATUS_COL`) IN ('online','connected','up','1','true')")->fetchColumn();
  $pppoe_offline = max(0, $pppoe_total - $pppoe_online);
  if (hascol($pdo,$PPPOE_TABLE,'last_update')) {
    $pppoe_last = $pdo->query("SELECT MAX(last_update) FROM `$PPPOE_TABLE`")->fetchColumn();
  }
}

// --- Ringkasan Customers ---
$customers_total    = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$customers_bill     = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(billable,1)=1")->fetchColumn();
$customers_free     = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(billable,1)=0")->fetchColumn();
$customers_unlinked = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(pppoe_username,'')=''")->fetchColumn();

// --- Ringkasan Pembayaran (bulan lalu) ---
$pay_info = ['exists'=>false,'paid'=>null,'unpaid'=>null,'amount_sum'=>null,'note'=>null];
$period_for_dashboard = prev_period();
if (table_exists($pdo,'payments')) {
  $pay_info['exists']=true;
  $AMT = null; foreach(['amount','nominal','price','total'] as $c){ if(hascol($pdo,'payments',$c)){$AMT=$c;break;} }
  if (hascol($pdo,'payments','paid_at')) {
    $stPaid = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE period=? AND paid_at IS NOT NULL");
    $stPaid->execute([$period_for_dashboard]);
    $pay_info['paid'] = (int)$stPaid->fetchColumn();

    $stUn = $pdo->prepare("
      SELECT COUNT(*) FROM customers c
      WHERE c.billable=1 AND c.active=1
        AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.customer_id=c.id AND p.period=? AND p.paid_at IS NOT NULL)
    ");
    $stUn->execute([$period_for_dashboard]);
    $pay_info['unpaid'] = (int)$stUn->fetchColumn();
  } else {
    $pay_info['note'] = 'Kolom paid_at tidak ditemukan.';
  }
  if ($AMT) {
    if (hascol($pdo,'payments','paid_at')) {
      $stSum = $pdo->prepare("SELECT COALESCE(SUM($AMT),0) FROM payments WHERE period=? AND paid_at IS NOT NULL");
      $stSum->execute([$period_for_dashboard]);
    } else {
      $stSum = $pdo->prepare("SELECT COALESCE(SUM($AMT),0) FROM payments WHERE period=?");
      $stSum->execute([$period_for_dashboard]);
      $pay_info['note'] = trim(($pay_info['note'] ?? '').' Kolom paid_at tidak ada, total diambil dari semua baris.');
    }
    $pay_info['amount_sum'] = $stSum->fetchColumn();
  }
}

// --- Notifikasi Terbaru dari notification_history ---
$recent = [];
if (table_exists($pdo,'notification_history')) {
  $st = $pdo->query("SELECT id, message, timestamp FROM notification_history ORDER BY timestamp DESC LIMIT 10");
  $recent = $st->fetchAll();
}

// --- Data untuk halaman Status PPPoE (page=pppoe) ---
if ($page === 'pppoe') {
  $TAB = $_GET['tab'] ?? 'all';
  if (!in_array($TAB, ['all','online','offline'], true)) $TAB = 'all';

  $onlineCondSQL = "LOWER(COALESCE(status,'')) IN ('online','connected','up','1','true')";
  $has_last = hascol($pdo,$PPPOE_TABLE,'last_update');
  $orderBy  = $has_last ? "last_update DESC, username ASC" : "username ASC";

  $tot = (int)$pdo->query("SELECT COUNT(*) FROM `$PPPOE_TABLE`")->fetchColumn();
  $on  = (int)$pdo->query("SELECT COUNT(*) FROM `$PPPOE_TABLE` WHERE $onlineCondSQL")->fetchColumn();
  $off = max(0, $tot - $on);

  $where = '';
  if ($TAB === 'online')  $where = "WHERE $onlineCondSQL";
  if ($TAB === 'offline') $where = "WHERE NOT($onlineCondSQL)";

  $cols = $has_last ? "username, ip, status, last_update" : "username, ip, status";
  $sql  = "SELECT $cols FROM `$PPPOE_TABLE` $where ORDER BY $orderBy";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Adam Wifi</title>
<?php $css_ver = file_exists(__DIR__.'/assets/css/css_global.css') ? filemtime(__DIR__.'/assets/css/css_global.css') : time(); ?>
<link rel="stylesheet" href="assets/css/css_global.css?v=<?= $css_ver ?>">

</head>
<body>
<div class="wrap">
  <!-- Sidebar Kiri -->
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  
  <div id="sbMask"></div>


  <div class="main-wrapper">
  <!-- Form sinkron tetap tersembunyi -->
  <form id="syncForm" method="post" style="display:none" action="api/sinkron_pelanggan.php">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf']??'')?>">
    <input type="hidden" name="sync_customers" value="1">
  </form>
  <form id="autoLinkForm" method="post" style="display:none" action="api/auto_link_pppoe.php">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf']??'')?>">
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

    <?php if ($page === "dashboard"): ?>
      <?php include __DIR__ . "/halaman/halaman_dashboard.php"; ?>
    <?php elseif ($page === "pppoe"): ?>
      <?php include __DIR__ . "/halaman/halaman_pppoe_status.php"; ?>
    <?php elseif ($page === "pelanggan"): ?>
      <?php include __DIR__ . "/halaman/halaman_pelanggan.php"; ?>
    <?php elseif ($page === "inventaris"): ?>
      <?php include __DIR__ . "/halaman/halaman_inventaris.php"; ?>
    <?php elseif ($page === "reports"): ?>
      <?php include __DIR__ . "/halaman/halaman_job_teknisi.php"; ?>
    <?php elseif ($page === "voucher"): ?>
      <?php include __DIR__ . "/halaman/halaman_voucher.php"; ?>
    <?php elseif ($page === "payments"): ?>
      <?php include __DIR__ . "/halaman/halaman_pembayaran.php"; ?>
    <?php elseif ($page === "keuangan"): ?>
      <div class="card table-card">
        <div class="table-header">Keuangan</div>
        <div class="placeholder" style="color:var(--gray); padding:12px 0;">
          Fitur akan datang.
        </div>
      </div>
    <?php else: ?>
      <div class="card card-link card-pppoe">
        <div class="card-title" style="margin:0"><?=h($pageTitle)?></div>
        <div class="mini-muted" style="margin-top:6px">Fitur akan datang.</div>
      </div>

    <?php endif; ?>
</div>
</div>

<div class="g-toast-wrap" id="gToastWrap" aria-live="polite" aria-atomic="true" style="display:none">
  <div class="g-toast" id="gToastCard">
    <div class="g-toast-icon" id="gToastIcon">✓</div>
    <div class="g-toast-text" id="gToastText">Flash</div>
  </div>
</div>

<script>
(function(){
  var wrap = document.getElementById("gToastWrap");
  var card = document.getElementById("gToastCard");
  var text = document.getElementById("gToastText");
  var icon = document.getElementById("gToastIcon");
  var timer;

  window.showToast = function(msg, type){
    if(!wrap || !card || !text || !icon) return;
    clearTimeout(timer);
    card.classList.remove("error");
    icon.textContent = "✓";
    if(type === "danger" || type === "error"){
      card.classList.add("error");
      icon.textContent = "!";
    }
    text.textContent = msg || "";
    wrap.style.display = "flex";
    requestAnimationFrame(function(){ card.classList.add("show"); });
    timer = setTimeout(function(){
      card.classList.remove("show");
      setTimeout(function(){ wrap.style.display="none"; }, 250);
    }, 2600);
  };
})();
</script>

<style>
.g-toast-wrap{
  position:fixed; left:50%; bottom:18px;
  transform:translateX(-50%);
  z-index:2200; pointer-events:none;
  display:none;
}
.g-toast{
  min-width:240px; max-width:340px;
  background:linear-gradient(135deg, rgba(30,41,59,0.95), rgba(15,23,42,0.95));
  border:1px solid rgba(16,185,129,0.4);
  border-radius:14px;
  padding:12px 14px;
  color:#d1fae5;
  box-shadow:0 18px 40px rgba(0,0,0,0.35), 0 0 18px rgba(16,185,129,0.18);
  display:flex; gap:10px; align-items:flex-start;
  opacity:0; transform:translateY(10px) scale(.97);
  transition:opacity .25s ease, transform .25s ease;
  pointer-events:auto;
}
.g-toast.show{ opacity:1; transform:translateY(0) scale(1); }
.g-toast-icon{
  width:26px; height:26px; border-radius:9px;
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(16,185,129,0.12); color:#34d399; border:1px solid rgba(16,185,129,0.45);
  flex-shrink:0; font-weight:800;
}
.g-toast.error{ border-color:rgba(239,68,68,0.55); box-shadow:0 18px 40px rgba(0,0,0,0.35), 0 0 18px rgba(239,68,68,0.2); color:#ffe4e6; }
.g-toast.error .g-toast-icon{ background:rgba(239,68,68,0.14); color:#fecaca; border-color:rgba(239,68,68,0.6); }
.g-toast-text{ font-weight:700; line-height:1.4; }
</style>

<script src="assets/js/dashboard.js"></script>
<script>
// Sidebar animasi hanya sekali per sesi login
(function(){
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  const key = 'sidebarAnimated';
  const already = sessionStorage.getItem(key);
  if (already) return;
  sidebar.classList.add('animate-once');
  sessionStorage.setItem(key, '1');
  sidebar.addEventListener('animationend', () => {
    sidebar.classList.remove('animate-once');
  }, { once: true });
})();
</script>
<!-- Toggle sidebar (layout) -->
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
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var flash = null;
  try { flash = <?= $flash ? json_encode($flash, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : 'null' ?>; }
  catch(e){ flash = <?= $flash ? json_encode($flash) : 'null' ?>; }
  if(!flash) return;
  var wrap = document.getElementById('gToastWrap');
  var card = document.getElementById('gToastCard');
  var text = document.getElementById('gToastText');
  if(!wrap || !card || !text) return;
  text.textContent = flash;
  wrap.style.display = 'flex';
  requestAnimationFrame(function(){ card.classList.add('show'); });
  setTimeout(function(){
    card.classList.remove('show');
    setTimeout(function(){ wrap.style.display='none'; }, 250);
  }, 2600);
});
</script>

</body>
</html>
