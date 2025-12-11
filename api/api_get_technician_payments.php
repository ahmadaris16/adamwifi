<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

function prev_period() { return date('Y-m', strtotime('first day of last month')); }
$period = (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])) ? $_GET['period'] : prev_period();

try {
  // Ambil semua yang SUDAH bayar periode ini, dikelompokkan per teknisi
  $sql = "SELECT COALESCE(p.paid_by,'(tanpa nama)') AS technician, c.name AS customer
          FROM payments p
          JOIN customers c ON c.id = p.customer_id
          WHERE p.period = :prd AND p.paid_at IS NOT NULL
          ORDER BY technician ASC, c.name ASC";
  $st = $koneksi->prepare($sql);
  $st->execute([':prd'=>$period]);

  $grouped = [];
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $t = $row['technician'];
    if (!isset($grouped[$t])) $grouped[$t] = [];
    $grouped[$t][] = $row['customer'];
  }

  $out = ['period'=>$period,'technicians'=>[]];
  foreach ($grouped as $tech => $names) {
    $out['technicians'][] = ['technician'=>$tech, 'customers'=>$names];
  }

  echo json_encode(['status'=>'success','data'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'server_error']);
}
