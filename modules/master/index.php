<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

// ---------------------------------------------------------
// PROSES CRUD: TABEL master_provider
// ---------------------------------------------------------

// Aksi Tambah Data Provider Baru (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_provider = trim($_POST['NamaProvider']);
    // Sinkronisasi dengan ENUM database ('AKTIF' atau 'TIDAK')
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_provider)) {
        $stmt = $conn->prepare("INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, NOW())");
        $stmt->execute([$nama_provider, $status_data]);
    }
    header("Location: index.php");
    exit;
}

// Aksi Toggle Ubah Status (AKTIF <-> TIDAK)
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_provider WHERE KodeProvider = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Jika saat ini AKTIF, ubah menjadi TIDAK. Jika bukan AKTIF, ubah kembali ke AKTIF
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            
            $update = $conn->prepare("UPDATE master_provider SET StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
            $update->execute([$status_baru, $id]);
        }
    }
    header("Location: index.php");
    exit;
}

// Ambil seluruh data operator seluler dari tabel master_provider
$stmt = $conn->query("SELECT * FROM master_provider ORDER BY KodeProvider ASC");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kalkulasi Metrik Ringkasan (KPI Cards)
$total_operator = count($providers);
$total_aktif = 0;
foreach ($providers as $p) {
    if (($p['StatusData'] ?? '') === 'AKTIF') {
        $total_aktif++;
    }
}
$persentase_aktif = ($total_operator > 0) ? round(($total_aktif / $total_operator) * 100) : 0;

// ---------------------------------------------------------
// RENDER VIEW DENGAN BASE LAYOUT
// ---------------------------------------------------------
$page_title = "Master Data - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/provider.html'; 
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>