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

// 2. Proteksi Hak Akses (AM.UI Ditolak)
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? '';

if ($user_role === 'AM.UI') {
    $_SESSION['flash_alert'] = [
        'title' => 'Akses Ditolak!',
        'text'  => 'Peran AM.UI hanya memiliki hak akses untuk halaman Perintah Baku.',
        'icon'  => 'error'
    ];
    header("Location: /modules/master/pesan_Controller.php");
    exit;
}

$action = $_GET['action'] ?? 'index';

// 3. Tambah Provider (POST - store)
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_provider = trim($_POST['NamaProvider'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_provider)) {
        $stmt = $conn->prepare("INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, NOW())");
        $stmt->execute([$nama_provider, $status_data]);
    }
    header("Location: /modules/master/provider_Controller.php");
    exit;
}

// 4. Edit Provider (POST - update)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_provider = trim($_POST['KodeProvider'] ?? '');
    $nama_provider = trim($_POST['NamaProvider'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($kode_provider) && !empty($nama_provider)) {
        $stmt = $conn->prepare("UPDATE master_provider SET NamaProvider = ?, StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
        $stmt->execute([$nama_provider, $status_data, $kode_provider]);
    }
    header("Location: /modules/master/provider_Controller.php");
    exit;
}

// 5. Hapus Provider (GET - delete)
if ($action === 'delete') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM master_provider WHERE KodeProvider = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            echo "<script>
                alert('Gagal menghapus provider! Data ini sedang digunakan di tabel lain.');
                window.location.href = '/modules/master/provider_Controller.php';
            </script>";
            exit;
        }
    }
    header("Location: /modules/master/provider_Controller.php");
    exit;
}

// 6. Toggle Status (AKTIF / TIDAK)
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
    header("Location: /modules/master/provider_Controller.php");
    exit;
}

// 7. Query Data & Metrik KPI
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