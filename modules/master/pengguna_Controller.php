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

// 2. Ambil Role Aktif (Mendukung KodeHak maupun Role)
$user_role  = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$akun_login = (string) $_SESSION['NamaAkun'];

// Jika role AM.UI, paksa ke halaman pesan
if ($user_role === 'AM.UI') {
    header("Location: index.php?page=master&sub=pesan");
    exit;
}

// Izin hanya untuk SA.KP dan SF.UI
if (!in_array($user_role, ['SA.KP', 'SF.UI'], true)) {
    echo "<script>
        alert('Akses Ditolak: Peran " . htmlspecialchars($user_role) . " tidak memiliki wewenang untuk membuka modul pengguna.');
        window.location.href = 'index.php?page=dashboard';
    </script>";
    exit;
}

csrf_token(); // pastikan token tersedia di session untuk form

// Semua permintaan POST wajib membawa token CSRF yang sah
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$sub    = $_GET['filter'] ?? 'semua';
$action = $_GET['action'] ?? 'index';
$url_kembali = 'index.php?page=master&sub=pengguna';

// Daftar kode hak akses yang sah (whitelist)
$role_valid = ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];

// 3. Tambah Pengguna (POST - store)
// Kode hak akses HANYA ditentukan di sini (saat akun dibuat) dan tidak bisa diubah lagi.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'store') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = (string) ($_POST['KodeHak'] ?? '');
    $unit_upi    = '56';
    $unit_ap     = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : ($_SESSION['UnitAp'] ?? null);
    $unit_up     = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : ($_SESSION['UnitUp'] ?? null);
    $status_data = (($_POST['StatusData'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';

    if ($nama_akun === '' || $kata_kunci === '') {
        ms_redirect($url_kembali, 'gagal', 'Nama akun dan kata kunci wajib diisi.');
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $nama_akun)) {
        ms_redirect($url_kembali, 'gagal', 'Nama akun hanya boleh huruf, angka, titik, garis bawah, dan strip (3-50 karakter).');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ms_redirect($url_kembali, 'gagal', 'Format alamat email tidak valid.');
    }
    if (!in_array($kode_hak, $role_valid, true)) {
        ms_redirect($url_kembali, 'gagal', 'Kode hak akses tidak valid.');
    }
    // Proteksi privilege escalation: hanya SA.KP yang boleh membuat akun SA.KP
    if ($kode_hak === 'SA.KP' && $user_role !== 'SA.KP') {
        ms_redirect($url_kembali, 'gagal', 'Anda tidak memiliki wewenang untuk membuat akun Super Admin (SA.KP).');
    }

    try {
        $hash_kunci = password_hash($kata_kunci, PASSWORD_DEFAULT);
        $conn->prepare("INSERT INTO master_pengguna (NamaAkun, AlamatEmail, NamaPengguna, NomorKontak, KataKunci, KodeHak, UnitUpi, UnitAp, UnitUp, StatusData, WaktuData)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
             ->execute([$nama_akun, $email, $nama_user, $kontak, $hash_kunci, $kode_hak, $unit_upi, $unit_ap, $unit_up, $status_data]);
        ms_redirect($url_kembali, 'sukses', "Akun '$nama_akun' berhasil ditambahkan.");
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal menyimpan pengguna: ' . ms_pesan_db($e));
    }
}

// 4. Edit Pengguna (POST - update)
// KodeHak dari form DIABAIKAN: hak akses yang sudah tersimpan tidak bisa diubah lewat edit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $status_data = (($_POST['StatusData'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';

    $stmt_old = $conn->prepare("SELECT * FROM master_pengguna WHERE NamaAkun = ?");
    $stmt_old->execute([$nama_akun]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_data) {
        ms_redirect($url_kembali, 'gagal', 'Akun tidak ditemukan.');
    }
    // Proteksi: pengguna non-SA.KP tidak boleh mengedit akun SA.KP
    if ($old_data['KodeHak'] === 'SA.KP' && $user_role !== 'SA.KP') {
        ms_redirect($url_kembali, 'gagal', 'Anda tidak memiliki wewenang untuk mengubah akun Super Admin.');
    }
    // Proteksi: tidak boleh menonaktifkan akun sendiri
    if ($nama_akun === $akun_login && $status_data === 'TIDAK') {
        ms_redirect($url_kembali, 'gagal', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ms_redirect($url_kembali, 'gagal', 'Format alamat email tidak valid.');
    }

    // Jika input UnitAp/UnitUp kosong, pertahankan nilai lama di DB
    $unit_upi = '56';
    $unit_ap  = !empty($_POST['UnitAp']) ? $_POST['UnitAp'] : $old_data['UnitAp'];
    $unit_up  = !empty($_POST['UnitUp']) ? $_POST['UnitUp'] : $old_data['UnitUp'];

    try {
        if ($kata_kunci !== '') {
            $hash_kunci = password_hash($kata_kunci, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, KataKunci = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?")
                 ->execute([$email, $nama_user, $kontak, $hash_kunci, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
        } else {
            $conn->prepare("UPDATE master_pengguna SET AlamatEmail = ?, NamaPengguna = ?, NomorKontak = ?, UnitUpi = ?, UnitAp = ?, UnitUp = ?, StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?")
                 ->execute([$email, $nama_user, $kontak, $unit_upi, $unit_ap, $unit_up, $status_data, $nama_akun]);
        }
        ms_redirect($url_kembali, 'sukses', "Akun '$nama_akun' berhasil diperbarui.");
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal memperbarui pengguna: ' . ms_pesan_db($e));
    }
}

// 5. Toggle Status (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
    $id = trim((string) ($_POST['id'] ?? ''));

    if ($id === '') {
        ms_redirect($url_kembali, 'gagal', 'Akun tidak ditemukan.');
    }
    if ($id === $akun_login) {
        ms_redirect($url_kembali, 'gagal', 'Anda tidak dapat mengubah status akun Anda sendiri.');
    }
    try {
        $stmt = $conn->prepare("SELECT StatusData, KodeHak FROM master_pengguna WHERE NamaAkun = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            ms_redirect($url_kembali, 'gagal', 'Akun tidak ditemukan.');
        }
        if ($row['KodeHak'] === 'SA.KP' && $user_role !== 'SA.KP') {
            ms_redirect($url_kembali, 'gagal', 'Anda tidak memiliki wewenang untuk mengubah akun Super Admin.');
        }
        $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
        $conn->prepare("UPDATE master_pengguna SET StatusData = ?, WaktuData = NOW() WHERE NamaAkun = ?")
             ->execute([$status_baru, $id]);
        ms_redirect($url_kembali, 'sukses', "Akun '$id' berhasil " . ($status_baru === 'AKTIF' ? 'diaktifkan.' : 'dinonaktifkan.'));
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal mengubah status: ' . ms_pesan_db($e));
    }
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

$page_title = "Master Pengguna - Digitas";

// RENDER DENGAN BASE LAYOUT LENGKAP
ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
