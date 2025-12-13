<?php
// View Data Pelanggan (ikut layout index.php)
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!isset($pdo)) { require_once __DIR__ . '/../auth.php'; require_admin(); }

// Filter params
$q        = trim($_GET['q'] ?? '');
$gratis   = $_GET['gratis'] ?? '';      // '' | '0' | '1'
$status   = $_GET['status'] ?? '';      // '' | 'online' | 'offline'
$unlinked = $_GET['unlinked'] ?? '';    // '' | '1'
$csrf     = $_SESSION['csrf'] ?? '';

// Helper build URL
function qurl(array $overrides = []) {
  $base = $_GET;
  $base['page'] = 'pelanggan';
  foreach ($overrides as $k=>$v) {
    if ($v === null) unset($base[$k]); else $base[$k] = $v;
  }
  return 'index.php' . (($qs = http_build_query($base)) ? '?'.$qs : '');
}

// Statistik global (tidak terpengaruh filter) untuk angka tetap
$stat_rows = [];
try {
  $stat_rows = $pdo->query("
    SELECT COALESCE(c.billable,1) AS billable,
           COALESCE(c.pppoe_username,'') AS pppoe_username,
           COALESCE(s.status,'') AS status
    FROM customers c
    LEFT JOIN pppoe_status s ON s.username = c.pppoe_username
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $stat_rows = [];
}
$stat_total = count($stat_rows);
$stat_bill = $stat_free = $stat_unlinked = $stat_on = $stat_off = 0;
$isOn = function($st){ return in_array(strtolower((string)$st), ['online','connected','up','1','true']); };
foreach ($stat_rows as $r){
  ((int)$r['billable']===1) ? $stat_bill++ : $stat_free++;
  if (empty($r['pppoe_username'])) $stat_unlinked++;
  $isOn($r['status']) ? $stat_on++ : $stat_off++;
}

// Tentukan filter aktif (eksklusif)
$active_filter = 'all';
if ($unlinked === '1') $active_filter = 'unlinked';
elseif ($status === 'online') $active_filter = 'online';
elseif ($status === 'offline') $active_filter = 'offline';
elseif ($gratis === '0') $active_filter = 'bill';
elseif ($gratis === '1') $active_filter = 'free';

// Query data
$where = []; $params = []; $data_error = null;
// kolom phone
$phoneCol = '';
if (function_exists('hascol') && hascol($pdo, 'customers', 'phone')) $phoneCol = 'c.phone';
elseif (function_exists('hascol') && hascol($pdo, 'customers', 'whatsapp_number')) $phoneCol = 'c.whatsapp_number';

if ($q !== '') {
  $filters = ["c.name LIKE ?"];
  if ($phoneCol) { $filters[] = "$phoneCol LIKE ?"; }
  $filters[] = "c.pppoe_username LIKE ?";
  $where[] = '('.implode(' OR ', $filters).')';
  $params[] = "%$q%";
  if ($phoneCol) { $params[] = "%$q%"; }
  $params[] = "%$q%";
}
if ($active_filter === 'bill') {
  $where[] = "COALESCE(c.billable,1)=1";
} elseif ($active_filter === 'free') {
  $where[] = "COALESCE(c.billable,1)=0";
} elseif ($active_filter === 'unlinked') {
  $where[] = "COALESCE(c.pppoe_username,'')=''";
} elseif ($active_filter === 'online') {
  $where[] = "LOWER(COALESCE(s.status,'')) IN ('online','connected','up','1','true')";
} elseif ($active_filter === 'offline') {
  $where[] = "NOT(LOWER(COALESCE(s.status,'')) IN ('online','connected','up','1','true'))";
}

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
  $data_error = $e->getMessage();
  $rows = [];
}

// Ringkasan hasil filter (untuk tampilan tabel)
$total = count($rows);
$tot_bill = $tot_free = $tot_unlinked = $on = $off = 0;
foreach ($rows as $r){
  ((int)$r['billable']===1) ? $tot_bill++ : $tot_free++;
  if (empty($r['pppoe_username'])) $tot_unlinked++;
  $isOnline = $isOn($r['status']);
  $isOnline ? $on++ : $off++;
}

?>

<style>
.summary .pill.active{
  border-color:rgba(251,191,36,0.5);
  box-shadow:0 8px 20px rgba(251,191,36,0.12);
  background:linear-gradient(135deg, rgba(251,191,36,0.15), rgba(245,158,11,0.1));
}
.search-icon{ display:none; }
.search-row{
  max-width:420px;
  width:100%;
  position:relative;
}
.search-row input{
  width:100%;
  padding-right:36px;
  padding-left:12px;
  border-radius:12px;
}
.search-row .clear-btn{
  position:absolute;
  right:10px;
  top:50%;
  transform:translateY(-50%);
  background:transparent;
  border:0;
  color:var(--gray);
  font-size:16px;
  cursor:pointer;
  line-height:1;
  display:none;
  z-index:2;
}

/* Modal detail pelanggan */
.cust-modal-backdrop{
  position:fixed; inset:0;
  background:rgba(0,0,0,0.55);
  backdrop-filter:blur(2px);
  opacity:0; pointer-events:none;
  transition:opacity .25s ease;
  z-index:1200;
}
.cust-modal{
  position:fixed; inset:0;
  display:flex; align-items:center; justify-content:center;
  padding:20px;
  opacity:0; pointer-events:none;
  z-index:1201;
  transition:opacity .25s ease;
}
.cust-modal.show,
.cust-modal.show + .cust-modal-backdrop{ opacity:1; pointer-events:auto; }
.cust-modal-card{
  width:100%;
  max-width:780px;
  background:linear-gradient(135deg, rgba(30,41,59,0.95), rgba(15,23,42,0.95));
  border:1px solid rgba(251,191,36,0.18);
  border-radius:16px;
  box-shadow:0 20px 60px rgba(0,0,0,0.45);
  padding:18px 20px 20px 20px;
  position:relative;
}
.cust-modal-close{
  position:absolute; top:12px; right:12px;
  background:rgba(239,68,68,0.14);
  border:1px solid rgba(239,68,68,0.5);
  color:#fecaca;
  width:34px; height:34px;
  border-radius:10px;
  cursor:pointer;
  font-size:18px;
  font-weight:700;
  line-height:1;
  display:flex;
  align-items:center;
  justify-content:center;
}
.cust-modal-head{
  display:flex; justify-content:space-between; gap:12px; align-items:flex-start;
  margin-bottom:12px;
  padding-right:52px; /* ruang untuk tombol close */
}
.cust-modal-title{ font-size:20px; font-weight:800; }
.cust-modal-sub{ color:var(--gray); font-size:13px; }
.cust-modal-badges{ display:flex; gap:8px; flex-wrap:wrap; }
.cust-modal-body{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.cust-modal-body label{ display:block; color:var(--gray); font-weight:700; margin-bottom:6px; font-size:13px; }
.cust-modal-input{
  width:100%;
  border-radius:12px;
  border:1px solid rgba(251,191,36,0.18);
  background:rgba(30,41,59,0.5);
  color:var(--light);
  padding:10px 12px;
}
.cust-modal-checkbox{
  width:18px; height:18px;
  accent-color: var(--primary);
}
.cust-modal-checkbox-row{
  display:flex; gap:10px; align-items:center;
  padding:10px 12px;
  border-radius:12px;
  border:1px dashed rgba(251,191,36,0.25);
}
.cust-modal-actions{
  display:flex; gap:10px; justify-content:flex-end; margin-top:16px;
}
.cust-pill{ display:inline-flex; gap:8px; align-items:center; padding:10px 12px;
  background:rgba(30,41,59,0.6);
  border:1px solid rgba(251,191,36,0.12);
  border-radius:12px;
  font-weight:700; font-size:13px;
}
.cust-pill .dot{ width:8px; height:8px; border-radius:50%; display:inline-block; }
.cust-pill .dot.on{ background:var(--success); }
.cust-pill .dot.off{ background:var(--danger); }
.cust-modal-alert{
  display:none;
  margin:0 0 10px;
  padding:10px 12px;
  border-radius:10px;
  border:1px solid rgba(239,68,68,0.35);
  background:rgba(239,68,68,0.1);
  color:#fecaca;
  font-weight:600;
}

@media(max-width:640px){
  .cust-modal-body{ grid-template-columns:1fr; }
}
.badge.free{
  background: linear-gradient(135deg, rgba(14,165,233,0.2), rgba(2,132,199,0.2));
  color: #7dd3fc;
  border: 1px solid rgba(59,130,246,0.35);
}
.badge.bill{
  background: linear-gradient(135deg, rgba(251,191,36,0.15), rgba(245,158,11,0.08));
  color: var(--primary);
  border: 1px solid rgba(251,191,36,0.35);
}
</style>

<div class="card">
  <!-- Toolbar Sinkron & Auto-Link -->
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
    <form method="post" action="api/sinkron_pelanggan.php" style="margin:0">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
      <button class="btn" type="submit" onclick="return confirm('Sinkronkan pelanggan baru dari PPPoE?')">Sinkronkan Pelanggan</button>
    </form>
    <form method="post" action="api/auto_link_pppoe.php" style="margin:0">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
      <button class="btn" type="submit" onclick="return confirm('Hubungkan otomatis PPPoE ke pelanggan?')">Auto-Link PPPoE</button>
    </form>
  </div>

  <!-- Pencarian -->
  <form class="filters" method="get" action="index.php" style="position:relative;">
    <input type="hidden" name="page" value="pelanggan">
    <input type="hidden" name="unlinked" value="<?=h($unlinked)?>">
    <div class="search-row">
      <input id="searchInput" type="text" name="q" placeholder="Ketik untuk mencari nama / WA / PPPoE…" value="<?=h($q)?>" aria-label="Pencarian">
      <button type="button" id="clearSearch" class="clear-btn" aria-label="Bersihkan pencarian">✕</button>
    </div>
  </form>

  <!-- Pills filter (eksklusif) -->
  <div class="summary">
    <a class="pill <?= $active_filter==='all'?'active':'' ?>" href="<?= h(qurl(['gratis'=>null, 'status'=>null,'unlinked'=>null])) ?>"><span class="dot warn"></span> Semua: <strong id="pillTotal"><?=number_format($stat_total)?></strong></a>
    <a class="pill <?= $active_filter==='bill'?'active':'' ?>" href="<?= h(qurl(['gratis'=>'0','unlinked'=>null,'status'=>null])) ?>"><span class="dot on"></span> Ditagih: <strong id="pillBill"><?=number_format($stat_bill)?></strong></a>
    <a class="pill <?= $active_filter==='free'?'active':'' ?>" href="<?= h(qurl(['gratis'=>'1','unlinked'=>null,'status'=>null])) ?>"><span class="dot off"></span> Gratis: <strong id="pillFree"><?=number_format($stat_free)?></strong></a>
    <a class="pill <?= $active_filter==='unlinked'?'active':'' ?>" href="<?= h(qurl(['unlinked'=>'1','status'=>null,'gratis'=>null ])) ?>"><span class="dot warn"></span> Unlinked: <strong><?=number_format($stat_unlinked)?></strong></a>
    <a class="pill <?= $active_filter==='online'?'active':'' ?>" href="<?= h(qurl(['status'=>'online','unlinked'=>null,'gratis'=>null])) ?>"><span class="dot on"></span> Online: <strong><?=number_format($stat_on)?></strong></a>
    <a class="pill <?= $active_filter==='offline'?'active':'' ?>" href="<?= h(qurl(['status'=>'offline','unlinked'=>null,'gratis'=>null])) ?>"><span class="dot off"></span> Offline: <strong><?=number_format($stat_off)?></strong></a>
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
        <?php if($data_error): ?>
          <tr><td colspan="5" style="color:red;">Terjadi kendala: <?=h($data_error)?></td></tr>
        <?php elseif(!$rows): ?>
          <tr>
            <td colspan="5">
              <div style="text-align:center;padding:40px">
                <div style="font-size:48px;margin-bottom:16px;opacity:0.3">👥</div>
                <div style="color:var(--gray);font-size:16px">Tidak ada data pelanggan</div>
                <div style="color:var(--gray);font-size:14px;margin-top:8px">Coba ubah filter atau pencarian</div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php $no=1; foreach($rows as $i => $r):
            $isOnline = $isOn($r['status']);
          ?>
            <tr class="clickable"
              data-id="<?=$r['id']?>"
              data-name="<?=h($r['name'] ?? '')?>"
              data-phone="<?=h($r['phone'] ?? '')?>"
              data-pppoe="<?=h($r['pppoe_username'] ?? '')?>"
              data-ip="<?=h($r['ip'] ?? '')?>"
              data-online="<?=$isOnline ? '1' : '0'?>"
              data-billable="<?= (int)$r['billable'] ?>"
              style="animation: fadeInUp 0.5s ease-out <?=0.1 + ($i * 0.05)?>s backwards">
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

<!-- Modal detail/edit pelanggan -->
<div id="custModal" class="cust-modal" aria-modal="true" role="dialog">
  <div class="cust-modal-card">
    <button type="button" class="cust-modal-close" aria-label="Tutup">&times;</button>
    <div class="cust-modal-head">
      <div>
        <div class="cust-modal-title" id="custName">-</div>
        <div class="cust-modal-sub" id="custPppoe">-</div>
      </div>
      <div class="cust-modal-badges">
        <span class="badge" id="custStatus">-</span>
        <span class="badge" id="custBill">-</span>
      </div>
    </div>

    <div class="cust-pill" style="margin-bottom:10px;">
      <span class="dot" id="custDot"></span>
      <span>IP: <span id="custIp">-</span></span>
    </div>

    <div class="cust-modal-alert" id="custAlert"></div>

    <form id="custForm" novalidate>
      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
      <input type="hidden" name="id" id="custId">

      <div class="cust-modal-body">
        <div>
          <label for="custPhone">Nomor WhatsApp</label>
          <input class="cust-modal-input" id="custPhone" name="phone" placeholder="08xxxxxxxxx">
        </div>
        <div>
          <label>Status Tagihan</label>
          <label class="cust-modal-checkbox-row">
            <input type="checkbox" class="cust-modal-checkbox" id="custFree" name="is_free">
            <span>Centang jika pelanggan gratis (tidak ditagih)</span>
          </label>
        </div>
      </div>
      <div class="cust-modal-actions">
        <button type="button" class="xbtn" id="custCancel">Batal</button>
        <button type="submit" class="btn" id="custSave">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<div class="cust-modal-backdrop" id="custBackdrop"></div>

<script>
(function(){
  var form = document.querySelector('.filters');
  var input = document.getElementById('searchInput');
  var clearBtn = document.getElementById('clearSearch');
  var tableWrap = document.querySelector('.table-wrap');
  var modal = document.getElementById('custModal');
  var backdrop = document.getElementById('custBackdrop');
  var alertBox = document.getElementById('custAlert');
  var nameEl = document.getElementById('custName');
  var pppoeEl = document.getElementById('custPppoe');
  var statusEl = document.getElementById('custStatus');
  var billEl = document.getElementById('custBill');
  var dotEl = document.getElementById('custDot');
  var ipEl = document.getElementById('custIp');
  var phoneInput = document.getElementById('custPhone');
  var freeInput = document.getElementById('custFree');
  var idInput = document.getElementById('custId');
  var saveBtn = document.getElementById('custSave');
  var cancelBtn = document.getElementById('custCancel');
  var closeBtn = document.querySelector('.cust-modal-close');
  var custForm = document.getElementById('custForm');
  var activeRow = null;
  var timer;
  var pillBill = document.getElementById('pillBill');
  var pillFree = document.getElementById('pillFree');
  if (!form || !input || !tableWrap) return;

  // Jangan reload penuh saat submit
  form.addEventListener('submit', function(e){ e.preventDefault(); });

  function setBadge(el, ok, textOn, textOff){
    if(!el) return;
    el.textContent = ok ? textOn : textOff;
    el.className = 'badge ' + (ok ? 'on' : 'off');
  }
  function setBill(el, billable){
    if(!el) return;
    var isFree = String(billable) === '0';
    el.textContent = isFree ? 'Gratis' : 'Ditagih';
    el.className = 'badge ' + (isFree ? 'free' : 'bill');
  }
  function openModal(tr){
    if(!modal || !tr) return;
    activeRow = tr;
    var d = tr.dataset;
    if(alertBox){ alertBox.style.display='none'; alertBox.textContent=''; }
    nameEl.textContent = d.name || 'Tanpa nama';
    pppoeEl.textContent = d.pppoe || 'Belum tertaut PPPoE';
    ipEl.textContent = d.ip || '-';
    idInput.value = d.id || '';
    phoneInput.value = d.phone || '';
    freeInput.checked = (d.billable === '0');
    setBadge(statusEl, d.online === '1', 'Online', 'Offline');
    setBill(billEl, d.billable);
    if(dotEl){
      dotEl.className = 'dot ' + (d.online === '1' ? 'on' : 'off');
    }
    modal.classList.add('show');
    if(backdrop) backdrop.classList.add('show');
  }
  function closeModal(){
    if(modal) modal.classList.remove('show');
    if(backdrop) backdrop.classList.remove('show');
    activeRow = null;
  }

  function bindRows(){
    document.querySelectorAll('tr.clickable').forEach(function(tr){
      tr.addEventListener('click', function(){
        openModal(tr);
      });
    });
  }
  bindRows();

  function refreshCounts(){
    pillBill = document.getElementById('pillBill');
    pillFree = document.getElementById('pillFree');
  }

  async function refreshCustomers(){
    try{
      var res = await fetch(window.location.href, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      var html = await res.text();
      var dom = new DOMParser().parseFromString(html, 'text/html');
      var newWrap = dom.querySelector('.table-wrap');
      var curWrap = document.querySelector('.table-wrap');
      if (newWrap && curWrap) curWrap.innerHTML = newWrap.innerHTML;
      var newSummary = dom.querySelector('.summary');
      var curSummary = document.querySelector('.summary');
      if (newSummary && curSummary) curSummary.innerHTML = newSummary.innerHTML;
      refreshCounts();
      bindRows();
    }catch(err){
      window.location.reload();
    }
  }

  function fetchTable(val){
    var url = new URL(window.location.href);
    url.searchParams.set('page','pelanggan');
    url.searchParams.set('q', val);
    // pertahankan filter lain dari query saat ini
    ['gratis','status','unlinked'].forEach(function(k){
      var cur = (new URL(window.location.href)).searchParams.get(k);
      if (cur !== null) url.searchParams.set(k, cur);
    });
    fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.text(); })
      .then(function(html){
        var dom = new DOMParser().parseFromString(html, 'text/html');
        var newWrap = dom.querySelector('.table-wrap');
        if (newWrap) {
          tableWrap.innerHTML = newWrap.innerHTML;
          bindRows();
        }
      })
      .catch(function(){ /* diamkan saja */ });
  }

  input.addEventListener('input', function(){
    clearTimeout(timer);
    if (clearBtn) clearBtn.style.display = input.value ? 'inline-flex' : 'none';
    var val = input.value.trim();
    timer = setTimeout(function(){ fetchTable(val); }, 400);
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function(){
      input.value = '';
      clearBtn.style.display = 'none';
      fetchTable('');
    });
  }

  [backdrop, closeBtn, cancelBtn].forEach(function(el){
    if(el){ el.addEventListener('click', closeModal); }
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ closeModal(); }
  });

  if(custForm){
    custForm.addEventListener('submit', function(e){
      e.preventDefault();
      if(!activeRow || !idInput.value){ return; }
      var prevBillable = activeRow.dataset.billable;
      if(saveBtn){
        saveBtn.disabled = true;
        saveBtn.textContent = 'Menyimpan...';
      }
      if(alertBox){
        alertBox.style.display='none';
        alertBox.textContent='';
      }
      var fd = new FormData(custForm);
      fetch('api/pelanggan_update.php', {
        method:'POST',
        body: fd,
        headers:{'X-Requested-With':'XMLHttpRequest'}
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(!res || !res.ok){
          throw new Error(res && res.message ? res.message : 'Gagal menyimpan.');
        }
        var phoneVal = fd.get('phone') || '';
        activeRow.dataset.phone = phoneVal;
        activeRow.dataset.billable = String(res.billable ?? (fd.get('is_free') ? '0' : '1'));
        var cellPhone = activeRow.querySelector('td:nth-child(4)');
        if(cellPhone){ cellPhone.textContent = phoneVal || '-'; }
        setBill(billEl, activeRow.dataset.billable);
        freeInput.checked = activeRow.dataset.billable === '0';
        if (pillBill && pillFree && prevBillable !== activeRow.dataset.billable) {
          var billNum = parseInt((pillBill.textContent||'0').replace(/[^0-9]/g,''),10) || 0;
          var freeNum = parseInt((pillFree.textContent||'0').replace(/[^0-9]/g,''),10) || 0;
          if (activeRow.dataset.billable === '0') {
            billNum = Math.max(0, billNum - 1);
            freeNum += 1;
          } else {
            freeNum = Math.max(0, freeNum - 1);
            billNum += 1;
          }
          pillBill.textContent = billNum.toLocaleString('id-ID');
          pillFree.textContent = freeNum.toLocaleString('id-ID');
        }
        if (window.showToast) {
          window.showToast(res.message || 'Perubahan disimpan.', 'success');
        }
        closeModal();
        refreshCustomers();
      })
      .catch(function(err){
        if(alertBox){
          alertBox.textContent = err.message || 'Terjadi kesalahan.';
          alertBox.style.display = 'block';
        }
      })
      .finally(function(){
        if(saveBtn){
          saveBtn.disabled = false;
          saveBtn.textContent = 'Simpan Perubahan';
        }
      });
    });
  }
})();
</script>
