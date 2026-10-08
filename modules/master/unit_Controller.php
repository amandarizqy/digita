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

$sub    = $_GET['sub'] ?? 'ui';
$action = $_GET['action'] ?? 'index';

// 3. Tambah Unit (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $status_data = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if ($sub === 'ui') {
        $unit_upi       = trim($_POST['UnitUpi'] ?? '');
        $nama_unit      = trim($_POST['NamaUnit'] ?? '');
        $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');

        if (!empty($unit_upi) && !empty($nama_unit)) {
            $stmt = $conn->prepare("INSERT INTO master_upi (UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        }
    } elseif ($sub === 'up3') {
        $unit_ap        = trim($_POST['UnitAp'] ?? '');
        $unit_upi       = trim($_POST['UnitUpi'] ?? '');
        $nama_unit      = trim($_POST['NamaUnit'] ?? '');
        $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');

        if (!empty($unit_ap) && !empty($nama_unit)) {
            $stmt = $conn->prepare("INSERT INTO master_ap (UnitAp, UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        }
    } elseif ($sub === 'ulp') {
        $unit_up        = trim($_POST['UnitUp'] ?? '');
        $unit_ap        = trim($_POST['UnitAp'] ?? '');
        $unit_upi       = trim($_POST['UnitUpi'] ?? '');
        $nama_unit      = trim($_POST['NamaUnit'] ?? '');
        $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');

        if (!empty($unit_up) && !empty($nama_unit)) {
            $stmt = $conn->prepare("INSERT INTO master_up (UnitUp, UnitAp, UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$unit_up, $unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        }
    }
    header("Location: index.php?page=master&sub=" . urlencode($sub));
    exit;
}

// 4. Edit Unit (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id_primary     = trim($_POST['IdPrimary'] ?? '');
    $nama_unit      = trim($_POST['NamaUnit'] ?? '');
    $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');
    $status_data    = (isset($_POST['StatusData']) && $_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if ($sub === 'ui') {
        $stmt = $conn->prepare("UPDATE master_upi SET NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitUpi = ?");
        $stmt->execute([$nama_unit, $singkatan_nama, $status_data, $id_primary]);
    } elseif ($sub === 'up3') {
        $unit_upi = trim($_POST['UnitUpi'] ?? '');
        $stmt = $conn->prepare("UPDATE master_ap SET UnitUpi = ?, NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitAp = ?");
        $stmt->execute([$unit_upi, $nama_unit, $singkatan_nama, $status_data, $id_primary]);
    } elseif ($sub === 'ulp') {
        $unit_ap  = trim($_POST['UnitAp'] ?? '');
        $unit_upi = trim($_POST['UnitUpi'] ?? '');
        $stmt = $conn->prepare("UPDATE master_up SET UnitAp = ?, UnitUpi = ?, NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitUp = ?");
        $stmt->execute([$unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data, $id_primary]);
    }

    header("Location: index.php?page=master&sub=" . urlencode($sub));
    exit;
}

// 5. Toggle Status (GET - toggle_status)
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        if ($sub === 'ui') {
            $stmt = $conn->prepare("SELECT StatusData FROM master_upi WHERE UnitUpi = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
                $conn->prepare("UPDATE master_upi SET StatusData = ?, WaktuData = NOW() WHERE UnitUpi = ?")->execute([$status_baru, $id]);
            }
        } elseif ($sub === 'up3') {
            $stmt = $conn->prepare("SELECT StatusData FROM master_ap WHERE UnitAp = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
                $conn->prepare("UPDATE master_ap SET StatusData = ?, WaktuData = NOW() WHERE UnitAp = ?")->execute([$status_baru, $id]);
            }
        } elseif ($sub === 'ulp') {
            $stmt = $conn->prepare("SELECT StatusData FROM master_up WHERE UnitUp = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
                $conn->prepare("UPDATE master_up SET StatusData = ?, WaktuData = NOW() WHERE UnitUp = ?")->execute([$status_baru, $id]);
            }
        }
    }
    header("Location: index.php?page=master&sub=" . urlencode($sub));
    exit;
}

// 6. Query Data & Metrik
if ($sub === 'up3') {
    $stmt = $conn->query("SELECT a.*, i.SingkatanNama AS NamaIndukUpi FROM master_ap a LEFT JOIN master_upi i ON a.UnitUpi = i.UnitUpi ORDER BY a.UnitAp ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $label_unit = "Unit Pelaksana (UP3)";
} elseif ($sub === 'ulp') {
    $stmt = $conn->query("SELECT u.*, a.SingkatanNama AS NamaIndukAp, i.SingkatanNama AS NamaIndukUpi FROM master_up u LEFT JOIN master_ap a ON u.UnitAp = a.UnitAp LEFT JOIN master_upi i ON u.UnitUpi = i.UnitUpi ORDER BY u.UnitUp ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $label_unit = "Unit Layanan (ULP)";
} else {
    $sub = 'ui';
    $stmt = $conn->query("SELECT * FROM master_upi ORDER BY UnitUpi ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $label_unit = "Unit Induk (UI)";
}

$total_unit = count($units);
$total_aktif = 0;
foreach ($units as $u) {
    if (($u['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_unit > 0) ? round(($total_aktif / $total_unit) * 100) : 0;

$list_upi = $conn->query("SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_ap  = $conn->query("SELECT UnitAp, SingkatanNama FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Master Unit - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/unit.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';