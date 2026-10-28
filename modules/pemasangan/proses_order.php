<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (($_SESSION['KodeHak'] ?? '') !== 'TL.UP') die("Akses Ditolak. Hanya TL.UP yang dapat membuat Order.");

$action    = $_GET['action'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? '';
$tgl_sekarang = date('Y-m-d');
$tgl_format = date('Ymd');
$prefix = $unit_up; 

try {
    // A. BUAT FPK BARU
    if ($action === 'create_fpk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_pel  = trim($_POST['id_pel']);
        $no_ref  = trim($_POST['no_ref']);
        
        $stmt_seq = $conn->prepare("SELECT NoFpk FROM frm_pk WHERE NoFpk LIKE :pola ORDER BY NoFpk DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_format . '-FPK']);
        $last_form = $stmt_seq->fetchColumn();
        $urutan_baru = $last_form ? (int) substr($last_form, strlen($prefix), 3) + 1 : 1;
        $no_fpk = $prefix . str_pad($urutan_baru, 3, '0', STR_PAD_LEFT) . $tgl_format . '-FPK';

        // Ambil Data Alat
        $stmt_get_alat = $conn->prepare("SELECT SimId, ImeiModem FROM master_barang WHERE NoRef = ?");
        $stmt_get_alat->execute([$no_ref]);
        $alat = $stmt_get_alat->fetch();

        // Simpan FPK (Sebagai Pemasangan, karena IdPel blm ada di master_barang)
        $stmt = $conn->prepare("INSERT INTO frm_pk (NoFpk, TglFpk, IdPel, SimId, ImeiModem, NomorBarang, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF')");
        $stmt->execute([$no_fpk, $tgl_sekarang, $id_pel, $alat['SimId'], $alat['ImeiModem'], $no_ref, $nama_akun, $unit_up, $unit_ap, $unit_upi]);
        
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar");
        exit;
    }

    // B. HAPUS FPK (Bisa dihapus jika belum masuk Order)
    if ($action === 'delete_fpk') {
        $no_fpk = $_GET['no_fpk'];
        $conn->prepare("DELETE FROM frm_pk WHERE NoFpk=?")->execute([$no_fpk]);
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar");
        exit;
    }

    // C. BUAT DRAFT ORDER BARU (SPK)
    if ($action === 'create_draft') {
        $stmt_seq = $conn->prepare("SELECT NoFormOrder FROM frm_order WHERE NoFormOrder LIKE :pola ORDER BY NoFormOrder DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_format . '-F.E']);
        $last_form = $stmt_seq->fetchColumn();
        $urutan_baru = $last_form ? (int) substr($last_form, strlen($prefix), 3) + 1 : 1;
        $no_form = $prefix . str_pad($urutan_baru, 3, '0', STR_PAD_LEFT) . $tgl_format . '-F.E';
        
        $stmt = $conn->prepare("INSERT INTO frm_order (NoFormOrder, TglOrder, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData) VALUES (?, ?, ?, ?, ?, ?, 'TIDAK')");
        $stmt->execute([$no_form, $tgl_sekarang, $nama_akun, $unit_up, $unit_ap, $unit_upi]);
        
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=detail&no_form=" . urlencode($no_form));
        exit;
    }

    // D. TAMBAH FPK KE DRAFT ORDER (SPK)
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form = trim($_POST['no_form_order']);
        $no_fpk  = trim($_POST['no_fpk']);
        
        $stmt = $conn->prepare("INSERT INTO frm_order_detil (NoFormOrder, NoFpk) VALUES (?, ?)");
        $stmt->execute([$no_form, $no_fpk]);
        
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=detail&no_form=" . urlencode($no_form));
        exit;
    }

    // E. HAPUS FPK DARI ORDER (SPK)
    if ($action === 'delete_item') {
        $no_form = $_GET['no_form'];
        $conn->prepare("DELETE FROM frm_order_detil WHERE NoFormOrder=? AND NoFpk=?")->execute([$no_form, $_GET['no_fpk']]);
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=detail&no_form=" . urlencode($no_form));
        exit;
    }

    // F. HAPUS DRAFT ORDER (SPK) SECARA PERMANEN
    if ($action === 'delete_draft') {
        $no_form = $_GET['no_form'];
        
        // Pastikan hanya draft yang bisa dihapus (StatusData = 'TIDAK')
        $stmt_check = $conn->prepare("SELECT StatusData FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
        $stmt_check->execute([$no_form, $unit_up]);
        $status = $stmt_check->fetchColumn();

        if ($status === 'TIDAK') {
            $conn->beginTransaction();
            // Hapus detail keterkaitan FPK terlebih dahulu (agar FPK bebas lagi)
            $conn->prepare("DELETE FROM frm_order_detil WHERE NoFormOrder = ?")->execute([$no_form]);
            // Hapus form order utamanya
            $conn->prepare("DELETE FROM frm_order WHERE NoFormOrder = ?")->execute([$no_form]);
            $conn->commit();
        }
        
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar");
        exit;
    }

    // F. FINALISASI ORDER (SPK TERBIT)
    if ($action === 'execute_form') {
        $no_form = trim($_POST['no_form_order']);
        $conn->prepare("UPDATE frm_order SET StatusData='AKTIF' WHERE NoFormOrder=?")->execute([$no_form]);
        
        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar&status=sukses_order");
        exit;
    }

} catch (Exception $e) {
    die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}