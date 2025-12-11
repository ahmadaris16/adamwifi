<?php
// Kode untuk menampilkan detail error (untuk sementara)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Mengatur header sebagai JSON
header('Content-Type: application/json');

// Informasi koneksi database kamu
$db_host = "localhost";
$db_name = "adah1658_monitor";
$db_user = "adah1658_admin";
$db_pass = "Nuriska16";

// Buat koneksi ke database dengan variabel yang BENAR
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit();
}

// Tentukan periode (bulan sebelumnya)
$target_timestamp = strtotime('first day of last month');
$bulan = (int)date('n', $target_timestamp);
$tahun = (int)date('Y', $target_timestamp);

// Array untuk nama bulan dalam Bahasa Indonesia
$nama_bulan_id = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$nama_bulan = $nama_bulan_id[$bulan];

// Inisialisasi variabel hasil
$jumlah_lunas = 0; // Biarkan 0 untuk sementara
$total_pelanggan = 0;

// --- Kueri 1: Hitung total pelanggan ---
// PASTIKAN NAMA TABEL 'users' DAN KOLOM 'status' SUDAH BENAR
$sql_total = "SELECT COUNT(id) as total FROM users WHERE status = 'aktif'";
$result_total = $conn->query($sql_total);
if ($result_total && $result_total->num_rows > 0) {
    $row = $result_total->fetch_assoc();
    $total_pelanggan = (int)$row['total'];
}

/* // --- BAGIAN INI DINONAKTIFKAN SEMENTARA KARENA ERROR ---

// --- Kueri 2: Hitung pelanggan yang sudah lunas ---
$sql_lunas = "SELECT COUNT(id) as total_lunas FROM pembayaran WHERE bulan = ? AND tahun = ? AND status_bayar = 'lunas'";
$stmt = $conn->prepare($sql_lunas);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$result_lunas = $stmt->get_result();
if ($result_lunas && $result_lunas->num_rows > 0) {
    $row = $result_lunas->fetch_assoc();
    $jumlah_lunas = (int)$row['total_lunas'];
}
$stmt->close();

*/

// Tutup koneksi database
$conn->close();

// Siapkan data untuk output JSON
$response = [
    'status' => 'success',
    'data' => [
        'bulan' => $bulan,
        'tahun' => $tahun,
        'nama_bulan' => $nama_bulan,
        'jumlah_lunas' => $jumlah_lunas,
        'total_pelanggan' => $total_pelanggan
    ]
];

// Cetak output JSON
echo json_encode($response, JSON_PRETTY_PRINT);

?>