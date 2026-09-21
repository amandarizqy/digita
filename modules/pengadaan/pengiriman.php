<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Ambil hierarki Hak Akses
$kode_hak = $_SESSION['KodeHak'] ?? '';
$unit_upi = $_SESSION['UnitUpi'] ?? '56';
$unit_ap  = $_SESSION['UnitAp'] ?? '';

// Filter Tujuan: UI melihat semua UP, AP hanya melihat UP di wilayahnya
$hak_ui = ['SA.KP', 'MB.UI', 'AM.UI', 'SF.UI', 'SUP'];
if (in_array($kode_hak, $hak_ui)) {
    $query_unit = "SELECT u.UnitUp, u.NamaUnit AS NamaUp, a.NamaUnit AS NamaAp 
                   FROM master_up u 
                   JOIN master_ap a ON u.UnitAp = a.UnitAp 
                   WHERE u.UnitUpi = :upi";
    $stmt = $conn->prepare($query_unit);
    $stmt->execute([':upi' => $unit_upi]);
} else {
    $query_unit = "SELECT u.UnitUp, u.NamaUnit AS NamaUp, a.NamaUnit AS NamaAp 
                   FROM master_up u 
                   JOIN master_ap a ON u.UnitAp = a.UnitAp 
                   WHERE u.UnitAp = :ap";
    $stmt = $conn->prepare($query_unit);
    $stmt->execute([':ap' => $unit_ap]);
}

$unit_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render Tampilan
$page_title = "Formulir Pengiriman S41 - Digita";
ob_start();
require_once '../../templates/pengadaan/pengiriman.php';
$content = ob_get_clean();
require_once '../../templates/layouts/base.php';
?>