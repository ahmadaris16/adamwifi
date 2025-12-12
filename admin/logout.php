<?php
require_once __DIR__ . '/config/persiapan_admin.php';
$_SESSION = [];
session_destroy();
header('Location: login.php'); exit;
exit;
