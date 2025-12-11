<?php
// update_username.php - Mengubah username teknisi

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Ambil data yang dikirim dari aplikasi
$old_username = $_POST['old_username'] ?? '';
$new_username = $_POST['new_username'] ?? '';
$password = $_POST['password'] ?? '';

// Validasi input
if (empty($old_username) || empty($new_username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Semua kolom harus diisi.']);
    exit();
}

try {
    // Langkah 1: Verifikasi apakah username lama dan password cocok
    $stmt_verify = $koneksi->prepare("SELECT id FROM users WHERE username = ? AND password = PASSWORD(?)");
    $stmt_verify->execute([$old_username, $password]);

    if ($stmt_verify->fetch()) {
        // Jika cocok, lanjutkan ke Langkah 2: Cek apakah username baru sudah dipakai
        $stmt_check_new = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check_new->execute([$new_username]);

        if ($stmt_check_new->fetch()) {
            // Jika username baru sudah ada
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Username baru sudah digunakan oleh akun lain.']);
        } else {
            // Jika username baru tersedia, update
            $stmt_update = $koneksi->prepare("UPDATE users SET username = ? WHERE username = ?");

            if ($stmt_update->execute([$new_username, $old_username])) {
                echo json_encode(['status' => 'success', 'message' => 'Username berhasil diperbarui.']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui username.']);
            }
        }
    } else {
        // Jika username lama dan password tidak cocok
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Password yang Anda masukkan salah.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>