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
$csrf = $_SESSION['csrf'] ?? csrf_token();

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

<style>
.job-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:1300;}
.job-modal.is-open{display:flex;}
.job-modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(3px);}
.job-modal .dialog{
  position:relative;
  width:min(720px,calc(100% - 28px));
  background:linear-gradient(135deg, rgba(30,41,59,0.95), rgba(15,23,42,0.96));
  border:1px solid rgba(251,191,36,0.25);
  border-radius:18px;
  padding:24px;
  box-shadow:0 18px 50px rgba(0,0,0,0.35), 0 0 30px rgba(251,191,36,0.15);
}
.job-modal .dialog h3{
  margin:0 0 14px;
  color:#fbbf24;
  font-size:22px;
  font-weight:800;
  letter-spacing:.3px;
}
.job-modal .dialog label{
  display:block;
  margin-bottom:12px;
  color:var(--light);
  font-size:13px;
  font-weight:600;
}
.job-modal .dialog input,
.job-modal .dialog select,
.job-modal .dialog textarea{
  width:100%;
  margin-top:6px;
  border:1px solid rgba(100,116,139,0.35);
  border-radius:12px;
  background:#0b1424;
  color:#e5e7eb;
  padding:12px 13px;
  font:inherit;
  transition:border-color .2s ease, box-shadow .2s ease, transform .1s ease;
}
.job-modal .dialog input:focus,
.job-modal .dialog select:focus,
.job-modal .dialog textarea:focus{
  outline:none;
  border-color:#fbbf24;
  box-shadow:0 0 0 2px rgba(251,191,36,0.25);
  transform:translateY(-1px);
}
.job-modal .dialog textarea{min-height:110px;resize:vertical;}
.job-modal .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px;}
.job-modal .close-btn{
  position:absolute;top:12px;right:12px;
  background:rgba(15,23,42,0.9);
  border:1px solid rgba(251,191,36,0.35);
  color:#fbbf24;
  border-radius:12px;
  width:38px;height:34px;
  display:grid;place-items:center;
  font-weight:700;
  cursor:pointer;
  transition:all .2s ease;
}
.job-modal .close-btn:hover{background:rgba(251,191,36,0.12);border-color:#fbbf24;}
body.modal-open{overflow:hidden;}
.dot-menu{
  width:36px; height:32px; border-radius:10px;
  background:rgba(30,41,59,0.8);
  border:1px solid rgba(251,191,36,0.3);
  color:#fbbf24; font-size:18px; font-weight:700;
  cursor:pointer; line-height:1;
  transition:all .2s ease;
}
.dot-menu:hover{ background:rgba(251,191,36,0.12); }
.action-menu{
  position:fixed; z-index:4000;
  background:#0f172a; border:1px solid rgba(251,191,36,0.35);
  border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,0.45);
  min-width:160px; overflow:hidden;
}
.action-menu button{
  width:100%; text-align:left; padding:10px 14px;
  background:transparent; border:0; color:#e5e7eb;
  font-weight:700; cursor:pointer;
}
.action-menu button:hover{ background:rgba(251,191,36,0.12); color:#fbbf24; }
</style>

<div class="card table-card">
  <?php if ($flash): ?>
    <div class="info" role="status" aria-live="polite" style="margin-top:0">
      <?=h($flash)?>
      <button class="close" aria-label="Tutup">x</button>
    </div>
  <?php endif; ?>

  <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:8px 0 4px;">
    <button class="btn" type="button" id="openJobModal" style="height:42px; display:inline-flex; align-items:center; padding:0 16px;">Tambah Job</button>
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
          <th>Tanggal</th>
          <th>Teknisi</th>
          <th>Deskripsi</th>
          <th style="text-align:right;">Fee</th>
          <th style="width:60px; text-align:right;">Aksi</th>
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
              <td style="text-align:right;">
                <button type="button" class="dot-menu"
                  data-id="<?=$r['id']?>"
                  data-date="<?=h($r['job_date'])?>"
                  data-tech="<?=$r['technician_id']?>"
                  data-desc="<?=h($r['description'] ?? '')?>"
                  data-fee="<?=h($r['fee_amount'] ?? 0)?>"
                  aria-label="Aksi">
                  ⋮
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="job-modal" id="jobModal" aria-hidden="true">
  <div class="backdrop" data-close-modal></div>
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="jobModalTitle">
    <button class="close-btn" type="button" aria-label="Tutup" data-close-modal>X</button>
    <h3 id="jobModalTitle">Tambah Job</h3>
    <form id="jobForm" action="api/api_job_create.php" method="post">
      <?php csrf_field(); ?>
      <input type="hidden" name="job_id" id="jobId">
      <label>Tanggal
        <input type="date" name="job_date" value="<?=h(date('Y-m-d'))?>" required>
      </label>
      <label>Teknisi
        <select name="technician_id" required>
          <option value="">Pilih teknisi</option>
          <?php foreach($techs as $t): ?>
            <option value="<?=$t['id']?>"><?=h($t['username'] ?? '-')?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Deskripsi
        <textarea name="description" placeholder="Uraian singkat pekerjaan"></textarea>
      </label>
      <label>Fee (Rp)
        <input type="text" name="fee_amount" id="feeInput" inputmode="numeric" placeholder="contoh 50.000" autocomplete="off" required>
      </label>
      <div class="actions">
        <button class="xbtn" type="button" data-close-modal>Batal</button>
        <button class="btn" type="submit" id="jobSubmitBtn">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('jobModal');
  var openBtn = document.getElementById('openJobModal');
  var closeEls = modal ? modal.querySelectorAll('[data-close-modal]') : [];
  var form = document.getElementById('jobForm');
  var submitBtn = document.getElementById('jobSubmitBtn');
  var feeInput = document.getElementById('feeInput');
  var jobIdInput = document.getElementById('jobId');
  var modalTitle = document.getElementById('jobModalTitle');
  var dotMenus = document.querySelectorAll('.dot-menu');
  var currentMode = 'create'; // create|edit
  var filterFrom = <?= json_encode($from) ?>;
  var filterTo   = <?= json_encode($to) ?>;
  var filterTech = <?= json_encode($tech) ?>;
  var csrfVal    = <?= json_encode($csrf) ?>;

  function openModal(){
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
  }
  function closeModal(){
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
  }

  openBtn && openBtn.addEventListener('click', function(e){
    e.preventDefault();
    currentMode = 'create';
    if (modalTitle) modalTitle.textContent = 'Tambah Job';
    if (form) form.action = 'api/api_job_create.php';
    if (jobIdInput) jobIdInput.value = '';
    if (form) form.reset();
    if (feeInput) feeInput.value = '';
    openModal();
  });
  closeEls.forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      closeModal();
    });
  });
  modal && modal.addEventListener('click', function(e){
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal();
  });

  function formatFeeInput(){
    if (!feeInput) return;
    var digits = (feeInput.value || '').replace(/\D/g,'');
    if (digits === '') {
      feeInput.value = '';
      return;
    }
    var formatted = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    feeInput.value = formatted;
  }
  feeInput && feeInput.addEventListener('input', formatFeeInput);
  feeInput && feeInput.addEventListener('blur', formatFeeInput);

  function openEditMenu(data){
    currentMode = 'edit';
    if (modalTitle) modalTitle.textContent = 'Edit Job';
    if (form) form.action = 'api/api_job_update.php';
    if (jobIdInput) jobIdInput.value = data.id || '';
    var dateField = form.querySelector('input[name=\"job_date\"]');
    var techField = form.querySelector('select[name=\"technician_id\"]');
    var descField = form.querySelector('textarea[name=\"description\"]');
    if (dateField) dateField.value = data.date || '';
    if (techField) techField.value = data.tech || '';
    if (descField) descField.value = data.desc || '';
    if (feeInput){
      feeInput.value = '';
      feeInput.value = (data.fee || '').toString();
      formatFeeInput();
    }
    openModal();
  }

  // Action dropdown
  var actionMenu = document.createElement('div');
  actionMenu.className = 'action-menu';
  actionMenu.style.display = 'none';
  actionMenu.innerHTML = '<button type=\"button\" data-action=\"edit\">Edit</button><button type=\"button\" data-action=\"delete\">Hapus</button>';
  document.body.appendChild(actionMenu);

  function closeMenu(){ actionMenu.style.display='none'; }

  document.addEventListener('click', function(e){
    if (actionMenu.style.display === 'none') return;
    if (!actionMenu.contains(e.target) && !(e.target && e.target.classList && e.target.classList.contains('dot-menu'))) {
      closeMenu();
    }
  });

  actionMenu.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action]');
    if(!btn) return;
    var action = btn.getAttribute('data-action');
    var data = actionMenu._data;
    closeMenu();
    if (!data) return;
    if (action === 'edit'){
      openEditMenu(data);
    } else if (action === 'delete'){
      if(!confirm('Hapus job ini?')) return;
      var fd = new FormData();
      fd.set('id', data.id || '');
      fd.set('_csrf', csrfVal);
      fd.set('back_from', filterFrom);
      fd.set('back_to', filterTo);
      fd.set('back_tech', filterTech);
      fetch('api/job_delete.php', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json().catch(function(){ return {ok:false,message:'Respon tidak valid'}; }); })
        .then(function(res){
          if(!res || !res.ok){ throw new Error(res && res.message ? res.message : 'Gagal menghapus'); }
          if (window.showToast) window.showToast(res.message || 'Job dihapus.', 'success');
          refreshJobs();
        })
        .catch(function(err){
          if (window.showToast) window.showToast(err.message || 'Gagal menghapus.', 'danger');
          refreshJobs();
        });
    }
  });

  function bindMenus(){
    dotMenus = document.querySelectorAll('.dot-menu');
    dotMenus.forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        var rect = btn.getBoundingClientRect();
        actionMenu.style.display = 'block';
        actionMenu.style.top = (rect.bottom + 6 + window.scrollY) + 'px';
        actionMenu.style.left = (rect.right - actionMenu.offsetWidth + window.scrollX) + 'px';
        actionMenu._data = {
          id: btn.dataset.id,
          date: btn.dataset.date,
          tech: btn.dataset.tech,
          desc: btn.dataset.desc,
          fee: btn.dataset.fee
        };
      });
    });
  }
  bindMenus();

  async function refreshJobs(){
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
      bindMenus();
    }catch(err){
      window.location.reload();
    }
  }


  form && form.addEventListener('submit', async function(e){
    e.preventDefault();
    if(!submitBtn) return;
    var digits = feeInput ? (feeInput.value || '').replace(/\D/g,'') : '';
    if (feeInput && digits === '') {
      feeInput.focus();
      if (window.showToast) window.showToast('Fee wajib diisi.', 'danger');
      return;
    }
    submitBtn.disabled = true;
    var originalText = submitBtn.textContent;
    submitBtn.textContent = currentMode === 'edit' ? 'Mengupdate...' : 'Menyimpan...';
    try {
      var fd = new FormData(form);
      if (feeInput) fd.set('fee_amount', digits);
      var res = await fetch(form.action, { method:'POST', body:fd });
      var data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Gagal menyimpan');
      if (window.showToast) {
        window.showToast(currentMode === 'edit' ? 'Job diperbarui.' : 'Job teknisi tersimpan.', 'success');
      }
      closeModal();
      refreshJobs();
    } catch(err){
      if (window.showToast) window.showToast(err.message || 'Gagal menyimpan', 'danger');
      else alert(err.message || 'Gagal menyimpan');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
})();
</script>
