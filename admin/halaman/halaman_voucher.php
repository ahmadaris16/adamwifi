<?php
// Kelola Voucher Sisa (partial untuk layout utama)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../auth.php';
require_admin();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// Koneksi MySQLi (sesuai kode asli)
$DB_HOST='localhost'; $DB_USER='adah1658_admin'; $DB_PASS='Nuriska16'; $DB_NAME='adah1658_monitor';
$mysqli = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_errno) { http_response_code(500); die('DB error'); }

// Update data
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
  header('Location: index.php?page=voucher'); exit;
}

// Ambil data
$sql = "SELECT nama, DATE_FORMAT(tanggal,'%Y-%m-%d') AS tanggal, total, voucher_4_jam, voucher_1_hari, voucher_1_bulan
        FROM voucher_sisa ORDER BY FIELD(nama,'Ervianto','Nyoto','Anik'), nama";
$res  = $mysqli->query($sql);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if (session_status()===PHP_SESSION_ACTIVE) { session_write_close(); }
?>

<div class="card table-card">
  <div class="table-header">Kelola Voucher Sisa</div>

  <?php if ($flash): ?>
    <div class="info" role="status" aria-live="polite" style="margin-top:0">
      <?= h($flash) ?>
      <button class="close" aria-label="Tutup">x</button>
    </div>
  <?php endif; ?>

  <div class="voucher-grid">
    <?php foreach($rows as $idx => $r): ?>
      <form class="card voucher-card" method="post" action="index.php?page=voucher" style="animation-delay: <?= 0.1 + ($idx * 0.08) ?>s">
        <?php csrf_field(); ?>
        <input type="hidden" name="nama" value="<?= h($r['nama']) ?>">

        <div class="card-header">
          <?= h($r['nama']) ?>
        </div>

        <div class="voucher-row">
          <label>Tanggal</label>
          <input type="date" name="tanggal" value="<?= h($r['tanggal']) ?>">
        </div>

        <div class="voucher-row">
          <label>Total Voucher</label>
          <input type="number" name="total" value="<?= (int)$r['total'] ?>" min="0">
        </div>

        <div class="voucher-row">
          <label>Voucher 4 Jam</label>
          <input type="number" name="voucher_4_jam" value="<?= (int)$r['voucher_4_jam'] ?>" min="0">
        </div>

        <div class="voucher-row">
          <label>Voucher 1 Hari</label>
          <input type="number" name="voucher_1_hari" value="<?= (int)$r['voucher_1_hari'] ?>" min="0">
        </div>

        <div class="voucher-row">
          <label>Voucher 1 Bulan</label>
          <input type="number" name="voucher_1_bulan" value="<?= (int)$r['voucher_1_bulan'] ?>" min="0">
        </div>

        <div class="voucher-actions">
          <button class="btn" type="submit">Simpan Perubahan</button>
        </div>
      </form>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
      <div class="card voucher-card">
        <div style="text-align:center;padding:32px;color:var(--gray);">
          Belum ada data voucher
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
