<?php
// pelanggan_update.php - update kontak/tagihan via modal
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php'; require_admin();

function json_out($ok, $message, array $extra = []){
  echo json_encode(array_merge(['ok'=>$ok,'message'=>$message], $extra));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  json_out(false, 'Metode tidak diizinkan.');
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  json_out(false, 'Token tidak valid.');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  json_out(false, 'ID tidak valid.');
}

$phone    = trim($_POST['phone'] ?? '');
$is_free  = isset($_POST['is_free']) ? 1 : 0;
$billable = $is_free ? 0 : 1;

try {
  $stmt = $pdo->prepare("UPDATE customers SET phone=?, billable=? WHERE id=?");
  $stmt->execute([$phone !== '' ? $phone : null, $billable, $id]);
  json_out(true, 'Perubahan disimpan.', ['billable' => $billable]);
} catch (Throwable $e) {
  json_out(false, 'Gagal menyimpan: ' . $e->getMessage());
}
