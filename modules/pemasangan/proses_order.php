<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/order_notif_helper.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$kode_hak = $_SESSION['KodeHak'] ?? '';
if (!in_array($kode_hak, ['TL.UP', 'SA.KP'])) {
    die("<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'error', title:'Akses Ditolak', text:'Anda tidak memiliki wewenang.'}).then(()=>{window.history.back();});</script></body></html>");
}

$action    = $_GET['action'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? 'System';

try {
    // 1. SIMPAN FPK PEMASANGAN
    if ($action === 'simpan_fpk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tgl_fpk = trim($_POST['tgl_fpk']);
        $id_pel  = trim($_POST['id_pel']);
        $no_ref  = trim($_POST['no_ref']);

        if (empty($id_pel) || empty($no_ref)) {
            throw new Exception("Pelanggan dan Perangkat Alat wajib dipilih!");
        }

        // Jika login sebagai SA.KP (UnitUp session kosong), ambil UnitUp/Ap/Upi dari tabel dil pelanggan
        $target_up  = $unit_up;
        $target_ap  = $unit_ap;
        $target_upi = $unit_upi;
        if (empty($target_up)) {
            $st_dil = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM dil WHERE IdPel = ? LIMIT 1");
            $st_dil->execute([$id_pel]);
            $dil_info   = $st_dil->fetch(PDO::FETCH_ASSOC);
            $target_up  = $dil_info['UnitUp'] ?? '';
            $target_ap  = $dil_info['UnitAp'] ?? '';
            $target_upi = $dil_info['UnitUpi'] ?? '';
        }

        $conn->beginTransaction();

        $tgl_fmt = date('Ymd', strtotime($tgl_fpk));
        $prefix  = $target_up;
        $stmt_seq = $conn->prepare("SELECT NoFpk FROM frm_pk WHERE NoFpk LIKE :pola ORDER BY NoFpk DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_fmt . '-F.F']);
        $last_pk = $stmt_seq->fetchColumn();
        $urutan = $last_pk ? (int) substr($last_pk, strlen($prefix), 3) + 1 : 1;
        $no_fpk = $prefix . str_pad($urutan, 3, '0', STR_PAD_LEFT) . $tgl_fmt . '-F.F';

        $stmt_al = $conn->prepare("
            SELECT b.SimId, b.ImeiModem, n.NomorAkun 
            FROM master_barang b 
            LEFT JOIN master_nomor n ON b.SimId = n.SimId 
            WHERE b.NoRef = ?
        ");
        $stmt_al->execute([$no_ref]);
        $alat_info = $stmt_al->fetch(PDO::FETCH_ASSOC);

        $sim_id = $alat_info['SimId'] ?? null;
        $imei   = $alat_info['ImeiModem'] ?? null;
        $nomor_akun = $alat_info['NomorAkun'] ?? null;
        $status_barang = 'STOK';

        $stmt_ins = $conn->prepare("
            INSERT INTO frm_pk (NoFpk, TglFpk, JenisPk, IdPel, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData) 
            VALUES (?, ?, 'PASANG', ?, ?, ?, ?, ?, 'AKTIF')
        ");
        $stmt_ins->execute([$no_fpk, $tgl_fpk, $id_pel, $nama_akun, $target_up, $target_ap, $target_upi]);

        $stmt_ins_detil = $conn->prepare("
            INSERT INTO frm_pk_detil (NoFpk, StatusBarang, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_ins_detil->execute([$no_fpk, $status_barang, $no_ref, $sim_id, $imei, $nomor_akun, $nomor_akun]);

        $conn->commit();

        echo "<!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Formulir FPK Pemasangan berhasil dibuat.',
                    confirmButtonColor: '#0d6efd',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang';
                });
            </script>
        </body>
        </html>";
        exit;
    }

    // 2. HAPUS FPK
    if ($action === 'hapus_fpk') {
        $no_fpk = $_GET['no_fpk'] ?? '';
        $is_sa = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
        
        $conn->beginTransaction();
        
        $conn->prepare("DELETE FROM frm_pk_detil WHERE NoFpk = ?")->execute([$no_fpk]);
        
        if ($is_sa) {
            $conn->prepare("DELETE FROM frm_pk WHERE NoFpk = ?")->execute([$no_fpk]);
        } else {
            $conn->prepare("DELETE FROM frm_pk WHERE NoFpk = ? AND UnitUp = ?")->execute([$no_fpk, $unit_up]);
        }
        
        $conn->commit();

        echo "<!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Terhapus',
                    text: 'FPK berhasil dihapus secara keseluruhan.',
                    confirmButtonColor: '#0d6efd',
                    timer: 1200,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang';
                });
            </script>
        </body>
        </html>";
        exit;
    }

    // 3. SIMPAN ORDER (SPK) BARU + KIRIM EMAIL NOTIFIKASI & PDF KE SF.UP
    if ($action === 'simpan_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tgl_order = trim($_POST['tgl_order']);
        $fpk_terpilih = $_POST['fpk_terpilih'] ?? [];

        if (empty($fpk_terpilih)) {
            throw new Exception("Pilih minimal satu FPK untuk dibuatkan Order/SPK!");
        }

        // Ambil UnitUp/Ap/Upi dari FPK pertama yang dipilih (penting jika pembuatnya SA.KP)
        $target_up  = $unit_up;
        $target_ap  = $unit_ap;
        $target_upi = $unit_upi;

        if (empty($target_up)) {
            $st_fpk_unit = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM frm_pk WHERE NoFpk = ? LIMIT 1");
            $st_fpk_unit->execute([$fpk_terpilih[0]]);
            $fpk_unit   = $st_fpk_unit->fetch(PDO::FETCH_ASSOC);
            $target_up  = $fpk_unit['UnitUp'] ?? '';
            $target_ap  = $fpk_unit['UnitAp'] ?? '';
            $target_upi = $fpk_unit['UnitUpi'] ?? '';
        }

        $conn->beginTransaction();

        $tgl_fmt = date('Ymd', strtotime($tgl_order));
        $prefix = $target_up;
        $stmt_seq = $conn->prepare("SELECT NoFormOrder FROM frm_order WHERE NoFormOrder LIKE :pola ORDER BY NoFormOrder DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_fmt . '-F.E']);
        $last_ord = $stmt_seq->fetchColumn();
        $urutan = $last_ord ? (int) substr($last_ord, strlen($prefix), 3) + 1 : 1;
        $no_order = $prefix . str_pad($urutan, 3, '0', STR_PAD_LEFT) . $tgl_fmt . '-F.E';

        $kd_petugas = 'IPM.001';

        $stmt_ord = $conn->prepare("
            INSERT INTO frm_order (NoFormOrder, TglOrder, KdPetugas, UnitUp, UnitAp, UnitUpi, StatusData, NamaAkun) 
            VALUES (?, ?, ?, ?, ?, ?, 'TIDAK', ?)
        ");
        $stmt_ord->execute([$no_order, $tgl_order, $kd_petugas, $target_up, $target_ap, $target_upi, $nama_akun]);

        $stmt_detil = $conn->prepare("INSERT INTO frm_order_detil (NoFormOrder, NoFpk) VALUES (?, ?)");
        foreach ($fpk_terpilih as $no_fpk) {
            $stmt_detil->execute([$no_order, $no_fpk]);
        }

        // Commit database terlebih dahulu agar Order aman tersimpan walaupun email gagal terkirim
        $conn->commit();

        // Kirim Email Notifikasi + Lampiran PDF ke SF.UP UnitUp terkait
        $notif_res = kirimNotifOrderPasang($conn, $no_order);
        $swal_icon  = $notif_res['status'] ? 'success' : 'warning';
        $swal_title = $notif_res['status'] ? 'Berhasil & Terkirim!' : 'Order Tersimpan (Email Gagal)';
        $swal_text  = addslashes("Order {$no_order} berhasil dibuat. " . $notif_res['message']);

        echo "<!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
            <script>
                Swal.fire({
                    icon: '{$swal_icon}',
                    title: '{$swal_title}',
                    text: '{$swal_text}',
                    confirmButtonColor: '#0d6efd'
                }).then(() => {
                    window.location.href = '../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang';
                });
            </script>
        </body>
        </html>";
        exit;
    }
    
    // 4. HAPUS KESELURUHAN ORDER / SPK
    if ($action === 'hapus_order') {
        $no_form = $_GET['no_form'];
        $conn->beginTransaction();
        
        $conn->prepare("DELETE FROM frm_order_detil WHERE NoFormOrder=?")->execute([$no_form]);
        $conn->prepare("DELETE FROM frm_order WHERE NoFormOrder=? AND StatusData='TIDAK'")->execute([$no_form]);
        
        $conn->commit();

        echo "<!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Terhapus',
                    text: 'SPK/Order berhasil dibatalkan.',
                    confirmButtonColor: '#0d6efd',
                    timer: 1200,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar';
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