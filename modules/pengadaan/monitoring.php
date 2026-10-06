<?php
$unit_ap = $_SESSION['UnitAp'] ?? '';
$unit_up = $_SESSION['UnitUp'] ?? '';

// PENERAPAN HIERARKI ORGANISASI:
$filter_sql = "1=1";
$params = [];

if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
    $filter_sql = "KodeUp = ?";
    $params[] = $unit_up;
} elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
    $filter_sql = "(KodeAp = ? OR KodeUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
    $params[] = $unit_ap;
    $params[] = $unit_ap;
}

// AMBIL KATA KUNCI PENCARIAN
$keyword = '%' . trim($_GET['q'] ?? '') . '%';

try {
    if ($sub === 'aset') {
        $filter_barang = "1=1";
        $params_barang = [];
        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_barang = "UnitUp = ?";
            $params_barang[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            $filter_barang = "UnitAp = ?";
            $params_barang[] = $unit_ap;
        }
        
        // Filter Smart Search untuk Aset
        $filter_barang .= " AND (NoRef LIKE ? OR SimId LIKE ? OR ImeiModem LIKE ? OR IdPel LIKE ?)";
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;

        $stmt = $conn->prepare("SELECT * FROM master_barang WHERE StatusData = 'AKTIF' AND $filter_barang ORDER BY WaktuData DESC");
        $stmt->execute($params_barang);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($sub === 'pembelian') {
        $filter_sql .= " AND NoFormulir LIKE ?";
        $params[] = $keyword;

        $stmt = $conn->prepare("SELECT * FROM formulir_pembelian WHERE $filter_sql ORDER BY WaktuData DESC");
        $stmt->execute($params);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($sub === 'pengiriman') {
        $filter_sql .= " AND NoFormulir LIKE ?";
        $params[] = $keyword;

        $stmt = $conn->prepare("SELECT * FROM formulir_pengiriman WHERE $filter_sql ORDER BY WaktuData DESC");
        $stmt->execute($params);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($sub === 'penerimaan') {
        $filter_sql .= " AND NoFormulir LIKE ?";
        $params[] = $keyword;

        $stmt = $conn->prepare("SELECT * FROM formulir_penerimaan WHERE $filter_sql ORDER BY WaktuData DESC");
        $stmt->execute($params);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($sub === 'pulsa') {
        $filter_sql .= " AND (SimId LIKE ? OR NomorAkun LIKE ?)";
        $params[] = $keyword;
        $params[] = $keyword;

        $stmt = $conn->prepare("SELECT * FROM master_nomor WHERE JumlahKredit IS NOT NULL AND $filter_sql ORDER BY TglPulsaTerakhir DESC");
        $stmt->execute($params);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $list_monitoring = [];
}
?>