<?php
// register_token.php - Menerima dan menyimpan token FCM dari aplikasi Android

// Sertakan file konfigurasi database Anda
require_once __DIR__ . '/../config/config.php';

// Ambil token yang dikirim dari aplikasi (via metode POST)
$token = $_POST['token'] ?? '';

// Validasi token agar tidak kosong
if (empty($token)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Token tidak diterima.']);
    exit();
}

try {
    // Siapkan perintah SQL untuk memasukkan token baru jika belum ada
    // ON DUPLICATE KEY UPDATE akan memperbarui waktu jika token sudah ada
    // PERBAIKAN: Menggunakan variabel $koneksi (bukan $koneosi)
    $stmt = $koneksi->prepare(
        "INSERT INTO fcm_tokens (token) VALUES (?)
         ON DUPLICATE KEY UPDATE timestamp = NOW()"
    );

    // Eksekusi perintah
    if ($stmt->execute([$token])) {
        // Beri respons sukses
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Token berhasil disimpan.']);
    } else {
        // Beri respons gagal jika ada masalah eksekusi
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan token.']);
    }

} catch (PDOException $e) {
    // Tangani error jika terjadi masalah koneksi atau query database
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

?>