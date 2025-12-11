<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

try {
    // Siapkan query untuk mengambil semua data dari tabel pppoe_status
    // KODE BARU (URUT NAMA A-Z)
    $stmt = $koneksi->prepare("SELECT username, ip, status, last_update FROM pppoe_status ORDER BY username ASC");
    $stmt->execute();
    $data_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kembalikan data dalam format JSON
    echo json_encode([
        'status' => 'success',
        'data' => $data_users
    ]);

} catch (PDOException $e) {
    // Tangani error jika terjadi masalah pada database
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data: ' . $e->getMessage()
    ]);
}
?>