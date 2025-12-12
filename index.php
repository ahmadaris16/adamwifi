<?php
// Routing ke halaman publik utama
// Arahkan error log ke folder logs supaya tidak menumpuk di root backend
ini_set('error_log', __DIR__ . '/admin/logs/error_log');

require_once __DIR__ . '/public/index.php';
