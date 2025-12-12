<?php
// View dashboard: layout kartu asli (PPPoE, Pelanggan, Pembayaran) + tabel notifikasi.
?>
<div class="stats-grid">
  <!-- KARTU PPPoE -->
  <a class="card card-link card-pppoe" href="halaman/halaman_pppoe_status.php?tab=all">
    <div class="pppoe-header">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="card-icon">📡</div>
        <div>
          <div class="card-title" style="margin:0">PPPoE Status</div>
          <div class="mini-muted">Monitoring koneksi pelanggan</div>
        </div>
      </div>
      <div class="badge <?= ($pppoe_online>0?'on':'off') ?>">
        <?php $pppoe_pct = $pppoe_total ? round($pppoe_online/$pppoe_total*100) : 0; ?>
        <?=$pppoe_pct?>%
      </div>
    </div>

    <div class="kpi-row">
      <div class="card-value"><?=number_format($pppoe_online)?></div>
      <div class="kpi-label">Online</div>
    </div>

    <div class="progress">
      <span style="width: <?=$pppoe_total? (100*$pppoe_online/max(1,$pppoe_total)) : 0?>%"></span>
    </div>

    <div class="card-stats compact">
      <div class="stat-item">🟢 Online: <strong><?=number_format($pppoe_online)?></strong></div>
      <div class="stat-item">🔴 Offline: <strong><?=number_format($pppoe_offline)?></strong></div>
      <div class="stat-item">📦 Total: <strong><?=number_format($pppoe_total)?></strong></div>
    </div>

    <div class="card-meta">
      Total <?=number_format($pppoe_total)?> akun terdaftar
      <?php if($pppoe_last): ?>
        <br><small style="opacity:.7">Update: <?=h($pppoe_last)?></small>
      <?php endif; ?>
    </div>

    <div class="card-action">Lihat detail PPPoE</div>
  </a>

  <!-- KARTU PELANGGAN -->
  <a class="card card-link card-customers" href="halaman/halaman_pelanggan.php">
    <div class="customers-header">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="card-icon">👥</div>
        <div>
          <div class="card-title" style="margin:0">Data Pelanggan</div>
          <div class="mini-muted">Ringkasan pelanggan aktif</div>
        </div>
      </div>
      <div class="warn-badge" title="Belum ter-link PPPoE">
        <?php $unl = max(0,(int)$customers_unlinked); ?>
        <?=$unl?> Unlinked
      </div>
    </div>

    <div class="kpi-row">
      <div class="card-value"><?=number_format($customers_total)?></div>
      <div class="kpi-label">Total</div>
    </div>

    <?php
      $tot = max(1,(int)$customers_total);
      $bill = (int)$customers_bill;
      $free = max(0,(int)$customers_free);
      $bill_pct = max(0,min(100, round($bill/$tot*100)));
      $free_pct = max(0,min(100, round($free/$tot*100)));
      $rest = max(0, 100 - $bill_pct - $free_pct);
    ?>
    <div class="progress-split" aria-label="Komposisi pelanggan">
      <span class="seg billable" style="width: <?=$bill_pct?>%"></span>
      <span class="seg free" style="width: <?=$free_pct?>%"></span>
      <?php if ($rest>0): ?>
        <span class="seg" style="width: <?=$rest?>%"></span>
      <?php endif; ?>
    </div>

    <div class="card-stats compact">
      <div class="stat-item">💰 Ditagih: <strong><?=number_format($customers_bill)?></strong> (<?=$bill_pct?>%)</div>
      <div class="stat-item">🎁 Gratis: <strong><?=number_format($customers_free)?></strong> (<?=$free_pct?>%)</div>
      <div class="stat-item">⚠️ Unlinked: <strong><?=number_format($customers_unlinked)?></strong></div>
    </div>

    <div class="card-meta">
      Kelola data pelanggan & tautan PPPoE.
    </div>

    <div class="card-action">Lihat detail pelanggan</div>
  </a>

  <!-- KARTU PEMBAYARAN -->
  <a class="card card-link card-payments" href="halaman/halaman_pembayaran.php?tab=unpaid&period=<?=h($period_for_dashboard)?>">
    <?php if(!$pay_info['exists']): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
        <div class="card-icon">💳</div>
        <div>
          <div class="card-title" style="margin:0">Pembayaran — <?=h(period_label($period_for_dashboard))?></div>
          <div class="mini-muted">Periode <?=h(period_label($period_for_dashboard))?></div>
        </div>
      </div>
      <div class="card-meta">
        Tabel <code style="background:rgba(251,191,36,0.1);padding:2px 6px;border-radius:4px">payments</code> tidak ditemukan.
      </div>
      <div class="card-action">Buka modul pembayaran</div>
    <?php else: ?>
      <?php
        $paid   = (int)($pay_info['paid'] ?? 0);
        $unpaid = (int)($pay_info['unpaid'] ?? 0);
        $total  = max(0, $paid + $unpaid);
        $pct    = $total ? round($paid / $total * 100) : 0;
      ?>
      <div class="payments-header">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="card-icon">💳</div>
          <div>
            <div class="card-title" style="margin:0">Pembayaran — <?=h(period_label($period_for_dashboard))?></div>
            <div class="mini-muted">Periode <?=h(period_label($period_for_dashboard))?></div>
          </div>
        </div>
        <div class="badge on"><?=$pct?>% Lunas</div>
      </div>

      <div class="kpi-row">
        <div class="card-value">
          <?= $pay_info['amount_sum'] !== null
               ? rupiah($pay_info['amount_sum'])
               : '<span style="font-size:24px">Data tidak tersedia</span>' ?>
        </div>
        <div class="kpi-label">Total nominal</div>
      </div>

      <div class="progress-split <?= $total ? '' : 'is-empty' ?>" aria-label="Progress pembayaran">
        <span class="seg paid"   style="width: <?= $total ? round($paid/$total*100) : 0 ?>%"></span>
        <span class="seg unpaid" style="width: <?= $total ? max(0,100 - round($paid/$total*100)) : 0 ?>%"></span>
      </div>

      <div class="card-stats compact">
        <div class="stat-item">✅ Lunas: <strong><?=is_null($pay_info['paid'])?'-':number_format($pay_info['paid'])?></strong></div>
        <div class="stat-item">⏳ Pending: <strong><?=is_null($pay_info['unpaid'])?'-':number_format($pay_info['unpaid'])?></strong></div>
      </div>

      <div class="payment-divider"></div>

      <?php if($pay_info['note']): ?>
        <div class="card-meta"><?=h($pay_info['note'])?></div>
      <?php endif; ?>

      <div class="card-action">Lihat detail pembayaran</div>
    <?php endif; ?>
  </a>
</div><!-- end .stats-grid -->

<!-- Tabel Notifikasi Terbaru -->
<div class="table-card">
  <div class="table-header">Riwayat Notifikasi Terbaru</div>
  <table class="tbl notification-table">
    <thead>
      <tr>
        <th style="width:60px">ID</th>
        <th>Pesan Notifikasi</th>
        <th style="width:180px">Waktu</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$recent): ?>
        <tr>
          <td colspan="3">
            <div style="text-align:center;padding:40px">
              <div style="font-size:48px;margin-bottom:16px;opacity:0.3">📭</div>
              <div style="color:var(--gray);font-size:16px">Tidak ada riwayat notifikasi</div>
              <div style="color:var(--gray);font-size:14px;margin-top:8px">Notifikasi akan muncul setelah ada aktivitas sistem</div>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach($recent as $idx => $r): ?>
          <tr style="animation: fadeInUp 0.5s ease-out <?=0.5 + ($idx * 0.05)?>s backwards">
            <td style="text-align:center;font-weight:600;color:var(--primary)">#<?=h($r['id']??'-')?></td>
            <td><?=h($r['message']??'-')?></td>
            <td style="font-size:13px;color:var(--gray)">
              <?php 
                if($r['timestamp']) {
                  echo date('d/m/Y H:i', strtotime($r['timestamp']));
                } else {
                  echo '-';
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
