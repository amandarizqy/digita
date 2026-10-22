<?php
session_start();
require_once '../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$kode_hak = $_SESSION['KodeHak'] ?? '';$allowed_roles = ['AM.UI', 'AM.AP', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak,$allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses pengiriman.");
}

$action = $_GET['action'] ?? '';$nama_akun = !empty($_SESSION['NamaAkun']) ?$_SESSION['NamaAkun'] : NULL;
$unit_upi  = !empty($_SESSION['UnitUpi']) ?$_SESSION['UnitUpi'] : '56';

try {
    // AKSI 1: BUAT DRAFT PENGIRIMAN (DINAMIS SEPENUHNYA)
    if ($action === 'create_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {$tgl_form = date('Y-m-d');
        $tgl_format = date('Ymd', strtotime($tgl_form));
        
        $raw_ap_tujuan = trim($_POST['kode_ap'] ?? '');
        $raw_up_tujuan = trim($_POST['kode_up'] ?? '');

        $ap_tujuan = !empty($raw_ap_tujuan) ?$raw_ap_tujuan : null;
        $up_tujuan = !empty($raw_up_tujuan) ?$raw_up_tujuan : null;

        if (empty($ap_tujuan) && empty($up_tujuan)) {
            throw new Exception("Pilih salah satu tujuan unit pengiriman (AP atau UP).");
        }

        $pembuat_upi = $_SESSION['UnitUpi'] ?? $_SESSION['KodeUpi'] ?? '56';
        if (empty($pembuat_upi)) {
            throw new Exception("Sesi Unit UPI tidak ditemukan. Silakan login ulang.");
        }

        $pembuat_up  =$_SESSION['UnitUp'] ?? null; 
        $pembuat_ap  =$_SESSION['UnitAp'] ?? null; 

        $prefiks_no = !empty($pembuat_up) ? $pembuat_up : (!empty($pembuat_ap) ? $pembuat_ap :$pembuat_upi);
        
        // Hitung nomor urut berdasarkan jumlah hari ini yang murni milik UPI tersebut menggunakan kolom UnitUpi[cite: 43]
        $stmt_count =$conn->prepare("SELECT COUNT(*) FROM formulir_pengiriman WHERE TglFormulir = ? AND UnitUpi = ?");
        $stmt_count->execute([$tgl_form,$pembuat_upi]);
        $jumlah_hari_ini = (int)$stmt_count->fetchColumn();
        $urutan_baru =$jumlah_hari_ini + 1;
        $urutan_str = str_pad($urutan_baru, 3, '0', STR_PAD_LEFT);
        
        $no_formulir =$prefiks_no . $urutan_str .$tgl_format . '-F.B';

        // INSERT DATA KE TABEL MENGGUNAKAN NAMA KOLOM YANG SESUAI DATABASE (`NoFormKirim`, `UnitUp`, `UnitAp`, `UnitUpi`, `KodeUpTujuan`, `KodeApTujuan`, `KodeUpiTujuan`)[cite: 43]
        $query_form = "INSERT INTO formulir_pengiriman (
                            NoFormKirim, TglFormulir, NamaAkun, 
                            UnitUp, UnitAp, UnitUpi, 
                            KodeUpTujuan, KodeApTujuan, KodeUpiTujuan, 
                            StatusData
                       ) VALUES (
                            :no_form, :tgl, :akun, 
                            :p_up, :p_ap, :p_upi, 
                            :t_up, :t_ap, :t_upi, 
                            'TIDAK'
                       )";
        
        $stmt_form =$conn->prepare($query_form);$stmt_form->execute([
            ':no_form' => $no_formulir,
            ':tgl'     => $tgl_form,
            ':akun'    => $nama_akun,
            ':p_up'    => $pembuat_up,
            ':p_ap'    => $pembuat_ap,
            ':p_upi'   => $pembuat_upi,
            ':t_up'    => $up_tujuan,
            ':t_ap'    => $ap_tujuan,
            ':t_upi'   => $pembuat_upi
        ]);
        
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar");
        exit;
    }

    // AKSI 2: TAMBAH ITEM KE KERANJANG
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir = $_POST['no_formulir'] ?? '';
        $metode = $_POST['metode'] ?? 'manual';
        
        $user_ap = $_SESSION['UnitAp'] ?? '';
        $is_ap_role = ($kode_hak === 'AM.AP');

        // Validasi stok disesuaikan berdasarkan level role yang login (AM.AP vs AM.UI)
        if ($is_ap_role) {
            $stmt_check = $conn->prepare("SELECT NoRef FROM master_barang WHERE NoRef = :noref AND UnitAp = :ap AND (UnitUp IS NULL OR UnitUp = '')");
            $stmt_check->bindParam(':ap', $user_ap);
        } else {
            $stmt_check = $conn->prepare("SELECT NoRef FROM master_barang WHERE NoRef = :noref AND (UnitAp IS NULL OR UnitAp = '') AND (UnitUp IS NULL OR UnitUp = '')");
        }
        
        $stmt_check_cart = $conn->prepare("SELECT d.NoRef FROM formulir_pengiriman_detil d JOIN formulir_pengiriman f ON d.NoFormKirim = f.NoFormKirim WHERE d.NoRef = :noref AND f.NoFormKirim NOT IN (SELECT NoFormKirim FROM formulir_penerimaan)");

        $conn->beginTransaction();
        $query_detil = "INSERT INTO formulir_pengiriman_detil (NoFormKirim, NoRef, StikerQC, CacatFisik) 
                        VALUES (:no_form, :noref, :qc, :cacat)";
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

                    if ($is_ap_role) {
                        $stmt_check->execute([':noref' => $no_ref, ':ap' => $user_ap]);
                    } else {
                        $stmt_check->execute([':noref' => $no_ref]);
                    }

                    if ($stmt_check->rowCount() === 0) {
                        $error_msgs[] = "$no_ref (Tidak ada di stok unit Anda)";
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
                if ($is_ap_role) {
                    $stmt_check->execute([':noref' => $no_ref, ':ap' => $user_ap]);
                } else {
                    $stmt_check->execute([':noref' => $no_ref]);
                }

                if ($stmt_check->rowCount() === 0) {
                    throw new Exception("Barang $no_ref tidak ditemukan di stok unit gudang Anda.");
                }
                
                $stmt_check_cart->execute([':noref' => $no_ref]);
                if ($stmt_check_cart->rowCount() > 0) {
                    throw new Exception("Barang $no_ref sedang dalam proses pengiriman lain.");
                }

                $stmt_detil->execute([
                    ':no_form' => $no_formulir,
                    ':noref'   => $no_ref,
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
            $stmt =$conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormKirim = :no_form AND NoRef = :no_ref");
            $stmt->execute([':no_form' => $no_formulir, ':no_ref' =>$no_ref]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'reset_list') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');

        if (!empty($no_formulir)) {
            $stmt =$conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormKirim = :no_form");
            $stmt->execute([':no_form' =>$no_formulir]);
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    else if ($action === 'delete_draft') {
        $no_formulir = trim($_POST['no_formulir'] ?? '');

        if (!empty($no_formulir)) {
            $conn->beginTransaction();
            
            // 1. Hapus dulu detil item pengiriman berdasarkan NoFormKirim
            $stmt_detil = $conn->prepare("DELETE FROM formulir_pengiriman_detil WHERE NoFormKirim = :no_form");
            $stmt_detil->execute([':no_form' => $no_formulir]);

            // 2. Hapus header formulir pengiriman yang statusnya masih Draft ('TIDAK')
            $stmt_main = $conn->prepare("DELETE FROM formulir_pengiriman WHERE NoFormKirim = :no_form AND StatusData = 'TIDAK'");
            $stmt_main->execute([':no_form' => $no_formulir]);

            $conn->commit();
        }
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar");
        exit;
    }

    else if ($action === 'execute_form' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_formulir =$_POST['no_formulir'] ?? '';
        
        $stmt_update =$conn->prepare("UPDATE formulir_pengiriman SET StatusData = 'AKTIF' WHERE NoFormKirim = :no_form");
        $stmt_update->execute([':no_form' =>$no_formulir]);
        
        header("Location: ../../index.php?page=pengadaan&menu=pengiriman&view=daftar&status=sukses");
        exit;
    }

} catch (\Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {$conn->rollBack();
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