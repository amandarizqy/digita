<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,                       // JavaScript tidak bisa membaca cookie session
    'samesite' => 'Lax',                      // menahan sebagian besar serangan CSRF
    'secure'   => !empty($_SERVER['HTTPS']),  // di HTTPS cookie hanya dikirim lewat HTTPS
]);
session_start();

// Panggil konfigurasi database & pembatas percobaan login (berbasis file, tanpa tabel tambahan)
require_once '../../config/database.php';
require_once __DIR__ . '/../../functions/login_throttle.php';

// Jika pengguna sudah login, arahkan langsung ke dashboard
if (isset($_SESSION['NamaAkun'])) {
    header("Location: ../../index.php");
    exit;
}

$error_message = '';
$error_field   = '';   // 'username' | 'password' | '' -> dapat dipakai template untuk menandai kolom yang salah

// Proses form jika metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mb_substr(trim($_POST['username'] ?? ''), 0, 100);
    $password = trim($_POST['password'] ?? '');

    if ($username === '') {
        $error_message = "Username wajib diisi.";
        $error_field   = 'username';
    } elseif ($password === '') {
        $error_message = "Kata sandi wajib diisi.";
        $error_field   = 'password';
    } else {
        try {
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';   // sengaja bukan X-Forwarded-For (mudah dipalsukan)
            $kunci_ua = 'ua:' . mb_strtolower($username) . '|' . $ip;
            $kunci_ip = 'ip:' . $ip;

            // 1. Sedang dikunci? Tolak sebelum memeriksa sandi.
            $sisa = lt_sisa_kunci([$kunci_ua, $kunci_ip]);
            if ($sisa > 0) {
                $error_message = "Terlalu banyak percobaan login yang gagal. Coba lagi dalam " . lt_format_sisa($sisa) . ".";
            } else {
                // 2. Cari akun berdasarkan username saja
                $stmt = $conn->prepare("SELECT * FROM master_pengguna WHERE NamaAkun = :username LIMIT 1");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $gagal = false;
                if (!$user) {
                    $error_message = "Username tidak ditemukan.";
                    $error_field   = 'username';
                    $gagal = true;
                } elseif (($user['StatusData'] ?? '') !== 'AKTIF') {
                    $error_message = "Akun tidak aktif. Hubungi administrator.";
                    $error_field   = 'username';
                    $gagal = true;
                } else {
                    $tersimpan = (string) $user['KataKunci'];
                    if (!password_verify($password, $tersimpan)) {
                        $error_message = "Kata sandi salah.";
                        $error_field   = 'password';
                        $gagal = true;
                    }
                }

                if ($gagal) {
                    lt_catat_gagal($kunci_ua, LT_BATAS_AKUN);
                    lt_catat_gagal($kunci_ip, LT_BATAS_IP);

                    $sisa_percobaan = LT_BATAS_AKUN - lt_jumlah_gagal($kunci_ua);
                    if ($sisa_percobaan > 0) {
                        $error_message .= " Sisa percobaan: $sisa_percobaan.";
                    } else {
                        $error_message = "Terlalu banyak percobaan login yang gagal. Coba lagi dalam " . LT_KUNCI_MENIT . " menit.";
                        $error_field   = '';
                    }
                } else {
                    // 3. Berhasil: bersihkan hitungan gagal, perbarui hash bila perlu, buat session baru
                    lt_reset($kunci_ua);

                    if (password_needs_rehash($tersimpan, PASSWORD_DEFAULT)) {
                        $up = $conn->prepare("UPDATE master_pengguna SET KataKunci = :h WHERE NamaAkun = :u");
                        $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':u' => $user['NamaAkun']]);
                    }

                    session_regenerate_id(true);   // cegah session fixation

                    // Inisialisasi Session RBAC S41
                    $_SESSION['NamaAkun'] = $user['NamaAkun'];
                    $_SESSION['NamaPengguna'] = $user['NamaPengguna'];
                    $_SESSION['KodeHak'] = $user['KodeHak']; 
                    $_SESSION['UnitUpi'] = $user['UnitUpi'];
                    $_SESSION['UnitAp'] = $user['UnitAp'];
                    $_SESSION['UnitUp'] = $user['UnitUp'];

                    // Redirect ke dashboard utama
                    header("Location: ../../index.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('login: ' . $e->getMessage());   // detail hanya di log server
            $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

// Muat tampilan UI (View)
require_once '../../templates/auth/login.php';
?>