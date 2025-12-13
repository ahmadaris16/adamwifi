<?php
// Partial Pembayaran (ikut layout index.php)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('ym_valid')) {
  function ym_valid($s){ return (bool)preg_match('/^\d{4}-\d{2}$/', $s); }
}
if (!function_exists('ym_label_id')) {
  function ym_label_id($ym){
    if(!ym_valid($ym)) return $ym;
    [$y,$m] = explode('-', $ym);
    $bln = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return ($bln[$m] ?? $m).' '.$y;
  }
}
if (!function_exists('prev_period')) {
  function prev_period(){ return date('Y-m', strtotime('first day of last month')); }
}
function qs(array $merge){
  $q = array_merge($_GET,$merge);
  foreach($q as $k=>$v){ if($v===null) unset($q[$k]); }
  return http_build_query($q);
}

// Data utama
$tab    = $_GET['tab'] ?? 'unpaid'; // unpaid|paid|collectors
$tech   = trim($_GET['tech'] ?? '');
$period = (isset($_GET['period']) && ym_valid($_GET['period'])) ? $_GET['period'] : prev_period();

$paid_rows = []; $unpaid_rows = []; $collectors = []; $collector_paid_rows = []; $collector_groups = [];
try {
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

  $sqlUnpaid = "SELECT c.id, c.name, COALESCE(c.whatsapp_number, c.phone) AS phone
                FROM customers c
                WHERE c.billable=1 AND COALESCE(c.active,1)=1
                  AND NOT EXISTS (
                    SELECT 1 FROM payments p
                    WHERE p.customer_id = c.id AND p.period = :prd AND p.paid_at IS NOT NULL
                  )
                ORDER BY c.name ASC";
  $st2 = $pdo->prepare($sqlUnpaid); $st2->execute([':prd'=>$period]); $unpaid_rows = $st2->fetchAll(PDO::FETCH_ASSOC);

  $sqlCol = "SELECT COALESCE(p.paid_by,'(tanpa nama)') AS technician,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(p.amount),0) AS total
             FROM payments p
             WHERE p.period = :prd AND p.paid_at IS NOT NULL
             GROUP BY technician
             ORDER BY technician ASC";
  $st3 = $pdo->prepare($sqlCol); $st3->execute([':prd'=>$period]); $collectors = $st3->fetchAll(PDO::FETCH_ASSOC);

  if ($tab === 'collectors') {
    $sqlPaidAll = "SELECT p.customer_id, c.name,
                          COALESCE(c.whatsapp_number, c.phone) AS phone,
                          COALESCE(p.amount,0) AS amount,
                          DATE_FORMAT(p.paid_at,'%Y-%m-%d %H:%i:%s') AS paid_at,
                          COALESCE(p.paid_by,'(tanpa nama)') AS technician
                   FROM payments p
                   JOIN customers c ON c.id = p.customer_id
                   WHERE p.period = :prd AND p.paid_at IS NOT NULL
                   ORDER BY technician ASC, p.paid_at DESC";
    $st4 = $pdo->prepare($sqlPaidAll); $st4->execute([':prd'=>$period]); $collector_paid_rows = $st4->fetchAll(PDO::FETCH_ASSOC);
    foreach ($collector_paid_rows as $row) {
      $t = $row['technician'] ?? '(tanpa nama)';
      if (!isset($collector_groups[$t])) {
        $collector_groups[$t] = ['rows'=>[], 'count'=>0, 'total'=>0];
      }
      $collector_groups[$t]['rows'][] = $row;
      $collector_groups[$t]['count']++;
      $collector_groups[$t]['total'] += (float)($row['amount'] ?? 0);
    }
    // Isi summary jika sudah ada agregat kolektor
    foreach ($collectors as $c) {
      $t = $c['technician'] ?? '(tanpa nama)';
      if (!isset($collector_groups[$t])) {
        $collector_groups[$t] = ['rows'=>[], 'count'=>0, 'total'=>0];
      }
      $collector_groups[$t]['count'] = (int)$c['cnt'];
      $collector_groups[$t]['total'] = (float)$c['total'];
    }
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB error: ".h($e->getMessage()); exit;
}

$paid_count   = count($paid_rows);
$unpaid_count = count($unpaid_rows);
$total_count  = $paid_count + $unpaid_count;
$pct_paid     = $total_count ? round($paid_count / $total_count * 100) : 0;
$total_nominal = 0.0; foreach($paid_rows as $r){ $total_nominal += (float)$r['amount']; }
$admin_name = $_SESSION['admin_user']['username'] ?? 'admin';
?>

<style>
.payments-page .toolbar{
  display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:16px;
}
.payments-page .toolbar label{
  font-size:13px; color:var(--gray); display:flex; align-items:center; gap:6px;
}
.payments-page .toolbar input.month{
  padding:10px 12px; border-radius:10px; border:1px solid rgba(251,191,36,0.18);
  background:rgba(30,41,59,0.8); color:var(--light); min-width:160px;
}
.payments-page .toolbar form{
  display:flex; align-items:center; gap:10px; margin:0;
}
.payments-page .toolbar .btn,
.payments-page .toolbar .xbtn{ height:42px; }
.payments-page .toolbar-spacer{ flex:1; min-width:20px; }
.payments-page .chips{ display:flex; gap:10px; flex-wrap:wrap; margin:10px 0; }
.payments-page .chip{
  background:rgba(30,41,59,0.8); border:1px solid rgba(251,191,36,0.18);
  color:var(--light); padding:8px 12px; border-radius:12px; font-size:13px; display:inline-flex; align-items:center; gap:6px;
}
.payments-page .chip .icon{ opacity:0.85; }
.payments-page .grid{
  display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:12px;
}
.payments-page .collector-card{
  display:block; text-decoration:none; color:var(--light);
  background:linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border:1px solid rgba(251,191,36,0.12); border-radius:14px;
  padding:14px; transition:transform .2s ease, box-shadow .2s ease; position:relative;
}
.payments-page .collector-card:hover{
  transform:translateY(-2px);
  box-shadow:0 10px 30px rgba(0,0,0,0.25);
  border-color:rgba(251,191,36,0.35);
}
.payments-page .xbtn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:10px 14px;
  border-radius:12px;
  border:1px solid rgba(251,191,36,0.35);
  background:rgba(30,41,59,0.6);
  color:var(--primary);
  font-weight:700;
  text-decoration:none;
  transition:all .2s ease;
  cursor:pointer;
}
.payments-page .xbtn:hover{
  border-color:rgba(251,191,36,0.6);
  color:var(--primary-light);
  transform:translateY(-1px);
}
.payments-page .xbtn.danger{
  border-color:rgba(239,68,68,0.5);
  background:linear-gradient(135deg, rgba(239,68,68,0.15), rgba(220,38,38,0.12));
  color:#fecaca;
}
.payments-page .xbtn.danger:hover{
  border-color:rgba(239,68,68,0.8);
  color:#fff;
  box-shadow:0 8px 20px rgba(239,68,68,0.25);
}
.payments-page .collector-name{ font-weight:800; margin-bottom:6px; }
.payments-page .collector-meta{ color:var(--gray); font-size:13px; }
.payments-page .collector-link{ font-size:13px; color:var(--primary); margin-top:8px; display:inline-flex; align-items:center; gap:4px; }
.payments-page .inline-form{ display:inline; margin:0; }
.payments-page .collector-panel{
  margin-top:16px;
  background:linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border:1px solid rgba(251,191,36,0.12);
  border-radius:14px;
  padding:16px;
}
.payments-page .collector-tabs{
  display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;
}
.payments-page .collector-tab{
  border:1px solid rgba(251,191,36,0.2);
  background:rgba(30,41,59,0.6);
  color:var(--light);
  padding:8px 12px;
  border-radius:12px;
  cursor:pointer;
  font-weight:700;
  transition:all .2s ease;
}
.payments-page .collector-tab.active{
  border-color:rgba(251,191,36,0.6);
  color:var(--primary);
  box-shadow:0 6px 20px rgba(251,191,36,0.15);
}
.payments-page .collector-summary{
  display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px;
}
.payments-page .collector-summary .pill{
  background:rgba(30,41,59,0.6);
  border:1px solid rgba(251,191,36,0.12);
  border-radius:12px;
  padding:8px 12px;
  color:var(--light);
  font-weight:700;
  display:inline-flex; gap:6px; align-items:center;
}
.payments-page .collector-section{ display:none; }
.payments-page .collector-section.active{ display:block; }
</style>

<div class="card payments-page">
  <div class="toolbar">
    <form method="get">
      <input type="hidden" name="page" value="payments">
      <input type="hidden" name="tab" value="<?=h($tab)?>">
      <?php if($tech!==''): ?><input type="hidden" name="tech" value="<?=h($tech)?>"><?php endif; ?>
      <label>
        Periode
        <input class="month" type="month" name="period" value="<?=h($period)?>">
      </label>
      <button class="btn" type="submit">Terapkan</button>
    </form>

    <div class="toolbar-spacer"></div>

    <form method="post" action="api/pembayaran_aksi.php"
          onsubmit="return confirm('Reset semua status periode <?=h(ym_label_id($period))?> ke BELUM BAYAR?');"
          style="margin-left:auto">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="reset_period">
      <input type="hidden" name="page" value="payments">
      <input type="hidden" name="tab" value="<?=h($tab)?>">
      <input type="hidden" name="period" value="<?=h($period)?>">
      <?php if($tech!==''): ?><input type="hidden" name="tech" value="<?=h($tech)?>"><?php endif; ?>
      <button class="xbtn danger" type="submit">Reset Semua (Periode Ini)</button>
    </form>
  </div>

  <div class="tabbar">
    <a class="tab <?= $tab==='unpaid'?'active':'' ?>" href="index.php?<?=qs(['page'=>'payments','tab'=>'unpaid','tech'=>null])?>">
      Belum Dibayar <span class="badge"><?=number_format($unpaid_count)?></span>
    </a>
    <a class="tab <?= $tab==='paid'?'active':'' ?>" href="index.php?<?=qs(['page'=>'payments','tab'=>'paid'])?>">
      Sudah Dibayar <span class="badge"><?=number_format($paid_count)?></span>
    </a>
    <a class="tab <?= $tab==='collectors'?'active':'' ?>" href="index.php?<?=qs(['page'=>'payments','tab'=>'collectors','tech'=>null])?>">
      Penanggung Jawab
    </a>
  </div>

  <div class="chips">
    <span class="chip"><span class="icon">&#x2714;</span> Lunas: <b><?=number_format($paid_count)?></b></span>
    <span class="chip"><span class="icon">&#x23F3;</span> Pending: <b><?=number_format($unpaid_count)?></b></span>
    <span class="chip"><span class="icon">&#x1F4B0;</span> Total nominal: <b>Rp <?=number_format((float)$total_nominal,0,',','.')?></b></span>
  </div>
  <div class="progress" aria-label="Progress pembayaran">
    <span style="width: <?=$pct_paid?>%"></span>
  </div>
  <div style="font-size:12px;color:var(--gray);margin-bottom:16px">
    <?=$pct_paid?>% Lunas - Periode <?=h(ym_label_id($period))?>
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
              <form class="inline-form" method="post" action="api/pembayaran_aksi.php"
                    onsubmit="return confirm('Tandai SUDAH BAYAR untuk <?=h($r['name'])?>?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="page" value="payments">
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
        <a class="xbtn" href="index.php?<?=qs(['page'=>'payments','tech'=>null])?>">Reset Filter</a>
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
                <div style="font-size:48px;margin-bottom:16px;opacity:0.3">🧾</div>
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
              <form class="inline-form" method="post" action="api/pembayaran_aksi.php"
                    onsubmit="return confirm('Batalkan pembayaran untuk <?=h($r['name'])?>?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="mark_unpaid">
                <input type="hidden" name="page" value="payments">
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
    <div class="collector-panel">
      <?php if(!$collectors): ?>
        <div style="text-align:center;padding:40px;opacity:0.8">
          <div style="font-size:48px;margin-bottom:16px;opacity:0.3">dY`?</div>
          <div style="color:var(--gray);font-size:16px">Belum ada data penanggung jawab</div>
          <div style="color:var(--gray);font-size:14px;margin-top:8px">Periode <?=h(ym_label_id($period))?></div>
        </div>
      <?php else: ?>
        <div class="collector-tabs" role="tablist" aria-label="Penanggung jawab">
          <?php foreach($collectors as $i => $c): ?>
            <button type="button" class="collector-tab" data-tech="<?=h($c['technician'])?>" role="tab" aria-selected="false">
              <?=h($c['technician'])?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php foreach($collector_groups as $tech => $info): ?>
          <div class="collector-section" data-tech="<?=h($tech)?>" role="tabpanel">
            <div class="collector-summary">
              <div class="pill">Teknisi: <strong><?=h($tech)?></strong></div>
              <div class="pill">Pelanggan: <strong><?=number_format((int)$info['count'])?></strong></div>
              <div class="pill">Total: <strong>Rp <?=number_format((float)$info['total'],0,',','.')?></strong></div>
            </div>
            <div class="table-wrap">
              <table class="tbl">
                <thead>
                  <tr>
                    <th>Waktu Bayar</th>
                    <th>Nama</th>
                    <th style="text-align:right">Jumlah (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(empty($info['rows'])): ?>
                    <tr>
                      <td colspan="3" style="text-align:center; color:var(--gray); padding:24px">
                        Belum ada pembayaran untuk teknisi ini pada periode <?=h(ym_label_id($period))?>.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach($info['rows'] as $i => $r): ?>
                      <tr style="animation: fadeInUp 0.4s ease-out <?=0.05 + ($i * 0.03)?>s backwards">
                        <td><?=h($r['paid_at'] ?? '-')?></td>
                        <td><?=h($r['name'])?> <small style="opacity:.7"><?=h($r['phone'] ?? '-')?></small></td>
                        <td style="text-align:right"><?=number_format((float)($r['amount'] ?? 0),0,',','.')?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var rows = document.querySelectorAll('.tbl tbody tr');
  rows.forEach(function(tr, i){
    if(!tr.style.animation && !tr.querySelector('td[colspan]')){
      tr.style.animation = 'fadeInUp .35s ease-out both';
      tr.style.animationDelay = (0.03 * i + 0.12) + 's';
    }
  });

  var tabBtns = document.querySelectorAll('.collector-tab');
  var sections = document.querySelectorAll('.collector-section');
  function activate(tech){
    sections.forEach(function(sec){
      var active = sec.getAttribute('data-tech') === tech;
      sec.classList.toggle('active', active);
    });
    tabBtns.forEach(function(btn){
      var active = btn.getAttribute('data-tech') === tech;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }
  if(tabBtns.length){
    activate(tabBtns[0].getAttribute('data-tech'));
    tabBtns.forEach(function(btn){
      btn.addEventListener('click', function(){
        activate(btn.getAttribute('data-tech'));
      });
    });
  }
});
</script>
