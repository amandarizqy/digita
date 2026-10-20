<?php
// modules/pengaturan/update_password.php  -> LOGIKA (controller)
// Dipanggil oleh modules/pengaturan/index.php, yang sudah dibungkus layout oleh /index.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/login_throttle.php';

$base = defined('BASE_URL') ? BASE_URL : '';

// 1. Wajib login; akun diambil dari session, bukan dari input form
$nama_akun = $_SESSION['NamaAkun'] ?? '';
if ($nama_akun === '') {
    header('Location: ' . $base . '/modules/auth/login.php');
    exit;
}

// 2. Token CSRF
if (empty($_SESSION['csrf_pw'])) {
    $_SESSION['csrf_pw'] = bin2hex(random_bytes(32));
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // trim() sama seperti login.php dan pengguna_Controller.php, agar hash cocok saat login
    $lama    = trim($_POST['password_lama']    ?? '');
    $baru    = trim($_POST['password_baru']    ?? '');
    $konfirm = trim($_POST['password_konfirm'] ?? '');
    $token   = $_POST['csrf_token']            ?? '';

    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $kunci = 'pw:' . mb_strtolower($nama_akun) . '|' . $ip;

    if (!hash_equals($_SESSION['csrf_pw'], $token)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }
    if ($lama === '' || $baru === '' || $konfirm === '') {
        $errors[] = 'Semua kolom wajib diisi.';
    }
    if (strlen($baru) < 8) {
        $errors[] = 'Password baru minimal 8 karakter.';
    }
    if (!preg_match('/[A-Za-z]/', $baru) || !preg_match('/\d/', $baru)) {
        $errors[] = 'Password baru harus mengandung huruf dan angka.';
    }
    if ($baru !== $konfirm) {
        $errors[] = 'Konfirmasi password tidak sama dengan password baru.';
    }
    if ($baru !== '' && $baru === $lama) {
        $errors[] = 'Password baru tidak boleh sama dengan password lama.';
    }

    // 3. Batasi percobaan salah (memakai pembatas login yang sudah ada)
    if (!$errors) {
        $sisa = lt_sisa_kunci([$kunci]);
        if ($sisa > 0) {
            $errors[] = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . lt_format_sisa($sisa) . '.';
        }
    }

    // 4. Verifikasi password lama lalu simpan hash baru
    if (!$errors) {
        try {
            $stmt = $conn->prepare(
                "SELECT KataKunci FROM master_pengguna
                 WHERE NamaAkun = :akun AND StatusData = 'AKTIF' LIMIT 1"
            );
            $stmt->execute([':akun' => $nama_akun]);
            $hash_db = $stmt->fetchColumn();

            if ($hash_db === false) {
                $errors[] = 'Akun tidak ditemukan atau tidak aktif.';
            } elseif (!password_verify($lama, (string) $hash_db)) {
                lt_catat_gagal($kunci, LT_BATAS_AKUN);
                $errors[] = 'Password lama salah.';
            } else {
                $upd = $conn->prepare("UPDATE master_pengguna SET KataKunci = :hash WHERE NamaAkun = :akun");
                $upd->execute([
                    ':hash' => password_hash($baru, PASSWORD_DEFAULT),
                    ':akun' => $nama_akun,
                ]);
                lt_reset($kunci);
                session_regenerate_id(true);
                $_SESSION['csrf_pw'] = bin2hex(random_bytes(32));
                $success = true;
            }
        } catch (PDOException $e) {
            error_log('update_password: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan pada server. Silakan coba lagi.';
        }
    }
}

$csrf_token = $_SESSION['csrf_pw'];

// 5. Tampilkan view
require __DIR__ . '/../../templates/pengaturan/update_password.php';