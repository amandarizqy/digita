<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Verifikasi Session Pengguna
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'index';

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
    }
    header("Location: modem_Controller.php");
    exit;
}

// 3. Aksi Ubah Status (Toggle AKTIF <-> TIDAK)
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
require_once __DIR__ . '/../../templates/master/nomor_server.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
?>