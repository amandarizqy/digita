<?php
session_start();
require_once '../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses pengiriman.");
}

$action = $_GET['action'] ?? '';
$nama_akun = $_SESSION['NamaAkun'];
$unit_upi  = $_SESSION['UnitUpi'] ?? '56';

try {
    // AKSI 1: BUAT DRAFT PENGIRIMAN
    if ($action === 'create_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = trim($_POST['no_formulir']);
        $tgl_form = $_POST['tgl_form'];
        $ap_tujuan = !empty($_POST['kode_ap_tujuan']) ? $_POST['kode_ap_tujuan'] : NULL;
        $up_tujuan = !empty($_POST['kode_up_tujuan']) ? $_POST['kode_up_tujuan'] : NULL;

        // KodeUp dan KodeAp di formulir ini bertindak sebagai Tujuan
        $query_form = "INSERT INTO formulir_pengiriman (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusPengiriman, StatusData) 
                       VALUES (:no_form, :tgl, :akun, :up, :ap, :upi, 'DIKIRIM', 'TIDAK')";
        $stmt_form = $conn->prepare($query_form);
        $stmt_form->execute([
            ':no_form' => $no_formulir,
            ':tgl'     => $tgl_form,
            ':akun'    => $nama_akun,
            ':up'      => $up_tujuan,
            ':ap'      => $ap_tujuan,
            ':upi'     => $unit_upi
        ]);
        
        header("Location: pengiriman.php?view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // AKSI 2: TAMBAH ITEM KE KERANJANG (VALIDASI ASET)
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'];
        $metode = $_POST['metode'] ?? 'manual';
        
        // Siapkan pengecekan apakah aset tersedia dan valid (Bukan milik AP/UP lain)
        $stmt_check = $conn->prepare("SELECT NoRef FROM master_barang WHERE NoRef = :noref AND (UnitAp IS NULL OR UnitAp = '') AND (UnitUp IS NULL OR UnitUp = '')");
        
        // Siapkan pengecekan apakah sudah ada di keranjang lain yang aktif/belum diterima
        $stmt_check_cart = $conn->prepare("SELECT d.NoRef FROM formulir_pengiriman_detil d JOIN formulir_pengiriman f ON d.NoFormulir = f.NoFormulir WHERE d.NoRef = :noref AND f.StatusPengiriman = 'DIKIRIM'");

        $conn->beginTransaction();
        $query_detil = "INSERT INTO formulir_pengiriman_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) 
                        VALUES (:no_form, :noref, :noref_copy, :qc, :cacat)";
        $stmt_detil = $conn->prepare($query_detil);

        $added_count = 0;
        $error_msgs = [];

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); 
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $no_ref = trim($row[0] ?? '');
                    $qc     = strtoupper(trim($row[1] ?? 'ADA'));
                    $cacat  = strtoupper(trim($row[2] ?? 'TIDAK'));

                    if (empty($no_ref)) continue;

                    // Validasi Ketersediaan
                    $stmt_check->execute([':noref' => $no_ref]);
                    if ($stmt_check->rowCount() === 0) {
                        $error_msgs[] = "$no_ref (Tidak ada di stok UI)";
                        continue;
                    }
                    
                    $stmt_check_cart->execute([':noref' => $no_ref]);
                    if ($stmt_check_cart->rowCount() > 0) {
                        $error_msgs[] = "$no_ref (Sedang dalam proses pengiriman lain)";
                        continue;
                    }

                    $stmt_detil->execute([
                        ':no_form' => $no_formulir,
                        ':noref'   => $no_ref,
                        ':noref_copy' => $no_ref,
                        ':qc'      => $qc,
                        ':cacat'   => $cacat
                    ]);
                    $added_count++;
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $no_ref = trim($_POST['no_ref'] ?? '');
            $qc     = strtoupper(trim($_POST['stiker_qc'] ?? 'ADA'));
            $cacat  = strtoupper(trim($_POST['cacat_fisik'] ?? 'TIDAK'));

            if (!empty($no_ref)) {
                $stmt_check->execute([':noref' => $no_ref]);
                if ($stmt_check->rowCount() === 0) {
                    throw new Exception("Barang $no_ref tidak ditemukan di stok gudang UI.");
                }
                
                $stmt_check_cart->execute([':noref' => $no_ref]);
                if ($stmt_check_cart->rowCount() > 0) {
                    throw new Exception("Barang $no_ref sedang dalam proses pengiriman lain.");
                }

                $stmt_detil->execute([
                    ':no_form' => $no_formulir,
                    ':noref'   => $no_ref,
                    ':noref_copy' => $no_ref,
                    ':qc'      => $qc,
                    ':cacat'   => $cacat
                ]);
            }
        }
        
        $conn->commit();
        
        // Penanganan alert untuk upload massal
        if (!empty($error_msgs)) {
            $_SESSION['flash_error'] = "Beberapa item gagal diinput: " . implode(", ", $error_msgs);
        }
        
        header("Location: pengiriman.php?view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // AKSI 3: EKSEKUSI FINAL FORMULIR PENGIRIMAN
    if ($action === 'execute_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'];
        
        // Ubah status form menjadi AKTIF agar terdeteksi oleh unit tujuan
        $stmt_update = $conn->prepare("UPDATE formulir_pengiriman SET StatusData = 'AKTIF', StatusPengiriman = 'DIKIRIM' WHERE NoFormulir = :no_form");
        $stmt_update->execute([':no_form' => $no_formulir]);
        
        header("Location: pengiriman.php?view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>