<?php
// voucher_sisa.php — Kelola Voucher Sisa (themed + animasi, fungsi tetap)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/auth.php'; // pakai guard & CSRF milik proyek
require_admin();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// ====== KONEKSI MYSQLI (sesuai kode asli kamu) ======
$DB_HOST='localhost'; $DB_USER='adah1658_admin'; $DB_PASS='Nuriska16'; $DB_NAME='adah1658_monitor';
$mysqli = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_errno) { http_response_code(500); die('DB error'); }

// ====== UPDATE (fungsi tetap) ======
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $nama    = $_POST['nama'] ?? '';
  $tanggal = $_POST['tanggal'] ?? '';
  $total   = (int)($_POST['total'] ?? 0);
  $v4      = (int)($_POST['voucher_4_jam'] ?? 0);
  $v1d     = (int)($_POST['voucher_1_hari'] ?? 0);
  $v1b     = (int)($_POST['voucher_1_bulan'] ?? 0);

  $stmt = $mysqli->prepare("UPDATE voucher_sisa SET tanggal=?, total=?, voucher_4_jam=?, voucher_1_hari=?, voucher_1_bulan=? WHERE nama=?");
  if ($stmt){
    $stmt->bind_param("siiiis", $tanggal, $total, $v4, $v1d, $v1b, $nama);
    $stmt->execute();
  }
  $_SESSION['flash'] = 'Perubahan tersimpan.';
  header('Location: voucher_sisa.php'); exit;
}

// ====== AMBIL DATA ======
$sql = "SELECT nama, DATE_FORMAT(tanggal,'%Y-%m-%d') AS tanggal, total, voucher_4_jam, voucher_1_hari, voucher_1_bulan
        FROM voucher_sisa ORDER BY FIELD(nama,'Ervianto','Nyoto','Anik'), nama";
$res  = $mysqli->query($sql);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kelola Voucher Sisa — AdamWifi Admin</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

* { margin: 0; padding: 0; box-sizing: border-box; }

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
  --gradient-soft: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(245,158,11,0.05));
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

/* Animated Background */
body::before {
  content: '';
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
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
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background-image:
    linear-gradient(rgba(251,191,36,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(251,191,36,0.03) 1px, transparent 1px);
  background-size: 50px 50px;
  z-index: -1;
  animation: gridMove 10s linear infinite;
  pointer-events: none;
}
@keyframes gridMove { 0% { transform: translate(0, 0); } 100% { transform: translate(50px, 50px); } }

/* Layout wrapper */
.wrap { position: relative; z-index: 1; max-width: 1400px; margin: 0 auto; min-height: 100vh; }

/* Topbar */
.topbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  display: flex;
  padding: 12px 20px;
  background: rgba(15,23,42,0.95);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(251,191,36,0.2);
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
  animation: slideDown 0.5s ease-out;
}
@keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Judul & alignment di topbar */
.topbar-content{
  max-width: 1400px; margin: 0 auto; width: 100%;
  display: grid;
  grid-template-columns: 1fr auto;   /* kiri: title, kanan: actions */
  align-items: center;                /* CENTER vertikal keduanya */
  gap: 12px;
  min-height: 56px;
}

.topbar .title{
  display: inline-flex; align-items: center; gap: 12px; line-height: 1;
}
.topbar .title-icon{ font-size: 28px; line-height: 1; }

.topbar .title-text{
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent; color: transparent;
  font-size: 24px; font-weight: 800;
}

/* === PISAHKAN ACTIONS: topbar vs card === */
.actions{ display:flex; gap:12px; }                 /* base */

.topbar .actions{                                    /* topbar: center vertikal */
  align-items:center;
  margin-top:0;
  padding-top:0;
  border-top:none !important;
}

.card .actions{                                      /* card: jarak nyaman */
  margin-top:20px;
  padding-top:16px;
  border-top:none !important;
}

/* Main content */
.main { padding: 24px; padding-top: 120px; }

/* Buttons */
.btn {
  padding: 10px 20px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: var(--dark);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700; font-size: 14px;
  transition: all 0.3s ease;
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  border: none; cursor: pointer; position: relative; overflow: hidden;
  box-shadow: 0 4px 12px rgba(251,191,36,0.3);
}
.btn::before{
  content:''; position:absolute; top:50%; left:50%; width:0; height:0;
  background: rgba(255,255,255,0.3); border-radius:50%;
  transform: translate(-50%,-50%); transition: width .4s, height .4s;
}
.btn:hover::before{ width:300px; height:300px; }
.btn:hover{ transform: translateY(-2px); box-shadow: 0 6px 20px rgba(251,191,36,0.5); }
.btn:active{ transform: translateY(0); }
.btn.secondary{
  background: rgba(30,41,59,0.9); color: var(--light);
  border: 1px solid rgba(251,191,36,0.18);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Flash Message */
.info{
  background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.1));
  border: 1px solid var(--success); color: var(--success);
  padding: 16px 24px; border-radius: 16px; margin: 0 24px 20px;
  display: flex; align-items: center; gap: 12px;
  animation: slideIn 0.5s ease-out; box-shadow: 0 4px 12px rgba(16,185,129,0.2);
  position: relative;
}
.info::before{
  content:'✓'; width:24px; height:24px; background:var(--success); color:var(--white);
  border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;
}
.info .close{ position:absolute; top:10px; right:12px; border:0; background:transparent; color:inherit; font-weight:800; font-size:16px; cursor:pointer; opacity:.8; }
.info .close:hover{ opacity:1; }
@keyframes slideIn { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* Cards grid */
.grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:20px; }

/* Card */
.card{
  background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border: 1px solid rgba(251,191,36,0.1); border-radius: 20px;
  padding: 24px; position: relative; overflow: hidden; transition: all .3s ease;
  backdrop-filter: blur(10px); animation: fadeInUp .6s ease-out;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}
.card:nth-child(1){ animation-delay:.1s; }
.card:nth-child(2){ animation-delay:.2s; }
.card:nth-child(3){ animation-delay:.3s; }
@keyframes fadeInUp{ from{ opacity:0; transform:translateY(30px);} to{ opacity:1; transform:translateY(0);} }
.card::before{
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
  background: var(--gradient); transform: scaleX(0); transform-origin: left; transition: transform .3s ease;
}
.card:hover::before{ transform: scaleX(1); }

/* Card header */
.card-header{
  font-weight:800; font-size:18px; margin-bottom:20px;
  display:flex; align-items:center; gap:12px; color:var(--light);
  padding-bottom:12px; border-bottom:1px solid rgba(251,191,36,0.1);
}
.card-header::before{
  content:''; width:12px; height:12px; border-radius:50%;
  background: linear-gradient(135deg, var(--success), var(--secondary));
  box-shadow: 0 0 20px rgba(16,185,129,0.5);
}

/* Form row */
.row{
  display:grid; grid-template-columns:140px 1fr; gap:12px; align-items:center; margin:12px 0;
}
.row label{
  font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray);
}
.row input[type="date"], .row input[type="number"]{
  width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(251,191,36,0.18);
  background:rgba(30,41,59,0.5); color:var(--light); outline:none; font-family:inherit; font-size:14px; transition: all .3s ease;
}
.row input:focus{ border-color:var(--primary); box-shadow:0 0 0 3px rgba(251,191,36,0.1); }

/* Page header */
.page-header{ margin-bottom:24px; }
.hero{
  font-size:32px; font-weight:800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent;
  background-clip:text; color:transparent;
  margin:0; padding:0 24px;
}

/* Responsive */
@media (max-width: 768px){
  .topbar{ flex-direction: column; align-items: flex-start; }
  .topbar-content{ grid-template-columns: 1fr auto; min-height: 52px; }
  .main{ padding:16px; padding-top:120px; }
  .grid{ grid-template-columns:1fr; }
  .row{ grid-template-columns:1fr; gap:8px; }
  .card{ padding:16px; }
}
</style>

</head>
<body>
<!-- Topbar -->
<div class="topbar">
  <div class="topbar-content">
    <div class="title">
      <span class="title-text">Kelola Voucher Sisa</span>
    </div>
    <div class="actions">
      <a class="btn secondary" href="index.php">← Kembali</a>
    </div>
  </div>
</div>

  <div class="wrap">
    <div class="main">
      <?php if ($flash): ?>
        <div class="info" id="flash">
          <?= h($flash) ?>
          <button class="close" id="flashClose" aria-label="Tutup">×</button>
        </div>
        <script>
          (function(){
            var f=document.getElementById('flash'), b=document.getElementById('flashClose');
            function hide(){
              if(f){
                f.style.opacity='0';
                f.style.transform='translateY(-4px)';
                setTimeout(function(){ if(f&&f.parentNode){ f.parentNode.removeChild(f);} }, 260);
              }
            }
            if(b){ b.addEventListener('click', hide); }
            setTimeout(hide, 6000);
          })();
        </script>
      <?php endif; ?>

      <div class="page-header">
        <h1 class="hero">Data Voucher Per Reseller</h1>
      </div>

      <div class="grid">
        <?php foreach($rows as $idx => $r): ?>
          <form class="card" method="post" style="animation-delay: <?= 0.1 + ($idx * 0.1) ?>s">
            <?php csrf_field(); ?>
            <input type="hidden" name="nama" value="<?= h($r['nama']) ?>">

            <div class="card-header">
              <?= h($r['nama']) ?>
            </div>

            <div class="row">
              <label>Tanggal</label>
              <input type="date" name="tanggal" value="<?= h($r['tanggal']) ?>">
            </div>

            <div class="row">
              <label>Total Voucher</label>
              <input type="number" name="total" value="<?= (int)$r['total'] ?>" min="0">
            </div>

            <div class="row">
              <label>Voucher 4 Jam</label>
              <input type="number" name="voucher_4_jam" value="<?= (int)$r['voucher_4_jam'] ?>" min="0">
            </div>

            <div class="row">
              <label>Voucher 1 Hari</label>
              <input type="number" name="voucher_1_hari" value="<?= (int)$r['voucher_1_hari'] ?>" min="0">
            </div>

            <div class="row">
              <label>Voucher 1 Bulan</label>
              <input type="number" name="voucher_1_bulan" value="<?= (int)$r['voucher_1_bulan'] ?>" min="0">
            </div>

            <div class="actions">
              <button class="btn" type="submit">Simpan Perubahan</button>
            </div>
          </form>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <div class="card">
            <div style="text-align:center;padding:40px">
              <div style="font-size:48px;margin-bottom:16px;opacity:0.3">📭</div>
              <div style="color:var(--gray);font-size:16px">Belum ada data voucher</div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
