<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

function prev_period() { return date('Y-m', strtotime('first day of last month')); }

$period = (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period']))
  ? $_GET['period'] : prev_period();

try {
  // PAID: daftar yang sudah bayar (terbaru dulu)
  $sqlPaid = "SELECT p.customer_id AS id,
                   c.name         AS name,
                   c.phone        AS phone,
                   p.amount,
                   DATE_FORMAT(p.paid_at,'%Y-%m-%d %H:%i:%s') AS paid_at,
                   p.paid_by      AS technician
            FROM payments p
            JOIN customers c ON c.id = p.customer_id
            WHERE p.period = :prd AND p.paid_at IS NOT NULL
            ORDER BY p.paid_at DESC";

  $st = $koneksi->prepare($sqlPaid);
  $st->execute([':prd'=>$period]);
  $paid = $st->fetchAll(PDO::FETCH_ASSOC);

  // UNPAID: semua pelanggan yang BELUM punya bayar (periode ini)
  $sqlUnpaid = "SELECT c.id, c.name, c.phone, 0 AS amount
              FROM customers c
              WHERE c.billable = 1 AND c.active = 1
                AND NOT EXISTS (
                  SELECT 1 FROM payments p
                  WHERE p.customer_id = c.id AND p.period = :prd AND p.paid_at IS NOT NULL
                )
              ORDER BY c.name ASC";

  $st2 = $koneksi->prepare($sqlUnpaid);
  $st2->execute([':prd'=>$period]);
  $unpaid = $st2->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'status' => 'success',
    'data' => [
      'period'       => $period,
      'unpaid_count' => count($unpaid),
      'paid_count'   => count($paid),
      'unpaid'       => $unpaid,
      'paid'         => $paid
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'server_error']);
}
