<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/csrf.php';
require_once __DIR__ . '/../../functions/master_ui.php';

$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// 1. Verifikasi Sesi Login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: " . $base_path . "/modules/auth/login.php");
    exit;
}

// 2. Proteksi Hak Akses (Hanya SA.KP dan SF.UI)
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';

if ($user_role === 'AM.UI') {
    header("Location: index.php?page=master&sub=pesan");
    exit;
}

if (!in_array($user_role, ['SA.KP', 'SF.UI'], true)) {
    header("Location: index.php?page=dashboard");
    exit;
}

// 3. Semua permintaan POST wajib membawa token CSRF yang sah
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$sub = $_GET['sub'] ?? 'ui';
if (!in_array($sub, ['ui', 'up3', 'ulp'], true)) {
    $sub = 'ui';
}
$action = $_GET['action'] ?? 'index';
$url_kembali = "index.php?page=master&sub=" . urlencode($sub);

// 4. Tambah Unit (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'store') {
    $status_data    = (($_POST['StatusData'] ?? '') === 'AKTIF') ? 'AKTIF' : 'TIDAK';
    $nama_unit      = trim($_POST['NamaUnit'] ?? '');
    $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');
    $unit_upi       = trim($_POST['UnitUpi'] ?? '');
    $unit_ap        = trim($_POST['UnitAp'] ?? '');
    $unit_up        = trim($_POST['UnitUp'] ?? '');

    try {
        if ($sub === 'ui') {
            if ($unit_upi === '' || $nama_unit === '') {
                ms_redirect($url_kembali, 'gagal', 'Kode UI dan nama unit wajib diisi.');
            }
            $conn->prepare("INSERT INTO master_upi (UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, NOW())")
                 ->execute([$unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        } elseif ($sub === 'up3') {
            if ($unit_ap === '' || $unit_upi === '' || $nama_unit === '') {
                ms_redirect($url_kembali, 'gagal', 'Kode UP3, UI induk, dan nama unit wajib diisi.');
            }
            $conn->prepare("INSERT INTO master_ap (UnitAp, UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, NOW())")
                 ->execute([$unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        } else {
            if ($unit_up === '' || $unit_ap === '' || $unit_upi === '' || $nama_unit === '') {
                ms_redirect($url_kembali, 'gagal', 'Kode ULP, UP3 induk, UI induk, dan nama unit wajib diisi.');
            }
            $conn->prepare("INSERT INTO master_up (UnitUp, UnitAp, UnitUpi, NamaUnit, SingkatanNama, StatusData, WaktuData) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                 ->execute([$unit_up, $unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data]);
        }
        ms_redirect($url_kembali, 'sukses', 'Data unit berhasil ditambahkan.');
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal menambah unit: ' . ms_pesan_db($e));
    }
}

// 5. Edit Unit (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id_primary     = trim($_POST['IdPrimary'] ?? '');
    $nama_unit      = trim($_POST['NamaUnit'] ?? '');
    $singkatan_nama = trim($_POST['SingkatanNama'] ?? '');
    $status_data    = (($_POST['StatusData'] ?? '') === 'AKTIF') ? 'AKTIF' : 'TIDAK';
    $unit_upi       = trim($_POST['UnitUpi'] ?? '');
    $unit_ap        = trim($_POST['UnitAp'] ?? '');

    if ($id_primary === '' || $nama_unit === '') {
        ms_redirect($url_kembali, 'gagal', 'Data unit tidak lengkap.');
    }

    try {
        if ($sub === 'ui') {
            $conn->prepare("UPDATE master_upi SET NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitUpi = ?")
                 ->execute([$nama_unit, $singkatan_nama, $status_data, $id_primary]);
        } elseif ($sub === 'up3') {
            if ($unit_upi === '') {
                ms_redirect($url_kembali, 'gagal', 'UI induk wajib dipilih.');
            }
            $conn->prepare("UPDATE master_ap SET UnitUpi = ?, NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitAp = ?")
                 ->execute([$unit_upi, $nama_unit, $singkatan_nama, $status_data, $id_primary]);
        } else {
            if ($unit_ap === '' || $unit_upi === '') {
                ms_redirect($url_kembali, 'gagal', 'UP3 dan UI induk wajib dipilih.');
            }
            $conn->prepare("UPDATE master_up SET UnitAp = ?, UnitUpi = ?, NamaUnit = ?, SingkatanNama = ?, StatusData = ?, WaktuData = NOW() WHERE UnitUp = ?")
                 ->execute([$unit_ap, $unit_upi, $nama_unit, $singkatan_nama, $status_data, $id_primary]);
        }
        ms_redirect($url_kembali, 'sukses', 'Data unit berhasil diperbarui.');
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal memperbarui unit: ' . ms_pesan_db($e));
    }
}

// 6. Toggle Status (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
    $id = trim((string) ($_POST['id'] ?? ''));
    $tabel = ['ui' => ['master_upi', 'UnitUpi'], 'up3' => ['master_ap', 'UnitAp'], 'ulp' => ['master_up', 'UnitUp']][$sub];
    [$nama_tabel, $kolom_id] = $tabel;

    if ($id !== '') {
        try {
            $stmt = $conn->prepare("SELECT StatusData FROM $nama_tabel WHERE $kolom_id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
                $conn->prepare("UPDATE $nama_tabel SET StatusData = ?, WaktuData = NOW() WHERE $kolom_id = ?")
                     ->execute([$status_baru, $id]);
                ms_redirect($url_kembali, 'sukses', "Status unit $id berhasil diubah.");
            }
        } catch (Throwable $e) {
            ms_redirect($url_kembali, 'gagal', 'Gagal mengubah status: ' . ms_pesan_db($e));
        }
    }
    ms_redirect($url_kembali, 'gagal', 'Unit tidak ditemukan.');
}

// 7. Query Data & Metrik
if ($sub === 'up3') {
    $stmt = $conn->query("SELECT a.*, i.SingkatanNama AS NamaIndukUpi FROM master_ap a LEFT JOIN master_upi i ON a.UnitUpi = i.UnitUpi ORDER BY a.UnitAp ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $label_unit = "Unit Pelaksana (UP3)";
} elseif ($sub === 'ulp') {
    $stmt = $conn->query("SELECT u.*, a.SingkatanNama AS NamaIndukAp, i.SingkatanNama AS NamaIndukUpi FROM master_up u LEFT JOIN master_ap a ON u.UnitAp = a.UnitAp LEFT JOIN master_upi i ON u.UnitUpi = i.UnitUpi ORDER BY u.UnitUp ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $label_unit = "Unit Layanan (ULP)";
} else {
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

$page_title = "Master Unit - Digitas";

ob_start();
require_once __DIR__ . '/../../templates/master/unit.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
