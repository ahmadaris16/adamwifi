<div class="table-card" style="margin-top:12px">
  <!-- Tabs -->
  <div class="tabbar">
    <a class="tab <?= $TAB==='all'?'active':'' ?>" href="index.php?page=pppoe&tab=all">
      Semua <span class="badge"><?= number_format($tot) ?></span>
    </a>
    <a class="tab <?= $TAB==='online'?'active':'' ?>" href="index.php?page=pppoe&tab=online">
      Online <span class="badge on"><?= number_format($on) ?></span>
    </a>
    <a class="tab <?= $TAB==='offline'?'active':'' ?>" href="index.php?page=pppoe&tab=offline">
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
