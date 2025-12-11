<?php
// customers.php — daftar pelanggan (tema seragam + filter + ringkasan)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// ====== FILTERS ======
$q       = trim($_GET['q'] ?? '');
$gratis  = $_GET['gratis'] ?? '';    // '' | '0' (Ditagih) | '1' (Gratis)
$status  = $_GET['status'] ?? '';    // '' | 'online' | 'offline'

// helper utk build link tab sambil mempertahankan query lain
function qurl(array $overrides = []) {
  $base = $_GET;
  foreach ($overrides as $k=>$v) {
    if ($v === null) unset($base[$k]); else $base[$k] = $v;
  }
  $qs = http_build_query($base);
  return basename($_SERVER['PHP_SELF']).($qs?('?'.$qs):'');
}

// ====== QUERY ======
$where = []; $params = [];
if ($q !== '') { $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR c.pppoe_username LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($gratis === '1') { $where[] = "COALESCE(c.billable,1)=0"; }
elseif ($gratis === '0') { $where[] = "COALESCE(c.billable,1)=1"; }

$onlineCond = "LOWER(COALESCE(s.status,'')) IN ('online','connected','up','1','true')";
if ($status === 'online')  { $where[] = $onlineCond; }
if ($status === 'offline') { $where[] = "NOT($onlineCond)"; }

$sql = "SELECT c.id, c.name, c.phone, COALESCE(c.billable,1) AS billable, COALESCE(c.pppoe_username,'') AS pppoe_username,
               s.ip, s.status
        FROM customers c
        LEFT JOIN pppoe_status s ON s.username = c.pppoe_username
        ".($where ? "WHERE ".implode(" AND ", $where) : "")."
        ORDER BY c.name ASC";

$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// ====== RINGKASAN (berdasar hasil filter yg tampil) ======
$total = count($rows);
$tot_bill = $tot_free = $tot_unlinked = $on = $off = 0;
foreach ($rows as $r){
  ((int)$r['billable']===1) ? $tot_bill++ : $tot_free++;
  ($r['pppoe_username']==='') && $tot_unlinked++;
  $isOn = in_array(strtolower((string)($r['status'] ?? '')), ['online','connected','up','1','true']);
  $isOn ? $on++ : $off++;
}
$bill_pct = $total ? round($tot_bill/$total*100) : 0;
$free_pct = $total ? round($tot_free/$total*100) : 0;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pelanggan — AdamWifi Admin</title>
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
  content: '👥';
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
  margin-bottom: 20px;
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

/* Tabs filter dengan animasi */
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

/* Search + status filters */
.filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  animation: slideIn 0.5s ease-out 0.2s backwards;
}

@keyframes slideIn {
  from {
    transform: translateX(-30px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.filters input[type="text"], .filters select {
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

.filters input[type="text"] {
  flex: 1;
  min-width: 220px;
}

.filters input[type="text"]:focus, .filters select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(251,191,36,0.1);
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

/* Progress bar dengan animasi */
.progress-split {
  position: relative;
  width: 100%;
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(100,116,139,0.25);
  border: 1px solid rgba(100,116,139,0.2);
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.25);
  margin: 16px 0 12px;
}

.progress-split .seg {
  height: 100%;
  display: inline-block;
  transition: width 0.8s ease;
  animation: progressGlow 2s ease-in-out infinite alternate;
}

@keyframes progressGlow {
  0% { box-shadow: 0 0 8px rgba(251,191,36,0.3); }
  100% { box-shadow: 0 0 16px rgba(251,191,36,0.6); }
}

.progress-split .seg.bill {
  background: linear-gradient(90deg, var(--primary), var(--primary-dark));
}

.progress-split .seg.free {
  background: linear-gradient(90deg, var(--secondary), #0ea5e9);
}

/* Summary pills dengan animasi stagger */
.summary {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin: 8px 0 20px;
}

.pill {
  background: var(--gradient-soft);
  border: 1px solid rgba(251,191,36,0.25);
  padding: 10px 14px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.3s ease;
  animation: fadeInUp 0.5s ease-out backwards;
  position: relative;
  overflow: hidden;
}

.pill:nth-child(1) { animation-delay: 0.1s; }
.pill:nth-child(2) { animation-delay: 0.2s; }
.pill:nth-child(3) { animation-delay: 0.3s; }
.pill:nth-child(4) { animation-delay: 0.4s; }
.pill:nth-child(5) { animation-delay: 0.5s; }

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

.pill .on { background: var(--success); }
.pill .off { background: var(--danger); }
.pill .warn { background: var(--warning); }

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
  cursor: pointer;
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

.tbl td:nth-child(3) { /* IP */
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Courier New", monospace;
  font-size: 13px;
  background: rgba(30,41,59,0.5);
  border-radius: 6px;
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

/* Responsive */
@media (max-width: 960px) {
  .filters {
    flex-direction: column;
  }
  
  .filters input[type="text"] {
    min-width: auto;
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
  
  .card {
    padding: 16px;
  }
  
  .tabbar {
    flex-direction: column;
  }
  
  .summary {
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
      <div class="title">Data Pelanggan</div>
      <div class="actions">
        <a class="btn secondary" href="index.php">← Kembali ke Dashboard</a>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="main">
      <div class="card">
        <!-- Tabs: Semua / Ditagih / Gratis -->
        <div class="tabbar">
          <a class="tab <?= ($gratis==='')?'active':'' ?>" href="<?= h(qurl(['gratis'=>''])) ?>">
            Semua <span class="badge"><?= number_format($total) ?></span>
          </a>
          <a class="tab <?= ($gratis==='0')?'active':'' ?>" href="<?= h(qurl(['gratis'=>'0'])) ?>">
            Ditagih <span class="badge"><?= number_format($tot_bill) ?></span>
          </a>
          <a class="tab <?= ($gratis==='1')?'active':'' ?>" href="<?= h(qurl(['gratis'=>'1'])) ?>">
            Gratis <span class="badge"><?= number_format($tot_free) ?></span>
          </a>
        </div>

        <!-- Search + status -->
        <form class="filters" method="get" action="">
          <div style="position:relative; flex:1; min-width:220px;">
            <input type="text" name="q" placeholder="Ketik untuk mencari nama / WA / PPPoE…" value="<?=h($q)?>" aria-label="Pencarian" style="width:100%">
            <div class="search-icon" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--gray); pointer-events:none;">
              🔍
            </div>
          </div>
          <select name="status" title="Status PPPoE">
            <option value="">Semua status</option>
            <option value="online"  <?= $status==='online'?'selected':'' ?>>Online</option>
            <option value="offline" <?= $status==='offline'?'selected':'' ?>>Offline</option>
          </select>
          <!-- pertahankan tab 'gratis' saat submit -->
          <input type="hidden" name="gratis" value="<?=h($gratis)?>">
          <button class="btn" type="submit">Terapkan</button>
          <a class="xbtn" href="customers.php">Reset</a>
        </form>

        <!-- Progress + ringkasan -->
        <div class="progress-split" aria-label="Komposisi pelanggan">
          <span class="seg bill" style="width: <?=$bill_pct?>%"></span>
          <span class="seg free" style="width: <?=$free_pct?>%"></span>
        </div>
        <div class="summary">
          <div class="pill"><span class="dot on"></span> Ditagih: <strong><?=number_format($tot_bill)?></strong> (<?=$bill_pct?>%)</div>
          <div class="pill"><span class="dot off"></span> Gratis: <strong><?=number_format($tot_free)?></strong> (<?=$free_pct?>%)</div>
          <div class="pill"><span class="dot warn"></span> Unlinked: <strong><?=number_format($tot_unlinked)?></strong></div>
          <div class="pill"><span class="dot on"></span> Online: <strong><?=number_format($on)?></strong></div>
          <div class="pill"><span class="dot off"></span> Offline: <strong><?=number_format($off)?></strong></div>
        </div>

        <!-- Tabel -->
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th style="width:70px">No</th>
                <th>Nama</th>
                <th>IP Address</th>
                <th>Nomor WhatsApp</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$rows): ?>
                <tr>
                  <td colspan="5">
                    <div style="text-align:center;padding:40px">
                      <div style="font-size:48px;margin-bottom:16px;opacity:0.3">👥</div>
                      <div style="color:var(--gray);font-size:16px">Belum ada data pelanggan</div>
                      <div style="color:var(--gray);font-size:14px;margin-top:8px">Coba ubah filter pencarian</div>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php $no=1; foreach($rows as $i => $r):
                  $isOnline = in_array(strtolower((string)($r['status'] ?? '')), ['online','connected','up','1','true']);
                ?>
                  <tr class="clickable" data-href="customer.php?id=<?=$r['id']?>" style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
                    <td><?= $no++ ?></td>
                    <td><?= h($r['name'] ?? '-') ?></td>
                    <td><?= h($r['ip'] ?? '-') ?></td>
                    <td><?= h($r['phone'] ?? '-') ?></td>
                    <td><?= $isOnline ? '<span class="badge on">Online</span>' : '<span class="badge off">Offline</span>' ?></td>
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

// Klik baris → detail pelanggan
document.querySelectorAll('tr.clickable').forEach(function(tr){
  tr.addEventListener('click', function(){
    var url = tr.getAttribute('data-href');
    if(url) location.href = url;
  });
});
</script>
</body>
</html>