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
      <form class="card voucher-card voucher-form" method="post" action="api/voucher_update.php" style="animation-delay: <?= 0.1 + ($idx * 0.08) ?>s">
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
          <input type="number" name="total" value="<?= (int)$r['total'] ?>" min="0" readonly>
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

<script>
(function(){
  var forms = document.querySelectorAll('.voucher-form');
  function toInt(val){ var n = parseInt(val,10); return isNaN(n)?0:n; }
  function syncTotal(form){
    if(!form) return;
    var totalEl = form.querySelector('input[name="total"]');
    var v4  = form.querySelector('input[name="voucher_4_jam"]');
    var v1d = form.querySelector('input[name="voucher_1_hari"]');
    var v1b = form.querySelector('input[name="voucher_1_bulan"]');
    if(!totalEl) return;
    var sum = toInt(v4 && v4.value) + toInt(v1d && v1d.value) + toInt(v1b && v1b.value);
    totalEl.value = sum;
  }
  forms.forEach(function(f){
    ['voucher_4_jam','voucher_1_hari','voucher_1_bulan'].forEach(function(name){
      var input = f.querySelector('input[name="'+name+'"]');
      if (input){ input.addEventListener('input', function(){ syncTotal(f); }); }
    });
    syncTotal(f);

    f.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = f.querySelector('button[type="submit"]');
      var original = btn ? btn.textContent : '';
      if (btn){ btn.disabled = true; btn.textContent = 'Menyimpan...'; }
      syncTotal(f);
      var fd = new FormData(f);
      fetch(f.action, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(async function(r){
          var isJson = (r.headers.get('content-type') || '').includes('application/json');
          var data = isJson ? await r.json() : { ok:false, message: await r.text() };
          if(!r.ok || !data.ok){
            throw new Error(data && data.message ? data.message : 'Gagal menyimpan');
          }
          if (window.showToast) window.showToast(data.message || 'Tersimpan.', 'success');
        })
        .catch(function(err){
          if (window.showToast) window.showToast(err.message || 'Gagal menyimpan', 'danger');
        })
        .finally(function(){
          if (btn){ btn.disabled = false; btn.textContent = original || 'Simpan Perubahan'; }
        });
    });
  });
})();
</script>
