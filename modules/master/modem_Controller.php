<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

<<<<<<< HEAD
// 1. Verifikasi Sesi Login
=======
// 1. Verifikasi Session Pengguna
>>>>>>> origin/main
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'index';

<<<<<<< HEAD
// ---------------------------------------------------------
// 2. TAMBAH MODEM (POST - store)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $imei_modem       = trim($_POST['ImeiModem'] ?? '');
    $kode_up          = trim($_POST['KodeUp'] ?? '');
    $kode_ap          = trim($_POST['KodeAp'] ?? '');
    $kode_upi         = trim($_POST['KodeUpi'] ?? '');
    $sim_id           = trim($_POST['SimId'] ?? '');
    $ip_server_data   = trim($_POST['IpServerData'] ?? '');
    $ip_server_engine = trim($_POST['IpServerEngine'] ?? '');
    $port_engine      = trim($_POST['PortEngine'] ?? '');
    $status_data      = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    // Wajib konversi ke NULL murni jika string kosong agar aman dari Foreign Key
    $sim_id_val    = (!empty($sim_id) && $sim_id !== '') ? $sim_id : null;
    $ip_data_val   = (!empty($ip_server_data) && $ip_server_data !== '') ? $ip_server_data : null;
    $ip_engine_val = (!empty($ip_server_engine) && $ip_server_engine !== '') ? $ip_server_engine : null;
    $port_val      = (!empty($port_engine) && $port_engine !== '') ? $port_engine : null;

    if (empty($kode_upi) || empty($kode_ap)) {
        echo "<script>
            alert('Gagal menyimpan! Unit Induk (UPI) dan Unit Pelaksana (AP) wajib dipilih.');
            window.location.href='modem_Controller.php';
        </script>";
        exit;
    }

    // Jika KodeUp kosong, isi dengan KodeAp atau anak ULP pertamanya yang valid
    $kode_up_val = (!empty($kode_up) && $kode_up !== '') ? $kode_up : null;
    if (empty($kode_up_val)) {
        $cek_up = $conn->prepare("SELECT UnitUp FROM master_up WHERE UnitUp = ? LIMIT 1");
        $cek_up->execute([$kode_ap]);
        if ($cek_up->fetch()) {
            $kode_up_val = $kode_ap;
        } else {
            $ambil_anak = $conn->prepare("SELECT UnitUp FROM master_up WHERE UnitAp = ? LIMIT 1");
            $ambil_anak->execute([$kode_ap]);
            $anak = $ambil_anak->fetch(PDO::FETCH_ASSOC);
            $kode_up_val = $anak ? $anak['UnitUp'] : null;
        }
    }

    if (!empty($imei_modem)) {
        try {
            $stmt = $conn->prepare("INSERT INTO master_modem 
                (ImeiModem, KodeUp, KodeAp, KodeUpi, SimId, IpServerData, IpServerEngine, PortEngine, StatusData, WaktuData) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$imei_modem, $kode_up_val, $kode_ap, $kode_upi, $sim_id_val, $ip_data_val, $ip_engine_val, $port_val, $status_data]);

            header("Location: modem_Controller.php");
            exit;
        } catch (PDOException $e) {
            $err_msg = $e->getMessage();
            if (stripos($err_msg, 'FK-master_modem-SimId') !== false) {
                $pesan = "Gagal menyimpan! SIM ID yang dipilih tidak terdaftar di tabel master_nomor.";
            } elseif ($e->getCode() == 23000) {
                $pesan = "Gagal menyimpan! IMEI Modem \"{$imei_modem}\" sudah terdaftar.";
            } else {
                $pesan = "Terjadi galat database:\n" . $err_msg;
            }

            echo "<script>
                alert(" . json_encode($pesan) . ");
                window.location.href='modem_Controller.php';
            </script>";
            exit;
        }
    }
}

// ---------------------------------------------------------
// 3. EDIT MODEM (POST - update)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $imei_modem       = trim($_POST['ImeiModem'] ?? '');
    $kode_up          = trim($_POST['KodeUp'] ?? '');
    $kode_ap          = trim($_POST['KodeAp'] ?? '');
    $kode_upi         = trim($_POST['KodeUpi'] ?? '');
    $sim_id           = trim($_POST['SimId'] ?? '');
    $ip_server_data   = trim($_POST['IpServerData'] ?? '');
    $ip_server_engine = trim($_POST['IpServerEngine'] ?? '');
    $port_engine      = trim($_POST['PortEngine'] ?? '');
    $status_data      = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    $sim_id_val    = (!empty($sim_id) && $sim_id !== '') ? $sim_id : null;
    $ip_data_val   = (!empty($ip_server_data) && $ip_server_data !== '') ? $ip_server_data : null;
    $ip_engine_val = (!empty($ip_server_engine) && $ip_server_engine !== '') ? $ip_server_engine : null;
    $port_val      = (!empty($port_engine) && $port_engine !== '') ? $port_engine : null;

    $kode_up_val = (!empty($kode_up) && $kode_up !== '') ? $kode_up : null;
    if (empty($kode_up_val)) {
        $cek_up = $conn->prepare("SELECT UnitUp FROM master_up WHERE UnitUp = ? LIMIT 1");
        $cek_up->execute([$kode_ap]);
        if ($cek_up->fetch()) {
            $kode_up_val = $kode_ap;
        } else {
            $ambil_anak = $conn->prepare("SELECT UnitUp FROM master_up WHERE UnitAp = ? LIMIT 1");
            $ambil_anak->execute([$kode_ap]);
            $anak = $ambil_anak->fetch(PDO::FETCH_ASSOC);
            $kode_up_val = $anak ? $anak['UnitUp'] : null;
        }
    }

    if (!empty($imei_modem)) {
        try {
            $stmt = $conn->prepare("UPDATE master_modem SET 
                KodeUp = ?, KodeAp = ?, KodeUpi = ?, SimId = ?, IpServerData = ?, IpServerEngine = ?, PortEngine = ?, StatusData = ?, WaktuData = NOW() 
                WHERE ImeiModem = ?");
            $stmt->execute([$kode_up_val, $kode_ap, $kode_upi, $sim_id_val, $ip_data_val, $ip_engine_val, $port_val, $status_data, $imei_modem]);

            header("Location: modem_Controller.php");
            exit;
        } catch (PDOException $e) {
            echo "<script>
                alert('Gagal update database:\n" . addslashes($e->getMessage()) . "');
                window.location.href='modem_Controller.php';
            </script>";
            exit;
        }
    }
}

// ---------------------------------------------------------
// 4. HAPUS MODEM (GET - delete)
// ---------------------------------------------------------
if ($action === 'delete') {
    $imei = $_GET['id'] ?? null;
    if ($imei) {
        try {
            $stmt = $conn->prepare("DELETE FROM master_modem WHERE ImeiModem = ?");
            $stmt->execute([$imei]);
        } catch (PDOException $e) {
            echo "<script>alert('Gagal menghapus modem! Data sedang digunakan.'); window.location.href='modem_Controller.php';</script>";
            exit;
        }
=======
// 2. Aksi Tambah Modem Baru (POST dari Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $imei_modem       = trim($_POST['ImeiModem']);
    $kode_up          = trim($_POST['KodeUp']);
    $kode_ap          = trim($_POST['KodeAp']);
    $kode_upi         = trim($_POST['KodeUpi']);
    $sim_id           = trim($_POST['SimId']);
    $ip_server_data   = trim($_POST['IpServerData']);
    $ip_server_engine = trim($_POST['IpServerEngine']);
    $port_engine      = trim($_POST['PortEngine']);
    $status_data      = $_POST['StatusData'] ?? 'AKTIF';

    if (!empty($imei_modem)) {
        $stmt = $conn->prepare("INSERT INTO master_modem 
            (ImeiModem, KodeUp, KodeAp, KodeUpi, SimId, IpServerData, IpServerEngine, PortEngine, StatusData, WaktuData) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$imei_modem, $kode_up, $kode_ap, $kode_upi, $sim_id, $ip_server_data, $ip_server_engine, $port_engine, $status_data]);
>>>>>>> origin/main
    }
    header("Location: modem_Controller.php");
    exit;
}

<<<<<<< HEAD
// ---------------------------------------------------------
// 5. TOGGLE STATUS MODEM (GET - toggle_status)
// ---------------------------------------------------------
=======
// 3. Aksi Ubah Status (Toggle AKTIF <-> TIDAK)
>>>>>>> origin/main
if ($action === 'toggle_status') {
    $imei = $_GET['id'] ?? null;
    if ($imei) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_modem WHERE ImeiModem = ?");
        $stmt->execute([$imei]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            $update = $conn->prepare("UPDATE master_modem SET StatusData = ?, WaktuData = NOW() WHERE ImeiModem = ?");
            $update->execute([$status_baru, $imei]);
        }
    }
    header("Location: modem_Controller.php");
    exit;
}

<<<<<<< HEAD
// ---------------------------------------------------------
// 6. QUERY DATA & HITUNG METRIK
// ---------------------------------------------------------
$stmt = $conn->query("SELECT * FROM master_modem ORDER BY WaktuData DESC");
$modems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data master untuk dropdown dinamis unit
$list_upi = $conn->query("SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_ap  = $conn->query("SELECT UnitAp, SingkatanNama FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_up  = $conn->query("SELECT UnitUp, SingkatanNama FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

// AMBIL DAFTAR SIM ID DARI master_nomor (Mencegah FK Constraint Fail)
$list_sim = [];
try {
    $stmt_sim = $conn->query("SELECT SimId FROM master_nomor ORDER BY SimId ASC");
    $list_sim = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $list_sim = [];
}

$total_modem = count($modems);
$total_modem_aktif = 0;
foreach ($modems as $m) {
    if (($m['StatusData'] ?? '') === 'AKTIF') $total_modem_aktif++;
}
$persentase_aktif = ($total_modem > 0) ? round(($total_modem_aktif / $total_modem) * 100) : 0;

$page_title = "Master Modem GSM - Digita S41";

ob_start();
=======
// 4. Ambil Data dari Tabel master_modem
$stmt = $conn->query("SELECT * FROM master_modem ORDER BY WaktuData DESC");
$modems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Hitung Metrik Ringkasan
$total_modem = count($modems);
$total_modem_aktif = 0;
foreach ($modems as $m) {
    if ($m['StatusData'] === 'AKTIF') {
        $total_modem_aktif++;
    }
}
$persentase_aktif = ($total_modem > 0) ? round(($total_modem_aktif / $total_modem) * 100) : 0;

// 6. Render View ke Base Layout
$page_title = "Master Modem GSM - Digita S41";

ob_start();
// Memanggil file template nomor_server.html secara aman
>>>>>>> origin/main
require_once __DIR__ . '/../../templates/master/nomor_server.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>