<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!in_array($_SESSION['KodeHak'] ?? '', ['SF.UP', 'SA.KP'])) {
    die("<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'error', title:'Akses Ditolak', text:'Anda tidak memiliki wewenang.'}).then(()=>{window.history.back();});</script></body></html>");
}

$unit_up   = $_SESSION['UnitUp'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? 'System';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form_order = trim($_POST['no_form_order']);
        $no_ref        = trim($_POST['no_ref']);
        $id_pel        = trim($_POST['id_pel']);
        $sim_id        = trim($_POST['sim_id']);
        $imei_modem    = trim($_POST['imei_modem']);
        $tgl_pasang    = trim($_POST['tgl_pasang']);
        $posisi_pasang = trim($_POST['posisi_pasang']);
        $lampu_indikasi= trim($_POST['kondisi_relay']); // MENYALA atau TIDAK
        $longitude     = trim($_POST['longitude'] ?? '');
        $latitude      = trim($_POST['latitude'] ?? '');
        $no_fpk        = trim($_POST['no_fpk'] ?? '');

        if (empty($no_fpk)) {
            $stmt_fpk = $conn->prepare("SELECT NoFpk FROM frm_pk WHERE NoRef = ? AND JenisPk = 'PASANG' ORDER BY WaktuData DESC LIMIT 1");
            $stmt_fpk->execute([$no_ref]);
            $no_fpk = $stmt_fpk->fetchColumn();
        }

        // Ambil NomorBarang dan NomorModem dari tabel master_nomor berdasarkan SimId
        $nomor_telepon = null;
        if (!empty($sim_id)) {
            $stmt_nomor = $conn->prepare("SELECT NomorAkun FROM master_nomor WHERE SimId = ? LIMIT 1");
            $stmt_nomor->execute([$sim_id]);
            $nomor_telepon = $stmt_nomor->fetchColumn();
            if (!$nomor_telepon) {
                $stmt_nomor2 = $conn->prepare("SELECT NomorAkund FROM master_nomor WHERE SimId = ? LIMIT 1");
                $stmt_nomor2->execute([$sim_id]);
                $nomor_telepon = $stmt_nomor2->fetchColumn();
            }
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

        // 1. Cek apakah Berita Acara (BA) pemasangan untuk FPK ini sudah ada
        $stmt_check = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFpk = ? AND JenisPekerjaan = 'PASANG'");
        $stmt_check->execute([$no_fpk]);
        $no_fba = $stmt_check->fetchColumn();

        $kd_petugas = 'IPM.001';

        if ($no_fba) {
            $stmt_up_ba = $conn->prepare("UPDATE frm_ba SET TglFba=?, NamaAkun=? WHERE NoFba=?");
            $stmt_up_ba->execute([$tgl_pasang, $nama_akun, $no_fba]);
        } else {
            $stmt_ins_ba = $conn->prepare("
                INSERT INTO frm_ba (NoFpk, TglFba, IdPel, JenisPekerjaan, KdPetugas, UnitUp, UnitAp, UnitUpi, StatusData, NamaAkun) 
                VALUES (?, ?, ?, 'PASANG', ?, ?, ?, ?, 'AKTIF', ?)
            ");
            $stmt_ins_ba->execute([$no_fpk, $tgl_pasang, $id_pel, $kd_petugas, $unit_up, $unit_ap, $unit_upi, $nama_akun]);
            
            $no_fba = $conn->query("SELECT NoFba FROM frm_ba WHERE NoFpk = '$no_fpk' AND JenisPekerjaan = 'PASANG' ORDER BY WaktuData DESC LIMIT 1")->fetchColumn();
        }

        // 2. Simpan atau Update ke tabel frm_ba_detil
        $sql_detil = "INSERT INTO frm_ba_detil (NoFba, NoRef, StatusPekerjaan, SimId, ImeiModem, NomorBarang, NomorModem, PosisiPasang, LampuIndikator, `Long`, `Lat`";
        $val_detil = ") VALUES (?, ?, 'PASANG', ?, ?, ?, ?, ?, ?, ?, ?";
        $params_detil = [$no_fba, $no_ref, $sim_id, $imei_modem, $nomor_telepon, $nomor_telepon, $posisi_pasang, $lampu_indikasi, $longitude, $latitude];

        if ($foto_barang) { $sql_detil .= ", FotoBarang"; $val_detil .= ", ?"; $params_detil[] = $foto_barang; }
        if ($foto_pasang) { $sql_detil .= ", FotoTerpasang"; $val_detil .= ", ?"; $params_detil[] = $foto_pasang; }
        
        $sql_detil .= $val_detil . ") ON DUPLICATE KEY UPDATE SimId=VALUES(SimId), ImeiModem=VALUES(ImeiModem), NomorBarang=VALUES(NomorBarang), NomorModem=VALUES(NomorModem), PosisiPasang=VALUES(PosisiPasang), LampuIndikator=VALUES(LampuIndikator), `Long`=VALUES(`Long`), `Lat`=VALUES(`Lat`)";
        if ($foto_barang) $sql_detil .= ", FotoBarang=VALUES(FotoBarang)";
        if ($foto_pasang) $sql_detil .= ", FotoTerpasang=VALUES(FotoTerpasang)";

        $conn->prepare($sql_detil)->execute($params_detil);

        // 3. Update master_barang untuk mapping id_pel dan status relay
        $status_relay = ($lampu_indikasi === 'MENYALA') ? 'ON' : 'OFF';
        $stmt_barang = $conn->prepare("UPDATE master_barang SET IdPel = ?, StatusRelay = ? WHERE NoRef = ?");
        $stmt_barang->execute([$id_pel, $status_relay, $no_ref]);

        // 4. Update LongDil dan LatDil pada tabel frm_pk sesuai titik koordinat pemasangan di lapangan
        if (!empty($longitude) && !empty($latitude)) {
            $stmt_pk_loc = $conn->prepare("UPDATE frm_pk SET LongDil = ?, LatDil = ? WHERE NoFpk = ?");
            $stmt_pk_loc->execute([$longitude, $latitude, $no_fpk]);
        }

        $conn->commit();

        echo "<!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Berita Acara Pemasangan berhasil disimpan & koordinat FPK diperbarui.',
                    confirmButtonColor: '#0d6efd',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=" . urlencode($no_form_order) . "';
                });
            </script>
        </body>
        </html>";
        exit;
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
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
                title: 'Gagal Memproses',
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