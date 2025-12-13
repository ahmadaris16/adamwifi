<?php
// sinkron_pelanggan.php ? ambil username baru dari pppoe_status dan tambah ke customers.
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../auth.php'; require_admin();
require_once __DIR__ . '/../config/config.php'; // pakai koneksi DB

if (!function_exists('hascol')) {
  function hascol(PDO $pdo,$t,$c){ $s=$pdo->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$c]); return (bool)$s->fetch(); }
}
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo,$t){ try{$pdo->query("SELECT 1 FROM `$t` LIMIT 1");return true;}catch(Throwable $e){return false;} }
}

$PPPOE_TABLE='pppoe_status'; $USER_COL='username';

// CSRF
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  $_SESSION['flash'] = 'Sinkron gagal: token tidak valid.';
  header('Location: ../index.php?page=pelanggan'); exit;
}

// Pastikan tabel ada
if (!table_exists($pdo, $PPPOE_TABLE) || !table_exists($pdo, 'customers')) {
  $_SESSION['flash'] = 'Sinkron gagal: tabel tidak ditemukan.'; header('Location: ../index.php?page=pelanggan'); exit;
}

// Ambil username baru dari pppoe_status yang belum ada di customers
$sqlNew = "SELECT DISTINCT s.`$USER_COL` AS u
           FROM `$PPPOE_TABLE` s
           LEFT JOIN customers c ON c.pppoe_username = s.`$USER_COL`
           WHERE COALESCE(s.`$USER_COL`, '') <> '' AND c.pppoe_username IS NULL";
$newUsers = $pdo->query($sqlNew)->fetchAll(PDO::FETCH_COLUMN);

if (!$newUsers) {
  $_SESSION['flash'] = 'Tidak ada username baru untuk disinkronkan.'; header('Location: ../index.php?page=pelanggan'); exit;
}

// Siapkan kolom aman yang tersedia di customers
$cols = ['pppoe_username'];
if (hascol($pdo,'customers','name'))      $cols[] = 'name';
if (hascol($pdo,'customers','billable'))  $cols[] = 'billable';
if (hascol($pdo,'customers','active'))    $cols[] = 'active';
if (hascol($pdo,'customers','created_at'))$cols[] = 'created_at';

$ph  = array_map(function($c){ return ':'.$c; }, $cols);
$sqlIns = "INSERT INTO customers (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
$stmtIns = $pdo->prepare($sqlIns);

$ins = 0;
$pdo->beginTransaction();
try {
  foreach ($newUsers as $u) {
    $params = [];
    foreach ($cols as $c) {
      if ($c === 'pppoe_username') { $params[':pppoe_username'] = $u; }
      elseif ($c === 'name')       { $params[':name'] = $u; }
      elseif ($c === 'billable')   { $params[':billable'] = 1; }
      elseif ($c === 'active')     { $params[':active'] = 1; }
      elseif ($c === 'created_at') { $params[':created_at'] = date('Y-m-d H:i:s'); }
    }
    try { $stmtIns->execute($params); $ins++; } catch (Throwable $e) { /* skip baris yang gagal */ }
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  $_SESSION['flash'] = 'Sinkron gagal: '.$e->getMessage();
  header('Location: ../index.php?page=pelanggan'); exit;
}

$_SESSION['flash'] = "Sinkron selesai: {$ins} pelanggan baru ditambahkan.";
header('Location: ../index.php?page=pelanggan'); exit;
