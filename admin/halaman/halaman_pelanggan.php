<?php
// View Data Pelanggan (tanpa kerangka). Memakai $pdo dari kerangka.
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!isset($pdo)) { require_once __DIR__ . '/../auth.php'; require_admin(); }

// Filter
$q       = trim($_GET['q'] ?? '');
$gratis  = $_GET['gratis'] ?? '';    // '' | '0' | '1'
$status  = $_GET['status'] ?? '';    // '' | 'online' | 'offline'

// Helper untuk build URL dengan parameter + page=pelanggan
function qurl(array $overrides = []) {
  $base = $_GET;
  $base['page'] = 'pelanggan';
  foreach ($overrides as $k=>$v) {
    if ($v === null) unset($base[$k]); else $base[$k] = $v;
  }
  $qs = http_build_query($base);
  return 'index.php' . ($qs ? ('?'.$qs) : '');
}

// Query data (tahan error supaya halaman tetap tampil jika ada kolom yang hilang)
$where = []; $params = []; $data_error = null;

// Tentukan kolom telepon yang tersedia (phone atau whatsapp_number)
$phoneCol = '';
if (function_exists('hascol') && hascol($pdo, 'customers', 'phone')) {
  $phoneCol = 'c.phone';
} elseif (function_exists('hascol') && hascol($pdo, 'customers', 'whatsapp_number')) {
  $phoneCol = 'c.whatsapp_number';
}

if ($q !== '') {
  $filters = ["c.name LIKE ?"];
  if ($phoneCol) { $filters[] = "$phoneCol LIKE ?"; }
  $filters[] = "c.pppoe_username LIKE ?";
  $where[] = '('.implode(' OR ', $filters).')';

  $params[] = "%$q%";
  if ($phoneCol) { $params[] = "%$q%"; }
  $params[] = "%$q%";
}

if ($gratis === '1') { $where[] = "COALESCE(c.billable,1)=0"; }
elseif ($gratis === '0') { $where[] = "COALESCE(c.billable,1)=1"; }

$onlineCond = "LOWER(COALESCE(s.status,'')) IN ('online','connected','up','1','true')";
if ($status === 'online')  { $where[] = $onlineCond; }
if ($status === 'offline') { $where[] = "NOT($onlineCond)"; }

$selPhone = $phoneCol ? "$phoneCol AS phone" : "NULL AS phone";
$sql = "SELECT c.id, c.name, $selPhone, COALESCE(c.billable,1) AS billable, COALESCE(c.pppoe_username,'') AS pppoe_username,
               s.ip, s.status
        FROM customers c
        LEFT JOIN pppoe_status s ON s.username = c.pppoe_username
        ".($where ? "WHERE ".implode(" AND ", $where) : "")."
        ORDER BY c.name ASC";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
  $data_error = $e->getMessage();
}

// Ringkasan
$total = count($rows);
$tot_bill = $tot_free = $tot_unlinked = $on = $off = 0;
foreach ($rows as $r){
  ((int)$r['billable']===1) ? $tot_bill++ : $tot_free++;
  if (empty($r['pppoe_username'])) $tot_unlinked++;
  $isOnline = in_array(strtolower((string)($r['status'] ?? '')), ['online','connected','up','1','true']);
  $isOnline ? $on++ : $off++;
}
$tot = max(1, $total);
$bill_pct = max(0, min(100, round($tot_bill/$tot*100)));
$free_pct = max(0, min(100, round($tot_free/$tot*100)));
?>

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
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="pelanggan">
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
    <a class="xbtn" href="index.php?page=pelanggan">Reset</a>
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
    <?php if (!empty($data_error)): ?>
      <div class="info" role="status" aria-live="polite" style="margin:0 0 16px">
        Terjadi kendala memuat data pelanggan: <?= h($data_error) ?>
      </div>
    <?php endif; ?>

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
            <tr class="clickable" data-href="../halaman/detail_pelanggan.php?id=<?=$r['id']?>" style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
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
