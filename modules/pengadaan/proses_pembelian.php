<?php
session_start();
require_once '../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses pembelian.");
}

$action = $_GET['action'] ?? '';

$nama_akun = !empty($_SESSION['NamaAkun']) ? $_SESSION['NamaAkun'] : NULL;
$unit_upi  = !empty($_SESSION['UnitUpi']) ? $_SESSION['UnitUpi'] : '56'; // Default Banten (56)
$unit_ap   = !empty($_SESSION['UnitAp']) ? $_SESSION['UnitAp'] : NULL;
$unit_up   = !empty($_SESSION['UnitUp']) ? $_SESSION['UnitUp'] : NULL;

try {
    // AKSI 1: BUAT DRAFT FORMULIR SECARA OTOMATIS (ONE-CLICK)
    if ($action === 'create_draft') {
        $tgl_beli = date('Y-m-d');
        $tgl_format = date('Ymd'); // Output: 20261008
        
        $upi_tujuan = NULL;
        $ap_tujuan  = NULL;
        $up_tujuan  = NULL;

        // KODE GENERATOR NOMOR FORMULIR (Format: UPI + 001 + YYYYMMDD + -F.A) = 17 Karakter
        $kode_unit = !empty($unit_upi) ? $unit_upi : '56'; 
        
        // Cari no formulir terakhir di database untuk hari yang sama
        $pola_pencarian = $kode_unit . '___' . $tgl_format . '-F.A'; 
        $stmt_seq = $conn->prepare("SELECT NoFormulir FROM formulir_pembelian WHERE NoFormulir LIKE :pola ORDER BY NoFormulir DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $pola_pencarian]);
        $last_form = $stmt_seq->fetchColumn();
        
        if ($last_form) {
            // Jika hari ini sudah ada form, ambil 3 digit urutannya (Karakter ke-3 sampai ke-5)
            $urutan_terakhir = (int) substr($last_form, 2, 3);
            $urutan_baru = $urutan_terakhir + 1;
        } else {
            // Jika belum ada form di hari ini, mulai dari 1
            $urutan_baru = 1;
        }
        
        // Jadikan format 3 digit (Contoh: 1 menjadi 001)
        $urutan_str = str_pad($urutan_baru, 3, '0', STR_PAD_LEFT); 
        
        // Gabungkan semuanya menjadi 17 Karakter!
        $no_formulir = $kode_unit . $urutan_str . $tgl_format . '-F.A';

        // INSERT DENGAN PARAMETER NoFormulir YANG SUDAH DI-GENERATE
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
        
        // REDIRECT KE HALAMAN DETAIL MENGGUNAKAN NOMOR YANG DIBUAT PHP
        header("Location: ../../index.php?page=pengadaan&menu=pembelian&view=detail&no_form=" . urlencode($no_formulir));
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
                fgetcsv($handle, 1000, ";"); 
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
        header("Location: ../../index.php?page=pengadaan&menu=pembelian&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'delete_item') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');
        $no_ref      = trim($_POST['no_ref'] ?? '');

        if (!empty($no_formulir) && !empty($no_ref)) {
            $stmt = $conn->prepare("DELETE FROM formulir_pembelian_detil WHERE NoFormulir = :no_form AND NoRef = :no_ref");
            $stmt->execute([':no_form' => $no_formulir, ':no_ref' => $no_ref]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pembelian&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'reset_list') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');

        if (!empty($no_formulir)) {
            $stmt = $conn->prepare("DELETE FROM formulir_pembelian_detil WHERE NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_formulir]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pembelian&view=detail&no_form=" . urlencode($no_formulir));
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
        header("Location: ../../index.php?page=pengadaan&menu=pembelian&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<script>alert('Gagal memproses data! Error DB: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>