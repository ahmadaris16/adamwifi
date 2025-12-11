<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();
set_time_limit(60);

$PPPOE_TABLE='pppoe_status'; $USER_COL='username';

/* 1) Cocok persis (cepat, pakai index) */
$exact = $pdo->exec("
  UPDATE customers c
  JOIN $PPPOE_TABLE s ON c.name = s.$USER_COL
  SET c.pppoe_username = s.$USER_COL
  WHERE (c.pppoe_username IS NULL OR c.pppoe_username='')
");

/* 2) Cocok “mirip” tanpa loop PHP (pakai TEMP TABLE + index) */
$pdo->exec("CREATE TEMPORARY TABLE tmp_pppoe (
  username VARCHAR(191) NOT NULL,
  norm     VARCHAR(191) NOT NULL,
  PRIMARY KEY (username),
  KEY idx_norm (norm)
) ENGINE=MEMORY");
$pdo->exec("
  INSERT INTO tmp_pppoe (username, norm)
  SELECT $USER_COL,
         LOWER(REPLACE(REPLACE(TRIM($USER_COL),' ',''),'.',''))
  FROM $PPPOE_TABLE
");

$pdo->exec("CREATE TEMPORARY TABLE tmp_cust (
  id   INT NOT NULL,
  norm VARCHAR(191) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_norm (norm)
) ENGINE=MEMORY");
$pdo->exec("
  INSERT INTO tmp_cust (id, norm)
  SELECT c.id,
         LOWER(REPLACE(REPLACE(TRIM(c.name),' ',''),'.',''))
  FROM customers c
  WHERE COALESCE(c.pppoe_username,'')='' AND COALESCE(c.name,'')<>''
");

$fuzzy = $pdo->exec("
  UPDATE customers c
  JOIN tmp_cust  tc ON tc.id  = c.id
  JOIN tmp_pppoe tp ON tp.norm= tc.norm
  SET c.pppoe_username = tp.username
  WHERE (c.pppoe_username IS NULL OR c.pppoe_username='')
");

/* 3) Susun pesan yang “manusiawi” */
$remaining = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(pppoe_username,'')=''")->fetchColumn();
$linked = (int)$exact + (int)$fuzzy;

if ($linked === 0 && $remaining === 0) {
  $msg = "Semua pelanggan sudah terhubung ke PPPoE. Tidak ada perubahan.";
} elseif ($linked === 0 && $remaining > 0) {
  $msg = "Tidak ada yang bisa dihubungkan otomatis. Sisa $remaining pelanggan belum terhubung (cek ulang nama pelanggan).";
} else {
  $bagian = [];
  if ((int)$exact > 0) $bagian[] = "$exact cocok langsung";
  if ((int)$fuzzy > 0) $bagian[] = "$fuzzy cocok setelah perapihan nama";
  $detail = $bagian ? " (".implode(" & ", $bagian).")" : "";
  $msg = $remaining > 0
    ? "Berhasil menghubungkan $linked pelanggan$detail. Sisa $remaining pelanggan belum terhubung."
    : "Berhasil menghubungkan $linked pelanggan$detail. Semua pelanggan sekarang sudah terhubung.";
}

$_SESSION['flash'] = $msg;
header('Location: index.php'); exit;
