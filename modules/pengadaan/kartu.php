<?php
// Hak Akses Kartu: TL.AP, TL.UP, SA.KP
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!in_array($kode_hak, $allowed_roles)) {
    // Redirect langsung ke halaman yang diizinkan atau tampilkan SweetAlert di dalam layout utama
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

$sub  = $_GET['sub'] ?? 'aktivasi'; 
$view = $_GET['view'] ?? 'daftar'; 

$unit_ap = $_SESSION['UnitAp'] ?? '';
$unit_up = $_SESSION['UnitUp'] ?? '';

$keyword = '%' . trim($_GET['q'] ?? '') . '%';
// ---------------------------------------------------------
// QUERY BACKEND BERDASARKAN SUB-ENTITAS
// ---------------------------------------------------------
if ($sub === 'aktivasi') {
    if ($view === 'daftar') {
        $filter_query = ($kode_hak === 'TL.UP') ? "n.KodeUp = :lokasi" : "n.KodeAp = :lokasi AND (n.KodeUp IS NULL OR n.KodeUp = '')";
        $lokasi = ($kode_hak === 'TL.UP') ? $unit_up : $unit_ap;
        if ($kode_hak === 'SA.KP') { $filter_query = "1=1"; $lokasi = 1;} 

        $query = "SELECT n.*, p.NamaProvider, pp.NamaProduk 
                  FROM master_nomor n
                  LEFT JOIN master_provider p ON n.KodeProvider = p.KodeProvider
                  LEFT JOIN master_provider_produk pp ON n.KodeProduk = pp.KodeProduk
                  WHERE $filter_query AND (n.SimId LIKE :keyword OR n.NomorAkun LIKE :keyword)
                  ORDER BY n.WaktuData DESC";
                  
        $stmt = $conn->prepare($query);
        if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
        $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $stmt->execute();
        $data['list_kartu'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($view === 'baru') {
        $stmt_prov = $conn->query("SELECT * FROM master_provider WHERE StatusData = 'AKTIF'");
        $data['providers'] = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_prod = $conn->query("SELECT * FROM master_provider_produk WHERE StatusData = 'AKTIF'");
        $data['products'] = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
    }

} elseif ($sub === 'pulsa') {
    if ($view === 'daftar') {
        $filter_query = ($kode_hak === 'TL.UP') ? "KodeUp = :lokasi" : "KodeAp = :lokasi AND (KodeUp IS NULL OR KodeUp = '')";
        $lokasi = ($kode_hak === 'TL.UP') ? $unit_up : $unit_ap;
        if ($kode_hak === 'SA.KP') { $filter_query = "1=1"; $lokasi = 1;}
        
        // Kalkulasi otomatis: Masa Aktif = TglPulsaTerakhir + 30 Hari
        $query = "SELECT SimId, NomorAkun, JumlahKredit, TglPulsaTerakhir, 
                         DATE_ADD(TglPulsaTerakhir, INTERVAL 30 DAY) AS MasaAktif,
                         DATEDIFF(DATE_ADD(TglPulsaTerakhir, INTERVAL 30 DAY), CURDATE()) AS SisaHari
                  FROM master_nomor 
                  WHERE JumlahKredit IS NOT NULL AND $filter_query AND (SimId LIKE :keyword OR NomorAkun LIKE :keyword)
                  ORDER BY TglPulsaTerakhir DESC";
                  
        $stmt = $conn->prepare($query);
        if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
        $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $stmt->execute();
        $data['list_pulsa'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>