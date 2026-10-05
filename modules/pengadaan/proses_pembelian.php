<?php
session_start();
require_once '../../config/database.php';

// Pastikan PDO memunculkan exception jika ada query yang gagal
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Pastikan pengguna sudah login dan memiliki otoritas
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses pembelian.");
}

$action = $_GET['action'] ?? '';

// PERBAIKAN: Paksa konversi string kosong "" menjadi NULL agar MySQL (Foreign Key) tidak error
$nama_akun = !empty($_SESSION['NamaAkun']) ? $_SESSION['NamaAkun'] : NULL;
$unit_upi  = !empty($_SESSION['UnitUpi']) ? $_SESSION['UnitUpi'] : NULL;
$unit_ap   = !empty($_SESSION['UnitAp']) ? $_SESSION['UnitAp'] : NULL;
$unit_up   = !empty($_SESSION['UnitUp']) ? $_SESSION['UnitUp'] : NULL;

try {
    // AKSI 1: BUAT DRAFT FORMULIR
    if ($action === 'create_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = trim($_POST['no_formulir']);
        $tgl_beli = $_POST['tgl_beli'];
        
        // PERBAIKAN: Tangkap input form tujuan dan paksa jadi NULL jika kosong
        $upi_tujuan = !empty($_POST['kode_upi']) ? trim($_POST['kode_upi']) : NULL;
        $ap_tujuan  = !empty($_POST['kode_ap']) ? trim($_POST['kode_ap']) : NULL;
        $up_tujuan  = !empty($_POST['kode_up']) ? trim($_POST['kode_up']) : NULL;

        $query_form = "INSERT INTO formulir_pembelian (NoFormulir, TglBeli, NamaAkun, KodeUp, KodeAp, KodeUpi, KodeUpTujuan, KodeApTujuan, KodeUpiTujuan, StatusData) 
                       VALUES (:no_form, :tgl, :akun, :up, :ap, :upi, :up_tuj, :ap_tuj, :upi_tuj, 'TIDAK')";
        $stmt_form = $conn->prepare($query_form);
        
        $stmt_form->execute([
            ':no_form' => $no_formulir,
            ':tgl'     => $tgl_beli,
            ':akun'    => $nama_akun,
            ':up'      => $unit_up,
            ':ap'      => $unit_ap,
            ':upi'     => $unit_upi,
            ':up_tuj'  => $up_tujuan,
            ':ap_tuj'  => $ap_tujuan,
            ':upi_tuj' => $upi_tujuan
        ]);
        
        header("Location: ../../index.php?page=pengadaan&menu=barang&sub=pembelian&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // AKSI 2: TAMBAH ITEM KE KERANJANG
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'];
        $metode = $_POST['metode'] ?? 'manual';
        
        $conn->beginTransaction();
        $query_detil = "INSERT INTO formulir_pembelian_detil (NoFormulir, NoRef, StikerQC, CacatFisik, HargaBeli) 
                        VALUES (:no_form, :noref, :qc, :cacat, :harga)";
        $stmt_detil = $conn->prepare($query_detil);

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Skip baris header
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $no_ref = trim($row[0] ?? '');
                    $harga  = (int) preg_replace('/[^0-9]/', '', $row[1] ?? '0');
                    $qc     = strtoupper(trim($row[2] ?? 'TIDAK'));
                    $cacat  = strtoupper(trim($row[3] ?? 'TIDAK'));

                    if (empty($no_ref)) continue;

                    $stmt_detil->execute([
                        ':no_form' => $no_formulir,
                        ':noref'   => $no_ref,
                        ':qc'      => $qc,
                        ':cacat'   => $cacat,
                        ':harga'   => $harga
                    ]);
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $stmt_detil->execute([
                ':no_form' => $no_formulir,
                ':noref'   => trim($_POST['no_ref'] ?? ''),
                ':qc'      => strtoupper(trim($_POST['stiker_qc'] ?? 'TIDAK')),
                ':cacat'   => strtoupper(trim($_POST['cacat_fisik'] ?? 'TIDAK')),
                ':harga'   => (int) preg_replace('/[^0-9]/', '', $_POST['harga'] ?? '0')
            ]);
        }
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=barang&sub=pembelian&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // AKSI 3: EKSEKUSI FINAL FORMULIR
    if ($action === 'execute_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'];
        
        $conn->beginTransaction();
        
        $stmt_update = $conn->prepare("UPDATE formulir_pembelian SET StatusData = 'AKTIF' WHERE NoFormulir = :no_form");
        $stmt_update->execute([':no_form' => $no_formulir]);
        
        $stmt_get = $conn->prepare("SELECT NoRef FROM formulir_pembelian_detil WHERE NoFormulir = :no_form");
        $stmt_get->execute([':no_form' => $no_formulir]);
        $items = $stmt_get->fetchAll(PDO::FETCH_ASSOC);
        
        $query_barang = "INSERT INTO master_barang (NoRef, UnitUpi, StatusData) VALUES (:noref, :upi, 'AKTIF')
                         ON DUPLICATE KEY UPDATE StatusData = 'AKTIF'";
        $stmt_barang = $conn->prepare($query_barang);
        
        foreach ($items as $item) {
            $stmt_barang->execute([
                ':noref' => $item['NoRef'],
                ':upi'   => $unit_upi
            ]);
        }
        
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=barang&sub=pembelian&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // Menampilkan error asli dari MySQL agar tidak tersembunyi
    die("<script>alert('Gagal memproses data! Error DB: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>