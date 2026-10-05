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

// Tangkap sub entitas filter (default: 'semua')
$sub    = $_GET['sub'] ?? 'semua';
$action = $_GET['action'] ?? 'index';

// ---------------------------------------------------------
// 2. TAMBAH PENGGUNA (POST - store)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = $_POST['KodeHak'] ?? 'SF.AP';
    $unit_upi    = !empty($_POST['UnitUpi']) ? $_POST['UnitUpi'] : null;
    $unit_ap     = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : null;
    $unit_up     = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : null;
    $status_data = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_akun) && !empty($kata_kunci)) {
        $hash_pass = md5($kata_kunci);
        $stmt = $conn->prepare("INSERT INTO master_pengguna (NamaAkun, AlamatEmail, NamaPengguna, NomorKontak, KataKunci, KodeHak, UnitUpi, UnitAp, UnitUp, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nama_akun, $email, $nama_user, $kontak, $hash_pass, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data]);
    }
    header("Location: /modules/master/pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// ---------------------------------------------------------
// 3. EDIT PENGGUNA (POST - update) -> Username (NamaAkun) Locked
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = $_POST['KodeHak'] ?? 'SF.AP';
    $unit_upi    = !empty($_POST['UnitUpi']) ? $_POST['UnitUpi'] : null;
    $unit_ap     = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : null;
    $unit_up     = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : null;
    $status_data = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($kata_kunci)) {
        // Jika password diisi
        $hash_pass = md5($kata_kunci);
        $stmt = $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KataKunci = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
        $stmt->execute([$email, $nama_user, $kontak, $hash_pass, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
    } else {
        // Jika password tidak diubah
        $stmt = $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?");
        $stmt->execute([$email, $nama_user, $kontak, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
    }

    header("Location: /modules/master/pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// ---------------------------------------------------------
// 4. TOGGLE STATUS (GET - toggle_status)
// ---------------------------------------------------------
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
    header("Location: /modules/master/pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// ---------------------------------------------------------
// 5. QUERY DATA & FILTER SUB ENTITAS
// ---------------------------------------------------------
$sql = "SELECT p.*, i.SingkatanNama AS NamaUpi, a.SingkatanNama AS NamaAp, u.SingkatanNama AS NamaUp 
        FROM master_pengguna p 
        LEFT JOIN master_upi i ON p.UnitUpi = i.UnitUpi 
        LEFT JOIN master_ap a ON p.UnitAp = a.UnitAp 
        LEFT JOIN master_up u ON p.UnitUp = u.UnitUp";

if ($sub === 'ui') {
    $sql .= " WHERE p.UnitUpi IS NOT NULL AND (p.UnitAp IS NULL OR p.UnitAp = '')";
} elseif ($sub === 'up3') {
    $sql .= " WHERE p.UnitAp IS NOT NULL AND (p.UnitUp IS NULL OR p.UnitUp = '')";
} elseif ($sub === 'ulp') {
    $sql .= " WHERE p.UnitUp IS NOT NULL AND p.UnitUp != ''";
}

$sql .= " ORDER BY p.NamaAkun ASC";

$stmt = $conn->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung metrik KPI
$total_akun = count($users);
$total_aktif = 0;
foreach ($users as $pg) {
    if (($pg['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_akun > 0) ? round(($total_aktif / $total_akun) * 100) : 0;

$master_tab_aktif = 'pengguna';
$list_upi = $conn->query("SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_ap  = $conn->query("SELECT UnitAp, SingkatanNama FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_up  = $conn->query("SELECT UnitUp, SingkatanNama FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Master Pengguna - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>