<?php
// pppoe.php — daftar status PPPoE (Semua / Online / Offline)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
// Pakai koneksi DB yang sama dengan receiver.php
require_once __DIR__ . '/../config/config.php';
if (isset($koneksi) && $koneksi instanceof PDO) {
  $pdo = $koneksi;
} elseif (isset($pdo) && $pdo instanceof PDO) {
  $koneksi = $pdo;
}



if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function hascol(PDO $db,$t,$c){ $s=$db->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$c]); return (bool)$s->fetch(); }


$TAB = $_GET['tab'] ?? 'all';
if (!in_array($TAB, ['all','online','offline'], true)) $TAB = 'all';

$onlineCondSQL = "LOWER(COALESCE(status,'')) IN ('online','connected','up','1','true')";
$has_last = hascol($koneksi,'pppoe_status','last_update');
$orderBy  = $has_last ? "last_update DESC, username ASC" : "username ASC";

// ringkasan
$tot = (int)$koneksi->query("SELECT COUNT(*) FROM pppoe_status")->fetchColumn();
$on  = (int)$koneksi->query("SELECT COUNT(*) FROM pppoe_status WHERE $onlineCondSQL")->fetchColumn();
$off = max(0, $tot - $on);

// data utama
$where = '';
if ($TAB === 'online')  $where = "WHERE $onlineCondSQL";
if ($TAB === 'offline') $where = "WHERE NOT($onlineCondSQL)";

$cols = $has_last ? "username, ip, status, last_update" : "username, ip, status";
$sql  = "SELECT $cols FROM pppoe_status $where ORDER BY $orderBy";
$rows = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Status PPPoE — AdamWifi Admin</title>
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
  content: '📡';
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

.btn.secondary {
  background: rgba(30,41,59,0.9);
  color: var(--light);
  border: 1px solid rgba(251,191,36,0.18);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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

/* Tabs dengan animasi */
.tabbar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.tab {
  text-decoration: none;
  color: var(--light);
  font-weight: 700;
  font-size: 14px;
  background: rgba(30,41,59,0.9);
  border: 1px solid rgba(251,191,36,0.18);
  padding: 10px 16px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.tab::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(251,191,36,0.1), transparent);
  transition: left 0.5s ease;
}

.tab:hover::before {
  left: 100%;
}

.tab.active {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: var(--dark);
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(251,191,36,0.3);
  transform: translateY(-2px);
}

.tab .badge {
  background: rgba(0,0,0,0.2);
  color: inherit;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  margin-left: 4px;
}

.tab.active .badge {
  background: rgba(255,255,255,0.2);
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

/* Table - sama dengan reports.php */
.tbl {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-top: 8px;
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
.tbl td:nth-child(2) {
  font-weight: 600;
  color: var(--primary-light);
}

/* IP styling */
.tbl td:nth-child(3) {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Courier New", monospace;
  font-size: 13px;
  background: rgba(30,41,59,0.5);
  border-radius: 6px;
}

/* Time styling */
.tbl td:last-child {
  color: var(--gray);
  font-size: 13px;
}

/* Responsive */
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
  
  .card {
    padding: 16px;
  }
  
  .tabbar {
    flex-direction: column;
  }
  
  .tbl {
    font-size: 12px;
  }
  
  .tbl th, .tbl td {
    padding: 8px;
  }
}
</style>
</head>
<body>
  <!-- Topbar di luar wrap -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="title">Status PPPoE</div>
      <div class="actions">
        <a class="btn secondary" href="index.php">← Kembali ke Dashboard</a>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="main">
      <div class="card">
        <!-- Tabs -->
        <div class="tabbar">
          <a class="tab <?= $TAB==='all'?'active':'' ?>" href="?tab=all">
            Semua <span class="badge"><?= number_format($tot) ?></span>
          </a>
          <a class="tab <?= $TAB==='online'?'active':'' ?>" href="?tab=online">
            Online <span class="badge on"><?= number_format($on) ?></span>
          </a>
          <a class="tab <?= $TAB==='offline'?'active':'' ?>" href="?tab=offline">
            Offline <span class="badge off"><?= number_format($off) ?></span>
          </a>
        </div>

        <!-- Tabel -->
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th style="width:70px">No</th>
                <th>Username</th>
                <th>IP Address</th>
                <th>Status</th>
                <?php if($has_last): ?><th>Waktu Update</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if(!$rows): ?>
                <tr>
                  <td colspan="<?= $has_last?5:4 ?>">
                    <div style="text-align:center; padding:40px">
                      <div style="font-size:48px; margin-bottom:16px; opacity:0.3">📭</div>
                      <div style="color:var(--gray); font-size:16px">Tidak ada data PPPoE</div>
                      <div style="color:var(--gray); font-size:14px; margin-top:8px">Data akan muncul setelah sistem menerima update</div>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php $no=1; foreach($rows as $idx => $r):
                  $isOn = in_array(strtolower((string)$r['status']), ['online','connected','up','1','true']);
                ?>
                  <tr style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($idx * 0.05)?>s backwards">
                    <td><?= $no++ ?></td>
                    <td><?= h($r['username'] ?? '-') ?></td>
                    <td><?= h($r['ip'] ?? $r['ip_address'] ?? '-') ?></td>
                    <td><?= $isOn ? '<span class="badge on">Online</span>' : '<span class="badge off">Offline</span>' ?></td>
                    <?php if($has_last): ?><td><?= h($r['last_update'] ?? '-') ?></td><?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<script>
// Animasi masuk untuk baris tabel (stagger)
document.addEventListener('DOMContentLoaded', function(){
  var rows = document.querySelectorAll('.tbl tbody tr');
  rows.forEach(function(tr, i){
    if (!tr.style.animation && !tr.querySelector('td[colspan]')) {
      tr.style.animation = 'fadeInUp .35s ease-out both';
      tr.style.animationDelay = (0.03 * i + 0.12) + 's';
    }
  });
});

// Enhanced badge interactions
document.querySelectorAll('.badge').forEach(badge => {
  badge.addEventListener('mouseenter', function() {
    this.style.filter = 'brightness(1.2)';
    this.style.transform = 'scale(1.05)';
  });
  badge.addEventListener('mouseleave', function() {
    this.style.filter = '';
    this.style.transform = '';
  });
});

// Table row selection feedback
document.querySelectorAll('.tbl tbody tr').forEach(tr => {
  tr.addEventListener('click', function() {
    document.querySelectorAll('.tbl tbody tr').forEach(row => {
      row.style.background = '';
    });
    this.style.background = 'rgba(251,191,36,0.05)';
  });
});
</script>
</body>
</html>