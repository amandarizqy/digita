<?php
session_start();

// Panggil konfigurasi database
require_once '../../config/database.php';

// Jika pengguna sudah login, arahkan langsung ke dashboard
if (isset($_SESSION['NamaAkun'])) {
    header("Location: ../../index.php");
    exit;
}

$error_message = '';

// Proses form jika metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Cek kredensial menggunakan PDO Prepared Statement
        $query = "SELECT * FROM master_pengguna WHERE NamaAkun = :username AND KataKunci = :password AND StatusData = 'AKTIF'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Inisialisasi Session RBAC S41
            $_SESSION['NamaAkun'] = $user['NamaAkun'];
            $_SESSION['NamaPengguna'] = $user['NamaPengguna'];
            $_SESSION['KodeHak'] = $user['KodeHak']; // Contoh: 'SUP', 'ADM', 'STF'
            $_SESSION['UnitUpi'] = $user['UnitUpi'];
            $_SESSION['UnitAp'] = $user['UnitAp'];
            $_SESSION['UnitUp'] = $user['UnitUp'];
            
            // Redirect ke dashboard utama
            header("Location: ../../index.php");
            exit;
        } else {
            $error_message = "Username atau kata sandi salah, atau akun tidak aktif.";
        }
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}

// Muat tampilan UI (View)
require_once '../../templates/auth/login.php';
?>