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

// 2. Proteksi Hak Akses (Hanya SA.KP dan SF.UI)
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';

if ($user_role === 'AM.UI') {
    header("Location: index.php?page=master&sub=pesan");
    exit;
}

if (!in_array($user_role, ['SA.KP', 'SF.UI'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$user_unit = $_SESSION['KodeUnit'] ?? ''; 
$action    = $_GET['action'] ?? 'index';

// Normalisasi status dari form: hanya 'AKTIF' atau 'TIDAK'
function normalisasi_status_modem($nilai): string
{
    return (strtoupper(trim((string) $nilai)) === 'TIDAK') ? 'TIDAK' : 'AKTIF';
}

// 3. Tambah Modem (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $imei_modem       = trim($_POST['ImeiModem'] ?? '');
    $kode_upi         = trim($_POST['KodeUpi'] ?? '');
    $kode_ap          = trim($_POST['KodeAp'] ?? '');
    $kode_up          = trim($_POST['KodeUp'] ?? '');
    $sim_id           = trim($_POST['SimId'] ?? '');
    $ip_server_data   = trim($_POST['IpServerData'] ?? '');
    $ip_server_engine = trim($_POST['IpServerEngine'] ?? '');
    $port_engine      = trim($_POST['PortEngine'] ?? '');

    // Status mengikuti pilihan di form
    $status_data      = normalisasi_status_modem($_POST['StatusData'] ?? 'AKTIF');

    if (empty($kode_upi) || strpos($kode_ap, '56') === 0 || strpos($kode_up, '56') === 0) {
        $kode_upi = '56';
    }

    $sim_id_val    = (!empty($sim_id)) ? $sim_id : null;
    $ip_data_val   = (!empty($ip_server_data)) ? $ip_server_data : null;
    $ip_engine_val = (!empty($ip_server_engine)) ? $ip_server_engine : null;
    $port_val      = (!empty($port_engine)) ? $port_engine : null;

    if (empty($kode_upi) || empty($kode_ap)) {
        echo "<script>
            alert('Gagal menyimpan! Unit Induk (UPI) dan UP3 (AP) wajib diisi/dipilih.');
            window.location.href='index.php?page=master&sub=modem';
        </script>";
        exit;
    }

    $kode_up_val = (!empty($kode_up)) ? $kode_up : null;
    if (empty($kode_up_val) && !empty($kode_ap)) {
        $ambil_ulp = $conn->prepare("SELECT UnitUp FROM master_up WHERE UnitAp = ? AND StatusData = 'AKTIF' LIMIT 1");
        $ambil_ulp->execute([$kode_ap]);
        $ulp = $ambil_ulp->fetch(PDO::FETCH_ASSOC);
        $kode_up_val = $ulp ? $ulp['UnitUp'] : null;
    }

    if (!empty($imei_modem)) {
        try {
            $stmt = $conn->prepare("INSERT INTO master_modem 
                (ImeiModem, KodeUp, KodeAp, KodeUpi, SimId, IpServerData, IpServerEngine, PortEngine, StatusData, WaktuData) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$imei_modem, $kode_up_val, $kode_ap, $kode_upi, $sim_id_val, $ip_data_val, $ip_engine_val, $port_val, $status_data]);

            header("Location: index.php?page=master&sub=modem");
            exit;
        } catch (PDOException $e) {
            $err_msg = $e->getMessage();
            if (stripos($err_msg, 'FK-master_modem-SimId') !== false) {
                $pesan = "Gagal menyimpan! SIM ID tidak terdaftar di master nomor.";
            } elseif ($e->getCode() == 23000) {
                $pesan = "Gagal menyimpan! IMEI Modem \"{$imei_modem}\" sudah terdaftar.";
            } else {
                $pesan = "Terjadi galat database:\n" . $err_msg;
            }

            echo "<script>
                alert(" . json_encode($pesan) . ");
                window.location.href='index.php?page=master&sub=modem';
            </script>";
            exit;
        }
    }
}

// 4. Edit Modem (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $imei_modem       = trim($_POST['ImeiModem'] ?? '');
    $kode_upi         = trim($_POST['KodeUpi'] ?? '');
    $kode_ap          = trim($_POST['KodeAp'] ?? '');
    $kode_up          = trim($_POST['KodeUp'] ?? '');
    $sim_id           = trim($_POST['SimId'] ?? '');
    $ip_server_data   = trim($_POST['IpServerData'] ?? '');
    $ip_server_engine = trim($_POST['IpServerEngine'] ?? '');
    $port_engine      = trim($_POST['PortEngine'] ?? '');

    // Status mengikuti pilihan di form (sebelumnya selalu dipaksa AKTIF)
    $status_data      = normalisasi_status_modem($_POST['StatusData'] ?? 'AKTIF');

    if (empty($kode_upi) || strpos($kode_ap, '56') === 0 || strpos($kode_up, '56') === 0) {
        $kode_upi = '56';
    }

    $sim_id_val    = (!empty($sim_id)) ? $sim_id : null;
    $ip_data_val   = (!empty($ip_server_data)) ? $ip_server_data : null;
    $ip_engine_val = (!empty($ip_server_engine)) ? $ip_server_engine : null;
    $port_val      = (!empty($port_engine)) ? $port_engine : null;
    $kode_up_val   = (!empty($kode_up)) ? $kode_up : null;

    if (!empty($imei_modem)) {
        try {
            $stmt = $conn->prepare("UPDATE master_modem SET 
                KodeUp = ?, KodeAp = ?, KodeUpi = ?, SimId = ?, IpServerData = ?, IpServerEngine = ?, PortEngine = ?, StatusData = ?, WaktuData = NOW() 
                WHERE ImeiModem = ?");
            $stmt->execute([$kode_up_val, $kode_ap, $kode_upi, $sim_id_val, $ip_data_val, $ip_engine_val, $port_val, $status_data, $imei_modem]);

            header("Location: index.php?page=master&sub=modem");
            exit;
        } catch (PDOException $e) {
            echo "<script>
                alert(" . json_encode("Gagal update database:\n" . $e->getMessage()) . ");
                window.location.href='index.php?page=master&sub=modem';
            </script>";
            exit;
        }
    }
}

// 5. Aktif / Nonaktif Modem (GET - toggle_status)
if ($action === 'toggle_status') {
    $imei = trim($_GET['id'] ?? '');
    if ($imei !== '') {
        try {
            $stmt = $conn->prepare("UPDATE master_modem 
                SET StatusData = IF(StatusData = 'AKTIF', 'TIDAK', 'AKTIF'), WaktuData = NOW() 
                WHERE ImeiModem = ?");
            $stmt->execute([$imei]);
        } catch (PDOException $e) {
            echo "<script>
                alert(" . json_encode("Gagal mengubah status modem:\n" . $e->getMessage()) . ");
                window.location.href='index.php?page=master&sub=modem';
            </script>";
            exit;
        }
    }
    header("Location: index.php?page=master&sub=modem");
    exit;
}

// 6. Hapus Modem (GET - delete)
if ($action === 'delete') {
    $imei = $_GET['id'] ?? null;
    if ($imei) {
        try {
            $stmt = $conn->prepare("DELETE FROM master_modem WHERE ImeiModem = ?");
            $stmt->execute([$imei]);
        } catch (PDOException $e) {
            echo "<script>alert('Gagal menghapus modem! Data sedang digunakan.'); window.location.href='index.php?page=master&sub=modem';</script>";
            exit;
        }
    }
    header("Location: index.php?page=master&sub=modem");
    exit;
}

// 7. Query Data & Metrik
$sql = "SELECT * FROM master_modem WHERE 1=1";
$params = [];

if ($user_role === 'ULP') {
    $sql .= " AND KodeUp = ?";
    $params[] = $user_unit;
} elseif ($user_role === 'UP3') {
    $sql .= " AND KodeAp = ?";
    $params[] = $user_unit;
} elseif ($user_role === 'UID') {
    $sql .= " AND KodeUpi = ?";
    $params[] = $user_unit;
}

$sql .= " ORDER BY WaktuData DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$modems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$list_upi = $conn->query("SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_ap  = $conn->query("SELECT UnitAp, SingkatanNama, UnitUpi FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_up  = $conn->query("SELECT UnitUp, SingkatanNama, UnitAp FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    if (($m['StatusData'] ?? '') === 'AKTIF') {
        $total_modem_aktif++;
    }
}
$persentase_aktif = ($total_modem > 0) ? round(($total_modem_aktif / $total_modem) * 100) : 0;

$page_title = "Master Modem GSM - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/nomor_server.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';