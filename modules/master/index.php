<?php
session_start();
require_once '../../config/database.php';

// 1. Verifikasi Session Pengguna
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

// 1. TAMBAH PROVIDER (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_provider = trim($_POST['NamaProvider']);
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_provider)) {
        $stmt = $conn->prepare("INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, NOW())");
        $stmt->execute([$nama_provider, $status_data]);
    }
    header("Location: index.php");
    exit;
}

// 2. EDIT PROVIDER (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $kode_provider = trim($_POST['KodeProvider']);
    $nama_provider = trim($_POST['NamaProvider']);
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($kode_provider) && !empty($nama_provider)) {
        $stmt = $conn->prepare("UPDATE master_provider SET NamaProvider = ?, StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
        $stmt->execute([$nama_provider, $status_data, $kode_provider]);
    }
    header("Location: index.php");
    exit;
}

// 3. HAPUS PROVIDER (GET - delete)
if ($action === 'delete') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM master_provider WHERE KodeProvider = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            echo "<script>alert('Gagal menghapus provider! Data ini sedang digunakan di tabel lain.'); window.location.href='index.php';</script>";
            exit;
        }
    }
    header("Location: index.php");
    exit;
}

// 4. TOGGLE UBAH STATUS (GET - toggle_status)
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_provider WHERE KodeProvider = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            $update = $conn->prepare("UPDATE master_provider SET StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
            $update->execute([$status_baru, $id]);
        }
    }
    header("Location: index.php");
    exit;
}

// 5. AMBIL DATA & HITUNG METRIK
$stmt = $conn->query("SELECT * FROM master_provider ORDER BY KodeProvider ASC");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_operator = count($providers);
$total_aktif = 0;
foreach ($providers as $p) {
    if (($p['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_operator > 0) ? round(($total_aktif / $total_operator) * 100) : 0;

$page_title = "Master Provider - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/provider.html'; 
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>