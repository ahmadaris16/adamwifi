<?php
// api_get_notifications.php - Mengambil semua riwayat notifikasi dari database

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    // Ambil semua data dari tabel notification_history, urutkan dari yang terbaru
    // Kita batasi hanya untuk 7 hari terakhir agar konsisten
    $stmt = $koneksi->prepare(
        "SELECT id, message, timestamp 
         FROM notification_history 
         WHERE timestamp >= NOW() - INTERVAL 7 DAY 
         ORDER BY timestamp DESC"
    );
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kembalikan data dalam format JSON
    echo json_encode($notifications);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data: ' . $e->getMessage()]);
}
?>