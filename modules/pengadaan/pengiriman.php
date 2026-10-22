<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Hak Akses Pengiriman: Hanya AM.UI & Super Admin
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'AM.AP' , 'SA.KP']; 
if (!in_array($kode_hak, $allowed_roles)) {
    die("<script>alert('Akses Ditolak: Modul Pengiriman hanya untuk Asman UI.'); window.location.href='../../index.php';</script>");
}

$active_menu = 'pengiriman';
$view = $_GET['view'] ?? 'daftar'; 

ob_start();

if ($view === 'baru') {
    $page_title = "Buat Draft Pengiriman S41 - Digita";
    
    // Tarik referensi data unit AP & UP untuk dropdown tujuan
    $stmt_ap = $conn->query("SELECT UnitAp, NamaUnit FROM master_ap WHERE StatusData = 'AKTIF'");
    $list_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_up = $conn->query("SELECT UnitUp, NamaUnit FROM master_up WHERE StatusData = 'AKTIF'");
    $list_up = $stmt_up->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pengiriman_baru.php'; 

} elseif ($view === 'detail' && isset($_GET['no_form'])) {
    $page_title = "Keranjang Pengiriman S41 - Digita";
    $no_formulir = $_GET['no_form'];
    
    // Data header pengiriman
    $stmt = $conn->prepare("SELECT f.*, u.NamaUnit as NamaUP, a.NamaUnit as NamaAP 
                            FROM formulir_pengiriman f 
                            LEFT JOIN master_up u ON f.UnitUp = u.UnitUp
                            LEFT JOIN master_ap a ON f.UnitAp = a.UnitAp
                            WHERE f.NoFormKirim = :no_form");
    $stmt->execute([':no_form' => $no_formulir]);
    $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // --- PASTIKAN BARIS INI ADA DAN AKTIF ---
    $session_akun = $_SESSION['NamaAkun'] ?? '';
    $db_akun = trim($formulir['NamaAkun'] ?? '');
    
    // Jika akun sama, ATAU jika role adalah Super Admin (SA.KP), maka diizinkan penuh
    $is_creator = ($db_akun === $session_akun) || ($kode_hak === 'SA.KP');
    // ----------------------------------------

    // Data item detail pengiriman
    $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormKirim = :no_form");
    $stmt_items->execute([':no_form' => $no_formulir]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil stok barang yang tersedia untuk dropdown scanner
    $user_upi = $_SESSION['UnitUpi'] ?? '56';
    $stmt_stok = $conn->prepare("
        SELECT b.NoRef 
        FROM master_barang b
        WHERE b.UnitUpi = :upi 
          AND b.StatusData = 'AKTIF'
          AND (b.UnitAp IS NULL OR b.UnitAp = '') 
          AND (b.UnitUp IS NULL OR b.UnitUp = '')
          AND b.NoRef NOT IN (
              SELECT d.NoRef 
              FROM formulir_pengiriman_detil d 
              JOIN formulir_pengiriman p ON d.NoFormKirim = p.NoFormKirim 
              WHERE p.NoFormKirim NOT IN (SELECT NoFormKirim FROM formulir_penerimaan)
          )
    ");
    $stmt_stok->execute([':upi' => $user_upi]);
    $stok_tersedia = $stmt_stok->fetchAll(PDO::FETCH_ASSOC);

    require_once '../../templates/pengadaan/pengiriman.php'; 

} else {
    $page_title = "Daftar Formulir Pengiriman - Digita";
    
    // Ambil daftar pengiriman dan hitung jumlah item
    $query = "SELECT f.*, COUNT(d.NoRef) as TotalItem 
              FROM formulir_pengiriman f 
              LEFT JOIN formulir_pengiriman_detil d ON f.NoFormKirim = d.NoFormKirim 
              GROUP BY f.NoFormKirim 
              ORDER BY f.WaktuData DESC";
    $stmt_list = $conn->prepare($query);
    $stmt_list->execute();
    $list_pengiriman = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pengiriman_daftar.php'; 
}

$content = ob_get_clean();
require_once '../../templates/layouts/base.php';
?>