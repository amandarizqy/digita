<?php
// modules/perencanaan/hasil_risiko.php

$pesan_error = "";

// 1. Tangkap Parameter Filter & Paginasi dari URL
$keyword      = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$prioritas    = isset($_GET['prioritas']) ? trim($_GET['prioritas']) : '';
$posisi_sr    = isset($_GET['posisi_sr']) ? trim($_GET['posisi_sr']) : '';
$level_dampak = isset($_GET['level_dampak']) ? trim($_GET['level_dampak']) : '';

// Pengaturan Paginasi
$limit = 10; // Jumlah baris data per halaman
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 2. Susun Kondisi Filter Dinamis
    // Hanya menampilkan data yang sudah diproses (SkalaPrioritas tidak kosong/0)
    $where = ["k.SkalaPrioritas IS NOT NULL AND k.SkalaPrioritas != 0"];
    $params = [];

    if (!empty($keyword)) {
        $where[] = "(d.Idpel LIKE :kw OR d.NamaPelanggan LIKE :kw)";
        $params[':kw'] = '%' . $keyword . '%';
    }

    if ($prioritas !== '') {
        $where[] = "k.SkalaPrioritas = :prioritas";
        $params[':prioritas'] = $prioritas;
    }

    if ($posisi_sr !== '') {
        $where[] = "k.PosisiSR = :posisi_sr";
        $params[':posisi_sr'] = $posisi_sr;
    }

    if (!empty($level_dampak)) {
        $where[] = "k.LevelDampak = :level_dampak";
        $params[':level_dampak'] = $level_dampak;
    }

    $where_sql = " WHERE " . implode(' AND ', $where);

    // 3. Hitung Total Data untuk Hitungan Paginasi
    $sql_count = "
        SELECT COUNT(*) 
        FROM dil d
        INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel
        $where_sql
    ";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_rows  = (int) $stmt_count->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 4. Query Ambil Data Sesuai Paginasi & Filter
    $sql_data = "
        SELECT 
            d.Idpel,
            d.NamaPelanggan,
            k.Periode,
            k.PosisiSR,
            k.LevelKeterlambatan,
            k.LevelKepentingan,
            k.LevelKemungkinan,
            k.LevelDampak,
            k.Kuadran,
            k.SkalaPrioritas
        FROM dil d
        INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel
        $where_sql
        ORDER BY k.SkalaPrioritas DESC, d.Idpel ASC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt_data = $conn->prepare($sql_data);

    // Bind parameter kondisi filter
    foreach ($params as $key => $val) {
        $stmt_data->bindValue($key, $val);
    }
    
    // Bind parameter LIMIT dan OFFSET secara terpisah sebagai tipe Integer
    $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_data->execute();

    $list_hasil = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ringkasan Statistik untuk Kartu Atas
    $total_p_tinggi = (int) $conn->query("SELECT COUNT(*) FROM kategorisasi_risiko WHERE SkalaPrioritas IN (7, 8, 9)")->fetchColumn();
    $total_p_sedang = (int) $conn->query("SELECT COUNT(*) FROM kategorisasi_risiko WHERE SkalaPrioritas IN (4, 5, 6)")->fetchColumn();
    $total_p_rendah = (int) $conn->query("SELECT COUNT(*) FROM kategorisasi_risiko WHERE SkalaPrioritas IN (1, 2, 3)")->fetchColumn();

} catch (PDOException $e) {
    $pesan_error = "Gagal memuat hasil evaluasi risiko: " . $e->getMessage();
}

include __DIR__ . '/../../templates/perencanaan/hasil_risiko.php';
?>