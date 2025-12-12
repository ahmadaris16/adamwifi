<?php
// Dashboard — PPPoE + Pelanggan + Pembayaran (semua kartu bisa diklik)
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
$allowedPages = ['dashboard','inventaris','reports','voucher'];
if (!in_array($page, $allowedPages, true)) { $page = 'dashboard'; }
$pageTitles = [
  'dashboard' => 'Dashboard',
  'inventaris'=> 'Inventaris',
  'reports'   => 'Job Teknisi',
  'voucher'   => 'Kelola Voucher',
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
    $stSum = $pdo->prepare("SELECT COALESCE(SUM($AMT),0) FROM payments WHERE period=?");
    $stSum->execute([$period_for_dashboard]);
    $pay_info['amount_sum'] = $stSum->fetchColumn();
  }
}

// --- Notifikasi Terbaru dari notification_history ---
$recent = [];
if (table_exists($pdo,'notification_history')) {
  $st = $pdo->query("SELECT id, message, timestamp FROM notification_history ORDER BY timestamp DESC LIMIT 10");
  $recent = $st->fetchAll();
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — AdamWifi Admin</title>
<link rel="stylesheet" href="assets/css/css_global.css">

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



  <?php if($flash): ?>
  <div class="info" role="status" aria-live="polite">
    <?=h($flash)?>
    <button class="close" aria-label="Tutup">x</button>
  </div>
<?php endif; ?>


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
    <?php else: ?>
      <div class="card card-link card-pppoe">
        <div class="card-title" style="margin:0"><?=h($pageTitle)?></div>
        <div class="mini-muted" style="margin-top:6px">Fitur akan datang.</div>
      </div>

    <?php endif; ?>
  </div>
</div>

<script src="assets/js/dashboard.js"></script>
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

</body>
</html>
