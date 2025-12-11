<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

function prev_period() { return date('Y-m', strtotime('first day of last month')); }

$customer_id = isset($_REQUEST['customer_id']) ? (int)$_REQUEST['customer_id'] : 0;
$amount      = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;   // isi dari app (boleh 0 dulu)
$technician  = isset($_REQUEST['technician']) ? trim($_REQUEST['technician']) : '';
$period      = (isset($_REQUEST['period']) && preg_match('/^\d{4}-\d{2}$/', $_REQUEST['period']))
               ? $_REQUEST['period'] : prev_period();

if ($customer_id <= 0 || $technician === '') {
  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>'bad_params']); exit;
}

try {
  // cek sudah ada record periode ini atau belum
  $up = $koneksi->prepare("
  INSERT INTO payments (customer_id, period, amount, paid_at, paid_by)
  VALUES (?, ?, ?, NOW(), ?)
  ON DUPLICATE KEY UPDATE
    amount  = VALUES(amount),
    paid_at = NOW(),
    paid_by = VALUES(paid_by)
");
$ok = $up->execute([$customer_id, $period, $amount, $technician]);


  echo json_encode($ok ? ['status'=>'success'] : ['status'=>'error','message'=>'save_failed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'server_error']);
}
