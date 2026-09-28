<<<<<<< HEAD
<<<<<<< HEAD
=======
=======
>>>>>>> origin/main
<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

<<<<<<< HEAD
=======
// 1. Verifikasi Session Pengguna
>>>>>>> origin/main
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'index';

<<<<<<< HEAD
// 1. TAMBAH PERINTAH BAKU (POST - store)
=======
// 2. Aksi Tambah Perintah Baku Baru (POST)
>>>>>>> origin/main
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'store') {
    $isi_pesan       = trim($_POST['IsiPesan']);
    $keterangan      = trim($_POST['Keterangan']);
    $jenis_perintah  = $_POST['JenisPerintah'] ?? 'KIRIM';
    $database_tujuan = trim($_POST['DatabaseTujuan'] ?? 'smsd');
<<<<<<< HEAD
    $status_data     = ($_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';
=======
    $status_data     = $_POST['StatusData'] ?? 'AKTIF';
>>>>>>> origin/main

    if (!empty($isi_pesan)) {
        $stmt = $conn->prepare("INSERT INTO baku_outbox 
            (IsiPesan, Keterangan, JenisPerintah, DatabaseTujuan, StatusData, WaktuData) 
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$isi_pesan, $keterangan, $jenis_perintah, $database_tujuan, $status_data]);
    }
    header("Location: pesan_Controller.php");
    exit;
}

<<<<<<< HEAD
// 2. EDIT PERINTAH BAKU (POST - update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id              = trim($_POST['Id']);
    $isi_pesan       = trim($_POST['IsiPesan']);
    $keterangan      = trim($_POST['Keterangan']);
    $jenis_perintah  = $_POST['JenisPerintah'] ?? 'KIRIM';
    $database_tujuan = trim($_POST['DatabaseTujuan'] ?? 'smsd');
    $status_data     = ($_POST['StatusData'] === 'AKTIF') ? 'AKTIF' : 'TIDAK';

    if (!empty($id) && !empty($isi_pesan)) {
        $stmt = $conn->prepare("UPDATE baku_outbox SET 
            IsiPesan = ?, Keterangan = ?, JenisPerintah = ?, DatabaseTujuan = ?, StatusData = ?, WaktuData = NOW() 
            WHERE Id = ?");
        $stmt->execute([$isi_pesan, $keterangan, $jenis_perintah, $database_tujuan, $status_data, $id]);
    }
    header("Location: pesan_Controller.php");
    exit;
}

// 3. HAPUS PERINTAH BAKU (GET - delete)
if ($action === 'delete') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM baku_outbox WHERE Id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            echo "<script>alert('Gagal menghapus perintah! Data sedang dipakai oleh daemon SMS.'); window.location.href='pesan_Controller.php';</script>";
            exit;
        }
    }
    header("Location: pesan_Controller.php");
    exit;
}

// 4. TOGGLE STATUS (GET - toggle_status)
=======
// 3. Aksi Ubah Status (Toggle AKTIF <-> TIDAK)
>>>>>>> origin/main
if ($action === 'toggle_status') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT StatusData FROM baku_outbox WHERE Id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $status_baru = ($row['StatusData'] === 'AKTIF') ? 'TIDAK' : 'AKTIF';
            $update = $conn->prepare("UPDATE baku_outbox SET StatusData = ?, WaktuData = NOW() WHERE Id = ?");
            $update->execute([$status_baru, $id]);
        }
    }
    header("Location: pesan_Controller.php");
    exit;
}

<<<<<<< HEAD
// 5. QUERY DATA & HITUNG METRIK
$stmt = $conn->query("SELECT * FROM baku_outbox ORDER BY Id ASC");
$perintah = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_perintah = count($perintah);
$total_aktif = 0;
foreach ($perintah as $p) {
    if (($p['StatusData'] ?? '') === 'AKTIF') $total_aktif++;
}
$persentase_aktif = ($total_perintah > 0) ? round(($total_aktif / $total_perintah) * 100) : 0;

=======
// 4. Ambil Seluruh Data dari Tabel baku_outbox
$stmt = $conn->query("SELECT * FROM baku_outbox ORDER BY Id ASC");
$perintah = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Hitung Metrik Ringkasan (KPI)
$total_perintah = count($perintah);
$total_aktif = 0;
foreach ($perintah as $p) {
    if ($p['StatusData'] === 'AKTIF') {
        $total_aktif++;
    }
}
$persentase_aktif = ($total_perintah > 0) ? round(($total_aktif / $total_perintah) * 100) : 0;

// 6. Siapkan data & Render ke Layout Utama
>>>>>>> origin/main
$page_title = "Master Perintah Baku - Digita S41";

ob_start();
require_once __DIR__ . '/../../templates/master/pesan.html';
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';
<<<<<<< HEAD
?>
>>>>>>> 00b2ee0 (Simpan perubahan sebelum pindah branch)
=======
?>
>>>>>>> origin/main
