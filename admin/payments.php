<?php
// payments.php — Unpaid / Paid / Penanggung Jawab + aksi ubah status & reset
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function ym_valid($s){ return (bool)preg_match('/^\d{4}-\d{2}$/', $s); }
function ym_label_id($ym){
  if(!ym_valid($ym)) return $ym;
  [$y,$m] = explode('-', $ym);
  $bln = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
  return ($bln[$m] ?? $m).' '.$y;
}
function prev_period(){ return date('Y-m', strtotime('first day of last month')); }
function qs(array $merge){ $q=array_merge($_GET,$merge); foreach($q as $k=>$v){ if($v===null) unset($q[$k]); } return http_build_query($q); }

$tab    = $_GET['tab'] ?? 'unpaid'; // unpaid|paid|collectors
$tech   = trim($_GET['tech'] ?? '');
$period = (isset($_GET['period']) && ym_valid($_GET['period'])) ? $_GET['period'] : prev_period();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$paid_rows = []; $unpaid_rows = []; $collectors = [];
try {
  // Paid list (ikutkan customer_id utk aksi)
  $sqlPaid = "SELECT p.customer_id, c.name,
                     COALESCE(c.whatsapp_number, c.phone) AS phone,
                     COALESCE(p.amount,0) AS amount,
                     DATE_FORMAT(p.paid_at,'%Y-%m-%d %H:%i:%s') AS paid_at,
                     COALESCE(p.paid_by,'(tanpa nama)') AS technician
              FROM payments p
              JOIN customers c ON c.id = p.customer_id
              WHERE p.period = :prd AND p.paid_at IS NOT NULL";
  $par = [':prd'=>$period];
  if ($tech !== '') { $sqlPaid .= " AND COALESCE(p.paid_by,'') = :tch"; $par[':tch']=$tech; }
  $sqlPaid .= " ORDER BY p.paid_at DESC";
  $st = $pdo->prepare($sqlPaid); $st->execute($par); $paid_rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Unpaid list
  $sqlUnpaid = "SELECT c.id, c.name, COALESCE(c.whatsapp_number, c.phone) AS phone
                FROM customers c
                WHERE c.billable=1 AND COALESCE(c.active,1)=1
                  AND NOT EXISTS (
                    SELECT 1 FROM payments p
                    WHERE p.customer_id = c.id AND p.period = :prd AND p.paid_at IS NOT NULL
                  )
                ORDER BY c.name ASC";
  $st2 = $pdo->prepare($sqlUnpaid); $st2->execute([':prd'=>$period]); $unpaid_rows = $st2->fetchAll(PDO::FETCH_ASSOC);

  // Collectors (penanggung jawab)
  $sqlCol = "SELECT COALESCE(p.paid_by,'(tanpa nama)') AS technician,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(p.amount),0) AS total
             FROM payments p
             WHERE p.period = :prd AND p.paid_at IS NOT NULL
             GROUP BY technician
             ORDER BY technician ASC";
  $st3 = $pdo->prepare($sqlCol); $st3->execute([':prd'=>$period]); $collectors = $st3->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB error: ".h($e->getMessage()); exit;
}

$paid_count   = count($paid_rows);
$unpaid_count = count($unpaid_rows);
$total_count  = $paid_count + $unpaid_count;
$pct_paid     = $total_count ? round($paid_count / $total_count * 100) : 0;
$total_nominal = 0.0; foreach($paid_rows as $r){ $total_nominal += (float)$r['amount']; }

$admin_name = $_SESSION['admin_user']['username'] ?? 'admin'; // default PJ
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pembayaran — AdamWifi Admin</title>
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
  content: '💳';
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

.xbtn.danger {
  background: rgba(127,29,29,0.8);
  color: #fecaca;
  border: 1px solid rgba(239,68,68,0.45);
  cursor: pointer;
}

.xbtn.danger:hover {
  background: rgba(239,68,68,0.2);
  border-color: var(--danger);
  color: var(--danger);
}

/* Flash Message */
.info {
  background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(6,182,212,0.1));
  border: 1px solid var(--success);
  color: var(--success);
  padding: 16px 24px;
  border-radius: 16px;
  margin: 0 0 20px 0;
  display: flex;
  align-items: center;
  gap: 12px;
  animation: slideIn 0.5s ease-out;
  box-shadow: 0 4px 12px rgba(16,185,129,0.2);
  position: relative;
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

/* Toolbar */
.toolbar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(251,191,36,0.1);
}

.toolbar form {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.toolbar label {
  display: flex;
  gap: 8px;
  align-items: center;
  font-weight: 700;
  color: var(--gray);
  font-size: 14px;
}

.month {
  appearance: none;
  height: 42px;
  padding: 0 12px;
  border-radius: 12px;
  border: 1px solid rgba(251,191,36,0.18);
  background: rgba(30,41,59,0.5);
  color: var(--light);
  font-weight: 700;
  font-family: inherit;
  outline: none;
  transition: all 0.3s ease;
}

.month:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(251,191,36,0.1);
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

/* Chips ringkasan dengan animasi */
.chips {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.chip {
  background: var(--gradient-soft);
  border: 1px solid rgba(251,191,36,0.25);
  padding: 8px 12px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 13px;
  display: inline-flex;
  gap: 8px;
  align-items: center;
  transition: all 0.3s ease;
  animation: fadeInUp 0.5s ease-out backwards;
}

.chip:nth-child(1) { animation-delay: 0.1s; }
.chip:nth-child(2) { animation-delay: 0.2s; }
.chip:nth-child(3) { animation-delay: 0.3s; }

.chip:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(251,191,36,0.2);
}

/* Progress bar dengan animasi */
.progress {
  width: 100%;
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(100,116,139,0.25);
  border: 1px solid rgba(100,116,139,0.2);
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.25);
  margin: 12px 0;
}

.progress > span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--success), #34d399);
  box-shadow: 0 0 16px rgba(16,185,129,0.25);
  transition: width 0.8s ease;
  animation: progressGlow 2s ease-in-out infinite alternate;
}

@keyframes progressGlow {
  0% { box-shadow: 0 0 8px rgba(16,185,129,0.3); }
  100% { box-shadow: 0 0 16px rgba(16,185,129,0.6); }
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

.tbl td:last-child {
  white-space: nowrap;
}

/* Inline forms */
.inline-form {
  display: inline;
}

/* Collectors grid */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
  margin-top: 8px;
}

.collector-card {
  text-decoration: none;
  color: inherit;
  display: block;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid rgba(251,191,36,0.18);
  background: var(--gradient-soft);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  animation: fadeInUp 0.5s ease-out backwards;
}

.collector-card:nth-child(1) { animation-delay: 0.1s; }
.collector-card:nth-child(2) { animation-delay: 0.2s; }
.collector-card:nth-child(3) { animation-delay: 0.3s; }

.collector-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(251,191,36,0.1), transparent);
  transition: left 0.5s ease;
}

.collector-card:hover::before {
  left: 100%;
}

.collector-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(251,191,36,0.2);
  border-color: var(--primary);
}

.collector-name {
  font-weight: 800;
  font-size: 16px;
  margin-bottom: 8px;
}

.collector-meta {
  opacity: 0.9;
  margin-top: 6px;
  font-size: 14px;
}

.collector-link {
  margin-top: 12px;
  font-weight: 700;
  color: var(--primary);
  font-size: 14px;
}

/* Responsive */
@media (max-width: 960px) {
  .toolbar {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .toolbar form {
    width: 100%;
    justify-content: space-between;
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
  
  .chips {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .tbl {
    font-size: 12px;
  }
  
  .tbl th, .tbl td {
    padding: 8px;
  }
  
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
  <!-- Topbar di luar wrap -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="title">Pembayaran</div>
      <div class="actions">
        <a class="btn secondary" href="index.php">← Kembali ke Dashboard</a>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="main">
      <?php if($flash): ?>
        <div class="info" id="flash">
          <?=h($flash)?>
        </div>
      <?php endif; ?>

      <div class="card">
        <!-- Toolbar di dalam kartu: Periode + Terapkan + Reset -->
        <div class="toolbar">
          <form method="get">
            <input type="hidden" name="tab" value="<?=h($tab)?>">
            <?php if($tech!==''): ?><input type="hidden" name="tech" value="<?=h($tech)?>"><?php endif; ?>
            <label>
              Periode
              <input class="month" type="month" name="period" value="<?=h($period)?>">
            </label>
            <button class="btn" type="submit">Terapkan</button>
          </form>

          <form method="post" action="payments_action.php"
                onsubmit="return confirm('Reset semua status periode <?=h(ym_label_id($period))?> ke BELUM BAYAR?');"
                style="margin-left:auto">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="reset_period">
            <input type="hidden" name="tab" value="<?=h($tab)?>">
            <input type="hidden" name="period" value="<?=h($period)?>">
            <?php if($tech!==''): ?><input type="hidden" name="tech" value="<?=h($tech)?>"><?php endif; ?>
            <button class="xbtn danger" type="submit">Reset Semua (Periode Ini)</button>
          </form>
        </div>

        <!-- Tabs -->
        <div class="tabbar">
          <a class="tab <?= $tab==='unpaid'?'active':'' ?>" href="?<?=qs(['tab'=>'unpaid','tech'=>null])?>">
            Belum Dibayar <span class="badge"><?=number_format($unpaid_count)?></span>
          </a>
          <a class="tab <?= $tab==='paid'?'active':'' ?>" href="?<?=qs(['tab'=>'paid'])?>">
            Sudah Dibayar <span class="badge"><?=number_format($paid_count)?></span>
          </a>
          <a class="tab <?= $tab==='collectors'?'active':'' ?>" href="?<?=qs(['tab'=>'collectors','tech'=>null])?>">
            Penanggung Jawab
          </a>
        </div>

        <!-- Chips ringkasan -->
        <div class="chips">
          <span class="chip">✅ Lunas: <b><?=number_format($paid_count)?></b></span>
          <span class="chip">⏳ Pending: <b><?=number_format($unpaid_count)?></b></span>
          <span class="chip">💰 Total nominal: <b>Rp <?=number_format((float)$total_nominal,0,',','.')?></b></span>
        </div>
        <div class="progress" aria-label="Progress pembayaran">
          <span style="width: <?=$pct_paid?>%"></span>
        </div>
        <div style="font-size:12px;color:var(--gray);margin-bottom:16px">
          <?=$pct_paid?>% Lunas — Periode <?=h(ym_label_id($period))?>
        </div>

        <?php if ($tab === 'unpaid'): ?>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr>
                <th>Nama</th><th>WhatsApp</th><th>Aksi</th>
              </tr></thead>
              <tbody>
              <?php if(!$unpaid_rows): ?>
                <tr>
                  <td colspan="3">
                    <div style="text-align:center;padding:40px">
                      <div style="font-size:48px;margin-bottom:16px;opacity:0.3">✅</div>
                      <div style="color:var(--gray);font-size:16px">Semua pelanggan sudah membayar</div>
                      <div style="color:var(--gray);font-size:14px;margin-top:8px">Periode <?=h(ym_label_id($period))?></div>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
              <?php foreach($unpaid_rows as $i => $r): ?>
                <tr style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
                  <td><?=h($r['name'])?></td>
                  <td><?=h($r['phone'] ?? '-')?></td>
                  <td>
                    <form class="inline-form" method="post" action="payments_action.php"
                          onsubmit="return confirm('Tandai SUDAH BAYAR untuk <?=h($r['name'])?>?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="mark_paid">
                      <input type="hidden" name="tab" value="unpaid">
                      <input type="hidden" name="period" value="<?=h($period)?>">
                      <input type="hidden" name="customer_id" value="<?=$r['id']?>">
                      <input type="hidden" name="amount" value="0">
                      <input type="hidden" name="paid_by" value="<?=h($admin_name)?>">
                      <button class="btn" type="submit">Tandai Lunas</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        <?php elseif ($tab === 'paid'): ?>
          <?php if($tech!==''): ?>
            <div class="chips" style="margin-bottom:16px">
              <span class="chip">Teknisi: <b><?=h($tech)?></b></span>
              <a class="xbtn" href="?<?=qs(['tech'=>null])?>">Reset Filter</a>
            </div>
          <?php endif; ?>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr>
                <th>Waktu Bayar</th><th>Nama</th><th>Teknisi</th>
                <th style="text-align:right">Jumlah (Rp)</th><th>Aksi</th>
              </tr></thead>
              <tbody>
              <?php if(!$paid_rows): ?>
                <tr>
                  <td colspan="5">
                    <div style="text-align:center;padding:40px">
                      <div style="font-size:48px;margin-bottom:16px;opacity:0.3">💳</div>
                      <div style="color:var(--gray);font-size:16px">Belum ada pembayaran</div>
                      <div style="color:var(--gray);font-size:14px;margin-top:8px">Periode <?=h(ym_label_id($period))?></div>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
              <?php foreach($paid_rows as $i => $r): ?>
                <tr style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
                  <td><?=h($r['paid_at'] ?? '-')?></td>
                  <td><?=h($r['name'])?> <small style="opacity:.7">(<?=h($r['phone'] ?? '-')?>)</small></td>
                  <td><?=h($r['technician'])?></td>
                  <td style="text-align:right"><?=number_format((float)($r['amount'] ?? 0),0,',','.')?></td>
                  <td>
                    <form class="inline-form" method="post" action="payments_action.php"
                          onsubmit="return confirm('Batalkan pembayaran untuk <?=h($r['name'])?>?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="mark_unpaid">
                      <input type="hidden" name="tab" value="paid">
                      <input type="hidden" name="period" value="<?=h($period)?>">
                      <?php if($tech!==''): ?><input type="hidden" name="tech" value="<?=h($tech)?>"><?php endif; ?>
                      <input type="hidden" name="customer_id" value="<?=$r['customer_id']?>">
                      <button class="xbtn danger" type="submit">Jadi Belum</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        <?php elseif ($tab === 'collectors'): ?>
          <div class="grid">
            <?php if(!$collectors): ?>
              <div style="text-align:center;padding:40px;opacity:0.8">
                <div style="font-size:48px;margin-bottom:16px;opacity:0.3">👥</div>
                <div style="color:var(--gray);font-size:16px">Belum ada data penanggung jawab</div>
                <div style="color:var(--gray);font-size:14px;margin-top:8px">Periode <?=h(ym_label_id($period))?></div>
              </div>
            <?php endif; ?>
            <?php foreach($collectors as $i => $c): ?>
              <a class="collector-card" href="?<?=qs(['tab'=>'paid','tech'=>$c['technician']])?>" style="animation-delay: <?=0.1 + ($i * 0.1)?>s">
                <div class="collector-name"><?=h($c['technician'])?></div>
                <div><?=number_format((int)$c['cnt'])?> pelanggan</div>
                <div class="collector-meta">Total: <b>Rp <?=number_format((float)$c['total'],0,',','.')?></b></div>
                <div class="collector-link">→ Lihat daftar</div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<script>
// Auto-hide flash message setelah 6 detik
document.addEventListener('DOMContentLoaded', function(){
  setTimeout(function(){ 
    var f = document.getElementById('flash'); 
    if(f) {
      f.style.opacity = '0';
      f.style.transform = 'translateY(-10px)';
      setTimeout(function(){ f.style.display = 'none'; }, 300);
    }
  }, 6000);
  
  // Animasi stagger untuk baris tabel
  var rows = document.querySelectorAll('.tbl tbody tr');
  rows.forEach(function(tr, i){
    if (!tr.style.animation && !tr.querySelector('td[colspan]')) {
      tr.style.animation = 'fadeInUp .35s ease-out both';
      tr.style.animationDelay = (0.03 * i + 0.12) + 's';
    }
  });
});
</script>
</body>
</html>