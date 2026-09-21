<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = $_POST['metode'] ?? 'manual';
    $tgl_beli = $_POST['tgl_beli_massal'] ?? $_POST['tgl_beli'] ?? date('Y-m-d');
    
    // Ambil hierarki unit dari sesi pengguna
    $unit_upi = $_SESSION['UnitUpi'] ?? '';
    $unit_ap  = $_SESSION['UnitAp'] ?? '';
    $unit_up  = $_SESSION['UnitUp'] ?? '';

    // Generate NoFormulir (Batas 12 Karakter: FB + ymd + 4 digit acak)
    $no_formulir = 'FB' . date('ymd') . rand(1000, 9999);

    try {
        // Mulai Transaksi Database
        $conn->beginTransaction();

        // 1. Buat Header Formulir Pembelian (Total Biaya 0 sementara)
        $query_form = "INSERT INTO formulir_pembelian (NoFormulir, TglBeli, BiayaBeli, KodeUp, KodeAp, KodeUpi, StatusData) 
                       VALUES (:no_form, :tgl, 0, :up, :ap, :upi, 'AKTIF')";
        $stmt_form = $conn->prepare($query_form);
        $stmt_form->execute([
            ':no_form' => $no_formulir,
            ':tgl' => $tgl_beli,
            ':up' => $unit_up,
            ':ap' => $unit_ap,
            ':upi' => $unit_upi
        ]);

        $total_biaya = 0;

        // Siapkan Statement (Prepared Statements) untuk efisiensi loop
        $query_barang = "INSERT INTO master_barang (KodeBarang, NamaBarang, NomorRef, UnitUp, UnitAp, UnitUpi, StatusData) 
                         VALUES (:kode, 'Perangkat S41', :noref, :up, :ap, :upi, 'AKTIF')";
        $stmt_barang = $conn->prepare($query_barang);

        $query_detil = "INSERT INTO formulir_pembelian_detil (NoFormulir, KodeBarang, NomorRef, StikerQC, CacatFisik, HargaBeli) 
                        VALUES (:no_form, :kode, :noref, :qc, :cacat, :harga)";
        $stmt_detil = $conn->prepare($query_detil);

        // --- BLOK PROSES FILE CSV ---
        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            
            // Buka file CSV untuk dibaca
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Lewati baris pertama (Header/Judul Kolom)
                fgetcsv($handle, 1000, ",");
                
                $index = 1;
                // Baca baris demi baris menggunakan delimiter koma (,)
                // Catatan: Ubah "," menjadi ";" jika PC pengguna menggunakan regional Indonesia
                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $no_ref = trim($row[0] ?? '');
                    // Bersihkan string harga menjadi angka murni
                    $harga  = (int) preg_replace('/[^0-9]/', '', $row[1] ?? '0');
                    $qc     = strtoupper(trim($row[2] ?? 'TIDAK'));
                    $cacat  = strtoupper(trim($row[3] ?? 'YA'));

                    // Lewati jika kolom Nomor Referensi kosong
                    if (empty($no_ref)) continue;

                    // Generate KodeBarang (Batas 12 Karakter: BR + ymd + 4 digit urutan)
                    $kode_barang = 'BR' . date('ymd') . str_pad($index, 4, '0', STR_PAD_LEFT);

                    // Eksekusi Insert ke Master Barang
                    $stmt_barang->execute([
                        ':kode'  => $kode_barang,
                        ':noref' => $no_ref,
                        ':up'    => $unit_up,
                        ':ap'    => $unit_ap,
                        ':upi'   => $unit_upi
                    ]);

                    // Eksekusi Insert ke Detail Pembelian
                    $stmt_detil->execute([
                        ':no_form' => $no_formulir,
                        ':kode'    => $kode_barang,
                        ':noref'   => $no_ref,
                        ':qc'      => $qc,
                        ':cacat'   => $cacat,
                        ':harga'   => $harga
                    ]);

                    $total_biaya += $harga;
                    $index++;
                }
                fclose($handle);
            }

        // --- BLOK PROSES MANUAL (Satu per Satu) ---
        } elseif ($metode === 'manual') {
            $no_ref = trim($_POST['no_ref'] ?? '');
            $harga  = (int) preg_replace('/[^0-9]/', '', $_POST['harga'] ?? '0');
            $qc     = strtoupper(trim($_POST['stiker_qc'] ?? 'TIDAK'));
            $cacat  = strtoupper(trim($_POST['cacat_fisik'] ?? 'YA'));

            if (!empty($no_ref)) {
                $kode_barang = 'BR' . date('ymd') . '0001';

                $stmt_barang->execute([
                    ':kode'  => $kode_barang, 
                    ':noref' => $no_ref,
                    ':up'    => $unit_up, 
                    ':ap'    => $unit_ap, 
                    ':upi'   => $unit_upi
                ]);

                $stmt_detil->execute([
                    ':no_form' => $no_formulir, 
                    ':kode'    => $kode_barang,
                    ':noref'   => $no_ref, 
                    ':qc'      => $qc, 
                    ':cacat'   => $cacat, 
                    ':harga'   => $harga
                ]);

                $total_biaya += $harga;
            }
        }

        // 2. Update Total Biaya pada Header Formulir Pembelian
        $stmt_update = $conn->prepare("UPDATE formulir_pembelian SET BiayaBeli = :total WHERE NoFormulir = :no_form");
        $stmt_update->execute([':total' => $total_biaya, ':no_form' => $no_formulir]);

        // Permanenkan perubahan database
        $conn->commit();
        
        // Arahkan kembali dengan pesan sukses
        header("Location: index.php?status=sukses");
        exit;

    } catch (Exception $e) {
        // Batalkan semua eksekusi insert jika terjadi error
        $conn->rollBack();
        die("Gagal memproses data pembelian: " . $e->getMessage());
    }
}
?>