<?php
// API: simpan Job Teknisi (balik JSON)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php'; 
require_admin();

try {
  verify_csrf();

  $technician_id = (int)($_POST['technician_id'] ?? 0);
  $job_date      = $_POST['job_date'] ?? '';
  $fee_amount    = $_POST['fee_amount'] ?? '';
  $description   = trim($_POST['description'] ?? '');

  if ($technician_id<=0 || $job_date==='' || !is_numeric($fee_amount)) {
    throw new Exception('Teknisi, tanggal, dan fee wajib diisi dengan benar.');
  }

  $ins = $pdo->prepare("INSERT INTO kinerja_teknisi (technician_id, job_date, fee_amount, description)
                        VALUES (?,?,?,?)");
  $ins->execute([$technician_id, $job_date, (float)$fee_amount, $description!==''?$description:null]);

  $_SESSION['flash'] = 'Job teknisi tersimpan.';
  if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

  $qs = http_build_query(['from'=>$job_date, 'to'=>$job_date, 'tech'=>$technician_id]);
  echo json_encode(['ok'=>true, 'redirect'=>"reports.php?$qs"]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
