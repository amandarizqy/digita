<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
if (!isset($conn)) {
    if (file_exists($root_path . '/config/database.php')) {
        require_once $root_path . '/config/database.php';
    } else {
        require_once __DIR__ . '/../../config/database.php';
    }
}

$type = $_GET['type'] ?? 'order';
$filename = "Laporan_Pemeliharaan_" . ucfirst($type) . "_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

$user_role  = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$session_ap = $_SESSION['UnitAp'] ?? '';
$session_up = $_SESSION['UnitUp'] ?? '';

$where_clauses = [];
$params_query = [];

if (!in_array($user_role, ['SA.KP', 'SF.UI'])) {
    if (!empty($session_up)) {
        $where_clauses[] = "p.UnitUp = ?";
        $params_query[] = $session_up;
    } elseif (!empty($session_ap)) {
        $where_clauses[] = "p.UnitAp = ?";
        $params_query[] = $session_ap;
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Query join master_barang
$sql = "SELECT p.NoFormPemeliharaan, p.NoFormOrder, p.TglPemeliharaan, p.KdPetugas, p.IdPel, p.NoRef, 
               p.SimId, p.ImeiModem, p.KondisiRelay, p.PosisiPasang, p.Latitude, p.Longitude, p.StatusData, p.WaktuData
        FROM frm_pemeliharaan p 
        LEFT JOIN master_barang b ON p.NoRef = b.NoRef 
        $where_sql 
        ORDER BY p.WaktuData DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params_query);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>REKAPITULASI LAPORAN PEMELIHARAAN S41 - DIGITA</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr style="background-color: #e9ecef; font-weight: bold;">
            <th>No</th>
            <th>No Form Pemeliharaan</th>
            <th>No Form Order</th>
            <th>Tgl Pemeliharaan</th>
            <th>Kode Petugas</th>
            <th>ID Pelanggan</th>
            <th>No Ref Perangkat</th>
            <th>SIM ID</th>
            <th>IMEI Modem</th>
            <th>Kondisi Relay</th>
            <th>Posisi Pasang</th>
            <th>Koordinat GPS</th>
            <th>Status Data</th>
            <th>Waktu Input</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $idx => $row): ?>
        <tr>
            <td><?= $idx + 1 ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['NoFormOrder'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['TglPemeliharaan']) ?></td>
            <td><?= htmlspecialchars($row['KdPetugas']) ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['IdPel']) ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['NoRef']) ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['SimId'] ?? '-') ?></td>
            <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['KondisiRelay']) ?></td>
            <td><?= htmlspecialchars($row['PosisiPasang']) ?></td>
            <td><?= htmlspecialchars(($row['Latitude'] ?? '') . ', ' . ($row['Longitude'] ?? '')) ?></td>
            <td><?= htmlspecialchars($row['StatusData']) ?></td>
            <td><?= htmlspecialchars($row['WaktuData']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>