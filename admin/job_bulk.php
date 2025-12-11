<?php
// job_bulk.php — aksi massal (saat ini: hapus terpilih)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin(); verify_csrf();

$action = $_POST['action'] ?? '';
$ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));

$from = $_POST['from'] ?? date('Y-m-01');
$to   = $_POST['to']   ?? date('Y-m-d');
$tech = (int)($_POST['tech'] ?? 0);
$qs = http_build_query(['from'=>$from,'to'=>$to,'tech'=>$tech]);

if (!$ids) {
  $_SESSION['flash'] = 'Tidak ada baris yang dipilih.';
  header('Location: reports.php?'.$qs); exit;
}

if ($action === 'delete') {
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $del = $pdo->prepare("DELETE FROM kinerja_teknisi WHERE id IN ($in)");
  $del->execute($ids);
  $n = $del->rowCount();
  $_SESSION['flash'] = "Hapus berhasil: $n job.";
  header('Location: reports.php?'.$qs); exit;
}

$_SESSION['flash'] = 'Aksi tidak dikenal.';
header('Location: reports.php?'.$qs); exit;
