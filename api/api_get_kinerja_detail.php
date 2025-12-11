<?php
// api_get_kinerja_detail.php - Menyediakan daftar rincian pekerjaan teknisi

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Ambil username teknisi yang sedang login dari aplikasi
$username = $_GET['username'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username teknisi tidak disertakan.']);
    exit();
}

try {
    // 1. Ambil data teknisi (terutama ID-nya)
    $stmt_tech = $koneksi->prepare("SELECT id FROM technicians WHERE username = ?");
    $stmt_tech->execute([$username]);
    $technician = $stmt_tech->fetch(PDO::FETCH_ASSOC);

    if (!$technician) {
        http_response_code(404);
        echo json_encode(['error' => 'Data teknisi tidak ditemukan.']);
        exit();
    }

    $technician_id = $technician['id'];

    // 2. Ambil semua catatan pekerjaan untuk teknisi tersebut di bulan ini, urutkan dari yang terbaru
    $stmt_jobs = $koneksi->prepare(
        "SELECT job_date, fee_amount, description 
         FROM kinerja_teknisi 
         WHERE technician_id = ? AND MONTH(job_date) = MONTH(CURDATE()) AND YEAR(job_date) = YEAR(CURDATE())
         ORDER BY job_date DESC, id DESC"
    );
    $stmt_jobs->execute([$technician_id]);
    $job_details = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    // Kembalikan hasilnya sebagai JSON
    echo json_encode($job_details);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>