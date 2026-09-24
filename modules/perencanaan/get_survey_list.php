<?php
// modules/perencanaan/get_survey_list.php
require_once '../../config/database.php';

header('Content-Type: application/json');

$type   = $_GET['type'] ?? 'sudah'; // 'sudah' atau 'belum'
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50; // Tampilkan 50 data per pencarian
$offset = ($page - 1) * $limit;

try {
    $params = [];
    
    if ($type === 'sudah') {
        $sql = "SELECT d.Idpel, d.NamaPelanggan, k.PosisiSR 
                FROM dil d 
                INNER JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel 
                WHERE k.PosisiSR IS NOT NULL AND k.PosisiSR != ''";
        
        if ($search !== '') {
            $sql .= " AND (d.Idpel LIKE ? OR d.NamaPelanggan LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY d.Idpel ASC LIMIT $limit OFFSET $offset";
        
    } else { // type 'belum'
        $sql = "SELECT d.Idpel, d.NamaPelanggan 
                FROM dil d 
                LEFT JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel 
                WHERE (k.Idpel IS NULL OR k.PosisiSR IS NULL OR k.PosisiSR = '')";
        
        if ($search !== '') {
            $sql .= " AND (d.Idpel LIKE ? OR d.NamaPelanggan LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY d.Idpel ASC LIMIT $limit OFFSET $offset";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>