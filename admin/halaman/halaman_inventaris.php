<?php
// Placeholder Inventaris - ditampilkan via layout index.php
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="card table-card">
  <div class="table-header">Inventaris</div>
  <div class="placeholder" style="color:var(--gray); display:flex; align-items:center; gap:12px; font-size:15px;">
    <span style="font-size:18px;">📦</span>
    <div>
      <strong>Fitur akan datang.</strong><br>
      Silakan kembali lagi nanti.
    </div>
  </div>
  <style>
    /* placeholder kecil agar konsisten tanpa mengganggu halaman lain */
    .table-card .placeholder strong{ color:var(--light); }
  </style>
</div>
