<?php
// Sidebar untuk layout admin; gunakan menu_active($page, ...) untuk memberi kelas aktif.
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <span class="logo" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M2.5 9.5a16 16 0 0119 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M5 12.55a11.8 11.8 0 0114 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M8.5 16.05a7 7 0 017 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M12 20h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
    </span>
    <h1>Adam Wifi</h1>
  </div>

  <nav class="sidebar-menu">
    <a class="menu-item<?= menu_active($page, 'dashboard') ?>" href="index.php?page=dashboard">
      <span class="menu-icon">🏠</span>
      <span class="menu-text">Dashboard</span>
    </a>
    <a class="menu-item" href="halaman/halaman_pppoe_status.php?tab=all">
      <span class="menu-icon">📡</span>
      <span class="menu-text">Status PPPoE</span>
    </a>
    <a class="menu-item" href="halaman/halaman_pelanggan.php">
      <span class="menu-icon">👥</span>
      <span class="menu-text">Daftar Pelanggan</span>
    </a>
    <a class="menu-item<?= menu_active($page, 'inventaris') ?>" href="halaman/halaman_inventaris.php">
      <span class="menu-icon">📦</span>
      <span class="menu-text">Inventaris</span>
    </a>
    <a class="menu-item<?= menu_active($page, 'reports') ?>" href="index.php?page=reports">
      <span class="menu-icon">🛠</span>
      <span class="menu-text">Job Teknisi</span>
    </a>
    <a class="menu-item<?= menu_active($page, 'voucher') ?>" href="halaman/halaman_voucher.php">
      <span class="menu-icon">🎟️</span>
      <span class="menu-text">Kelola Voucher</span>
    </a>
    <a class="menu-item" href="halaman/halaman_pembayaran.php">
      <span class="menu-icon">💰</span>
      <span class="menu-text">Keuangan</span>
    </a>
    <a class="menu-item" href="#" onclick="document.getElementById('syncForm').submit();return false;">
      <span class="menu-icon">🔄</span>
      <span class="menu-text">Sinkronkan Pelanggan</span>
    </a>
    <a class="menu-item<?= menu_active($page, 'auto_link') ?>" href="api/auto_link_pppoe.php">
      <span class="menu-icon">⚙️</span>
      <span class="menu-text">Auto-Link PPPoE</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a class="menu-item logout" href="logout.php">
      <span class="menu-icon">🚪</span>
      <span class="menu-text">Keluar</span>
    </a>
  </div>
</aside>
