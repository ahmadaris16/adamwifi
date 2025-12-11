<?php
// Dashboard — PPPoE + Pelanggan + Pembayaran (semua kartu bisa diklik)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

// Pakai koneksi DB yang sama dengan receiver.php
require_once __DIR__ . '/../config/config.php'; // ini mendefinisikan $koneksi (PDO) yg dipakai receiver.php
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

// == Sinkronkan pelanggan dari PPPoE (hanya yang belum ada) ==
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_customers'])) {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = 'Sinkron gagal: token tidak valid.';
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
  }

  // Pastikan tabel ada
  if (!table_exists($pdo, $PPPOE_TABLE) || !table_exists($pdo, 'customers')) {
    $_SESSION['flash'] = 'Sinkron gagal: tabel tidak ditemukan.'; header('Location: ' . $_SERVER['PHP_SELF']); exit;
  }

  // Ambil username baru dari pppoe_status yang belum ada di customers
  $sqlNew = "SELECT DISTINCT s.`$USER_COL` AS u
             FROM `$PPPOE_TABLE` s
             LEFT JOIN customers c ON c.pppoe_username = s.`$USER_COL`
             WHERE COALESCE(s.`$USER_COL`, '') <> '' AND c.pppoe_username IS NULL";
  $newUsers = $pdo->query($sqlNew)->fetchAll(PDO::FETCH_COLUMN);

  if (!$newUsers) {
    $_SESSION['flash'] = 'Tidak ada username baru untuk disinkronkan.'; header('Location: ' . $_SERVER['PHP_SELF']); exit;
  }

  // Siapkan kolom aman yang tersedia di customers
  $cols = ['pppoe_username'];
  if (hascol($pdo,'customers','name'))      $cols[] = 'name';
  if (hascol($pdo,'customers','billable'))  $cols[] = 'billable';
  if (hascol($pdo,'customers','active'))    $cols[] = 'active';
  if (hascol($pdo,'customers','created_at'))$cols[] = 'created_at';

  $ph  = array_map(function($c){ return ':'.$c; }, $cols);
  $sqlIns = "INSERT INTO customers (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
  $stmtIns = $pdo->prepare($sqlIns);

  $ins = 0;
  $pdo->beginTransaction();
  try {
    foreach ($newUsers as $u) {
      $params = [];
      foreach ($cols as $c) {
        if ($c === 'pppoe_username') { $params[':pppoe_username'] = $u; }
        elseif ($c === 'name')       { $params[':name'] = $u; }
        elseif ($c === 'billable')   { $params[':billable'] = 1; }
        elseif ($c === 'active')     { $params[':active'] = 1; }
        elseif ($c === 'created_at') { $params[':created_at'] = date('Y-m-d H:i:s'); }
      }
      try { $stmtIns->execute($params); $ins++; } catch (Throwable $e) { /* skip baris yang gagal */ }
    }
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Sinkron gagal: '.$e->getMessage();
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
  }

  $_SESSION['flash'] = "Sinkron selesai: {$ins} pelanggan baru ditambahkan.";
  header('Location: ' . $_SERVER['PHP_SELF']); exit;
}


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
  --shadow-glow: 0 0 40px rgba(251,191,36,0.3);
}

/* === SIDEBAR LAYOUT === */
.wrap {
  display: flex;
  min-height: 100vh;
  position: relative;
}

/* Sidebar */
.sidebar {
  width: 260px;
  background: linear-gradient(180deg, rgba(15,23,42,0.95), rgba(30,41,59,0.95));
  backdrop-filter: blur(20px);
  border-right: 1px solid rgba(251,191,36,0.2);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  left: 0;
  height: 100vh;
  z-index: 1001;
  animation: slideInLeft 0.5s ease-out;
  flex-shrink: 0;
}

@keyframes slideInLeft {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}

.sidebar-header {
  padding: 24px;
  border-bottom: 1px solid rgba(251,191,36,0.1);
  display: flex;
  align-items: center;
  gap: 12px;
}

.sidebar-header .logo {
  font-size: 32px;
  animation: pulse 2s ease-in-out infinite;
}

.sidebar-header h1 {
  font-size: 20px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.sidebar-menu {
  flex: 1;
  padding: 20px 12px;
  overflow-y: auto;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 8px;
  color: var(--light);
  text-decoration: none;
  border-radius: 12px;
  transition: all 0.3s ease;
  font-weight: 600;
  font-size: 14px;
  position: relative;
  overflow: hidden;
}

.menu-item:hover {
  background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
  transform: translateX(4px);
  color: var(--primary);
}

.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 0;
  background: var(--primary);
  transition: height 0.3s ease;
}

.menu-item:hover::before {
  height: 70%;
}

.menu-icon {
  font-size: 20px;
  width: 28px;
  text-align: center;
}

.menu-text {
  flex: 1;
}

.sidebar-footer {
  padding: 12px;
  border-top: 1px solid rgba(251,191,36,0.1);
}

.menu-item.logout {
  background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1));
  border: 1px solid rgba(239,68,68,0.2);
}

.menu-item.logout:hover {
  background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.2));
  color: var(--danger);
  transform: translateX(4px);
}

/* Main wrapper */
.main-wrapper {
  flex: 1;
  margin-left: 0;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* Update topbar */
.topbar {
  background: rgba(15,23,42,0.8);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(251,191,36,0.2);
  padding: 20px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 1000;
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
}

.topbar > div:first-child {
  font-size: 24px;
  font-weight: 800;
  color: var(--light);
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.time {
  font-size: 14px;
  color: var(--gray);
  font-weight: 600;
}


/* Update topbar styling */
.topbar {
  background: rgba(15,23,42,0.8);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(251,191,36,0.2);
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 999; /* kurangi dari 1000 */
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.topbar-left h2 {
  font-size: 20px;
  font-weight: 700;
  color: var(--light);
  margin: 0;
}

.menu-toggle {
  display: none; /* default hidden, show on mobile */
  background: transparent;
  border: 1px solid rgba(251,191,36,0.3);
  color: var(--primary);
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 18px;
  transition: all 0.3s ease;
}

.menu-toggle:hover {
  background: rgba(251,191,36,0.1);
}

/* Sidebar collapsed state */
.sidebar.collapsed {
  width: 70px;
  transition: width 0.3s ease;
}

.sidebar.collapsed .menu-text,
.sidebar.collapsed h1 {
  display: none;
}

.sidebar.collapsed .sidebar-header {
  justify-content: center;
}

.sidebar.collapsed .menu-item {
  justify-content: center;
}

/* Responsive update */
@media (max-width: 768px) {
  .menu-toggle {
    display: block;
  }
}

/* Responsive KHUSUS SIDEBAR */
@media (max-width: 768px) {
  .wrap {
    display: block;
  }
  
  .sidebar {
    position: fixed;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }
  
  .sidebar.active {
    transform: translateX(0);
  }
  
  .main-wrapper {
    margin-left: 0;
    width: 100%;
  }
  
  
  .topbar {
    padding-left: 60px;
  }
}

/* Hapus atau comment style .btn di topbar */
/* .topbar .actions { display: none; } */

body {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  background: var(--dark);
  color: var(--light);
  min-height: 100vh;
  line-height: 1.6;
  position: relative;
  overflow-x: hidden;
}

/* Animated Background */
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
  z-index: 0;
  animation: bgMove 20s ease-in-out infinite;
}

@keyframes bgMove {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(-20px, -20px) scale(1.05); }
  66% { transform: translate(20px, -10px) scale(0.95); }
}

/* Grid Pattern */
body::after {
    z-index: -1;
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
  z-index: 0;
  animation: gridMove 10s linear infinite;
  pointer-events: none;
}

@keyframes gridMove {
  0% { transform: translate(0, 0); }
  100% { transform: translate(50px, 50px); }
}

.wrap {
  position: relative;
  z-index: 1;
  max-width: 100%
  margin: 0 auto;
  padding: 0;
  min-height: 100vh;
}

/* Top Bar */
.topbar {
  background: rgba(15,23,42,0.8);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(251,191,36,0.2);
  padding: 20px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 1000;
  animation: slideDown 0.5s ease-out;
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
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

.topbar > div:first-child {
  font-size: 24px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar > div:first-child::before {
  content: '⚡';
  font-size: 28px;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

/* Buttons */
.btn {
  padding: 10px 20px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: var(--dark);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 14px;
  transition: all 0.3s ease;
  display: inline-block;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(251,191,36,0.3);
  position: relative;
  overflow: hidden;
}

/* Bar tombol: semua tombol lebar sama, rapi & responsif */
.topbar .actions{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap:12px; align-items:stretch;
}
.topbar .actions a.btn,
.topbar .actions button.btn{
  appearance:none; -webkit-appearance:none;
  display:flex; align-items:center; justify-content:center;
  height:46px; padding:0 20px; line-height:1; width:100%; text-align:center;
}
.topbar .actions form{ margin:0 } /* form sinkron tersembunyi */


.topbar .actions button.btn{
  appearance:none; -webkit-appearance:none;
  display:inline-flex; align-items:center; justify-content:center;
  height:46px !important; padding:0 20px !important; line-height:1 !important;
  border:0 !important;
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

/* Flash Message */
.info {
  background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.1));
  border: 1px solid var(--success);
  color: var(--success);
  padding: 16px 24px;
  border-radius: 16px;
  margin: 20px 30px;
  display: flex;
  align-items: center;
  gap: 12px;
  animation: slideIn 0.5s ease-out;
  box-shadow: 0 4px 12px rgba(16,185,129,0.2);
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

.info.hide{ opacity:0; transform:translateY(-4px); pointer-events:none; transition:opacity .25s, transform .25s }
.info .close{
  position:absolute; top:10px; right:12px; border:0; background:transparent;
  color:inherit; font-weight:800; font-size:16px; cursor:pointer; opacity:.8
}
.info .close:hover{ opacity:1 }


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

/* Main Content */
.main-content {
  padding: 30px;
  flex: 1;
}

/* Page Header - tambahkan setelah .main-content */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: rgba(15,23,42,0.95);
  backdrop-filter: blur(20px);
  z-index: 100;
  padding: 20px;
  margin: -30px -30px 30px -30px;
  border-bottom: 1px solid rgba(251,191,36,0.1);
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
}

.page-title {
  font-size: 32px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin: 0;
}

.page-header .time {
  font-size: 14px;
  color: var(--gray);
  font-weight: 600;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 24px;
  margin-bottom: 30px;
}

/* Card Styles */
.card {
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border: 1px solid rgba(251,191,36,0.1);
  border-radius: 20px;
  padding: 24px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  animation: fadeInUp 0.6s ease-out backwards;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }

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

.card-link {
  text-decoration: none;
  color: inherit;
  display: block;
  cursor: pointer;
}

.card-link:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0,0,0,0.3), 0 0 40px rgba(251,191,36,0.15);
  border-color: rgba(251,191,36,0.3);
}

/* Card Icons */
.card-icon {
  width: 56px;
  height: 56px;
  background: var(--gradient-soft);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-bottom: 16px;
  box-shadow: 0 4px 12px rgba(251,191,36,0.2);
}



/* Card Title */
.card-title {
  font-weight: 800;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--gray);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Card Value */
.card-value {
  font-size: 36px;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 12px;
  background: linear-gradient(135deg, var(--light), var(--gray));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* Card Stats */
.card-stats {
  display: flex;
  gap: 16px;
  margin: 16px 0;
  flex-wrap: wrap;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--gray);
}

/* Badges */
.badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.badge.on {
  background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(34,197,94,0.2));
  color: var(--success);
  border: 1px solid rgba(16,185,129,0.3);
}

.badge.off {
  background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.2));
  color: var(--danger);
  border: 1px solid rgba(239,68,68,0.3);
}

.badge::before {
  content: '';
  width: 6px;
  height: 6px;
  background: currentColor;
  border-radius: 50%;
  animation: blink 2s ease-in-out infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

/* Card Meta */
.card-meta {
  font-size: 13px;
  color: var(--gray);
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(100,116,139,0.2);
}

/* Card Action */
.card-action {
  margin-top: 16px;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  transition: gap 0.3s ease;
}

.card-link:hover .card-action {
  gap: 16px;
}

.card-action::after {
  content: '→';
  transition: transform 0.3s ease;
}

.card-link:hover .card-action::after {
  transform: translateX(4px);
}

/* Accent Card */
.card--accent {
  background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
  border-color: rgba(251,191,36,0.3);
  position: relative;
  overflow: hidden;
}

.card--accent::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(251,191,36,0.1) 0%, transparent 70%);
  animation: rotate 20s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Table Container */
.table-card {
  margin-top: 30px;
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border: 1px solid rgba(251,191,36,0.1);
  border-radius: 20px;
  padding: 24px;
  backdrop-filter: blur(10px);
  animation: fadeInUp 0.6s ease-out 0.4s backwards;
}

.table-header {
  font-weight: 800;
  font-size: 18px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--light);
}

.table-header::before {
  content: '🔔';
  font-size: 24px;
}

/* Table Styles */
.tbl {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-top: 16px;
}

.tbl thead {
  background: linear-gradient(135deg, rgba(251,191,36,0.05), transparent);
}

.tbl th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--gray);
  border-bottom: 2px solid rgba(251,191,36,0.1);
}

.tbl tbody tr {
  transition: all 0.2s ease;
  border-bottom: 1px solid rgba(100,116,139,0.1);
}

.tbl tbody tr:hover {
  background: rgba(251,191,36,0.03);
  transform: translateX(4px);
}

.tbl td {
  padding: 14px 16px;
  font-size: 14px;
  color: var(--light);
  border-bottom: 1px solid rgba(100,116,139,0.05);
}

.tbl tbody tr:last-child td {
  border-bottom: none;
}

/* Username styling */
.tbl td:first-child {
  font-weight: 600;
  color: var(--primary-light);
}



/* Kolom kedua tabel - untuk pesan notifikasi */
.tbl td:nth-child(2) {
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  font-size: 14px;
  color: var(--light);
  padding: 14px 16px;
}

/* Time styling */
.tbl td:last-child {
  color: var(--gray);
  font-size: 13px;
}

/* Style khusus tabel notifikasi */
.notification-table td:first-child {
  font-weight: 700;
  color: var(--primary);
  text-align: center;
  font-size: 13px;
}

.notification-table td:nth-child(2) {
  font-size: 14px;
  color: var(--light);
  padding: 14px 20px;
  position: relative;
  padding-left: 40px;
}

.notification-table td:nth-child(2)::before {
  content: '•';
  position: absolute;
  left: 20px;
  color: var(--success);
  font-size: 20px;
  animation: pulse 2s infinite;
}

.notification-table td:last-child {
  color: var(--gray);
  font-size: 12px;
  text-align: right;
  opacity: 0.8;
}

/* Empty state */
.tbl tbody tr td[colspan] {
  text-align: center;
  padding: 40px;
  color: var(--gray);
  font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
  .topbar {
    flex-direction: column;
    gap: 16px;
    padding: 16px;
  }
  
  .topbar > div:first-child {
    font-size: 20px;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .card-value {
    font-size: 28px;
  }
  
  .tbl {
    font-size: 12px;
  }
  
  .tbl th, .tbl td {
    padding: 8px;
  }
  
  .tbl td:nth-child(2) {
    font-size: 11px;
  }
}

/* Loading animation */
@keyframes shimmer {
  0% { background-position: -1000px 0; }
  100% { background-position: 1000px 0; }
}

.loading {
  background: linear-gradient(90deg, rgba(251,191,36,0.1) 25%, rgba(251,191,36,0.2) 50%, rgba(251,191,36,0.1) 75%);
  background-size: 1000px 100%;
  animation: shimmer 2s infinite;
}

/* Hover glow effect */
.glow-hover {
  position: relative;
}

.glow-hover::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle, rgba(251,191,36,0.2) 0%, transparent 70%);
  transform: translate(-50%, -50%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.glow-hover:hover::after {
  opacity: 1;
}

/* Floating animation */
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.float {
  animation: float 3s ease-in-out infinite;
}
/* === PPPoE Card — enhancements (no animation changes) === */
.pppoe-header{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:8px
}
.pppoe-header .mini-muted{font-size:12px;color:var(--gray);opacity:.9}

.kpi-row{display:flex;align-items:flex-end;gap:10px;margin:6px 0 10px}
.kpi-row .kpi-label{
  font-size:13px;font-weight:700;letter-spacing:.3px;color:var(--gray)
}

.progress{
  width:100%;height:8px;border-radius:999px;overflow:hidden;
  background:rgba(100,116,139,.25);border:1px solid rgba(100,116,139,.2);
  box-shadow:inset 0 1px 2px rgba(0,0,0,.25);margin:2px 0 10px
}
.progress>span{
  display:block;height:100%;
  background:linear-gradient(90deg,var(--success),var(--primary));
  box-shadow:0 0 16px rgba(16,185,129,.25);
  transition:width .6s ease
}

.card-stats.compact{gap:12px;margin-top:4px}
.card-stats.compact .stat-item{font-size:13px;color:var(--gray)}
.card-stats.compact .stat-item strong{color:var(--light);font-weight:700}

/* === Customers Card — enhancements (no animation changes) === */
.customers-header{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:8px
}
.customers-header .mini-muted{font-size:12px;color:var(--gray);opacity:.9}

.warn-badge{
  background:linear-gradient(135deg, rgba(249,115,22,.25), rgba(251,191,36,.15));
  color:var(--primary-light);
  border:1px solid rgba(249,115,22,.4);
  padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;
  display:inline-flex;gap:6px;align-items:center
}
.warn-badge::before{
  content:'';width:8px;height:8px;background:currentColor;border-radius:50%;
  animation:blink 2s ease-in-out infinite;opacity:.9
}

/* kpi & progress: sama seperti pppoe */
.kpi-row{display:flex;align-items:flex-end;gap:10px;margin:6px 0 10px}
.kpi-row .kpi-label{font-size:13px;font-weight:700;letter-spacing:.3px;color:var(--gray)}

.progress-split{
  position:relative;width:100%;height:8px;border-radius:999px;overflow:hidden;
  background:rgba(100,116,139,.25);border:1px solid rgba(100,116,139,.2);
  box-shadow:inset 0 1px 2px rgba(0,0,0,.25);margin:2px 0 10px
}
.progress-split .seg{height:100%;display:inline-block}
.progress-split .seg.billable{
  background:linear-gradient(90deg,var(--primary),var(--primary-dark));
  box-shadow:0 0 16px rgba(251,191,36,.25)
}
.progress-split .seg.free{
  background:linear-gradient(90deg,var(--secondary),#0ea5e9);
  box-shadow:0 0 16px rgba(14,165,233,.25)
}

/* compact stats (dipakai juga di PPPoE) */
.card-stats.compact{gap:12px;margin-top:4px}
.card-stats.compact .stat-item{font-size:13px;color:var(--gray)}
.card-stats.compact .stat-item strong{color:var(--light);font-weight:700}


/* === PPPoE: balik ke gaya lama (emas) === */
.card-pppoe{
  /* pakai background default kartu yang gelap */
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border-color: rgba(251,191,36,0.1);
}
.card-pppoe .card-icon{
  /* aksen lembut pakai var default emas */
  background: var(--gradient-soft);
}
/* kalau sebelumnya ada blok .card-pppoe { ... } warna toska, biarkan snippet ini di bawahnya agar menimpa */

/* === Pelanggan: biru solid + aksen biru === */
.card-customers{
  /* garis 3px di atas ikut biru */
  --gradient: linear-gradient(135deg,#3b82f6,#0ea5e9);
  /* background kartu biru gelap (non-transparan) */
  background: linear-gradient(135deg,#0B1D36,#081426);
  border-color: #3b82f633;
}
.card-customers .card-icon{
  background: linear-gradient(135deg,#3b82f6,#0ea5e9);
}

/* Header kartu pembayaran: seragam dengan pppoe/customers */
.payments-header{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;
}
.payments-header .mini-muted{font-size:12px;color:var(--gray);opacity:.9}

/* Progress split khusus pembayaran: paid (hijau) vs unpaid (oranye) */
.progress-split .seg.paid{
  background: linear-gradient(90deg,#34d399,#10b981);
  box-shadow: 0 0 16px rgba(16,185,129,.25);
}
.progress-split .seg.unpaid{
  background: linear-gradient(90deg,#f59e0b,#f97316);
  box-shadow: 0 0 16px rgba(249,115,22,.25);
}

/* Payments: solid hijau (timpa transparansi card--accent) */
.card-payments{
  --gradient: linear-gradient(135deg,#34d399,#10b981); /* garis 3px di atas */
  background: linear-gradient(135deg,#0E3B2D,#0B2A21); /* SOLID */
  border-color: #34d39933;
}
.card-payments .card-icon{
  background: linear-gradient(135deg,#34d399,#10b981);
}

/* === PATCH: Progress split rapet & stabil === */
.progress-split{
  display:flex;            /* tadinya inline-block, bikin rapet */
  gap:0;
  width:100%;
  height:8px;
  border-radius:999px;
  overflow:hidden;
  background:rgba(100,116,139,.25);
  border:1px solid rgba(100,116,139,.2);
  box-shadow:inset 0 1px 2px rgba(0,0,0,.25);
  margin:2px 0 10px;
}
.progress-split .seg{
  display:block;
  height:100%;
  flex:0 0 auto;          /* hormati width inline-style */
}

/* Saat total=0, tampilkan pola kosong (biar kelihatan “aktif”) */
.progress-split.is-empty{
  background: repeating-linear-gradient(
    45deg,
    rgba(100,116,139,.25),
    rgba(100,116,139,.25) 8px,
    rgba(100,116,139,.35) 8px,
    rgba(100,116,139,.35) 16px
  );
}

/* === PATCH: Garis pembatas di blok Lunas/Pending === */
.card-stats.with-divider{
  border-top:1px solid rgba(100,116,139,.2);
  padding-top:12px;
  margin-top:12px;
}

/* Arrow di kiri judul */
.page-title-wrap{ display:flex; align-items:center; gap:8px; }

.page-menu-toggle{
  display:none;                /* default: sembunyi di desktop */
  width:32px; height:32px;
  border-radius:10px;
  border:1px solid rgba(251,191,36,.35);
  background:rgba(15,23,42,.9);
  color:var(--primary);
  display:inline-flex; align-items:center; justify-content:center;
  line-height:0; cursor:pointer;
  box-shadow:0 6px 20px rgba(0,0,0,.35);
  transition:transform .25s ease, filter .2s ease;
}
.page-menu-toggle:hover{ filter:brightness(1.1); }
.page-menu-toggle.open{ transform:rotate(180deg); }

/* Mask klik-luar (muncul saat sidebar aktif) */
#sbMask{
  position:fixed; inset:0;
  background:rgba(0,0,0,.35);
  backdrop-filter:blur(1px);
  display:none;
  z-index:1000; /* di bawah .sidebar (1001), di atas konten */
}

/* Hanya di HP/Tablet */
@media (max-width:768px){
  .page-menu-toggle{ display:inline-flex; }
  .sidebar{ width:260px; transform:translateX(-100%); transition:transform .28s ease; }
  .sidebar.active{ transform:translateX(0); }

  /* dorong seluruh konten (judul & jam ikut) saat sidebar aktif */
  .main-wrapper{ transition:transform .28s ease; will-change:transform; }
  .sidebar.active ~ #sbMask{ display:block; }
  .sidebar.active ~ #sbMask + .main-wrapper{ transform:translateX(260px); }

  /* rapikan header agar jam responsif */
  .page-header{ flex-wrap:nowrap; gap:8px; }
  .page-title{ min-width:0; }
  .page-header .time{
    margin-left:auto;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
}

/* Tambahan untuk layar sangat kecil */
@media (max-width:600px){
  .page-title{ font-size:24px; }
  .page-header .time{ font-size:12px; }
}

.sidebar-header .logo{ color: var(--primary); }       /* warna emas kamu */
.sidebar-header .logo svg{ width:26px; height:26px; display:block; }

.payment-divider {
  border-top: 1px solid rgba(100,116,139,0.2);
  margin-top: 12px;
  padding-top: 12px;
}

</style>
</head>
<body>
<div class="wrap">
  <!-- Sidebar Kiri -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo" aria-hidden="true">
  <svg viewBox="0 0 24 24" fill="none">
    <path d="M2.5 9.5a16 16 0 0119 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M5 12.55a11.8 11.8 0 0114 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M8.5 16.05a7 7 0 017 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M12 20h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
  </svg>
</span>

      <h1>Adam Wifi</h1>
    </div>
    
    <nav class="sidebar-menu">
      <a class="menu-item" href="reports.php">
        <span class="menu-icon">📊</span>
        <span class="menu-text">Job Teknisi</span>
      </a>
      <a class="menu-item" href="voucher_sisa.php">
        <span class="menu-icon">🎫</span>
        <span class="menu-text">Kelola Voucher</span>
      </a>
      <a class="menu-item" href="#" onclick="document.getElementById('syncForm').submit();return false;">
        <span class="menu-icon">🔄</span>
        <span class="menu-text">Sinkronkan Pelanggan</span>
      </a>
      <a class="menu-item" href="auto_link_pppoe.php">
        <span class="menu-icon">🔗</span>
        <span class="menu-text">Auto-Link PPPoE</span>
      </a>
    </nav>
    
    <div class="sidebar-footer">
      <a class="menu-item logout" href="logout.php">
        <span class="menu-icon">🚪</span>
        <span class="menu-text">Keluar</span>
      </a>
    </div>
  </aside>
  
  <div id="sbMask"></div>


  <div class="main-wrapper">
  <!-- Form sinkron tetap tersembunyi -->
  <form id="syncForm" method="post" style="display:none">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf']??'')?>">
    <input type="hidden" name="sync_customers" value="1">
  </form>



  <?php if($flash): ?>
  <div class="info" role="status" aria-live="polite">
    <?=h($flash)?>
    <button class="close" aria-label="Tutup">×</button>
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
  <h1 class="page-title">Dashboard</h1>
</div>
<span class="time" id="clock"></span>

    </div>
    <div class="stats-grid">
      <!-- KARTU PPPoE -->
<a class="card card-link card-pppoe" href="pppoe.php?tab=all">
  <div class="pppoe-header">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="card-icon">📡</div>
      <div>
        <div class="card-title" style="margin:0">PPPoE Status</div>
        <div class="mini-muted">Monitoring koneksi pelanggan</div>
      </div>
    </div>
    <div class="badge <?= ($pppoe_online>0?'on':'off') ?>">
      <?php $pppoe_pct = $pppoe_total ? round($pppoe_online/$pppoe_total*100) : 0; ?>
      <?=$pppoe_pct?>%
    </div>
  </div>

  <div class="kpi-row">
    <div class="card-value"><?=number_format($pppoe_online)?></div>
    <div class="kpi-label">Online</div>
  </div>

  <div class="progress">
    <span style="width: <?=$pppoe_total? (100*$pppoe_online/max(1,$pppoe_total)) : 0?>%"></span>
  </div>

  <div class="card-stats compact">
    <div class="stat-item">🟢 Online: <strong><?=number_format($pppoe_online)?></strong></div>
    <div class="stat-item">🔴 Offline: <strong><?=number_format($pppoe_offline)?></strong></div>
    <div class="stat-item">📦 Total: <strong><?=number_format($pppoe_total)?></strong></div>
  </div>

  <div class="card-meta">
    Total <?=number_format($pppoe_total)?> akun terdaftar
    <?php if($pppoe_last): ?>
      <br><small style="opacity:.7">Update: <?=h($pppoe_last)?></small>
    <?php endif; ?>
  </div>

  <div class="card-action">Lihat detail PPPoE</div>
</a>


      <!-- KARTU PELANGGAN -->
     
<a class="card card-link card-customers" href="customers.php">
  <div class="customers-header">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="card-icon">👥</div>
      <div>
        <div class="card-title" style="margin:0">Data Pelanggan</div>
        <div class="mini-muted">Ringkasan pelanggan aktif</div>
      </div>
    </div>
    <div class="warn-badge" title="Belum ter-link PPPoE">
      <?php $unl = max(0,(int)$customers_unlinked); ?>
      <?=$unl?> Unlinked
    </div>
  </div>

  <div class="kpi-row">
    <div class="card-value"><?=number_format($customers_total)?></div>
    <div class="kpi-label">Total</div>
  </div>

  <?php
    $tot = max(1,(int)$customers_total);
    $bill = (int)$customers_bill;
    $free = max(0,(int)$customers_free);
    $bill_pct = max(0,min(100, round($bill/$tot*100)));
    $free_pct = max(0,min(100, round($free/$tot*100)));
    $rest = max(0, 100 - $bill_pct - $free_pct);
  ?>
  <div class="progress-split" aria-label="Komposisi pelanggan">
    <span class="seg billable" style="width: <?=$bill_pct?>%"></span>
    <span class="seg free" style="width: <?=$free_pct?>%"></span>
    <?php if ($rest>0): ?>
      <span class="seg" style="width: <?=$rest?>%"></span>
    <?php endif; ?>
  </div>

  <div class="card-stats compact">
    <div class="stat-item">💰 Ditagih: <strong><?=number_format($customers_bill)?></strong> (<?=$bill_pct?>%)</div>
    <div class="stat-item">🎁 Gratis: <strong><?=number_format($customers_free)?></strong> (<?=$free_pct?>%)</div>
    <div class="stat-item">⚠️ Unlinked: <strong><?=number_format($customers_unlinked)?></strong></div>
  </div>

  <div class="card-meta">
    Kelola data pelanggan & tautan PPPoE.
  </div>

  <div class="card-action">Lihat detail pelanggan</div>
</a>


      <!-- KARTU PEMBAYARAN -->
<a class="card card-link card-payments" href="payments.php?tab=unpaid&period=<?=h($period_for_dashboard)?>">
  <?php if(!$pay_info['exists']): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
      <div class="card-icon">💳</div>
      <div>
        <div class="card-title" style="margin:0">Pembayaran — <?=h(period_label($period_for_dashboard))?></div>
        <div class="mini-muted">Periode <?=h(period_label($period_for_dashboard))?></div>
      </div>
    </div>
    <div class="card-meta">
      Tabel <code style="background:rgba(251,191,36,0.1);padding:2px 6px;border-radius:4px">payments</code> tidak ditemukan.
    </div>
    <div class="card-action">Buka modul pembayaran</div>
  <?php else: ?>
    <?php
      $paid   = (int)($pay_info['paid'] ?? 0);
      $unpaid = (int)($pay_info['unpaid'] ?? 0);
      $total  = max(0, $paid + $unpaid);
      $pct    = $total ? round($paid / $total * 100) : 0;
    ?>
    <div class="payments-header">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="card-icon">💳</div>
        <div>
          <div class="card-title" style="margin:0">Pembayaran — <?=h(period_label($period_for_dashboard))?></div>
          <div class="mini-muted">Periode <?=h(period_label($period_for_dashboard))?></div>
        </div>
      </div>
      <div class="badge on"><?=$pct?>% Lunas</div>
    </div>

    <div class="kpi-row">
      <div class="card-value">
        <?= $pay_info['amount_sum'] !== null
             ? rupiah($pay_info['amount_sum'])
             : '<span style="font-size:24px">Data tidak tersedia</span>' ?>
      </div>
      <div class="kpi-label">Total nominal</div>
    </div>

    <div class="progress-split <?= $total ? '' : 'is-empty' ?>" aria-label="Progress pembayaran">
  <span class="seg paid"   style="width: <?= $total ? round($paid/$total*100) : 0 ?>%"></span>
  <span class="seg unpaid" style="width: <?= $total ? max(0,100 - round($paid/$total*100)) : 0 ?>%"></span>
</div>


    <div class="card-stats compact">
  <div class="stat-item">✅ Lunas: <strong><?=is_null($pay_info['paid'])?'-':number_format($pay_info['paid'])?></strong></div>
  <div class="stat-item">⏳ Pending: <strong><?=is_null($pay_info['unpaid'])?'-':number_format($pay_info['unpaid'])?></strong></div>
</div>

<div class="payment-divider"></div>


    <?php if($pay_info['note']): ?>
      <div class="card-meta"><?=h($pay_info['note'])?></div>
    <?php endif; ?>

    <div class="card-action">Lihat detail pembayaran</div>
  <?php endif; ?>
</a>



    <!-- Tabel Status PPPoE Terbaru -->
</div><!-- end .stats-grid -->

<!-- Tabel Notifikasi Terbaru -->
<div class="table-card">
  <div class="table-header">Riwayat Notifikasi Terbaru</div>
  <table class="tbl notification-table">
    <thead>
      <tr>
        <th style="width:60px">ID</th>
        <th>Pesan Notifikasi</th>
        <th style="width:180px">Waktu</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$recent): ?>
        <tr>
          <td colspan="3">
            <div style="text-align:center;padding:40px">
              <div style="font-size:48px;margin-bottom:16px;opacity:0.3">📭</div>
              <div style="color:var(--gray);font-size:16px">Tidak ada riwayat notifikasi</div>
              <div style="color:var(--gray);font-size:14px;margin-top:8px">Notifikasi akan muncul setelah ada aktivitas sistem</div>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach($recent as $idx => $r): ?>
          <tr style="animation: fadeInUp 0.5s ease-out <?=0.5 + ($idx * 0.05)?>s backwards">
            <td style="text-align:center;font-weight:600;color:var(--primary)">#<?=h($r['id']??'-')?></td>
            <td><?=h($r['message']??'-')?></td>
            <td style="font-size:13px;color:var(--gray)">
              <?php 
                if($r['timestamp']) {
                  echo date('d/m/Y H:i', strtotime($r['timestamp']));
                } else {
                  echo '-';
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
  </div>
</div>

<script>
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

// Add ripple animation
const style = document.createElement('style');
style.textContent = `
  @keyframes rippleEffect {
    0% {
      width: 20px;
      height: 20px;
      opacity: 1;
    }
    100% {
      width: 200px;
      height: 200px;
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);

// Auto refresh every 30 seconds (optional)
let refreshTimer;
function startAutoRefresh() {
  refreshTimer = setInterval(() => {
    // Add loading state to topbar
    const topbar = document.querySelector('.topbar > div:first-child');
    const originalContent = topbar.innerHTML;
    topbar.innerHTML = originalContent + ' <span style="margin-left:8px;font-size:14px;opacity:0.7">(Refreshing...)</span>';
    
    setTimeout(() => {
      location.reload();
    }, 1000);
  }, 30000);
}

// Uncomment to enable auto-refresh
// startAutoRefresh();

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  // Alt + R for reports
  if (e.altKey && e.key === 'r') {
    e.preventDefault();
    window.location.href = 'reports.php';
  }
  
  // Alt + P for PPPoE
  if (e.altKey && e.key === 'p') {
    e.preventDefault();
    window.location.href = 'pppoe.php?tab=all';
  }
  
  // Alt + C for customers
  if (e.altKey && e.key === 'c') {
    e.preventDefault();
    window.location.href = 'customers.php';
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
console.log('%cAlt + R : Reports', 'color: #64748b');
console.log('%cAlt + P : PPPoE', 'color: #64748b');
console.log('%cAlt + C : Customers', 'color: #64748b');
console.log('%cAlt + L : Logout', 'color: #64748b');

// Add visual feedback for table rows
document.querySelectorAll('.tbl tbody tr').forEach(tr => {
  tr.addEventListener('click', function() {
    // Remove previous selection
    document.querySelectorAll('.tbl tbody tr').forEach(row => {
      row.style.background = '';
    });
    // Highlight current row
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

// Performance monitoring
if (window.performance) {
  window.addEventListener('load', () => {
    const perfData = window.performance.timing;
    const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
    console.log(`%c⚡ Page loaded in ${pageLoadTime}ms`, 'color: #10b981; font-weight: bold');
  });
}

window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.info').forEach(el => {
    const closeBtn = el.querySelector('.close');
    const hide = () => { el.classList.add('hide'); setTimeout(() => el.remove(), 300); };
    if (closeBtn) closeBtn.addEventListener('click', hide);
    setTimeout(hide, 6000); // auto hilang 6 detik
  });
});

// Clock display
function updateClock() {
  const now = new Date();
  const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  const date = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  const clock = document.getElementById('clock');
  if (clock) {
    clock.innerHTML = `${time} • ${date}`;
  }
}
setInterval(updateClock, 1000);
updateClock();



</script>

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

  // jika resize ke desktop, paksa tutup
  const mq = window.matchMedia('(min-width:769px)');
  const handleMQ = e => { if (e.matches) close(); };
  if (mq.addEventListener) mq.addEventListener('change', handleMQ);
  else mq.addListener(handleMQ);
})();
</script>

</body>
</html>