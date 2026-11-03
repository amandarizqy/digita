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

// 2. Proteksi Hak Akses: Perintah Baku hanya untuk AM.UI (SA.KP = super admin)
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';

if (!in_array($user_role, ['SA.KP', 'AM.UI'], true)) {
    header("Location: index.php?page=dashboard");
    exit;
}

// 3. Semua permintaan POST wajib membawa token CSRF yang sah
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$action = $_GET['action'] ?? 'index';
$url_kembali = 'index.php?page=master&sub=pesan';

// Nilai yang diizinkan untuk kolom pilihan
$jenis_valid = ['KIRIM', 'TERIMA'];

/** Ambil & validasi input form perintah baku. Mengembalikan [data, pesan_error]. */
function pesan_baca_input(array $jenis_valid): array
{
    $isi        = trim((string) ($_POST['IsiPesan'] ?? ''));
    $ket        = trim((string) ($_POST['Keterangan'] ?? ''));
    $jenis      = (string) ($_POST['JenisPerintah'] ?? 'KIRIM');
    $db_tujuan  = trim((string) ($_POST['DatabaseTujuan'] ?? 'smsd'));
    $status     = (($_POST['StatusData'] ?? '') === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if ($isi === '') {
        return [null, 'Sintaks isi pesan wajib diisi.'];
    }
    if (!in_array($jenis, $jenis_valid, true)) {
        return [null, 'Jenis perintah tidak valid.'];
    }
    // Nama database: huruf, angka, dan garis bawah saja (mencegah isian sembarang)
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_tujuan)) {
        return [null, 'Database tujuan tidak valid (hanya huruf, angka, dan garis bawah).'];
    }
    return [['isi' => $isi, 'ket' => $ket, 'jenis' => $jenis, 'db' => $db_tujuan, 'status' => $status], null];
}

// 4. Tambah Perintah Baku (POST - store)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'store') {
    [$d, $err] = pesan_baca_input($jenis_valid);
    if ($err !== null) {
        ms_redirect($url_kembali, 'gagal', $err);
    }
    try {
        $conn->prepare("INSERT INTO baku_outbox (IsiPesan, Keterangan, JenisPerintah, DatabaseTujuan, StatusData, WaktuData)
                        VALUES (?, ?, ?, ?, ?, NOW())")
             ->execute([$d['isi'], $d['ket'], $d['jenis'], $d['db'], $d['status']]);
        ms_redirect($url_kembali, 'sukses', "Perintah '{$d['isi']}' berhasil ditambahkan.");
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal menambah perintah: ' . ms_pesan_db($e));
    }
}

// 5. Edit Perintah Baku (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = trim((string) ($_POST['Id'] ?? ''));
    [$d, $err] = pesan_baca_input($jenis_valid);
    if ($id === '' || !ctype_digit($id)) {
        $err = 'Data perintah tidak valid.';
    }
    if ($err !== null) {
        ms_redirect($url_kembali, 'gagal', $err);
    }
    try {
        $conn->prepare("UPDATE baku_outbox SET IsiPesan = ?, Keterangan = ?, JenisPerintah = ?, DatabaseTujuan = ?, StatusData = ?, WaktuData = NOW()
                        WHERE Id = ?")
             ->execute([$d['isi'], $d['ket'], $d['jenis'], $d['db'], $d['status'], $id]);
        ms_redirect($url_kembali, 'sukses', "Perintah '{$d['isi']}' berhasil diperbarui.");
    } catch (Throwable $e) {
        ms_redirect($url_kembali, 'gagal', 'Gagal memperbarui perintah: ' . ms_pesan_db($e));
    }
}

// 6. Hapus Perintah Baku (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = trim((string) ($_POST['id'] ?? ''));
    if ($id === '' || !ctype_digit($id)) {
        ms_redirect($url_kembali, 'gagal', 'Data perintah tidak valid.');
    }
    try {
        $conn->prepare("DELETE FROM baku_outbox WHERE Id = ?")->execute([$id]);
        ms_redirect($url_kembali, 'sukses', 'Perintah berhasil dihapus.');
    } catch (Throwable $e) {
        error_log('master pesan delete: ' . $e->getMessage());
        ms_redirect($url_kembali, 'gagal', 'Gagal menghapus perintah. Data sedang dipakai oleh daemon SMS.');
    }
}

// 7. Toggle Status (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
    $id = trim((string) ($_POST['id'] ?? ''));
    if ($id !== '' && ctype_digit($id)) {
        try {
            $stmt = $conn->prepare("SELECT StatusData FROM baku_outbox WHERE Id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
                $conn->prepare("UPDATE baku_outbox SET StatusData = ?, WaktuData = NOW() WHERE Id = ?")
                     ->execute([$status_baru, $id]);
                ms_redirect($url_kembali, 'sukses', 'Status perintah berhasil diubah.');
            }
        } catch (Throwable $e) {
            ms_redirect($url_kembali, 'gagal', 'Gagal mengubah status: ' . ms_pesan_db($e));
        }
    }
    ms_redirect($url_kembali, 'gagal', 'Perintah tidak ditemukan.');
}

// 8. Query Data & Metrik
$stmt = $conn->query("SELECT * FROM baku_outbox ORDER BY Id ASC");
$perintah = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_perintah = count($perintah);
$total_aktif = 0;
foreach ($perintah as $p) {
    if (($p['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_perintah > 0) ? round(($total_aktif / $total_perintah) * 100) : 0;

$page_title = "Master Perintah Baku - Digitas";

ob_start();
require_once __DIR__ . '/../../templates/master/pesan.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
