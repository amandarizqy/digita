<?php
// Tidak perlu memanggil database lagi karena sudah dipanggil di modul utama pengadaan/index.php

// Hak Akses Kartu: TL.AP, TL.UP, SA.KP
$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!in_array($kode_hak, $allowed_roles)) {
    echo "<script>alert('Akses Ditolak: Modul Kartu hanya untuk Team Leader.'); window.history.back();</script>";
    exit;
}

$sub  = $_GET['sub'] ?? 'aktivasi'; 
$view = $_GET['view'] ?? 'daftar'; 

$unit_ap = $_SESSION['UnitAp'] ?? '';
$unit_up = $_SESSION['UnitUp'] ?? '';

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
                  WHERE $filter_query
                  ORDER BY n.WaktuData DESC";
                  
        $stmt = $conn->prepare($query);
        if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
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
        
        $query = "SELECT SimId, NomorAkun, JumlahKredit, TglPulsaTerakhir 
                  FROM master_nomor 
                  WHERE JumlahKredit IS NOT NULL AND $filter_query
                  ORDER BY TglPulsaTerakhir DESC";
                  
        $stmt = $conn->prepare($query);
        if ($kode_hak !== 'SA.KP') { $stmt->bindParam(':lokasi', $lokasi); }
        $stmt->execute();
        $data['list_pulsa'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
// Tidak ada load template di sini. Kontrol dikembalikan ke index.php
?>