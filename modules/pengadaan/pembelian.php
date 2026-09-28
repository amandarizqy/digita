<?php
// 1. Panggil middleware pelindung URL (RBAC)
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Pengecekan Hak Akses Khusus Sub-Menu: AM.UI & Super Admin (SA.KP)
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['AM.UI', 'SA.KP']; 
if (!in_array($kode_hak, $allowed_roles)) {
    die("<script>alert('Akses Ditolak: Modul Pembelian hanya untuk Asman UI.'); window.location.href='../../index.php';</script>");
}

// 2. Atur penanda UI tab aktif
$active_menu = 'pembelian';
$view = $_GET['view'] ?? 'daftar'; 

// 3. Tangkap output tampilan (view) ke dalam variabel $content
ob_start();

if ($view === 'baru') {
    $page_title = "Buat Draft Pembelian S41 - Digita";
    require_once '../../templates/pengadaan/pembelian_baru.php'; 

} elseif ($view === 'detail' && isset($_GET['no_form'])) {
    $page_title = "Keranjang Pembelian S41 - Digita";
    $no_formulir = $_GET['no_form'];
    
    // Ambil data header
    $stmt = $conn->prepare("SELECT * FROM formulir_pembelian WHERE NoFormulir = :no_form");
    $stmt->execute([':no_form' => $no_formulir]);
    $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil data item detail
    $stmt_items = $conn->prepare("SELECT * FROM formulir_pembelian_detil WHERE NoFormulir = :no_form");
    $stmt_items->execute([':no_form' => $no_formulir]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pembelian.php'; 

} else {
    $page_title = "Daftar Formulir Pembelian - Digita";
    
    // Ambil daftar formulir beserta total biaya kalkulasi dari tabel detil
    $query = "SELECT f.*, COALESCE(SUM(d.HargaBeli), 0) as TotalBiaya, COUNT(d.NoRef) as TotalItem 
              FROM formulir_pembelian f 
              LEFT JOIN formulir_pembelian_detil d ON f.NoFormulir = d.NoFormulir 
              GROUP BY f.NoFormulir 
              ORDER BY f.WaktuData DESC";
    $stmt_list = $conn->prepare($query);
    $stmt_list->execute();
    $list_pembelian = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/pembelian_daftar.php'; 
}

$content = ob_get_clean();

// 4. Render ke dalam layout utama
require_once '../../templates/layouts/base.php';
?>