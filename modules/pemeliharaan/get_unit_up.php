<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
} else {
    require_once __DIR__ . '/../../config/database.php';
}

header('Content-Type: application/json');

$unit_ap = $_GET['unit_ap'] ?? '';

if (!empty($unit_ap)) {
    try {
        $stmt = $conn->prepare("SELECT UnitUp, SingkatanNama, NamaUnit 
                                FROM master_up 
                                WHERE UnitAp = :unit_ap AND StatusData = 'AKTIF' 
                                ORDER BY UnitUp ASC");
        $stmt->execute([':unit_ap' => $unit_ap]);
        $data_up = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data_up);
        exit;
    } catch (PDOException $e) {
        echo json_encode([]);
        exit;
    }
}

echo json_encode([]);