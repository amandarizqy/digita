<?php
session_start();
require_once '../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses pengiriman.");
}

$action = $_GET['action'] ?? '';
$nama_akun = !empty($_SESSION['NamaAkun']) ? $_SESSION['NamaAkun'] : NULL;
$unit_upi  = !empty($_SESSION['UnitUpi']) ? $_SESSION['UnitUpi'] : '56';

try {
    // AKSI 1: BUAT DRAFT PENGIRIMAN
    if ($action === 'create_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tgl_form = $_POST['tgl_form'] ?? date('Y-m-d');
        $tgl_format = date('Ymd', strtotime($tgl_form));
        
        $raw_ap = trim($_POST['kode_ap'] ?? '');
        $raw_up = trim($_POST['kode_up'] ?? '');

        $ap_tujuan = !empty($raw_ap) ? $raw_ap : null;
        $up_tujuan = !empty($raw_up) ? $raw_up : null;

        if (empty($ap_tujuan) && empty($up_tujuan)) {
            throw new Exception("Pilih salah satu tujuan unit pengiriman (AP atau UP).");
        }

        $kode_unit = !empty($unit_upi) ? $unit_upi : '56'; 
        $no_formulir = (string) ($kode_unit . date('His') . $tgl_format . '-F.B');

        // Kueri dengan fungsi SQL langsung untuk mencegah database mengosongkan NoFormulir
        $query_form = "INSERT INTO formulir_pengiriman (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusPengiriman, StatusData) 
                       VALUES (COALESCE(NULLIF(:no_form, ''), CONCAT(:upi, '001', :tgl_format, '-F.B')), :tgl, :akun, :up, :ap, :upi, 'DIKIRIM', 'TIDAK')";
        
        $stmt_form = $conn->prepare($query_form);
        $stmt_form->execute([
            ':no_form'    => $no_formulir,
            ':tgl_format' => $tgl_format,
            ':tgl'        => $tgl_form,
            ':akun'       => $nama_akun,
            ':up'         => $up_tujuan,
            ':ap'         => $ap_tujuan,
            ':upi'        => $unit_upi
        ]);
        
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar");
        exit;
    }

    // AKSI 2: TAMBAH ITEM KE KERANJANG
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'] ?? '';
        $metode = $_POST['metode'] ?? 'manual';
        
        $stmt_check = $conn->prepare("SELECT NoRef FROM master_barang WHERE NoRef = :noref AND (UnitAp IS NULL OR UnitAp = '') AND (UnitUp IS NULL OR UnitUp = '')");
        $stmt_check_cart = $conn->prepare("SELECT d.NoRef FROM formulir_pengiriman_detil d JOIN formulir_pengiriman f ON d.NoFormulir = f.NoFormulir WHERE d.NoRef = :noref AND f.StatusPengiriman = 'DIKIRIM'");

        $conn->beginTransaction();
        $query_detil = "INSERT INTO formulir_pengiriman_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) 
                        VALUES (:no_form, :noref, :noref_copy, :qc, :cacat)";
        $stmt_detil = $conn->prepare($query_detil);

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

                    if (empty($no_formulir)) {
                        throw new Exception("Nomor Formulir pengiriman kosong.");
                    }

                    $stmt_detil->execute([
                        ':no_form' => $no_formulir,
                        ':noref'   => $no_ref,
                        ':noref_copy' => $no_ref,
                        ':qc'      => $qc,
                        ':cacat'   => $cacat
                    ]);
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
        
        if (!empty($error_msgs)) {
            $_SESSION['flash_error'] = "Beberapa item gagal diinput: " . implode(", ", $error_msgs);
        }
        
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'delete_item') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');
        $no_ref      = trim($_POST['no_ref'] ?? '');

        if (!empty($no_formulir) && !empty($no_ref)) {
            $stmt = $conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form AND NoRef = :no_ref");
            $stmt->execute([':no_form' => $no_formulir, ':no_ref' => $no_ref]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'reset_list') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');

        if (!empty($no_formulir)) {
            $stmt = $conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_formulir]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // AKSI: HAPUS FORMULIR PENGIRIMAN BERSTATUS DRAFT
    else if ($action === 'delete_draft') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');

        if (!empty($no_formulir)) {
            $conn->beginTransaction();
            
            $stmt_detil = $conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
            $stmt_detil->execute([':no_form' => $no_formulir]);

            $stmt_main = $conn->prepare("DELETE FROM formulir_pengiriman WHERE NoFormulir = :no_form AND StatusData = 'TIDAK'");
            $stmt_main->execute([':no_form' => $no_formulir]);

            $conn->commit();
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar");
        exit;
    }

    // AKSI 3: EKSEKUSI FINAL FORMULIR PENGIRIMAN
    if ($action === 'execute_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'] ?? '';
        
        $stmt_update = $conn->prepare("UPDATE formulir_pengiriman SET StatusData = 'AKTIF', StatusPengiriman = 'DIKIRIM' WHERE NoFormulir = :no_form");
        $stmt_update->execute([':no_form' => $no_formulir]);
        
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar&status=sukses");
        exit;
    }

} catch (\Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<div style='font-family: sans-serif; padding: 20px; margin: 20px; border: 2px solid #dc3545; background: #f8d7da; color: #842029; border-radius: 8px;'>
            <h3 style='margin-top: 0;'>Gagal Memproses Data (Error Database)</h3>
            <p><strong>Pesan Error MySQL:</strong><br><br> " . nl2br(htmlspecialchars($e->getMessage())) . "</p>
            <p><strong>File:</strong> " . $e->getFile() . " (Baris " . $e->getLine() . ")</p>
            <br>
            <button onclick='window.history.back()' style='padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer;'>Kembali</button>
        </div>");
}
?>