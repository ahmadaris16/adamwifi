<?php
require_once __DIR__ . '/admin_config.php';

/**
 * Normalisasi sesi admin ke satu kunci: $_SESSION['admin_user']
 * Menerima berbagai pola lama: is_admin/admin_id, admin[], logged_in/user_id, dll.
 */
function admin_user() {
  // Sudah standar
  if (!empty($_SESSION['admin_user']) && is_array($_SESSION['admin_user'])) {
    return $_SESSION['admin_user'];
  }
  // Pola: is_admin + admin_id (+ admin_username/role)
  if (!empty($_SESSION['is_admin']) && !empty($_SESSION['admin_id'])) {
    return $_SESSION['admin_user'] = [
      'id'       => $_SESSION['admin_id'],
      'username' => $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'admin'),
      'role'     => $_SESSION['role'] ?? 'Owner',
    ];
  }
  // Pola: admin[] array
  if (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    return $_SESSION['admin_user'] = [
      'id'       => $_SESSION['admin']['id'] ?? null,
      'username' => $_SESSION['admin']['username'] ?? ($_SESSION['username'] ?? 'admin'),
      'role'     => $_SESSION['admin']['role'] ?? 'Owner',
    ];
  }
  // Pola: logged_in + user_id
  if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    return $_SESSION['admin_user'] = [
      'id'       => $_SESSION['user_id'],
      'username' => $_SESSION['username'] ?? 'admin',
      'role'     => $_SESSION['role'] ?? 'Owner',
    ];
  }
  return null;
}

function require_admin() {
  if (!admin_user()) {
    header('Location: login.php'); exit;
  }
}

function csrf_token() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_field() { echo '<input type="hidden" name="_csrf" value="'.h(csrf_token()).'">'; }
function verify_csrf() {
  if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'])) {
    http_response_code(400); exit('Invalid CSRF');
  }
}
