<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=UTF-8');

echo "__DIR__: ".__DIR__."\n";
$path = __DIR__ . '/../config/config.php';
echo "config.php exists? ".(file_exists($path)?'YES':'NO')."\n";
if (file_exists($path)) {
  require_once $path;
  echo "Included config.php\n";
  echo "PDO \$koneksi set? ".(isset($koneksi)?'YES':'NO')."\n";
  if (isset($koneksi)) {
    try { $koneksi->query('SELECT 1'); echo "DB test: OK\n"; }
    catch (Throwable $e) { echo "DB test ERROR: ".$e->getMessage()."\n"; }
  }
}

echo "\nFiles in /admin/:\n";
foreach (scandir(__DIR__) as $f) echo " - $f\n";
