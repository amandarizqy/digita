<?php
require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SA.KP']; 

// Jika hak akses tidak terdaftar sama sekali
if (!in_array($kode_hak, $allowed_roles)) {
    echo "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body class='bg-light'>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Akses ditolak',
                text: 'Anda tidak memiliki wewenang untuk membuka menu ini.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd',
                allowOutsideClick: false
            }).then(() => {
                window.history.back();
            });
        </script>
    </body>
    </html>
    ";
    exit;
}

// OTOMATIS REDIRECT BERDASARKAN ROLE JIKA USER KLIK MENU PENGADAAN
if (!isset($_GET['menu'])) {
    if (in_array($kode_hak, ['TL.AP', 'TL.UP'])) {
        header("Location: index.php?page=pengadaan&menu=penerimaan&view=daftar");
        exit;
    } elseif (in_array($kode_hak, ['AM.UI', 'SA.KP'])) {
        header("Location: index.php?page=pengadaan&menu=pembelian&view=daftar");
        exit;
    } else {
        header("Location: index.php?page=pengadaan&menu=monitoring&sub=aset");
        exit;
    }
}

// Tangkap parameter dari URL
$menu = $_GET['menu'] ?? 'monitoring'; 
$sub  = $_GET['sub'] ?? ($menu === 'monitoring' ? 'aset' : $menu);
$view = $_GET['view'] ?? 'daftar'; 

// PENGAMANAN UTAMA: Jika role non-otoritas memaksa akses menu Barang atau Kartu, langsung lempar ke monitoring
if (in_array($menu, ['pembelian', 'pengiriman', 'barang']) && !in_array($kode_hak, ['AM.UI', 'SA.KP'])) {
    header("Location: index.php?page=pengadaan&menu=monitoring&sub=aset");
    exit;
}

if ($menu === 'kartu' && !in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP'])) {
    header("Location: index.php?page=pengadaan&menu=monitoring&sub=aset");
    exit;
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$page_title = "Pengadaan & Stok - Digita S41";

// Jika menu Kartu
if ($menu === 'kartu') {
    $sub = $_GET['sub'] ?? 'aktivasi';
    require_once __DIR__ . '/kartu.php';
} 
// Jika menu Monitoring
elseif ($menu === 'monitoring') {
    $sub = $_GET['sub'] ?? 'aset';
    require_once __DIR__ . '/monitoring.php';
}

// Jika masuk ke ekosistem Barang (Pembelian / Pengiriman / Penerimaan)
elseif (in_array($menu, ['barang', 'pembelian', 'pengiriman', 'penerimaan'])) {
    
    // --- 1. PEMBELIAN ---
    if ($sub === 'pembelian') {
        if ($view === 'daftar') {
            $keyword = '%' . trim($_GET['q'] ?? '') . '%';
            
            // --- PENGATURAN PAGING ---
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            if (!in_array($limit, [5, 10, 20, 30])) { $limit = 10; }
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($page < 1) { $page = 1; }
            $offset = ($page - 1) * $limit;

            // Hitung Total Data Pembelian
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_pembelian f WHERE f.NoFormulir LIKE :keyword");
            $stmt_count->bindValue(':keyword', $keyword, PDO::PARAM_STR);
            $stmt_count->execute();
            $total_data = (int) $stmt_count->fetchColumn();
            $total_pages = ceil($total_data / $limit);
            if ($total_pages < 1) { $total_pages = 1; }
            if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

            // Query Data dengan LIMIT & OFFSET
            $query = "SELECT 
                        f.NoFormulir, f.TglBeli, f.StatusData, f.WaktuData,
                        (SELECT COALESCE(SUM(HargaBeli), 0) FROM formulir_pembelian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalBiaya, 
                        (SELECT COUNT(NoRef) FROM formulir_pembelian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                    FROM formulir_pembelian f 
                    WHERE f.NoFormulir LIKE :keyword
                    ORDER BY f.WaktuData DESC
                    LIMIT :limit OFFSET :offset";
            try {
                $stmt = $conn->prepare($query); 
                $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $list_pembelian = $stmt->fetchAll(PDO::FETCH_ASSOC); 
                
                // Simpan data paging untuk view
                $paging = [
                    'total_data' => $total_data,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ];
            } catch (PDOException $e) {
                die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "');</script>");
            }
        } elseif ($view === 'baru') {
            header("Location: modules/pengadaan/proses_pembelian.php?action=create_draft");
            exit;
        } elseif ($view === 'detail' && isset($_GET['no_form'])) {
            $no_formulir = $_GET['no_form'];
            $stmt = $conn->prepare("SELECT * FROM formulir_pembelian WHERE NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_formulir]);
            $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt_items = $conn->prepare("SELECT * FROM formulir_pembelian_detil WHERE NoFormulir = :no_form");
            $stmt_items->execute([':no_form' => $no_formulir]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- 2. PENGIRIMAN ---
    elseif ($sub === 'pengiriman') {
        if ($view === 'daftar') {
            $keyword = '%' . trim($_GET['q'] ?? '') . '%';
            
            // --- PENGATURAN PAGING ---
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            if (!in_array($limit, [5, 10, 20, 30])) { $limit = 10; }
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($page < 1) { $page = 1; }
            $offset = ($page - 1) * $limit;

            // Hitung Total Data Pengiriman
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_pengiriman f WHERE f.NoFormulir LIKE :keyword");
            $stmt_count->bindValue(':keyword', $keyword, PDO::PARAM_STR);
            $stmt_count->execute();
            $total_data = (int) $stmt_count->fetchColumn();
            $total_pages = ceil($total_data / $limit);
            if ($total_pages < 1) { $total_pages = 1; }
            if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

            // Query Data dengan LIMIT & OFFSET
            $query = "SELECT 
                        f.NoFormulir, f.TglFormulir, f.NamaAkun, f.StatusPengiriman, f.StatusData, f.WaktuData,
                        (SELECT COUNT(NoRef) FROM formulir_pengiriman_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                    FROM formulir_pengiriman f 
                    WHERE f.NoFormulir LIKE :keyword
                    ORDER BY f.WaktuData DESC
                    LIMIT :limit OFFSET :offset";
            try {
                $stmt = $conn->prepare($query); 
                $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $list_pengiriman = $stmt->fetchAll(PDO::FETCH_ASSOC); 
                
                $paging = [
                    'total_data' => $total_data,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ];
            } catch (PDOException $e) {
                die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "');</script>");
            }
        } elseif ($view === 'baru') {
            $unit_upi_login = $_SESSION['UnitUpi'] ?? '56';

            $stmt_ap = $conn->prepare("SELECT UnitAp, NamaUnit FROM master_ap WHERE UnitUpi = :upi AND StatusData = 'AKTIF'");
            $stmt_ap->execute([':upi' => $unit_upi_login]);
            $list_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);

            $stmt_up = $conn->prepare("SELECT UnitUp, NamaUnit FROM master_up WHERE UnitUpi = :upi AND StatusData = 'AKTIF'");
            $stmt_up->execute([':upi' => $unit_upi_login]);
            $list_up = $stmt_up->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($view === 'detail' && isset($_GET['no_form'])) {
            $no_pengiriman = $_GET['no_form'];
            
            // 1. PERBAIKAN: Join ke tabel tujuan (KodeUp_Tujuan & KodeAp_Tujuan)
            $stmt = $conn->prepare("SELECT f.*, 
                                    COALESCE(u.NamaUnit, f.KodeUp, '-') as NamaUP_Pembuat, 
                                    COALESCE(a.NamaUnit, f.KodeAp, '-') as NamaAP_Pembuat,
                                    COALESCE(ut.NamaUnit, f.KodeUp_Tujuan, '-') as NamaUP_Tujuan,
                                    COALESCE(at.NamaUnit, f.KodeAp_Tujuan, '-') as NamaAP_Tujuan
                                    FROM formulir_pengiriman f 
                                    LEFT JOIN master_up u ON f.KodeUp = u.UnitUp
                                    LEFT JOIN master_ap a ON f.KodeAp = a.UnitAp 
                                    LEFT JOIN master_up ut ON f.KodeUp_Tujuan = ut.UnitUp
                                    LEFT JOIN master_ap at ON f.KodeAp_Tujuan = at.UnitAp
                                    WHERE f.NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_pengiriman]);
            $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$formulir) {
                $formulir = [
                    'NoFormulir' => $no_pengiriman,
                    'TglFormulir' => date('Y-m-d'),
                    'StatusData' => 'TIDAK',
                    'StatusPengiriman' => 'DIKIRIM',
                    'NamaAP_Tujuan' => '-',
                    'NamaUP_Tujuan' => '-',
                    'KodeUpi_Tujuan' => '-',
                    'NamaAkun' => ''
                ];
            }

            // 2. PERBAIKAN: Menentukan Nama Tujuan yang Tampil
            $nama_tujuan = "-";
            if (!empty($formulir['NamaUP_Tujuan']) && $formulir['NamaUP_Tujuan'] !== '-') {
                $nama_tujuan = $formulir['NamaUP_Tujuan'];
            } elseif (!empty($formulir['NamaAP_Tujuan']) && $formulir['NamaAP_Tujuan'] !== '-') {
                $nama_tujuan = $formulir['NamaAP_Tujuan'];
            } elseif (!empty($formulir['KodeUpi_Tujuan'])) {
                $nama_tujuan = "UPI " . $formulir['KodeUpi_Tujuan'];
            }
            $formulir['NamaTujuanStr'] = $nama_tujuan;

            // 3. PERBAIKAN: Cek Proteksi Pembuat (Hanya yang buat form ini yang boleh edit)
            $session_akun = $_SESSION['NamaAkun'] ?? '';
            $is_creator = ($formulir['NamaAkun'] === $session_akun);
            
            $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
            $stmt_items->execute([':no_form' => $no_pengiriman]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            // 4. PERBAIKAN: Ambil Stok Tersedia di Gudang UPI untuk Dropdown Barcode Scanner
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
                      JOIN formulir_pengiriman p ON d.NoFormulir = p.NoFormulir 
                      WHERE p.StatusPengiriman = 'DIKIRIM'
                  )
            ");
            $stmt_stok->execute([':upi' => $user_upi]);
            $stok_tersedia = $stmt_stok->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- 3. PENERIMAAN ---
    elseif ($sub === 'penerimaan') {
        if (!in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP'])) {
            header("Location: index.php?page=pengadaan&menu=monitoring&sub=aset");
            exit;
        }

        if ($view === 'daftar') {
            $unit_ap = $_SESSION['UnitAp'] ?? '';
            $unit_up = $_SESSION['UnitUp'] ?? '';

            if ($kode_hak === 'TL.UP') {
                $filter_query = "f.KodeUp_Tujuan = :lokasi";
                $lokasi = $unit_up;
            } else {
                $filter_query = "f.KodeAp_Tujuan = :lokasi AND (f.KodeUp_Tujuan IS NULL OR f.KodeUp_Tujuan = '')";
                $lokasi = $unit_ap;
            }
            if ($kode_hak === 'SA.KP') { $filter_query = "1=1"; $lokasi = 1; }

            // --- PENGATURAN PAGING ---
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            if (!in_array($limit, [5, 10, 20, 30])) { $limit = 10; }
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($page < 1) { $page = 1; }
            $offset = ($page - 1) * $limit;

            // Hitung Total Data Penerimaan
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_pengiriman f WHERE f.StatusData = 'AKTIF' AND $filter_query");
            if ($kode_hak !== 'SA.KP') { $stmt_count->bindParam(':lokasi', $lokasi); }
            $stmt_count->execute();
            $total_data = (int) $stmt_count->fetchColumn();
            $total_pages = ceil($total_data / $limit);
            if ($total_pages < 1) { $total_pages = 1; }
            if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

            // Query Data dengan LIMIT & OFFSET
            $query = "SELECT 
                        f.NoFormulir, f.TglFormulir, f.NamaAkun, f.StatusPengiriman, f.TglTerima, f.WaktuData,
                        (SELECT COUNT(NoRef) FROM formulir_pengiriman_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                      FROM formulir_pengiriman f 
                      WHERE f.StatusData = 'AKTIF' AND $filter_query
                      ORDER BY (CASE WHEN f.StatusPengiriman = 'DIKIRIM' THEN 1 ELSE 2 END), f.WaktuData DESC
                      LIMIT :limit OFFSET :offset";
                      
            try {
                $stmt = $conn->prepare($query);
                if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $list_inbound = $stmt->fetchAll(PDO::FETCH_ASSOC); 
                
                $paging = [
                    'total_data' => $total_data,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ];
            } catch (PDOException $e) {
                die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "');</script>");
            }
        } elseif ($view === 'detail' && isset($_GET['no_form'])) {
            $no_pengiriman = $_GET['no_form'];
            
            $stmt = $conn->prepare("SELECT f.*, u.NamaUnit as NamaUP, a.NamaUnit as NamaAP 
                                    FROM formulir_pengiriman f 
                                    LEFT JOIN master_up u ON f.KodeUp = u.UnitUp
                                    LEFT JOIN master_ap a ON f.KodeAp = a.UnitAp 
                                    WHERE f.NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_pengiriman]);
            $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
            $stmt_items->execute([':no_form' => $no_pengiriman]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Panggil template wrapper utama
require_once __DIR__ . '/../../templates/pengadaan/index.php'; 
?>