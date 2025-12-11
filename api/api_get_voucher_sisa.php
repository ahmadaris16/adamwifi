<?php
// public_html/api_get_voucher_sisa.php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

// ==== koneksi DB ====
// Kalau kamu sudah punya file koneksi (mis. dipakai API lain), ganti baris di bawah:
// require_once __DIR__ . '/db.php';  // <-- pakai filemu sendiri bila ada
$DB_HOST = 'localhost';  // ganti bila perlu
$DB_USER = 'adah1658_admin';
$DB_PASS = 'Nuriska16';
$DB_NAME = 'adah1658_monitor';
$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

// Format tanggal Indonesia: "1 Agustus 2025"
function tgl_id($dateStr) {
  static $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $t = strtotime($dateStr);
  if (!$t) return '—';
  return intval(date('j',$t)).' '.$bulan[intval(date('n',$t))].' '.date('Y',$t);
}

$sql = "
  SELECT nama, tanggal, total, voucher_4_jam, voucher_1_hari, voucher_1_bulan
  FROM voucher_sisa
  ORDER BY FIELD(nama,'Ervianto','Nyoto','Anik')
";
$res = $mysqli->query($sql);

$data = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $data[] = [
      'nama'            => $row['nama'],
      'tanggal'         => tgl_id($row['tanggal']),
      'total'           => (int)$row['total'],
      'voucher_4_jam'   => (int)$row['voucher_4_jam'],
      'voucher_1_hari'  => (int)$row['voucher_1_hari'],
      'voucher_1_bulan' => (int)$row['voucher_1_bulan'],
    ];
  }
}

echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
