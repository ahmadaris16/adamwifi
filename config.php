<?php
// CONFIG.PHP - Setting Database & Mikrotik

// Database Rumahweb
$db_host = "localhost";
$db_name = "adah1658_monitor";
$db_user = "adah1658_admin";
$db_pass = "Nuriska16";  // <-- GANTI dengan password database yang tadi dibuat

// Mikrotik API
$mikrotik_ip = "103.63.26.147";
$mikrotik_user = "monitoring";
$mikrotik_pass = "Nuriska16";  // <-- GANTI dengan password user monitoring di Mikrotik
$mikrotik_port = "8728";

// Koneksi Database
try {
    $koneksi = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

?>