<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Hak Akses Pengiriman: Hanya AM.UI & Super Admin
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
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
                            LEFT JOIN master_up u ON f.KodeUp = u.UnitUp
                            LEFT JOIN master_ap a ON f.KodeAp = a.UnitAp
                            WHERE f.NoFormulir = :no_form");
    $stmt->execute([':no_form' => $no_formulir]);
    $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Data item detail pengiriman
    $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
    $stmt_items->execute([':no_form' => $no_formulir]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pengiriman.php'; 

} else {
    $page_title = "Daftar Formulir Pengiriman - Digita";
    
    // Ambil daftar pengiriman dan hitung jumlah item
    $query = "SELECT f.*, COUNT(d.NoRef) as TotalItem 
              FROM formulir_pengiriman f 
              LEFT JOIN formulir_pengiriman_detil d ON f.NoFormulir = d.NoFormulir 
              GROUP BY f.NoFormulir 
              ORDER BY f.WaktuData DESC";
    $stmt_list = $conn->prepare($query);
    $stmt_list->execute();
    $list_pengiriman = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pengiriman_daftar.php'; 
}

$content = ob_get_clean();
require_once '../../templates/layouts/base.php';
?>