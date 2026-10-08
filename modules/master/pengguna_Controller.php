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

// 2. Ambil Role Aktif (Mendukung KodeHak maupun Role)
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';

// Jika role AM.UI, paksa ke halaman pesan
if ($user_role === 'AM.UI') {
    header("Location: index.php?page=master&sub=pesan");
    exit;
}

// PERBAIKAN UTAMA: Berikan izin penuh untuk SA.KP dan SF.UI
if (!in_array($user_role, ['SA.KP', 'SF.UI'])) {
    echo "<script>
        alert('Akses Ditolak: Peran " . htmlspecialchars($user_role) . " tidak memiliki wewenang untuk membuka modul pengguna.');
        window.location.href = 'index.php?page=dashboard';
    </script>";
    exit;
}

$sub    = $_GET['filter'] ?? 'semua';
$action = $_GET['action'] ?? 'index';

// 3. Tambah Pengguna (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = $_POST['KodeHak'] ?? 'SF.AP';
    $unit_upi    = '56';
    $unit_ap     = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : null;
    $unit_up     = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : null;
    $status_data = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_akun) && !empty($kata_kunci)) {
        $stmt = $conn->prepare("INSERT INTO master_pengguna (NamaAkun, AlamatEmail, NamaPengguna, NomorKontak, KataKunci, KodeHak, UnitUpi, UnitAp, UnitUp, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nama_akun, $email, $nama_user, $kontak, $kata_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data]);
    }
    header("Location: index.php?page=master&sub=pengguna");
    exit;
}

// 4. Edit Pengguna (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = $_POST['KodeHak'] ?? 'SF.AP';
    $unit_upi    = '56';
    $unit_ap     = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : null;
    $unit_up     = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : null;
    $status_data = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($kata_kunci)) {
        $stmt = $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KataKunci = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
        $stmt->execute([$email, $nama_user, $kontak, $kata_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
    } else {
        $stmt = $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
        $stmt->execute([$email, $nama_user, $kontak, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
    }

    header("Location: index.php?page=master&sub=pengguna");
    exit;
}

// 5. Toggle Status (GET - toggle_status)
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_pengguna WHERE NamaAkun = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            $update = $conn->prepare("UPDATE master_pengguna SET StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
            $update->execute([$status_baru, $id]);
        }
    }
    header("Location: index.php?page=master&sub=pengguna");
    exit;
}

// 6. Query Data Pengguna
$sql = "SELECT p.*, i.SingkatanNama AS NamaUpi, a.SingkatanNama AS NamaAp, u.SingkatanNama AS NamaUp 
        FROM master_pengguna p 
        LEFT JOIN master_upi i ON p.UnitUpi = i.UnitUpi 
        LEFT JOIN master_ap a ON p.UnitAp = a.UnitAp 
        LEFT JOIN master_up u ON p.UnitUp = u.UnitUp 
        WHERE 1=1";

if ($sub === 'ui') {
    $sql .= " AND p.KodeHak LIKE '%.UI'";
} elseif ($sub === 'up3') {
    $sql .= " AND p.KodeHak LIKE '%.AP'";
} elseif ($sub === 'ulp') {
    $sql .= " AND p.KodeHak LIKE '%.UP'";
}

$sql .= " ORDER BY p.NamaAkun ASC";

$stmt = $conn->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_akun = count($users);
$total_aktif = 0;
foreach ($users as $pg) {
    if (($pg['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_akun > 0) ? round(($total_aktif / $total_akun) * 100) : 0;

$page_title = "Master Pengguna - Digita S41";

// RENDER DENGAN BASE LAYOUT LENGKAP
ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>