<?php
// reports.php — Daftar Job Teknisi dengan Modern Date Picker (fix overflow + pretty date + equal buttons)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$tech = (int)($_GET['tech'] ?? 0);

// Label periode (Indonesia)
function bulan_id($n){
  $arr=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $n=(int)$n; return $arr[$n-1]??'';
}
function date_id_long($ymd){
  try{ $d=new DateTime($ymd);
    return (int)$d->format('j').' '.bulan_id($d->format('n')).' '.$d->format('Y');
  }catch(Throwable $e){ return $ymd; }
}

$period_label = '';
try{
  $df = new DateTime($from);
  $dt = new DateTime($to);
  if ($df->format('Y-m') === $dt->format('Y-m')) {
    $period_label = bulan_id($df->format('n')).' '.$df->format('Y');
  } else {
    $period_label = $df->format('j').' '.bulan_id($df->format('n')).' '.$df->format('Y')
                  .' – '
                  .$dt->format('j').' '.bulan_id($dt->format('n')).' '.$dt->format('Y');
  }
} catch(Throwable $e){ $period_label = ''; }

/* Gunakan DATE() agar cocok untuk kolom DATE/DATETIME/VARCHAR */
$where = ["DATE(k.job_date) BETWEEN ? AND ?"];
$params = [$from, $to];
if ($tech > 0) { $where[] = "k.technician_id = ?"; $params[] = $tech; }
$whereSql = 'WHERE '.implode(' AND ', $where);

/* Data utama */
$st = $pdo->prepare("SELECT k.id, k.job_date, k.fee_amount, k.description, k.technician_id,
                            t.username AS technician_name
                     FROM kinerja_teknisi k
                     LEFT JOIN technicians t ON t.id = k.technician_id
                     $whereSql
                     ORDER BY k.job_date DESC, k.id DESC
                     LIMIT 200");
$st->execute($params); $rows = $st->fetchAll();

/* Dropdown teknisi & total fee */
$techs = $pdo->query("SELECT id, username FROM technicians ORDER BY username ASC")->fetchAll();
$st2 = $pdo->prepare("SELECT COALESCE(SUM(k.fee_amount),0) FROM kinerja_teknisi k $whereSql");
$st2->execute($params); $total_fee = $st2->fetchColumn();

/* Fallback kalau kosong */
$empty_note = '';
if (!$rows) {
  $empty_note = 'Tidak ada data di rentang filter. Menampilkan 50 job terbaru.';
  $st = $pdo->query("SELECT k.id, k.job_date, k.fee_amount, k.description, k.technician_id,
                            t.username AS technician_name
                     FROM kinerja_teknisi k
                     LEFT JOIN technicians t ON t.id = k.technician_id
                     ORDER BY k.job_date DESC, k.id DESC
                     LIMIT 50");
  $rows = $st->fetchAll();
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Job Teknisi — AdamWifi Admin</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

*{margin:0;padding:0;box-sizing:border-box}

:root{
  --primary:#fbbf24; --primary-light:#fde047; --primary-dark:#f59e0b;
  --secondary:#06b6d4; --success:#10b981; --danger:#ef4444; --warning:#f97316;
  --dark:#0f172a; --dark-light:#1e293b; --gray:#64748b; --light:#f8fafc; --white:#ffffff;
  --gradient:linear-gradient(135deg,#fbbf24 0%,#f59e0b 50%,#dc2626 100%);
  --gradient-soft:linear-gradient(135deg,rgba(251,191,36,0.1) 0%,rgba(245,158,11,0.05) 100%);
  --shadow-sm:0 2px 4px rgba(0,0,0,0.05); --shadow-md:0 4px 12px rgba(0,0,0,0.1);
  --shadow-lg:0 8px 24px rgba(0,0,0,0.15); --shadow-xl:0 12px 48px rgba(0,0,0,0.2);
}

body{
  font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;
  background:var(--dark); color:var(--light); min-height:100vh; line-height:1.6;
  position:relative; overflow-x:hidden;
}

/* Animated background */
body::before{content:'';position:fixed;inset:0;
  background:
   radial-gradient(circle at 20% 50%,rgba(251,191,36,0.15) 0%,transparent 50%),
   radial-gradient(circle at 80% 80%,rgba(245,158,11,0.1) 0%,transparent 50%),
   radial-gradient(circle at 40% 20%,rgba(6,182,212,0.08) 0%,transparent 50%);
  z-index:-1; animation:bgMove 20s ease-in-out infinite;
}
@keyframes bgMove{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(-20px,-20px) scale(1.05);}66%{transform:translate(20px,-10px) scale(0.95);}}

body::after{content:'';position:fixed;inset:0;
  background-image:linear-gradient(rgba(251,191,36,0.03) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(251,191,36,0.03) 1px,transparent 1px);
  background-size:50px 50px; z-index:-1; animation:gridMove 10s linear infinite; pointer-events:none;
}
@keyframes gridMove{0%{transform:translate(0,0);}100%{transform:translate(50px,50px);}}

/* Layout */
.wrap{position:relative;z-index:1;max-width:1400px;margin:0 auto;min-height:100vh}

/* Topbar */
.topbar{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 20px;background:rgba(15,23,42,0.95);backdrop-filter:blur(20px);border-bottom:1px solid rgba(251,191,36,0.2);box-shadow:0 4px 24px rgba(0,0,0,0.3);animation:slideDown .5s ease-out}
@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
.topbar-content{max-width:1400px;margin:0 auto;width:100%;display:flex;justify-content:space-between;align-items:center}
.topbar .title{font-size:24px;font-weight:800;background:linear-gradient(135deg,var(--primary) 0%,var(--warning) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;display:flex;align-items:center;gap:12px}
.topbar .title::before{content:'📈';font-size:28px;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.1);opacity:.8}}
.topbar .actions{display:flex;gap:12px;align-items:center}

/* Main */
.main{padding:24px;padding-top:90px}

/* Buttons (samakan tinggi untuk <a> & <button>) */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  height:42px; padding:0 20px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:var(--dark); text-decoration:none; border-radius:12px; font-weight:700; font-size:14px;
  transition:all .3s ease; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(251,191,36,.3);
  position:relative; overflow:hidden; line-height:1;
}
.btn::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.3);border-radius:50%;transform:translate(-50%,-50%);transition:width .4s,height .4s}
.btn:hover::before{width:300px;height:300px}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(251,191,36,.5)}
.btn:active{transform:translateY(0)}
.btn.secondary{background:rgba(30,41,59,.9);color:var(--light);border:1px solid rgba(251,191,36,.18);box-shadow:0 4px 12px rgba(0,0,0,.2)}
.btn.danger{background:linear-gradient(135deg,var(--danger),#dc2626);color:var(--white);box-shadow:0 4px 12px rgba(239,68,68,.3)}

/* Flash */
.info{background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(6,182,212,.1));border:1px solid var(--success);color:var(--success);padding:16px 24px;border-radius:16px;margin:0 0 20px;display:flex;align-items:center;gap:12px;animation:slideIn .5s ease-out;box-shadow:0 4px 12px rgba(16,185,129,.2);position:relative}
.info::before{content:'✓';width:24px;height:24px;background:var(--success);color:var(--white);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold}
.info .close{position:absolute;top:10px;right:12px;border:0;background:transparent;color:inherit;font-weight:800;font-size:16px;cursor:pointer;opacity:.8}
.info .close:hover{opacity:1}
@keyframes slideIn{from{transform:translateX(-100%);opacity:0}to{transform:translateX(0);opacity:1}}

/* Cards */
.card{background:linear-gradient(135deg,rgba(30,41,59,.9),rgba(15,23,42,.9));border:1px solid rgba(251,191,36,.1);border-radius:20px;padding:24px;position:relative;transition:all .3s ease;backdrop-filter:blur(10px);animation:fadeInUp .6s ease-out;margin-bottom:20px;box-shadow:0 8px 24px rgba(0,0,0,.15);overflow:hidden; /* NEW: default layer */ z-index:0;}
.card:nth-of-type(1){animation-delay:.1s}
.card:nth-of-type(2){animation-delay:.2s}
/* IZINKAN overflow untuk kartu filter agar date-picker tidak kepotong + layer atas */
.card.card-filter{overflow:visible; /* NEW */ z-index:999; }

@keyframes fadeInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--gradient);transform:scaleX(0);transform-origin:left;transition:transform .3s ease}
.card:hover::before{transform:scaleX(1)}

/* Filter */
.filter{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.filter-row{display:flex;gap:12px;align-items:flex-end;flex:1}
.filter .field{display:flex;flex-direction:column}
/* NEW: Lebar input tanggal diperpanjang */
.filter .field.date-field{width:230px}
.filter .field.select-field{width:180px}
.filter label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);margin:0 0 8px 2px}

/* Date input wrapper */
.date-input-wrapper{position:relative;width:100%}
.date-input-wrapper input{
  width:100%;height:42px;padding:0 40px 0 12px;border-radius:10px;border:1px solid rgba(251,191,36,.18);
  background:rgba(30,41,59,.5);color:var(--light);outline:none;font:inherit;font-size:14px;transition:all .3s ease;cursor:pointer
}
.date-input-wrapper .calendar-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--primary);opacity:.7;transition:opacity .3s}
.date-input-wrapper input:hover + .calendar-icon,
.date-input-wrapper input:focus + .calendar-icon{opacity:1}
.date-input-wrapper input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(251,191,36,.1)}

/* Date picker modal */
.date-picker-modal{
  display:none;position:absolute;top:calc(100% + 8px);left:0;/* NEW: naikkan z-index tinggi */
  z-index:9999;
  background:linear-gradient(135deg,rgba(30,41,59,.98),rgba(15,23,42,.98));
  border:1px solid rgba(251,191,36,.2);border-radius:16px;padding:16px;box-shadow:0 12px 48px rgba(0,0,0,.4);
  backdrop-filter:blur(20px);animation:fadeInScale .3s ease-out;min-width:360px  /* diperlebar agar "September" muat */
}
.date-picker-modal.active{display:block}
@keyframes fadeInScale{from{opacity:0;transform:scale(.95) translateY(-10px)}to{opacity:1;transform:scale(1) translateY(0)}}

.date-picker-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid rgba(251,191,36,.1);flex-wrap:wrap}
.date-picker-nav{display:flex;gap:8px}
.date-picker-nav button{width:32px;height:32px;border:none;background:rgba(251,191,36,.1);color:var(--primary);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .3s;font-size:16px}
.date-picker-nav button:hover{background:rgba(251,191,36,.2);transform:scale(1.1)}

.month-year-select{display:flex;gap:8px}
.month-year-select select{
  background: rgba(30,41,59,.5);
  color: var(--light);
  border: 1px solid rgba(251,191,36,.18);
  height: 32px;            /* sama dengan tombol ‹ › */
  line-height: 32px;
  padding: 0 10px;         /* lebih ramping */
  border-radius: 8px;
  font-size: 13px;         /* font lebih kecil */
  font-weight: 600;
  cursor: pointer;
  outline: none;
  transition: all .3s;
}

}
.month-year-select select:hover,.month-year-select select:focus{border-color:var(--primary);background:rgba(251,191,36,.05)}
/* pastikan bulan tidak terpotong */
.month-year-select .month-select{min-width:130px}

.month-year-select .year-select{min-width:90px}
/* NEW: paksa dropdown native gelap (browser yg mendukung) */
.month-year-select select option,
select option { background-color:#0f172a; color:#f8fafc; }
.month-year-select select option:checked { background:rgba(251,191,36,.25); color:#f8fafc; }

.date-picker-days{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.day-header{text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gray);padding:8px 0;opacity:.6}
.day-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:10px;cursor:pointer;transition:all .3s;font-size:14px;font-weight:500;color:var(--light);background:transparent;border:1px solid transparent}
.day-cell:hover:not(.disabled):not(.selected){background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.3);transform:scale(1.1)}
.day-cell.selected{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:var(--dark);font-weight:700;box-shadow:0 4px 12px rgba(251,191,36,.3)}
.day-cell.today:not(.selected){background:rgba(6,182,212,.1);border-color:var(--secondary);color:var(--secondary)}
.day-cell.disabled{opacity:.3;cursor:not-allowed}
.day-cell.other-month{opacity:.4}

.date-picker-footer{margin-top:16px;padding-top:12px;border-top:1px solid rgba(251,191,36,.1);display:flex;justify-content:space-between;gap:8px}
.date-picker-footer button{flex:1;padding:8px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
.date-picker-footer .btn-cancel{background:rgba(100,116,139,.1);color:var(--gray);border:1px solid rgba(100,116,139,.2)}
.date-picker-footer .btn-confirm{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:var(--dark);box-shadow:0 4px 12px rgba(251,191,36,.3)}
.date-picker-footer button:hover{transform:translateY(-2px)}

/* Select */
select{width:100%;height:42px;padding:0 12px;border-radius:10px;border:1px solid rgba(251,191,36,.18);background:rgba(30,41,59,.5);color:var(--light);outline:none;font:inherit;font-size:14px;transition:all .3s;cursor:pointer}
select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(251,191,36,.1)}

/* Total Fee */
.total-fee-section{width:100%;margin-top:16px;padding-top:16px;border-top:1px solid rgba(251,191,36,.1);display:flex;justify-content:space-between;align-items:center}
.period-info{display:flex;align-items:center;gap:24px}
.period-label{font-weight:700;font-size:18px;color:var(--light)}
.period-total{font-size:16px;color:var(--gray)}
.period-total b{color:var(--primary);font-size:20px}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid rgba(251,191,36,.1)}
.badge{padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:linear-gradient(135deg,rgba(251,191,36,.1),rgba(245,158,11,.05));color:var(--primary);border:1px solid rgba(251,191,36,.3);display:inline-flex;align-items:center;gap:6px}

/* Table */
.table-wrap{overflow-x:auto}
.tbl{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}
.tbl thead{background:linear-gradient(135deg,rgba(251,191,36,.05),transparent)}
.tbl th{padding:12px 16px;text-align:left;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);border-bottom:2px solid rgba(251,191,36,.1)}
.tbl tbody tr{transition:all .2s ease;border-bottom:1px solid rgba(100,116,139,.1)}
.tbl tbody tr:hover{
  background: rgba(251,191,36,.03);
  transform: none;                      /* <— ini yang bikin scrollbar */
  box-shadow: inset 4px 0 rgba(251,191,36,.15); /* aksen kiri biar tetap “hidup” */
}

.tbl td{padding:14px 16px;font-size:14px;color:var(--light);border-bottom:1px solid rgba(100,116,139,.05)}
.tbl tbody tr:last-child td{border-bottom:none}
.tbl input[type="checkbox"]{width:18px;height:18px;cursor:pointer}
.row-actions{display:flex;gap:8px;align-items:center}
.inline{display:inline}

/* Responsive */
@media (max-width:960px){
  .filter{flex-direction:column}
  .filter-row{width:100%}
  .filter .field.date-field,.filter .field.select-field{flex:1;width:auto}
}
@media (max-width:768px){
  .topbar{flex-direction:column;align-items:flex-start}
  .main{padding:16px;padding-top:120px}
  .filter-row{flex-direction:column}
  .filter .field.date-field,.filter .field.select-field{width:100%}
  .card{padding:16px}
  .tbl{font-size:12px}
  .tbl th,.tbl td{padding:8px}
  .total-fee-section{flex-direction:column;align-items:flex-start;gap:12px}
  .period-info{flex-direction:column;align-items:flex-start;gap:8px}
}
.row-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end}


</style>
</head>
<body>
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-content">
      <div class="title">Job Teknisi</div>
      <div class="actions">
        <a class="btn secondary" href="index.php">← Kembali</a>
        <a class="btn" href="report_new.php">+ Tambah Job</a>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="main">
      <?php if($flash): ?>
        <div class="info" id="flash">
          <?=h($flash)?>
          <button id="flashClose" class="close" aria-label="Tutup">×</button>
        </div>
        <script>
        (function(){
          var f=document.getElementById('flash'), b=document.getElementById('flashClose');
          function hide(){ if(f){ f.style.opacity='0'; f.style.transform='translateY(-4px)'; setTimeout(function(){ if(f&&f.parentNode){ f.parentNode.removeChild(f);} }, 260); } }
          if(b){ b.addEventListener('click', hide); }
          setTimeout(hide, 6000);
        })();
        </script>
      <?php endif; ?>

      <!-- Filter Card -->
      <div class="card card-filter">
        <form method="get" class="filter" id="filterForm">
          <div class="filter-row">
            <div class="field date-field">
              <label>Dari Tanggal</label>
              <div class="date-input-wrapper">
                <!-- hidden untuk submit (ISO) + visible untuk UI -->
                <input type="hidden" id="dateFrom" name="from" value="<?=h($from)?>">
                <input type="text" id="dateFromVis" value="<?=h(date_id_long($from))?>" readonly>
                <span class="calendar-icon">📅</span>
                <div class="date-picker-modal" id="datePickerFrom"></div>
              </div>
            </div>

            <div class="field date-field">
              <label>Sampai Tanggal</label>
              <div class="date-input-wrapper">
                <input type="hidden" id="dateTo" name="to" value="<?=h($to)?>">
                <input type="text" id="dateToVis" value="<?=h(date_id_long($to))?>" readonly>
                <span class="calendar-icon">📅</span>
                <div class="date-picker-modal" id="datePickerTo"></div>
              </div>
            </div>

            <div class="field select-field">
              <label>Teknisi</label>
              <select name="tech">
                <option value="0">Semua teknisi</option>
                <?php foreach($techs as $t): ?>
                  <option value="<?=$t['id']?>" <?= $tech===(int)$t['id']?'selected':'' ?>><?=h($t['username'])?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn">Terapkan</button>
            <a class="btn secondary" href="reports.php?from=<?=date('Y-m-01')?>&to=<?=date('Y-m-d')?>&tech=0">Reset</a>
          </div>

          <div class="total-fee-section">
            <div class="period-info">
              <div class="period-label"><?= $period_label ? h($period_label) : 'Periode' ?></div>
              <div class="period-total">Total fee: <b>Rp <?=number_format((float)$total_fee, 0, ',', '.')?></b></div>
            </div>
          </div>
        </form>
      </div>

      <!-- Table Card -->
      <div class="card">
        <form method="post" action="job_bulk.php" id="bulkForm" class="toolbar">
          <?php csrf_field(); ?>
          <input type="hidden" name="from" value="<?=h($from)?>">
          <input type="hidden" name="to" value="<?=h($to)?>">
          <input type="hidden" name="tech" value="<?=h($tech)?>">
          <button class="btn danger" id="bulkDelete" type="submit" name="action" value="delete">Hapus Terpilih</button>
          <span class="badge" id="selCount">0 dipilih</span>
        </form>

        <div class="table-wrap">
          <table class="tbl" id="jobsTable">
  <!-- Lebar kolom dikunci di colgroup (thead tidak set width lagi) -->
  <colgroup>
  <col style="width:42px">          <!-- checkbox -->
  <col style="width:170px">         <!-- Tanggal -->
  <col style="width:140px">         <!-- Teknisi -->
  <col style="min-width:200px">          <!-- Keterangan = sisa ruang -->
  <col style="width:120px">         <!-- Fee -->
  <col style="width:160px">         <!-- Aksi -->
</colgroup>

  <thead>
    <tr>
      <th><input type="checkbox" id="checkAll" aria-label="Pilih semua"></th>
      <th>Tanggal</th>
      <th>Teknisi</th>
      <th style="padding-right:6px">Keterangan</th>
<th style="text-align:right;padding-left:0">Fee (Rp)</th>

      <th>Aksi</th>
    </tr>
  </thead>

  <tbody>
    <?php if ($empty_note): ?>
      <tr><td colspan="6" style="opacity:.8;text-align:center;padding:24px"><?=h($empty_note)?></td></tr>
    <?php endif; ?>
    <?php if (!$rows): ?>
      <tr>
        <td colspan="6" style="text-align:center;padding:40px;color:#9aa9c0">Belum ada job teknisi</td>
      </tr>
    <?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
        <td><input type="checkbox" class="rowCheck" value="<?=$r['id']?>"></td>
        <td style="white-space:nowrap">
  <?=h(date_id_long(is_string($r['job_date']) ? substr($r['job_date'],0,10) : $r['job_date']))?>
</td>

        <td><?=h($r['technician_name'] ?? '-')?></td>
        <td style="padding-right:6px"><?=h($r['description'] ?? '')?></td>
<td style="text-align:right;padding-left:0">
  <?=number_format((float)$r['fee_amount'], 0, ',', '.')?>
</td>

        <td class="row-actions">
          <a class="btn secondary" href="report_edit.php?id=<?=$r['id']?>" style="padding:6px 12px;font-size:13px">Edit</a>
          <form class="inline" method="post" action="job_delete.php" onsubmit="return confirm('Hapus job ini?');">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" value="<?=$r['id']?>">
            <input type="hidden" name="back_from" value="<?=h($from)?>">
            <input type="hidden" name="back_to" value="<?=h($to)?>">
            <input type="hidden" name="back_tech" value="<?=h($tech)?>">
            <button class="btn danger" type="submit" style="padding:6px 12px;font-size:13px">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>


        </div>
      </div>
    </div>
  </div>

<script>
// ==== Modern Date Picker (visible pretty text + hidden ISO) ====
class ModernDatePicker {
  constructor(displayInputId, hiddenInputId, modalId) {
    this.displayInput = document.getElementById(displayInputId);
    this.hiddenInput  = document.getElementById(hiddenInputId);
    this.modal = document.getElementById(modalId);

    // sumber kebenaran: hidden ISO
    this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : new Date();
    this.currentMonth = new Date(this.selectedDate);
    this.tempDate = new Date(this.selectedDate);

    this.monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    this.dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

    // pastikan display terisi format cantik saat awal
    this.displayInput.value = this.formatPretty(this.selectedDate);

    this.init();
  }

  init() {
    this.renderPicker();

    // Buka picker saat klik input
    this.displayInput.addEventListener('click', (e) => {
      e.stopPropagation();
      this.show();
    });

    // Klik di luar -> tutup
    document.addEventListener('click', (e) => {
      if (!this.modal.contains(e.target) && e.target !== this.displayInput) {
        this.hide();
      }
    });
  }

  renderPicker() {
    this.modal.innerHTML = `
      <div class="date-picker-header">
        <div class="date-picker-nav">
          <button type="button" class="prev-month">‹</button>
          <button type="button" class="next-month">›</button>
        </div>
        <div class="month-year-select">
          <select class="month-select">
            ${this.monthNames.map((m, i) =>
              `<option value="${i}" ${i === this.currentMonth.getMonth() ? 'selected' : ''}>${m}</option>`
            ).join('')}
          </select>
          <select class="year-select">
            ${Array.from({length: 10}, (_, i) => {
              const year = new Date().getFullYear() - 5 + i;
              return `<option value="${year}" ${year === this.currentMonth.getFullYear() ? 'selected' : ''}>${year}</option>`;
            }).join('')}
          </select>
        </div>
      </div>
      <div class="date-picker-days">
        ${this.dayNames.map(d => `<div class="day-header">${d}</div>`).join('')}
        ${this.generateDays()}
      </div>
      <div class="date-picker-footer">
        <button type="button" class="btn-cancel">Batal</button>
        <button type="button" class="btn-confirm">Pilih</button>
      </div>
    `;
    this.attachEvents();
  }

  generateDays() {
    const year = this.currentMonth.getFullYear();
    const month = this.currentMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const prevLastDay = new Date(year, month, 0);
    const startDay = firstDay.getDay();
    const today = new Date();

    let days = [];

    // hari dari bulan sebelumnya (disabled)
    for (let i = startDay - 1; i >= 0; i--) {
      const day = prevLastDay.getDate() - i;
      days.push(`<div class="day-cell other-month disabled">${day}</div>`);
    }

    // hari bulan berjalan
    for (let day = 1; day <= lastDay.getDate(); day++) {
      const date = new Date(year, month, day);
      const isToday = date.toDateString() === today.toDateString();
      const isSelected = date.toDateString() === this.tempDate.toDateString();

      let classes = ['day-cell'];
      if (isToday) classes.push('today');
      if (isSelected) classes.push('selected');

      days.push(`<div class="${classes.join(' ')}" data-date="${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}">${day}</div>`);
    }

    // sisa sel agar 6 baris penuh
    const remainingCells = 42 - days.length;
    for (let day = 1; day <= remainingCells; day++) {
      days.push(`<div class="day-cell other-month disabled">${day}</div>`);
    }

    return days.join('');
  }

  attachEvents() {
    // navigasi bulan
    this.modal.querySelector('.prev-month').addEventListener('click', (e) => {
      e.stopPropagation();
      this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
      this.renderPicker();
    });
    this.modal.querySelector('.next-month').addEventListener('click', (e) => {
      e.stopPropagation();
      this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
      this.renderPicker();
    });

    // Month/Year select
    this.modal.querySelector('.month-select').addEventListener('change', (e) => {
      this.currentMonth.setMonth(parseInt(e.target.value));
      this.renderPicker();
    });

    this.modal.querySelector('.year-select').addEventListener('change', (e) => {
      this.currentMonth.setFullYear(parseInt(e.target.value));
      this.renderPicker();
    });

    // pilih hari
    this.modal.querySelectorAll('.day-cell:not(.disabled)').forEach(day => {
      day.addEventListener('click', (e) => {
        e.stopPropagation();
        const date = e.target.dataset.date;
        if (date) {
          this.tempDate = new Date(date);
          this.renderPicker();
        }
      });
    });

    // tombol
    this.modal.querySelector('.btn-cancel').addEventListener('click', (e) => {
      e.stopPropagation();
      this.hide();
    });
    this.modal.querySelector('.btn-confirm').addEventListener('click', (e) => {
      e.stopPropagation();
      this.selectedDate = new Date(this.tempDate);
      // update hidden (ISO) + visible (pretty)
      this.hiddenInput.value = this.formatISO(this.selectedDate);
      this.displayInput.value = this.formatPretty(this.selectedDate);
      this.hide();
    });
  }

  formatISO(date){
    const y = date.getFullYear();
    const m = String(date.getMonth()+1).padStart(2,'0');
    const d = String(date.getDate()).padStart(2,'0');
    return `${y}-${m}-${d}`;
  }
  formatPretty(date){
    const y = date.getFullYear();
    const m = this.monthNames[date.getMonth()].toLowerCase(); // “september”
    const d = date.getDate();
    return `${d} ${m} ${y}`;
  }

  show(){
    // tutup picker lain
    document.querySelectorAll('.date-picker-modal.active').forEach(m => { if (m !== this.modal) m.classList.remove('active'); });
    // sinkronkan dari hidden ISO
    this.tempDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : new Date();
    this.currentMonth = new Date(this.tempDate);
    this.renderPicker();
    this.modal.classList.add('active');
  }
  hide(){ this.modal.classList.remove('active'); }
}

// Inisialisasi
document.addEventListener('DOMContentLoaded', function(){
  new ModernDatePicker('dateFromVis','dateFrom','datePickerFrom');
  new ModernDatePicker('dateToVis','dateTo','datePickerTo');
});

// Animasi baris tabel (stagger)
document.addEventListener('DOMContentLoaded', function(){
  var rows = document.querySelectorAll('#jobsTable tbody tr');
  rows.forEach(function(tr, i){
    if (!tr.style.animation) {
      tr.style.animation = 'fadeInUp .35s ease-out both';
      tr.style.animationDelay = (0.03 * i + 0.12) + 's';
    }
  });
});

// Select all + counter + bulk submit
(function(){
  var checkAll = document.getElementById('checkAll');
  var bulkBtn  = document.getElementById('bulkDelete');
  var selCount = document.getElementById('selCount');
  var bulkForm = document.getElementById('bulkForm');

  function checks(){ return Array.prototype.slice.call(document.querySelectorAll('.rowCheck')); }
  function selected(){ return Array.prototype.slice.call(document.querySelectorAll('.rowCheck:checked')); }

  function updateUI(){
    var all = checks(), sel = selected();
    if (all.length){
      checkAll.indeterminate = sel.length>0 && sel.length<all.length;
      checkAll.checked = sel.length===all.length;
    }
    if (selCount) selCount.textContent = sel.length + ' dipilih';
    if (bulkBtn) bulkBtn.disabled = false;
  }

  if (checkAll){
    checkAll.addEventListener('change', function(){ checks().forEach(function(c){ c.checked = checkAll.checked; }); updateUI(); });
  }
  checks().forEach(function(c){ c.addEventListener('change', updateUI); });
  updateUI();

  if (bulkForm){
    bulkForm.addEventListener('submit', function(ev){
      Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="ids[]"]')).forEach(function(i){ i.remove(); });
      var sel = selected();
      if (sel.length === 0) { return; }
      if (!confirm('Hapus semua job yang dipilih?')) { ev.preventDefault(); return; }
      sel.forEach(function(c){
        var i=document.createElement('input');
        i.type='hidden'; i.name='ids[]'; i.value=c.value;
        bulkForm.appendChild(i);
      });
    });
  }
})();
</script>
</body>
</html>
