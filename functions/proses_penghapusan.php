<?php
// File: functions/proses_penghapusan.php
session_start();

// Panggil file koneksi database (PDO)
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

// Ambil variabel koneksi PDO (menyesuaikan variabel di database.php Anda)
$db = $pdo ?? $conn ?? $db ?? null;

// --- Aksi 1: Simpan FPK / Perintah Kerja Baru ---
if ($action === 'buat_pk') {
    $id_pelanggan = $_POST['id_pelanggan'] ?? '';
    $tanggal      = $_POST['tanggal'] ?? date('Y-m-d');
    $longitude    = $_POST['longitude'] ?? '';
    $latitude     = $_POST['latitude'] ?? '';
    $alasan       = $_POST['alasan_penghapusan'] ?? '';
    
    // Generate No PK Otomatis
    $no_pk = 'PK-' . date('Ym') . '-' . rand(100, 999);
    
    if ($db) {
        try {
            // Buat tabel jika belum ada (Sintaks PDO)
            $db->exec("CREATE TABLE IF NOT EXISTS perintah_kerja_hapus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                no_pk VARCHAR(50) NOT NULL,
                tgl_pk DATE NOT NULL,
                id_pelanggan VARCHAR(50) NOT NULL,
                latitude VARCHAR(50),
                longitude VARCHAR(50),
                alasan TEXT,
                no_order VARCHAR(50) DEFAULT '-',
                petugas VARCHAR(50) DEFAULT '-',
                status VARCHAR(30) DEFAULT 'PENDING'
            )");

            // Query Prepared Statement PDO
            $stmt = $db->prepare("INSERT INTO perintah_kerja_hapus (no_pk, tgl_pk, id_pelanggan, latitude, longitude, alasan) VALUES (?, ?, ?, ?, ?, ?)");
            
            // Eksekusi parameter dalam bentuk array (Khas PDO)
            $stmt->execute([$no_pk, $tanggal, $id_pelanggan, $latitude, $longitude, $alasan]);
        } catch (PDOException $e) {
            // Menangani error jika ada kendala database
            die("Database Error: " . $e->getMessage());
        }
    }

    echo "<script>
        alert('FPK Penghapusan $no_pk Berhasil Disimpan!');
        window.location.href = '../index.php?page=penghapusan&tab=eksekusi&sub=order_penghapusan';
    </script>";
    exit();
}

// --- Aksi 2: Simpan Order Penghapusan ---
if ($action === 'buat_order') {
    $id_pk    = $_POST['id_pk'] ?? '';
    $no_order = $_POST['no_order'] ?? '';
    $petugas  = $_POST['petugas'] ?? '';

    if ($db) {
        try {
            $stmt = $db->prepare("UPDATE perintah_kerja_hapus SET no_order = ?, petugas = ?, status = 'ORDER TERBIT' WHERE id = ? OR no_pk = ?");
            $stmt->execute([$no_order, $petugas, $id_pk, $id_pk]);
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }

    echo "<script>
        alert('Order Penghapusan Berhasil Diterbitkan!');
        window.location.href = '../index.php?page=penghapusan&tab=eksekusi&sub=order_penghapusan';
    </script>";
    exit();
}