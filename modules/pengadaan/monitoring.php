<?php
$unit_upi = $_SESSION['UnitUpi'] ?? '';
$unit_ap  = $_SESSION['UnitAp'] ?? '';
$unit_up  = $_SESSION['UnitUp'] ?? '';

// AMBIL KATA KUNCI PENCARIAN & PAGING
$keyword = '%' . trim($_GET['q'] ?? '') . '%';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [5, 10, 20, 30])) { $limit = 10; }
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $limit;

try {
    // --- 1. MONITORING ASET (MASTER BARANG) ---
    if ($sub === 'aset') {
        $filter_barang = "b.StatusData = 'AKTIF'";
        $params_barang = [];

        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_barang .= " AND b.UnitUp = ?";
            $params_barang[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            $filter_barang .= " AND (b.UnitAp = ? OR b.UnitUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
            $params_barang[] = $unit_ap;
            $params_barang[] = $unit_ap;
        } elseif (in_array($kode_hak, ['MB.UI', 'AM.UI', 'SF.UI', 'SA.KP'])) {
            if ($kode_hak !== 'SA.KP' && !empty($unit_upi)) {
                $filter_barang .= " AND b.UnitUpi = ?";
                $params_barang[] = $unit_upi;
            }
        }
        
        $filter_barang .= " AND (b.NoRef LIKE ? OR b.SimId LIKE ? OR b.ImeiModem LIKE ? OR b.IdPel LIKE ?)";
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;
        $params_barang[] = $keyword;

        // Hitung Total Data
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM master_barang b WHERE $filter_barang");
        $stmt_count->execute($params_barang);
        $total_data = (int) $stmt_count->fetchColumn();

        // Query Data dengan Aman menggunakan integer langsung untuk LIMIT & OFFSET
        $query_aset = "SELECT b.*, 
                              COALESCE(u.NamaUnit, b.UnitUpi, '-') as NamaUpi,
                              COALESCE(ap.NamaUnit, b.UnitAp, '-') as NamaAp,
                              COALESCE(up.NamaUnit, b.UnitUp, '-') as NamaUp
                       FROM master_barang b
                       LEFT JOIN master_upi u ON b.UnitUpi = u.UnitUpi
                       LEFT JOIN master_ap ap ON b.UnitAp = ap.UnitAp
                       LEFT JOIN master_up up ON b.UnitUp = up.UnitUp
                       WHERE $filter_barang 
                       ORDER BY b.WaktuData DESC
                       LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($query_aset);
        $stmt->execute($params_barang);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } 
    // --- 2. MONITORING AKTIVASI PERDANA (KARTU) ---
    elseif ($sub === 'pembelian') {
        $filter_kartu = "n.StatusData = 'AKTIF'";
        $params_kartu = [];

        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_kartu .= " AND n.KodeUp = ?";
            $params_kartu[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            $filter_kartu .= " AND (n.KodeAp = ? OR n.KodeUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
            $params_kartu[] = $unit_ap;
            $params_kartu[] = $unit_ap;
        } elseif (in_array($kode_hak, ['MB.UI', 'AM.UI', 'SF.UI', 'SA.KP'])) {
            if ($kode_hak !== 'SA.KP' && !empty($unit_upi)) {
                $filter_kartu .= " AND n.KodeUpi = ?";
                $params_kartu[] = $unit_upi;
            }
        }

        $filter_kartu .= " AND (n.SimId LIKE ? OR n.NomorAkun LIKE ?)";
        $params_kartu[] = $keyword;
        $params_kartu[] = $keyword;

        // Hitung Total Data
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM master_nomor n WHERE $filter_kartu");
        $stmt_count->execute($params_kartu);
        $total_data = (int) $stmt_count->fetchColumn();

        $query_kartu = "SELECT n.*, p.NamaProvider, pp.NamaProduk,
                               COALESCE(u.NamaUnit, n.KodeUpi, '-') as NamaUpi,
                               COALESCE(ap.NamaUnit, n.KodeAp, '-') as NamaAp,
                               COALESCE(up.NamaUnit, n.KodeUp, '-') as NamaUp
                        FROM master_nomor n
                        LEFT JOIN master_provider p ON n.KodeProvider = p.KodeProvider
                        LEFT JOIN master_provider_produk pp ON n.KodeProduk = pp.KodeProduk
                        LEFT JOIN master_upi u ON n.KodeUpi = u.UnitUpi
                        LEFT JOIN master_ap ap ON n.KodeAp = ap.UnitAp
                        LEFT JOIN master_up up ON n.KodeUp = up.UnitUp
                        WHERE $filter_kartu
                        ORDER BY n.WaktuData DESC
                        LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($query_kartu);
        $stmt->execute($params_kartu);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } 
    // --- 3. MONITORING PENGIRIMAN (Fokus Berdasarkan Wilayah Tujuan / Asal) ---
    elseif ($sub === 'pengiriman') {
        $filter_kirim = "1=1";
        $params_kirim = [];

        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_kirim .= " AND (KodeUp = ? OR KodeUp_Tujuan = ?)";
            $params_kirim[] = $unit_up;
            $params_kirim[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            // Hanya tampil jika unit AP pengirim atau AP tujuan, atau bawahan UP-nya
            $filter_kirim .= " AND (KodeAp = ? OR KodeAp_Tujuan = ? OR KodeUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?) OR KodeUp_Tujuan IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
            $params_kirim[] = $unit_ap;
            $params_kirim[] = $unit_ap;
            $params_kirim[] = $unit_ap;
            $params_kirim[] = $unit_ap;
        } elseif (in_array($kode_hak, ['MB.UI', 'AM.UI', 'SF.UI', 'SA.KP'])) {
            if ($kode_hak !== 'SA.KP' && !empty($unit_upi)) {
                $filter_kirim .= " AND (KodeUpi = ? OR KodeUpi_Tujuan = ?)";
                $params_kirim[] = $unit_upi;
                $params_kirim[] = $unit_upi;
            }
        }

        $filter_kirim .= " AND NoFormulir LIKE ?";
        $params_kirim[] = $keyword;

        // Hitung Total Data
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_pengiriman WHERE $filter_kirim");
        $stmt_count->execute($params_kirim);
        $total_data = (int) $stmt_count->fetchColumn();

        $query = "SELECT * FROM formulir_pengiriman WHERE $filter_kirim ORDER BY WaktuData DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params_kirim);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    // --- 4. MONITORING PENERIMAAN ---
    elseif ($sub === 'penerimaan') {
        $filter_terima = "StatusData = 'AKTIF'";
        $params_terima = [];

        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_terima .= " AND KodeUp = ?";
            $params_terima[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            $filter_terima .= " AND (KodeAp = ? OR KodeUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
            $params_terima[] = $unit_ap;
            $params_terima[] = $unit_ap;
        } elseif (in_array($kode_hak, ['MB.UI', 'AM.UI', 'SF.UI', 'SA.KP'])) {
            if ($kode_hak !== 'SA.KP' && !empty($unit_upi)) {
                $filter_terima .= " AND KodeUpi = ?";
                $params_terima[] = $unit_upi;
            }
        }

        $filter_terima .= " AND NoFormulir LIKE ?";
        $params_terima[] = $keyword;

        // Hitung Total Data
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_penerimaan WHERE $filter_terima");
        $stmt_count->execute($params_terima);
        $total_data = (int) $stmt_count->fetchColumn();

        $query = "SELECT * FROM formulir_penerimaan WHERE $filter_terima ORDER BY WaktuData DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params_terima);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } 
    // --- 5. MONITORING RIWAYAT PULSA ---
    elseif ($sub === 'pulsa') {
        $filter_pulsa = "JumlahKredit IS NOT NULL";
        $params_pulsa = [];

        if (in_array($kode_hak, ['ML.UP', 'TL.UP', 'SF.UP'])) {
            $filter_pulsa .= " AND KodeUp = ?";
            $params_pulsa[] = $unit_up;
        } elseif (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'])) {
            $filter_pulsa .= " AND (KodeAp = ? OR KodeUp IN (SELECT UnitUp FROM master_up WHERE UnitAp = ?))";
            $params_pulsa[] = $unit_ap;
            $params_pulsa[] = $unit_ap;
        } elseif (in_array($kode_hak, ['MB.UI', 'AM.UI', 'SF.UI', 'SA.KP'])) {
            if ($kode_hak !== 'SA.KP' && !empty($unit_upi)) {
                $filter_pulsa .= " AND KodeUpi = ?";
                $params_pulsa[] = $unit_upi;
            }
        }

        $filter_pulsa .= " AND (SimId LIKE ? OR NomorAkun LIKE ?)";
        $params_pulsa[] = $keyword;
        $params_pulsa[] = $keyword;

        // Hitung Total Data
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM master_nomor WHERE $filter_pulsa");
        $stmt_count->execute($params_pulsa);
        $total_data = (int) $stmt_count->fetchColumn();

        $query = "SELECT * FROM master_nomor WHERE $filter_pulsa ORDER BY TglPulsaTerakhir DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params_pulsa);
        $list_monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Kalkulasi Total Halaman Paging
    $total_pages = ceil(($total_data ?? 0) / $limit);
    if ($total_pages < 1) { $total_pages = 1; }
    if ($page > $total_pages) { $page = $total_pages; }

    $paging = [
        'total_data'   => $total_data ?? 0,
        'total_pages'  => $total_pages,
        'current_page' => $page,
        'limit'        => $limit
    ];

} catch (Exception $e) {
    $list_monitoring = [];
    $paging = ['total_data' => 0, 'total_pages' => 1, 'current_page' => 1, 'limit' => 10];
}
?>