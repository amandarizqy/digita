<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Verifikasi Sesi Login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$sub    = $_GET['sub'] ?? 'semua';
$action = $_GET['action'] ?? 'index';

// ---------------------------------------------------------
// 2. TAMBAH PENGGUNA (POST - store)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $nama_akun     = trim($_POST['NamaAkun'] ?? '');
    $alamat_email  = trim($_POST['AlamatEmail'] ?? '');
    $nama_pengguna = trim($_POST['NamaPengguna'] ?? '');
    $nomor_kontak  = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci    = trim($_POST['KataKunci'] ?? '');
    $kode_hak      = trim($_POST['KodeHak'] ?? '');
    $unit_upi      = trim($_POST['UnitUpi'] ?? '');
    $unit_ap       = trim($_POST['UnitAp'] ?? '');
    $unit_up       = trim($_POST['UnitUp'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_akun)) {
        try {
            $stmt = $conn->prepare("INSERT INTO master_pengguna 
                (NamaAkun, AlamatEmail, NamaPengguna, NomorKontak, KataKunci, KodeHak, UnitUpi, UnitAp, UnitUp, StatusData, WaktuData) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nama_akun, $alamat_email, $nama_pengguna, $nomor_kontak, $kata_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data]);

            header("Location: pengguna_Controller.php?sub=" . urlencode($sub));
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<script>
                    alert('Gagal menyimpan! Username \"' + " . json_encode($nama_akun) . " + '\" sudah digunakan. Silakan gunakan username lain.');
                    window.location.href = 'pengguna_Controller.php?sub=" . urlencode($sub) . "';
                </script>";
                exit;
            }
            throw $e;
        }
    }
}

// ---------------------------------------------------------
// 3. EDIT PENGGUNA (POST - update)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $nama_akun     = trim($_POST['NamaAkun'] ?? '');
    $alamat_email  = trim($_POST['AlamatEmail'] ?? '');
    $nama_pengguna = trim($_POST['NamaPengguna'] ?? '');
    $nomor_kontak  = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci    = trim($_POST['KataKunci'] ?? '');
    $kode_hak      = trim($_POST['KodeHak'] ?? '');
    $unit_upi      = trim($_POST['UnitUpi'] ?? '');
    $unit_ap       = trim($_POST['UnitAp'] ?? '');
    $unit_up       = trim($_POST['UnitUp'] ?? '');
    $status_data   = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($nama_akun)) {
        if (!empty($kata_kunci)) {
            $stmt = $conn->prepare("UPDATE master_pengguna SET 
                AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KataKunci = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() 
                WHERE NamaAkun = ?");
            $stmt->execute([$alamat_email, $nama_pengguna, $nomor_kontak, $kata_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
        } else {
            $stmt = $conn->prepare("UPDATE master_pengguna SET 
                AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KodeHak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() 
                WHERE NamaAkun = ?");
            $stmt->execute([$alamat_email, $nama_pengguna, $nomor_kontak, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
        }
    }

    header("Location: pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// ---------------------------------------------------------
// 4. HAPUS PENGGUNA (GET - delete)
// ---------------------------------------------------------
if ($action === 'delete') {
    $akun = $_GET['id'] ?? null;
    if ($akun) {
        try {
            $stmt = $conn->prepare("DELETE FROM master_pengguna WHERE NamaAkun = ?");
            $stmt->execute([$akun]);
        } catch (PDOException $e) {
            echo "<script>
                alert('Gagal menghapus pengguna! Akun ini masih terikat dengan relasi data lain.'); 
                window.location.href='pengguna_Controller.php?sub=" . urlencode($sub) . "';
            </script>";
            exit;
        }
    }
    header("Location: pengguna_Controller.php?sub=" . urlencode($sub));
    exit;
}

// ---------------------------------------------------------
// 5. TOGGLE STATUS (GET - toggle_status)
// ---------------------------------------------------------
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

// ---------------------------------------------------------
// 6. FILTER SUB-ENTITAS & QUERY DATA
// ---------------------------------------------------------
$query = "SELECT * FROM master_pengguna WHERE 1=1";
if ($sub === 'ui') {
    $query .= " AND KodeHak IN ('SF.UI', 'AM.UI', 'MB.UI', 'SA.KP')";
} elseif ($sub === 'up3') {
    $query .= " AND KodeHak IN ('SF.AP', 'TL.AP', 'AM.AP')";
} elseif ($sub === 'ulp') {
    $query .= " AND KodeHak IN ('SF.UP', 'TL.UP', 'ML.UP')";
}
$query .= " ORDER BY WaktuData DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data master untuk dropdown dinamis unit
$list_upi = $conn->query("SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_ap  = $conn->query("SELECT UnitAp, SingkatanNama FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_up  = $conn->query("SELECT UnitUp, SingkatanNama FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

// Kalkulasi KPI
$total_akun  = (int) $conn->query("SELECT COUNT(*) FROM master_pengguna")->fetchColumn();
$total_aktif = (int) $conn->query("SELECT COUNT(*) FROM master_pengguna WHERE StatusData = 'AKTIF'")->fetchColumn();
$persentase_aktif = ($total_akun > 0) ? round(($total_aktif / $total_akun) * 100) : 0;

$page_title = "Master Pengguna - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>