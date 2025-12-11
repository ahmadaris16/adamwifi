<?php
// report_edit.php — Edit Job Teknisi (UI modern seragam)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM kinerja_teknisi WHERE id=? LIMIT 1");
$st->execute([$id]);
$job = $st->fetch();
if (!$job) { http_response_code(404); echo "Data tidak ditemukan."; exit; }

$techs = $pdo->query("SELECT id, username FROM technicians ORDER BY username ASC")->fetchAll();

$notice = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $technician_id = (int)($_POST['technician_id'] ?? 0);
  $job_date      = $_POST['job_date'] ?? '';
  $fee_amount    = $_POST['fee_amount'] ?? '';
  $description   = trim($_POST['description'] ?? '');

  if ($technician_id<=0 || $job_date==='' || !is_numeric($fee_amount)) {
    $notice = 'Teknisi, tanggal, dan fee wajib diisi dengan benar.';
  } else {
    $upd = $pdo->prepare("UPDATE kinerja_teknisi
                          SET technician_id=?, job_date=?, fee_amount=?, description=?
                          WHERE id=?");
    $upd->execute([$technician_id, $job_date, (float)$fee_amount, $description!==''?$description:null, $id]);

    $_SESSION['flash'] = 'Job teknisi diperbarui.';
    $qs = http_build_query(['from'=>$job_date,'to'=>$job_date,'tech'=>$technician_id]);
    header('Location: reports.php?'.$qs); exit;
  }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Job Teknisi — AdamWifi Admin</title>
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
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.topbar .title h1 {
  font-size: 24px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary) 0%, var(--warning) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.topbar .title h1::before {
  content: '✏️';
  font-size: 28px;
  animation: pulse 2s ease-in-out infinite;
}

.topbar .title .sub {
  font-size: 14px;
  color: var(--gray);
  margin-left: 40px;
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
form#editForm {
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

/* Notice/Alert */
.notice {
  background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1));
  border: 1px solid var(--danger);
  color: var(--danger);
  padding: 14px 20px;
  border-radius: 12px;
  margin: 0 0 20px 0;
  display: none;
  align-items: center;
  gap: 12px;
  animation: slideIn 0.5s ease-out;
  box-shadow: 0 4px 12px rgba(239,68,68,0.2);
  position: relative;
}

.notice::before {
  content: '⚠';
  font-size: 20px;
}

.notice .close {
  position: absolute;
  top: 10px;
  right: 12px;
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
      <div class="title">
        <h1>Edit Job Teknisi</h1>
        <div class="sub">ID #<?=h($id)?></div>
      </div>
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
          <div class="title">Form Edit Job</div>
          <div class="sub">Perbarui data lalu simpan perubahan</div>
        </div>
      </div>

      <!-- Notifikasi -->
      <div class="notice" id="msg">
        <?php if ($notice): ?>
          <?=h($notice)?>
          <button class="close" aria-label="Tutup">×</button>
        <?php endif; ?>
      </div>

      <!-- FORM -->
      <form method="post" id="editForm">
        <?php csrf_field(); ?>

        <div class="row">
          <label>Pilih Teknisi</label>
          <select name="technician_id" required>
            <?php foreach($techs as $t): ?>
              <option value="<?=$t['id']?>" <?= (int)$job['technician_id']===(int)$t['id']?'selected':'' ?>>
                <?=h($t['username'])?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <label>Tanggal Pekerjaan</label>
          <input type="date" name="job_date" value="<?=h(substr((string)$job['job_date'],0,10))?>" required>
        </div>

        <div class="row">
          <label>Jumlah Fee (Rp)</label>
          <input type="number" step="100" min="0" name="fee_amount" value="<?=h($job['fee_amount'])?>" required>
        </div>

        <div class="row">
          <label>Keterangan Pekerjaan</label>
          <textarea name="description" placeholder="Deskripsi pekerjaan teknisi"><?=h($job['description'] ?? '')?></textarea>
        </div>

        <div class="button-group">
          <button class="btn" type="submit">Simpan Perubahan</button>
          <a class="btn secondary" href="reports.php">Batal</a>
        </div>
      </form>
    </div>
  </div>

<script>
// Notice handler untuk error message PHP
(function(){
  var box = document.getElementById('msg');
  if(!box) return;
  
  var closer = box.querySelector('.close');

  function hide(){
    box.style.transition = 'opacity .25s ease, transform .25s ease';
    box.style.opacity = '0';
    box.style.transform = 'translateY(-6px)';
    setTimeout(function(){
      box.style.display = 'none';
      box.style.opacity = '';
      box.style.transform = '';
    }, 260);
  }

  // Hanya tampilkan jika ada pesan error
  if (box.textContent.trim().length > 0) {
    box.style.display = 'flex';
    if (closer) {
      closer.onclick = hide;
    }
    setTimeout(hide, 6000);
  }
})();
</script>
</body>
</html>