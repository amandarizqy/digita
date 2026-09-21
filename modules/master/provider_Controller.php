<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'index';

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