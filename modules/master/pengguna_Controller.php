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

// Tab "Sub Entitas" asal form (ui/up3/ulp/vendor) -> akhiran kode hak yang diizinkan.
// Dipakai agar form tambah di tab tertentu hanya menerima hak tingkat itu, dan setelah simpan kembali ke tab yang sama.
$filter_valid = ['ui' => '.UI', 'up3' => '.AP', 'ulp' => '.UP', 'vendor' => '.VD'];
$filter_post  = (string) ($_POST['filter'] ?? '');
if (isset($filter_valid[$filter_post])) {
    $url_kembali .= '&filter=' . $filter_post;
}

// Daftar kode hak akses yang sah (whitelist)
$role_valid = ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];

// Validasi pilihan unit: UnitAp & UnitUp harus ada di master (AKTIF) dan UnitUp harus milik UnitAp tsb.
// Hak tingkat UP3 (.AP) wajib punya UnitAp; hak tingkat ULP (.UP) wajib punya UnitAp dan UnitUp.
// Hak lain (UI, VD) boleh mengosongkan unit, tetapi kalau diisi tetap divalidasi.
// Mengembalikan [unit_ap, unit_up, pesan_error]
function pg_validasi_unit(PDO $conn, string $kode_hak, string $unit_ap, string $unit_up): array
{
    $unit_ap = trim($unit_ap);
    $unit_up = trim($unit_up);
    $akhiran = substr($kode_hak, -3);

    // Form tingkat ULP hanya mengirim ULP: UP3 diturunkan otomatis dari ULP tsb.
    if ($unit_ap === '' && $unit_up !== '') {
        $st = $conn->prepare("SELECT UnitAp FROM master_up WHERE UnitUp = ? AND StatusData = 'AKTIF'");
        $st->execute([$unit_up]);
        $unit_ap = (string) $st->fetchColumn();
        if ($unit_ap === '') {
            return [null, null, 'Unit ULP yang dipilih tidak valid.'];
        }
    }
    $wajib_ap = in_array($akhiran, ['.AP', '.UP'], true);
    $wajib_up = ($akhiran === '.UP');

    if ($unit_ap === '') {
        if ($wajib_ap) {
            return [null, null, 'Unit UP3 wajib dipilih untuk hak akses ' . $kode_hak . '.'];
        }
        return [null, null, null];
    }
    $st = $conn->prepare("SELECT 1 FROM master_ap WHERE UnitAp = ? AND StatusData = 'AKTIF'");
    $st->execute([$unit_ap]);
    if (!$st->fetchColumn()) {
        return [null, null, 'Unit UP3 yang dipilih tidak valid.'];
    }
    if ($unit_up === '') {
        if ($wajib_up) {
            return [null, null, 'Unit ULP wajib dipilih untuk hak akses ' . $kode_hak . '.'];
        }
        return [$unit_ap, null, null];
    }
    $st = $conn->prepare("SELECT 1 FROM master_up WHERE UnitUp = ? AND UnitAp = ? AND StatusData = 'AKTIF'");
    $st->execute([$unit_up, $unit_ap]);
    if (!$st->fetchColumn()) {
        return [null, null, 'Unit ULP yang dipilih bukan milik UP3 tersebut.'];
    }
    return [$unit_ap, $unit_up, null];
}

// Validasi UID (UnitUpi). Hanya akun vendor (.VD) yang boleh memilih UID; hak lain tetap '56' seperti semula.
// Kalau UP3 ikut dipilih, UP3 itu harus milik UID tersebut.
// Mengembalikan [unit_upi, pesan_error]
function pg_validasi_upi(PDO $conn, string $kode_hak, string $unit_upi, ?string $unit_ap): array
{
    $unit_upi = trim($unit_upi);
    if (substr($kode_hak, -3) !== '.VD' || $unit_upi === '') {
        return ['56', null];
    }
    $st = $conn->prepare("SELECT 1 FROM master_upi WHERE UnitUpi = ? AND StatusData = 'AKTIF'");
    $st->execute([$unit_upi]);
    if (!$st->fetchColumn()) {
        return [null, 'UID yang dipilih tidak valid.'];
    }
    if ($unit_ap !== null && $unit_ap !== '') {
        $st = $conn->prepare("SELECT 1 FROM master_ap WHERE UnitAp = ? AND UnitUpi = ?");
        $st->execute([$unit_ap, $unit_upi]);
        if (!$st->fetchColumn()) {
            return [null, 'UP3 yang dipilih bukan milik UID tersebut.'];
        }
    }
    return [$unit_upi, null];
}

// 3. Tambah Pengguna (POST - store)
// Kode hak akses HANYA ditentukan di sini (saat akun dibuat) dan tidak bisa diubah lagi.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'store') {
    $nama_akun   = trim($_POST['NamaAkun'] ?? '');
    $email       = trim($_POST['AlamatEmail'] ?? '');
    $nama_user   = trim($_POST['NamaPengguna'] ?? '');
    $kontak      = trim($_POST['NomorKontak'] ?? '');
    $kata_kunci  = trim($_POST['KataKunci'] ?? '');
    $kode_hak    = (string) ($_POST['KodeHak'] ?? '');
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
    // Form dibuka dari tab tingkat tertentu: hak akses harus sesuai tingkat tab itu
    if (isset($filter_valid[$filter_post]) && substr($kode_hak, -3) !== $filter_valid[$filter_post]) {
        ms_redirect($url_kembali, 'gagal', 'Kode hak akses tidak sesuai dengan tab tingkat yang sedang dibuka.');
    }
    // Proteksi privilege escalation: hanya SA.KP yang boleh membuat akun SA.KP
    if ($kode_hak === 'SA.KP' && $user_role !== 'SA.KP') {
        ms_redirect($url_kembali, 'gagal', 'Anda tidak memiliki wewenang untuk membuat akun Super Admin (SA.KP).');
    }

    try {
        [$unit_ap, $unit_up, $err_unit] = pg_validasi_unit($conn, $kode_hak, (string) ($_POST['UnitAp'] ?? ''), (string) ($_POST['UnitUp'] ?? ''));
        if ($err_unit !== null) {
            ms_redirect($url_kembali, 'gagal', $err_unit);
        }
        [$unit_upi, $err_upi] = pg_validasi_upi($conn, $kode_hak, (string) ($_POST['UnitUpi'] ?? ''), $unit_ap);
        if ($err_upi !== null) {
            ms_redirect($url_kembali, 'gagal', $err_upi);
        }
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

    // UID akun dipertahankan (akun vendor bisa punya UID pilihan); default '56'
    $unit_upi = ($old_data['UnitUpi'] ?? '') !== '' ? $old_data['UnitUpi'] : '56';

    try {
        // Unit dipilih dari dropdown; ULP harus milik UP3 yang dipilih
        [$unit_ap, $unit_up, $err_unit] = pg_validasi_unit($conn, (string) $old_data['KodeHak'], (string) ($_POST['UnitAp'] ?? ''), (string) ($_POST['UnitUp'] ?? ''));
        if ($err_unit !== null) {
            ms_redirect($url_kembali, 'gagal', $err_unit);
        }
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

// Data pilihan unit untuk dropdown berantai (UP3 -> ULP)
$daftar_ap = $conn->query("SELECT UnitAp, UnitUpi, SingkatanNama, NamaUnit FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$daftar_up = $conn->query("SELECT UnitUp, UnitAp, SingkatanNama, NamaUnit FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

$daftar_upi = $conn->query("SELECT UnitUpi, SingkatanNama, NamaUnit FROM master_upi WHERE StatusData = 'AKTIF' ORDER BY UnitUpi ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Master Pengguna - Digitas";

// RENDER DENGAN BASE LAYOUT LENGKAP
ob_start();
require_once __DIR__ . '/../../templates/master/pengguna.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';