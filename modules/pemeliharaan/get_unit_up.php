<?php
// Daftar ULP aktif milik satu UP3 (untuk dropdown berantai). Wajib login dan hormati batas unit.
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['NamaAkun']) || !ph_akses_order()) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$unit_ap = trim((string) ($_GET['unit_ap'] ?? ''));

// Akun tingkat UP3/ULP hanya boleh melihat unit wilayahnya
if (!ph_role_semua_unit()) {
    $ap_sesi = trim((string) ($_SESSION['UnitAp'] ?? ''));
    if ($ap_sesi === '' || $unit_ap !== $ap_sesi) {
        echo json_encode([]);
        exit;
    }
}

if ($unit_ap === '') {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT UnitUp, SingkatanNama, NamaUnit 
                            FROM master_up 
                            WHERE UnitAp = :unit_ap AND StatusData = 'AKTIF' 
                            ORDER BY UnitUp ASC");
    $stmt->execute([':unit_ap' => $unit_ap]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log('pemeliharaan/get_unit_up: ' . $e->getMessage());
    echo json_encode([]);
}