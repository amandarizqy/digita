<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Hak Akses Penerimaan: Hanya TL.AP & TL.UP
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; // Super admin diizinkan untuk bypass/testing
if (!in_array($kode_hak, $allowed_roles)) {
    echo "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Akses Ditolak</title>
        <!-- SweetAlert2 CSS & JS CDN -->
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body class='bg-light'>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak',
                text: 'Modul Penerimaan hanya untuk Team Leader (TL).',
                confirmButtonText: 'Kembali',
                confirmButtonColor: '#4e73df',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        </script>
    </body>
    </html>
    ";
    exit;
}

$active_menu = 'penerimaan';
$view = $_GET['view'] ?? 'daftar'; 

$unit_ap = $_SESSION['UnitAp'] ?? '';
$unit_up = $_SESSION['UnitUp'] ?? '';

ob_start();

if ($view === 'detail' && isset($_GET['no_pengiriman'])) {
    $page_title = "Inspeksi & Penerimaan Barang - Digita";
    $no_pengiriman = $_GET['no_pengiriman'];
    
    // Ambil Header Pengiriman
    $stmt = $conn->prepare("SELECT f.*, u.NamaUnit as NamaUP, a.NamaUnit as NamaAP 
                            FROM formulir_pengiriman f 
                            LEFT JOIN master_up u ON f.KodeUp = u.UnitUp
                            LEFT JOIN master_ap a ON f.KodeAp = a.UnitAp
                            WHERE f.NoFormulir = :no_form");
    $stmt->execute([':no_form' => $no_pengiriman]);
    $pengiriman = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil Item Detail Pengiriman
    $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
    $stmt_items->execute([':no_form' => $no_pengiriman]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/penerimaan.php'; 

} else {
    $page_title = "Daftar Barang Masuk (Inbound) - Digita";
    
    // Kueri Filter Dinamis: Hanya tampilkan pengiriman yang ditujukan ke Unit user ini
    // Jika TL.UP (punya kode UP), filter by KodeUp. Jika TL.AP (hanya AP), filter by KodeAp (dan KodeUp kosong)
    if ($kode_hak === 'TL.UP') {
        $filter_query = "KodeUp = :lokasi";
        $lokasi = $unit_up;
    } else {
        $filter_query = "KodeAp = :lokasi AND (KodeUp IS NULL OR KodeUp = '')";
        $lokasi = $unit_ap;
    }

    // Bypass filter jika Super Admin (SA.KP)
    if ($kode_hak === 'SA.KP') {
        $filter_query = "1=1";
        $lokasi = 1;
    }

    $query = "SELECT f.*, COUNT(d.NoRef) as TotalItem 
              FROM formulir_pengiriman f 
              LEFT JOIN formulir_pengiriman_detil d ON f.NoFormulir = d.NoFormulir 
              WHERE f.StatusData = 'AKTIF' AND $filter_query
              GROUP BY f.NoFormulir 
              ORDER BY (CASE WHEN f.StatusPengiriman = 'DIKIRIM' THEN 1 ELSE 2 END), f.WaktuData DESC";
              
    $stmt_list = $conn->prepare($query);
    if ($kode_hak !== 'SA.KP') {
        $stmt_list->bindValue(':lokasi', $lokasi);
    }
    $stmt_list->execute();
    $list_inbound = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    
    require_once '../../templates/pengadaan/penerimaan_daftar.php'; 
}

$content = ob_get_clean();
require_once '../../templates/layouts/base.php';
?>