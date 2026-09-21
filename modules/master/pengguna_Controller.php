<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Verifikasi Session Login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$sub    = $_GET['sub'] ?? 'semua';
$action = $_GET['action'] ?? 'index';

// 2. Aksi Tambah Pengguna Baru (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_akun     = trim($_POST['NamaAkun']);
    $alamat_email  = trim($_POST['AlamatEmail']);
    $nama_pengguna = trim($_POST['NamaPengguna']);
    $nomor_kontak  = trim($_POST['NomorKontak']);
    $kata_kunci    = trim($_POST['KataKunci']);
    $kode_hak      = trim($_POST['KodeHak']);
    $unit_upi      = trim($_POST['UnitUpi']);
    $unit_ap       = trim($_POST['UnitAp']);
    $unit_up       = trim($_POST['UnitUp']);
    $status_data   = $_POST['StatusData'] ?? 'AKTIF';

    if (!empty($nama_akun)) {
        $stmt = $conn->prepare("INSERT INTO master_pengguna 
            (NamaAkun, AlamatEmail, NamaPengguna, NomorKontak, KataKunci, KodeHak, UnitUpi, UnitAp, UnitUp, StatusData, WaktuData) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nama_akun, $alamat_email, $nama_pengguna, $nomor_kontak, $kata_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data]);
    }
    header("Location: pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// 3. Aksi Ubah Status (Toggle AKTIF <-> TIDAK)
if ($action === 'toggle_status') {
    $akun = $_GET['id'] ?? null;
    if ($akun) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_pengguna WHERE NamaAkun = ?");
        $stmt->execute([$akun]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            $update = $conn->prepare("UPDATE master_pengguna SET StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
            $update->execute([$status_baru, $akun]);
        }
    }
    header("Location: pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// 4. Query Data dengan Filter Sub-Entitas
$query = "SELECT * FROM master_pengguna WHERE 1=1";
$params = [];

if ($sub === 'ui') {
    $query .= " AND KodeHak IN ('SF.UI', 'AM.UI', 'MB.UI', 'SA.KP')";
} elseif ($sub === 'up3') {
    $query .= " AND KodeHak IN ('SF.AP', 'TL.AP', 'AM.AP')";
} elseif ($sub === 'ulp') {
    $query .= " AND KodeHak IN ('SF.UP', 'TL.UP', 'ML.UP')";
}

$query .= " ORDER BY WaktuData DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Kalkulasi Metrik KPI
$total_stmt = $conn->query("SELECT COUNT(*) FROM master_pengguna");
$total_akun = (int) $total_stmt->fetchColumn();

$aktif_stmt = $conn->query("SELECT COUNT(*) FROM master_pengguna WHERE StatusData = 'AKTIF'");
$total_aktif = (int) $aktif_stmt->fetchColumn();

$persentase_aktif = ($total_akun > 0) ? round(($total_aktif / $total_akun) * 100) : 0;

// 6. Render ke Layout Utama
$page_title = "Master Pengguna - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>