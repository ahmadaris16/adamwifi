<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$path = __DIR__ . '/../config/config.php';
if (!file_exists($path)) { die('config.php TIDAK KETEMU di: '.$path); }
require_once $path;

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($koneksi)) { die('Variabel $koneksi tidak ada di config.php'); }
$pdo = $koneksi;
try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
