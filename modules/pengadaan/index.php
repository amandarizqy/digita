<?php
// Hapus session_start() dan auth_check.php di sini karena sudah 
// dieksekusi secara terpusat di root index.php untuk mencegah bentrok

// Gunakan __DIR__ untuk path absolut yang kebal terhadap perubahan root
require_once __DIR__ . '/../../config/database.php';

// ---------------------------------------------------------
// 1. TANGKAP PARAMETER ROUTING DARI URL
// ---------------------------------------------------------
$menu = isset($_GET['menu']) ? $_GET['menu'] : 'pembelian';
$sub  = isset($_GET['sub']) ? $_GET['sub'] : 'daftar';

$data = []; 

// ---------------------------------------------------------
// 2. AREA KERJA BACKEND (Query Menggunakan PDO)
// ---------------------------------------------------------
switch ($menu) {
    case 'pembelian':
        if ($sub == 'daftar') {
            $page_title = "Daftar Pembelian - Pengadaan S41";
            
            $query = "SELECT f.*, u.SingkatanNama 
                      FROM formulir_pembelian f 
                      LEFT JOIN master_up u ON f.KodeUp = u.UnitUp 
                      ORDER BY f.WaktuData DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $data['list_pembelian'] = [];
            if ($results) {
                foreach ($results as $row) {
                    $row['NamaUnit'] = !empty($row['SingkatanNama']) ? $row['SingkatanNama'] : $row['KodeUp'];
                    $data['list_pembelian'][] = $row;
                }
            }
            
        } elseif ($sub == 'baru') {
            $page_title = "Form Pembelian Baru - Pengadaan S41";
            
            $query_upi = "SELECT UnitUpi, SingkatanNama FROM master_upi WHERE StatusData = 'AKTIF'";
            $stmt_upi = $conn->prepare($query_upi);
            $stmt_upi->execute();
            $data['list_upi'] = $stmt_upi->fetchAll(PDO::FETCH_ASSOC);
        }
        break;

    case 'pengiriman':
        if ($sub == 'daftar') {
            $page_title = "Daftar Pengiriman - Pengadaan S41";
            $data['list_pengiriman'] = []; 
        } elseif ($sub == 'baru') {
            $page_title = "Form Pengiriman - Pengadaan S41";
        }
        break;

    case 'penerimaan':
        $page_title = "Penerimaan Gudang - Pengadaan S41";
        $sub = 'daftar'; 
        break;

    default:
        $menu = 'pembelian';
        $sub = 'daftar';
        $page_title = "Pengadaan & Stok - Digita S41";
        break;
}

// ---------------------------------------------------------
// 3. RENDER TEMPLATE
// ---------------------------------------------------------
// Cukup panggil templatenya saja menggunakan __DIR__. 
// ob_start() dan base.php DIBUANG karena sudah di-handle oleh root index.php
require_once __DIR__ . '/../../templates/pengadaan/index.php'; 
?>