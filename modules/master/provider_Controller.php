<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

// 1. Verifikasi Sesi Login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: /modules/auth/login.php");
    exit;
}

// 2. Ambil Role Aktif
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';

// Jika role AM.UI, paksa ke halaman pesan
if ($user_role === 'AM.UI') {
    header("Location: index.php?page=master&sub=pesan");
    exit;
}

// PERBAIKAN UTAMA: Berikan izin penuh untuk SA.KP dan SF.UI
if (!in_array($user_role, ['SA.KP', 'SF.UI'])) {
    echo "<script>
        alert('Akses Ditolak: Peran " . htmlspecialchars($user_role) . " tidak memiliki wewenang untuk membuka modul provider.');
        window.location.href = 'index.php?page=dashboard';
    </script>";
    exit;
}

$action = $_GET['action'] ?? 'index';

// 3. Tambah Provider (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_provider = trim($_POST['NamaProvider'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_provider)) {
        $stmt = $conn->prepare("INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, NOW())");
        $stmt->execute([$nama_provider, $status_data]);
    }
    header("Location: index.php?page=master&sub=provider");
    exit;
}

// 4. Edit Provider (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $kode_provider = trim($_POST['KodeProvider'] ?? '');
    $nama_provider = trim($_POST['NamaProvider'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($kode_provider) && !empty($nama_provider)) {
        $stmt = $conn->prepare("UPDATE master_provider SET NamaProvider = ?, StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
        $stmt->execute([$nama_provider, $status_data, $kode_provider]);
    }
    header("Location: index.php?page=master&sub=provider");
    exit;
}

// 5. Toggle Status (GET - toggle_status)
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
    header("Location: index.php?page=master&sub=provider");
    exit;
}

// 6. Query Data Provider
$stmt = $conn->query("SELECT * FROM master_provider ORDER BY KodeProvider ASC");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_operator = count($providers);
$total_aktif = 0;
foreach ($providers as $p) {
    if (($p['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_operator > 0) ? round(($total_aktif / $total_operator) * 100) : 0;

$page_title = "Master Provider - Digita S41";

// RENDER DENGAN BASE LAYOUT LENGKAP
ob_start();
require_once __DIR__ . '/../../templates/master/provider.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>