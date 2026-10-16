<?php
session_start();
require_once '../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("<script>alert('Akses Ditolak.'); window.history.back();</script>");
}

$action = $_GET['action'] ?? '';
$unit_upi = $_SESSION['UnitUpi'] ?? '';
$unit_ap_session  = $_SESSION['UnitAp'] ?? '';
$unit_up_session  = $_SESSION['UnitUp'] ?? '';

// --- LOGIKA PEMISAHAN HIERARKI UNIT ---
// Jika login sebagai TL.UP (tingkat ULP), maka KodeUp terisi, KodeAp di-NULL-kan (atau sesuaikan kebutuhan)
// Jika login sebagai TL.AP (tingkat UP3/AP), maka KodeAp terisi, KodeUp di-NULL-kan.
$final_ap = null;
$final_up = null;

if ($kode_hak === 'TL.UP') {
    $final_up = !empty($unit_up_session) ? $unit_up_session : null;
    // Jika AP tetap ingin diisi induknya atau dibiarkan null, sesuaikan di sini. 
    // Berdasarkan permintaan: "seharusnya hanya kodeap nya, kodeupnya tetap null dan begitu juga sebaliknya"
    $final_ap = null; 
} elseif ($kode_hak === 'TL.AP') {
    $final_ap = !empty($unit_ap_session) ? $unit_ap_session : null;
    $final_up = null; // Pastikan KodeUp bernilai NULL jika diinput oleh TL.AP
} elseif ($kode_hak === 'SA.KP') {
    // Super Admin Pusat bebas / ikuti session
    $final_ap = !empty($unit_ap_session) ? $unit_ap_session : null;
    $final_up = !empty($unit_up_session) ? $unit_up_session : null;
}
// -------------------------------------

try {
    $conn->beginTransaction();

    // -----------------------------------------
    // PROSES AKTIVASI PERDANA
    // -----------------------------------------
    if ($action === 'aktivasi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $metode = $_POST['metode'] ?? 'manual';
        
        $query_insert = "INSERT INTO master_nomor (SimId, KodeProduk, KodeProvider, NomorAkun, JenisProduk, TglAktifasi, KodeUp, KodeAp, KodeUpi, StatusData) 
                         VALUES (:simid, :produk, :provider, :akun, :jenis, :tgl, :up, :ap, :upi, 'AKTIF')
                         ON DUPLICATE KEY UPDATE TglAktifasi = :tgl, StatusData = 'AKTIF'";
        $stmt = $conn->prepare($query_insert);

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Skip header
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $simid    = trim($row[0] ?? '');
                    $akun     = trim($row[1] ?? '');
                    $provider = (int)($row[2] ?? 0);
                    $produk   = (int)($row[3] ?? 0);
                    $jenis    = strtoupper(trim($row[4] ?? 'PRABAYAR'));
                    $tgl      = trim($row[5] ?? date('Y-m-d'));

                    if (empty($simid)) continue;

                    $stmt->execute([
                        ':simid' => $simid, ':produk' => $produk, ':provider' => $provider, 
                        ':akun' => $akun, ':jenis' => $jenis, ':tgl' => $tgl,
                        ':up' => $final_up, ':ap' => $final_ap, ':upi' => $unit_upi
                    ]);
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $stmt->execute([
                ':simid'    => trim($_POST['sim_id']),
                ':produk'   => $_POST['kode_produk'],
                ':provider' => $_POST['kode_provider'],
                ':akun'     => trim($_POST['nomor_akun']),
                ':jenis'    => $_POST['jenis_produk'],
                ':tgl'      => $_POST['tgl_aktifasi'],
                ':up'       => $final_up, 
                ':ap'       => $final_ap, 
                ':upi'      => $unit_upi
            ]);
        }
        
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=kartu&sub=aktivasi&view=daftar&status=sukses");
        exit;
    }

    // -----------------------------------------
    // PROSES ISI PULSA (Sinkron Master & Transaksi)
    // -----------------------------------------
    if ($action === 'pulsa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $metode = $_POST['metode'] ?? 'manual';
        
        // 1. Update data terbaru di master_nomor
        $query_update = "UPDATE master_nomor SET JumlahKredit = :nominal, TglPulsaTerakhir = :tgl WHERE SimId = :simid";
        $stmt = $conn->prepare($query_update);

        // 2. Insert ke tabel transaksi_pulsa untuk histori audit
        $query_log = "INSERT INTO transaksi_pulsa (TglTrx, SimId, IsiKredit, JumlahKredit, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) 
                      VALUES (:tgl, :simid, 'YA', :nominal, :akun, :up, :ap, :upi, 'AKTIF')";
        $stmt_log = $conn->prepare($query_log);

        $nama_akun = $_SESSION['NamaAkun'] ?? 'System';
        $unit_upi  = $_SESSION['UnitUpi'] ?? '56';
        $unit_ap   = !empty($_SESSION['UnitAp']) ? $_SESSION['UnitAp'] : null;
        $unit_up   = !empty($_SESSION['UnitUp']) ? $_SESSION['UnitUp'] : null;

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Skip header
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $simid   = trim($row[0] ?? '');
                    $nominal = (int) preg_replace('/[^0-9]/', '', $row[1] ?? '0');
                    $tgl     = trim($row[2] ?? date('Y-m-d'));

                    if (empty($simid)) continue;

                    $stmt->execute([':nominal' => $nominal, ':tgl' => $tgl, ':simid' => $simid]);
                    
                    // Catat ke transaksi pulsa
                    $stmt_log->execute([
                        ':tgl' => $tgl, ':simid' => $simid, ':nominal' => $nominal,
                        ':akun' => $nama_akun, ':up' => $unit_up, ':ap' => $unit_ap, ':upi' => $unit_upi
                    ]);
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $nominal = (int) preg_replace('/[^0-9]/', '', $_POST['nominal']);
            $tgl     = $_POST['tgl_isi'];
            $simid   = trim($_POST['sim_id']);

            $stmt->execute([
                ':nominal' => $nominal,
                ':tgl'     => $tgl,
                ':simid'   => $simid
            ]);

            // Catat ke transaksi pulsa
            $stmt_log->execute([
                ':tgl' => $tgl, ':simid' => $simid, ':nominal' => $nominal,
                ':akun' => $nama_akun, ':up' => $unit_up, ':ap' => $unit_ap, ':upi' => $unit_upi
            ]);
        }
        
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=kartu&sub=pulsa&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<script>alert('Gagal memproses data: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>