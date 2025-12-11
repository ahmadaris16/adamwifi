<?php
// api_get_kinerja.php - Menyediakan data kinerja untuk dashboard teknisi

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
    // 1. Ambil data teknisi (terutama ID dan Gaji Pokok)
    $stmt_tech = $koneksi->prepare("SELECT id, base_salary FROM technicians WHERE username = ?");
    $stmt_tech->execute([$username]);
    $technician = $stmt_tech->fetch(PDO::FETCH_ASSOC);

    if (!$technician) {
        http_response_code(404);
        echo json_encode(['error' => 'Data teknisi tidak ditemukan.']);
        exit();
    }

    $technician_id = $technician['id'];
    $base_salary = (int)$technician['base_salary'];

    // 2. Hitung total fee untuk bulan ini
    $stmt_fee = $koneksi->prepare(
        "SELECT SUM(fee_amount) as total_fee 
         FROM kinerja_teknisi 
         WHERE technician_id = ? AND MONTH(job_date) = MONTH(CURDATE()) AND YEAR(job_date) = YEAR(CURDATE())"
    );
    $stmt_fee->execute([$technician_id]);
    $total_fee = (int)$stmt_fee->fetch(PDO::FETCH_ASSOC)['total_fee'];

    // 3. Hitung total gaji berjalan
    $total_salary = $base_salary + $total_fee;

    // Siapkan data untuk dikirim sebagai JSON
    $dashboard_data = [
        'username' => $username,
        'base_salary' => $base_salary,
        'total_fee_this_month' => $total_fee,
        'total_salary_running' => $total_salary
    ];

    echo json_encode($dashboard_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>