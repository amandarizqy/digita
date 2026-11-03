<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!in_array($_SESSION['KodeHak'] ?? '', ['SF.UP', 'SA.KP'])) die("Akses Ditolak. Hanya SF.UP atau Superadmin.");

$action    = $_GET['action'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? '';

try {
    // 1. FUNGSI FINALISASI SPK
    if ($action === 'finalisasi_spk') {
        $no_form_order = trim($_GET['no_form'] ?? '');
        
        $stmt = $conn->prepare("UPDATE frm_order SET StatusData = 'SELESAI' WHERE NoFormOrder = ?");
        $stmt->execute([$no_form_order]);

        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&status=sukses_final");
        exit;
    }

    // 2. PROSES PENYIMPANAN FORMULIR MUTASI PASANG (BA)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form_order = trim($_POST['no_form_order']);
        $no_ref        = trim($_POST['no_ref']);
        $id_pel        = trim($_POST['id_pel']);
        $sim_id        = trim($_POST['sim_id']);
        $imei_modem    = trim($_POST['imei_modem']);
        $tgl_pasang    = trim($_POST['tgl_pasang']);
        $posisi_pasang = trim($_POST['posisi_pasang']);
        $kondisi_relay = trim($_POST['kondisi_relay']);
        $longitude     = trim($_POST['longitude'] ?? '');
        $latitude      = trim($_POST['latitude'] ?? '');
        $no_fpk        = trim($_POST['no_fpk'] ?? '');

        // Fetch no_fpk jika tidak ada dari form
        if (empty($no_fpk)) {
            $stmt_fpk = $conn->prepare("SELECT NoFpk FROM frm_pk WHERE NoRef = ? AND JenisPk = 'PEMASANGAN' ORDER BY WaktuData DESC LIMIT 1");
            $stmt_fpk->execute([$no_ref]);
            $no_fpk = $stmt_fpk->fetchColumn();
        }

        $foto_barang = null;
        if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] === UPLOAD_ERR_OK) {
            $foto_barang = file_get_contents($_FILES['foto_barang']['tmp_name']);
        }

        $foto_pasang = null;
        if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
            $foto_pasang = file_get_contents($_FILES['foto_pasang']['tmp_name']);
        }

        $conn->beginTransaction();

        // Generate No Fba (Berita Acara)
        $prefix = $unit_up;
        $tgl_fmt = date('Ymd', strtotime($tgl_pasang));
        $stmt_seq = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFba LIKE :pola ORDER BY NoFba DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_fmt . '-BA']);
        $last_ba = $stmt_seq->fetchColumn();
        $urutan = $last_ba ? (int) substr($last_ba, strlen($prefix), 3) + 1 : 1;
        $no_fba = $prefix . str_pad($urutan, 3, '0', STR_PAD_LEFT) . $tgl_fmt . '-BA';

        // Cek apakah BA pemasangan untuk FPK ini sudah ada
        $stmt_check = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFpk = ? AND JenisPekerjaan = 'PEMASANGAN'");
        $stmt_check->execute([$no_fpk]);
        $existing_ba = $stmt_check->fetchColumn();

        if ($existing_ba) {
            $no_fba = $existing_ba; // Gunakan BA yang lama untuk diupdate
            $stmt_up_ba = $conn->prepare("UPDATE frm_ba SET TglFba=?, Longitude=?, Latitude=?, NamaAkun=? WHERE NoFba=?");
            $stmt_up_ba->execute([$tgl_pasang, $longitude, $latitude, $nama_akun, $no_fba]);
        } else {
            $stmt_ins_ba = $conn->prepare("
                INSERT INTO frm_ba (NoFba, NoFpk, TglFba, IdPel, JenisPekerjaan, Longitude, Latitude, UnitUp, UnitAp, UnitUpi, StatusData, NamaAkun) 
                VALUES (?, ?, ?, ?, 'PEMASANGAN', ?, ?, ?, ?, ?, 'AKTIF', ?)
            ");
            $stmt_ins_ba->execute([$no_fba, $no_fpk, $tgl_pasang, $id_pel, $longitude, $latitude, $unit_up, $unit_ap, $unit_upi, $nama_akun]);
        }

        // Insert / Update ke frm_ba_detil
        $sql_detil = "INSERT INTO frm_ba_detil (NoFba, NoRef, StatusPekerjaan, SimId, ImeiModem, PosisiPasang, KondisiRelay";
        $val_detil = ") VALUES (?, ?, 'PASANG', ?, ?, ?, ?";
        $params_detil = [$no_fba, $no_ref, $sim_id, $imei_modem, $posisi_pasang, $kondisi_relay];

        if ($foto_barang) { $sql_detil .= ", FotoBarang"; $val_detil .= ", ?"; $params_detil[] = $foto_barang; }
        if ($foto_pasang) { $sql_detil .= ", FotoTerpasang"; $val_detil .= ", ?"; $params_detil[] = $foto_pasang; }
        
        $sql_detil .= $val_detil . ") ON DUPLICATE KEY UPDATE SimId=VALUES(SimId), ImeiModem=VALUES(ImeiModem), PosisiPasang=VALUES(PosisiPasang), KondisiRelay=VALUES(KondisiRelay)";
        if ($foto_barang) $sql_detil .= ", FotoBarang=VALUES(FotoBarang)";
        if ($foto_pasang) $sql_detil .= ", FotoTerpasang=VALUES(FotoTerpasang)";

        $conn->prepare($sql_detil)->execute($params_detil);

        // Update master_barang untuk mapping id_pel dan status relay
        $status_relay = ($kondisi_relay === 'BERFUNGSI') ? 'ON' : 'OFF';
        $stmt_barang = $conn->prepare("UPDATE master_barang SET IdPel = ?, StatusRelay = ? WHERE NoRef = ?");
        $stmt_barang->execute([$id_pel, $status_relay, $no_ref]);

        $conn->commit();

        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=" . urlencode($no_form_order) . "&status=sukses_mutasi");
        exit;
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    $error_msg = addslashes($e->getMessage());
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body class='bg-light'>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '{$error_msg}',
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Kembali'
            }).then(() => {
                window.history.back();
            });
        </script>
    </body>
    </html>";
    exit;
}
?>