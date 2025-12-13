<?php
// API: update voucher_sisa (JSON)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth.php';
require_admin();
verify_csrf();

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

// Koneksi MySQLi (ikuti kode asli)
$DB_HOST='localhost'; $DB_USER='adah1658_admin'; $DB_PASS='Nuriska16'; $DB_NAME='adah1658_monitor';
$mysqli = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'DB error']); exit;
}

$nama    = $_POST['nama'] ?? '';
$tanggal = $_POST['tanggal'] ?? '';
$v4      = (int)($_POST['voucher_4_jam'] ?? 0);
$v1d     = (int)($_POST['voucher_1_hari'] ?? 0);
$v1b     = (int)($_POST['voucher_1_bulan'] ?? 0);
$total   = $v4 + $v1d + $v1b;

if ($nama === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Nama tidak valid']); exit;
}

$stmt = $mysqli->prepare("UPDATE voucher_sisa SET tanggal=?, total=?, voucher_4_jam=?, voucher_1_hari=?, voucher_1_bulan=? WHERE nama=?");
if ($stmt){
  $stmt->bind_param("siiiis", $tanggal, $total, $v4, $v1d, $v1b, $nama);
  $stmt->execute();
}

if (!$isAjax) {
  $_SESSION['flash'] = 'Perubahan tersimpan.';
}
echo json_encode(['ok'=>true,'message'=>'Perubahan tersimpan.']);
