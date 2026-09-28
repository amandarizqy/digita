<?php
session_start();
<<<<<<< HEAD

// Hubungkan ke koneksi database & pengecekan auth
require_once __DIR__ . '/../../config/database.php';
if (file_exists(__DIR__ . '/../../includes/auth_check.php')) {
    require_once __DIR__ . '/../../includes/auth_check.php';
}

// Catatan Hak Akses: Sesuai matriks otorisasi, menu Master Provider diperuntukkan bagi role SF.UI
// Jika sedang tahap pengujian/development dan belum login dengan role tersebut, baris pengecekan ini dapat disesuaikan.
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'SF.UI') {
    http_response_code(403);
    die("Akses ditolak: Anda tidak memiliki hak akses (SF.UI) untuk menu ini.");
=======
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
>>>>>>> origin/main
}

$action = $_GET['action'] ?? 'index';

<<<<<<< HEAD
switch ($action) {
    case 'index':
        // Mengambil seluruh data dari tabel master_provider
        $query = "SELECT KodeProvider, NamaProvider, StatusData, WaktuData FROM master_provider ORDER BY KodeProvider ASC";
        $result = mysqli_query($conn, $query);
        
        $providers = [];
        if ($result) {
            $providers = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }

        // Panggil template tampilan
        include __DIR__ . '/../../templates/master/provider.php';
        break;

    case 'store':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $namaProvider = trim($_POST['NamaProvider'] ?? '');
            $statusData   = $_POST['StatusData'] ?? 'AKTIF';

            if (!empty($namaProvider)) {
                $stmt = mysqli_prepare($conn, "INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, NOW())");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ss", $namaProvider, $statusData);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        header('Location: provider_Controller.php?action=index');
        exit;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $kodeProvider = (int)($_POST['KodeProvider'] ?? 0);
            $namaProvider = trim($_POST['NamaProvider'] ?? '');
            $statusData   = $_POST['StatusData'] ?? 'AKTIF';

            if ($kodeProvider > 0 && !empty($namaProvider)) {
                $stmt = mysqli_prepare($conn, "UPDATE master_provider SET NamaProvider = ?, StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssi", $namaProvider, $statusData, $kodeProvider);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        header('Location: provider_Controller.php?action=index');
        exit;

    case 'delete':
        $kodeProvider = (int)($_GET['id'] ?? 0);
        if ($kodeProvider > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM master_provider WHERE KodeProvider = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $kodeProvider);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: provider_Controller.php?action=index');
        exit;

    default:
        header('Location: provider_Controller.php?action=index');
        exit;
}
=======
// 1. TAMBAH PROVIDER BARU
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_provider = trim($_POST['NamaProvider']);
    $status_data   = $_POST['StatusData'] ?? 'AKTIF';
    $waktu_data    = date('Y-m-d H:i:s');

    if (!empty($nama_provider)) {
        $stmt = $conn->prepare("INSERT INTO master_provider (NamaProvider, StatusData, WaktuData) VALUES (?, ?, ?)");
        $stmt->execute([$nama_provider, $status_data, $waktu_data]);
    }
    header("Location: provider_Controller.php");
    exit;
}

// 2. TOGGLE STATUS (AKTIF / NONAKTIF)
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT StatusData FROM master_provider WHERE KodeProvider = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'NONAKTIF' : 'AKTIF';
            $update = $conn->prepare("UPDATE master_provider SET StatusData = ?, WaktuData = NOW() WHERE KodeProvider = ?");
            $update->execute([$status_baru, $id]);
        }
    }
    header("Location: provider_Controller.php");
    exit;
}

// 3. AMBIL DATA DARI TABEL master_provider
$stmt = $conn->query("SELECT * FROM master_provider ORDER BY KodeProvider ASC");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render template tampilan
require_once '../../templates/master/provider.html';
>>>>>>> origin/main
