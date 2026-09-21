<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$unit_up = $_SESSION['UnitUp'];
$unit_ap = $_SESSION['UnitAp'];
$kode_hak = $_SESSION['KodeHak'];

// Filter data yang belum diterima sesuai dengan level unit yang login
// Jika UP login, lihat pengiriman ke UP tersebut. Jika AP login, lihat pengiriman ke AP tersebut.
$query = "SELECT p.*, a.NamaPengguna AS Pengirim 
          FROM formulir_pengiriman p
          LEFT JOIN master_pengguna a ON p.NamaAkun = a.NamaAkun
          WHERE p.StatusPengiriman = 'DIKIRIM' AND p.StatusData = 'AKTIF'";

if ($kode_hak == 'STF' || $kode_hak == 'ADM') {
    $query .= " AND p.KodeUp = :up";
    $stmt = $conn->prepare($query);
    $stmt->execute([':up' => $unit_up]);
} else {
    $stmt = $conn->prepare($query);
    $stmt->execute();
}

$pengiriman_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Penerimaan Perangkat S41 - Digita";
ob_start();
require_once '../../templates/pengadaan/penerimaan.php';
$content = ob_get_clean();
require_once '../../templates/layouts/base.php';
?>