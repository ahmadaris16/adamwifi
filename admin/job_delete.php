<?php
// job_delete.php — Hapus Job Teknisi (POST)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$back_from = $_POST['back_from'] ?? null;
$back_to   = $_POST['back_to'] ?? null;
$back_tech = (int)($_POST['back_tech'] ?? 0);

// ambil data dulu (buat redirect yang ringan)
$st = $pdo->prepare("SELECT job_date, technician_id FROM kinerja_teknisi WHERE id=?");
$st->execute([$id]); $row = $st->fetch();

if ($id>0) {
  $del = $pdo->prepare("DELETE FROM kinerja_teknisi WHERE id=?");
  $del->execute([$id]);
  $_SESSION['flash'] = 'Job teknisi dihapus.';
} else {
  $_SESSION['flash'] = 'ID tidak valid.';
}

$from = $back_from ?: ($row['job_date'] ?? date('Y-m-d'));
$to   = $back_to   ?: $from;
$tech = $back_tech ?: (int)($row['technician_id'] ?? 0);

$qs = http_build_query(['from'=>$from,'to'=>$to,'tech'=>$tech]);
header('Location: reports.php?'.$qs); exit;
