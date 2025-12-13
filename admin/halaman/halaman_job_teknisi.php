<?php
// View Job Teknisi (partial, ikut layout index.php)
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$tech = (int)($_GET['tech'] ?? 0);

function bulan_id($n){
  $arr=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $n=(int)$n; return $arr[$n-1]??'';
}
function date_id_long($ymd){
  try{ $d=new DateTime($ymd);
    return (int)$d->format('j').' '.bulan_id($d->format('n')).' '.$d->format('Y');
  }catch(Throwable $e){ return $ymd; }
}

// Filter query
$where = ["DATE(k.job_date) BETWEEN ? AND ?"];
$params = [$from, $to];
if ($tech > 0) { $where[] = "k.technician_id = ?"; $params[] = $tech; }
$whereSql = 'WHERE '.implode(' AND ', $where);

// Data utama
$st = $pdo->prepare("SELECT k.id, k.job_date, k.fee_amount, k.description, k.technician_id,
                            t.username AS technician_name
                     FROM kinerja_teknisi k
                     LEFT JOIN technicians t ON t.id = k.technician_id
                     $whereSql
                     ORDER BY k.job_date DESC, k.id DESC
                     LIMIT 200");
$st->execute($params); $rows = $st->fetchAll();

// Dropdown teknisi & total fee
$techs = $pdo->query("SELECT id, username FROM technicians ORDER BY username ASC")->fetchAll();
$st2 = $pdo->prepare("SELECT COALESCE(SUM(k.fee_amount),0) FROM kinerja_teknisi k $whereSql");
$st2->execute($params); $total_fee = $st2->fetchColumn();

// Fallback kalau kosong
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

<div class="card table-card">
  <div class="table-header">Job Teknisi</div>

  <?php if ($flash): ?>
    <div class="info" role="status" aria-live="polite" style="margin-top:0">
      <?=h($flash)?>
      <button class="close" aria-label="Tutup">x</button>
    </div>
  <?php endif; ?>

  <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:8px 0 4px;">
    <a class="btn" href="halaman/tambah_job.php" style="height:42px; display:inline-flex; align-items:center; padding:0 16px;">Tambah Job</a>
  </div>

  <form class="filters" method="get" action="index.php" style="margin-top:12px">
    <input type="hidden" name="page" value="reports">
    <label style="color:var(--gray); font-size:13px;">Dari
      <input type="date" name="from" value="<?=h($from)?>" style="min-width:150px">
    </label>
    <label style="color:var(--gray); font-size:13px;">Sampai
      <input type="date" name="to" value="<?=h($to)?>" style="min-width:150px">
    </label>
    <select name="tech">
      <option value="0">Semua teknisi</option>
      <?php foreach($techs as $t): ?>
        <option value="<?=$t['id']?>" <?=$tech==($t['id']??0)?'selected':''?>><?=h($t['username']??'-')?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Terapkan</button>
    <a class="xbtn" href="index.php?page=reports">Reset</a>
  </form>

  <div class="summary">
    <div class="pill"><span class="dot on"></span> Rentang: <strong><?=h(date_id_long($from))?></strong> s/d <strong><?=h(date_id_long($to))?></strong></div>
    <div class="pill"><span class="dot on"></span> Total Fee: <strong>Rp <?=number_format((float)$total_fee,0,',','.')?></strong></div>
    <?php if ($tech>0): ?>
      <div class="pill"><span class="dot warn"></span> Teknisi terpilih: <strong><?=h(array_values(array_filter($techs, fn($x)=> (int)$x['id']===$tech))[0]['username'] ?? '')?></strong></div>
    <?php endif; ?>
    <?php if ($empty_note): ?>
      <div class="pill"><span class="dot warn"></span><?=h($empty_note)?></div>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:80px">Tanggal</th>
          <th style="width:160px">Teknisi</th>
          <th>Deskripsi</th>
          <th style="width:140px; text-align:right;">Fee</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr>
            <td colspan="4" style="text-align:center; padding:32px; color:var(--gray);">
              Tidak ada data.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($rows as $i => $r): ?>
            <tr style="animation: fadeInUp 0.45s ease-out <?=0.05*$i?>s both">
              <td><?=h(date_id_long($r['job_date']))?></td>
              <td><?=h($r['technician_name'] ?? '-')?></td>
              <td><?=h($r['description'] ?? '-')?></td>
              <td style="text-align:right;">Rp <?=number_format((float)($r['fee_amount'] ?? 0),0,',','.')?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
