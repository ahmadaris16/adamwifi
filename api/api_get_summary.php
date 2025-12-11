<?php
// api_get_summary.php - Menyediakan data ringkas untuk dashboard

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    // Hitung total pengguna dari tabel pppoe_status
    $stmt_total = $koneksi->prepare("SELECT COUNT(id) as total_users FROM pppoe_status");
    $stmt_total->execute();
    $total_users = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Hitung pengguna online
    $stmt_online = $koneksi->prepare("SELECT COUNT(id) as online_users FROM pppoe_status WHERE status = 'online'");
    $stmt_online->execute();
    $online_users = $stmt_online->fetch(PDO::FETCH_ASSOC)['online_users'];

    // Hitung pengguna offline
    $offline_users = $total_users - $online_users;

    // Siapkan data untuk dikirim sebagai JSON
    $summary = [
        'total' => (int)$total_users,
        'online' => (int)$online_users,
        'offline' => (int)$offline_users
    ];

    echo json_encode($summary);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data ringkasan: ' . $e->getMessage()]);
}
?>