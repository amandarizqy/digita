<?php
require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SA.KP']; 
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
            }).then((result) => {
                if (result.isConfirmed) {
                    window.history.back();
                }
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
    } elseif (in_array($kode_hak, ['AM.UI', 'SA.KP', 'MB.UI'])) {
        header("Location: index.php?page=pengadaan&menu=pembelian&view=daftar");
        exit;
    } else {
        header("Location: index.php?page=pengadaan&menu=monitoring&sub=aset");
        exit;
    }
}

// Tangkap parameter dari URL
$menu = $_GET['menu'] ?? 'pembelian'; 
$sub  = $_GET['sub'] ?? $menu;
$view = $_GET['view'] ?? 'daftar'; 

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$page_title = "Pengadaan & Stok - Digita S41";

// Jika menu Kartu
if ($menu === 'kartu') {
    $sub = $_GET['sub'] ?? 'aktivasi';
    require_once __DIR__ . '/kartu.php';
} 
// Jika menu Monitoring (Panggil file backend logic saja)
elseif ($menu === 'monitoring') {
    $sub = $_GET['sub'] ?? 'aset';
    require_once __DIR__ . '/monitoring.php';
}

// Jika masuk ke ekosistem Barang (Pembelian / Pengiriman / Penerimaan)
elseif (in_array($menu, ['barang', 'pembelian', 'pengiriman', 'penerimaan'])) {
    
    // --- 1. PEMBELIAN ---
    if ($sub === 'pembelian') {
        if (!in_array($kode_hak, ['AM.UI', 'SA.KP'])) {
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
                        window.location.href = 'index.php?page=pengadaan&menu=penerimaan&view=daftar';
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        }
        
        if ($view === 'daftar') {
            $keyword = '%' . trim($_GET['q'] ?? '') . '%';
            $query = "SELECT 
                        f.NoFormulir, f.TglBeli, f.StatusData, f.WaktuData,
                        (SELECT COALESCE(SUM(HargaBeli), 0) FROM formulir_pembelian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalBiaya, 
                        (SELECT COUNT(NoRef) FROM formulir_pembelian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                    FROM formulir_pembelian f 
                    WHERE f.NoFormulir LIKE :keyword
                    ORDER BY f.WaktuData DESC";
            try {
                $stmt = $conn->prepare($query); 
                $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
                $stmt->execute();
                $list_pembelian = $stmt->fetchAll(PDO::FETCH_ASSOC); 
            } catch (PDOException $e) {
                die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "');</script>");
            }
        } elseif ($view === 'baru') {
            // REDIRECT LANGSUNG KE PROSES PEMBUATAN DRAFT
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
        if (!in_array($kode_hak, ['AM.UI', 'SA.KP'])) {
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
                        window.location.href = 'index.php?page=pengadaan&menu=penerimaan&view=daftar';
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        }

        if ($view === 'daftar') {
            $keyword = '%' . trim($_GET['q'] ?? '') . '%';
            $query = "SELECT 
                        f.NoFormulir, f.TglFormulir, f.NamaAkun, f.StatusPengiriman, f.StatusData, f.WaktuData,
                        (SELECT COUNT(NoRef) FROM formulir_pengiriman_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                    FROM formulir_pengiriman f 
                    WHERE f.NoFormulir LIKE :keyword
                    ORDER BY f.WaktuData DESC";
            try {
                $stmt = $conn->prepare($query); 
                $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
                $stmt->execute();
                $list_pengiriman = $stmt->fetchAll(PDO::FETCH_ASSOC); 
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
            
            $stmt = $conn->prepare("SELECT f.*, u.NamaUnit as NamaUP, a.NamaUnit as NamaAP 
                                    FROM formulir_pengiriman f 
                                    LEFT JOIN master_up u ON f.KodeUp = u.UnitUp
                                    LEFT JOIN master_ap a ON f.KodeAp = a.UnitAp 
                                    WHERE f.NoFormulir = :no_form");
            $stmt->execute([':no_form' => $no_pengiriman]);
            $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // PASTIKAN BARIS INI MENGGUNAKAN :no_form DENGAN BENAR
            $stmt_items = $conn->prepare("SELECT * FROM formulir_pengiriman_detil WHERE NoFormulir = :no_form");
            $stmt_items->execute([':no_form' => $no_pengiriman]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- 3. PENERIMAAN ---
    elseif ($sub === 'penerimaan') {
        if (!in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP'])) {
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
                        window.location.href = 'index.php?page=pengadaan&menu=penerimaan&view=daftar';
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        }

        if ($view === 'daftar') {
            $unit_ap = $_SESSION['UnitAp'] ?? '';
            $unit_up = $_SESSION['UnitUp'] ?? '';

            if ($kode_hak === 'TL.UP') {
                $filter_query = "f.KodeUp = :lokasi";
                $lokasi = $unit_up;
            } else {
                $filter_query = "f.KodeAp = :lokasi AND (f.KodeUp IS NULL OR f.KodeUp = '')";
                $lokasi = $unit_ap;
            }
            if ($kode_hak === 'SA.KP') { $filter_query = "1=1"; $lokasi = 1; }

            $query = "SELECT 
                        f.NoFormulir, f.TglFormulir, f.NamaAkun, f.StatusPengiriman, f.TglTerima, f.WaktuData,
                        (SELECT COUNT(NoRef) FROM formulir_pengiriman_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalItem 
                      FROM formulir_pengiriman f 
                      WHERE f.StatusData = 'AKTIF' AND $filter_query
                      ORDER BY (CASE WHEN f.StatusPengiriman = 'DIKIRIM' THEN 1 ELSE 2 END), f.WaktuData DESC";
                      
            try {
                $stmt = $conn->prepare($query);
                if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
                $stmt->execute();
                $list_inbound = $stmt->fetchAll(PDO::FETCH_ASSOC); 
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