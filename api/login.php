<?php
// login.php - Memverifikasi login dari aplikasi Android

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Ambil data yang dikirim dari aplikasi
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validasi input
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username dan password harus diisi.']);
    exit();
}

try {
    // Cari user berdasarkan username
    $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika user tidak ditemukan
    if (!$user) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Username tidak ditemukan.']);
        exit();
    }

    // Verifikasi password
    // Kita perlu query lagi karena password di-hash dengan cara lama
    $stmt_pass = $koneksi->prepare("SELECT * FROM users WHERE username = ? AND password = PASSWORD(?)");
    $stmt_pass->execute([$username, $password]);

    if ($stmt_pass->fetch()) {
        // Jika password benar
        echo json_encode(['status' => 'success', 'message' => 'Login berhasil.']);
    } else {
        // Jika password salah
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Password salah.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>