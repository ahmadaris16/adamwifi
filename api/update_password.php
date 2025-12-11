<?php
// update_password.php - Mengubah password teknisi

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Ambil data yang dikirim dari aplikasi
$username = $_POST['username'] ?? '';
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Validasi input
if (empty($username) || empty($old_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Semua kolom harus diisi.']);
    exit();
}

try {
    // Langkah 1: Verifikasi apakah username dan password lama cocok
    $stmt_verify = $koneksi->prepare("SELECT id FROM users WHERE username = ? AND password = PASSWORD(?)");
    $stmt_verify->execute([$username, $old_password]);

    if ($stmt_verify->fetch()) {
        // Jika cocok, lanjutkan ke Langkah 2: Update password baru
        $stmt_update = $koneksi->prepare("UPDATE users SET password = PASSWORD(?) WHERE username = ?");

        if ($stmt_update->execute([$new_password, $username])) {
            echo json_encode(['status' => 'success', 'message' => 'Password berhasil diperbarui.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui password.']);
        }

    } else {
        // Jika username dan password lama tidak cocok
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Password lama yang Anda masukkan salah.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>