<?php
// modules/perencanaan/hasil_survey.php

$pesan_sukses = "";
$pesan_error = "";

// ---------------------------------------------------------
// 1. PROSES HAPUS DATA SURVEY
// ---------------------------------------------------------
if (isset($_GET['act']) && $_GET['act'] == 'delete') {
    $idpel_delete = trim($_GET['idpel'] ?? '');

    if (!empty($idpel_delete)) {
        try {
            $stmt_del = $conn->prepare("DELETE FROM kategorisasi_risiko WHERE Idpel = ?");
            $stmt_del->execute([$idpel_delete]);

            if ($stmt_del->rowCount() > 0) {
                $pesan_sukses = "Data survey untuk IdPel <strong>" . htmlspecialchars($idpel_delete) . "</strong> berhasil dihapus!";
            } else {
                $pesan_error = "Data tidak ditemukan atau gagal dihapus.";
            }
        } catch (PDOException $e) {
            $pesan_error = "Gagal menghapus data: " . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------
// 2. PARAMETER FILTER, PENCARIAN & PAGINASI
// ---------------------------------------------------------
$keyword   = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$posisi_sr = isset($_GET['posisi_sr']) && in_array($_GET['posisi_sr'], ['0', '1']) ? $_GET['posisi_sr'] : '';
$limit     = isset($_GET['limit']) && in_array((int)$_GET['limit'], [10, 25, 50, 100]) ? (int)$_GET['limit'] : 10;
$page      = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset    = ($page - 1) * $limit;

$where_clauses = [];
$params = [];

// Filter keyword pencarian (Optimasi Index Wildcard Kanan)
if (!empty($keyword)) {
    if (ctype_digit($keyword) && strlen($keyword) >= 11) {
        $where_clauses[] = "k.Idpel = :kw_exact";
        $params[':kw_exact'] = $keyword;
    } else {
        $where_clauses[] = "(k.Idpel LIKE :kw OR d.NamaPelanggan LIKE :kw OR k.UnitUp LIKE :kw)";
        $params[':kw'] = $keyword . '%';
    }
}

// Filter Posisi SR (0 = Mandiri, 1 = Terhubung)
if ($posisi_sr !== '') {
    $where_clauses[] = "k.PosisiSR = :posisi_sr";
    $params[':posisi_sr'] = $posisi_sr;
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// ---------------------------------------------------------
// 3. HITUNG TOTAL DATA (CEPAT)
// ---------------------------------------------------------
try {
    $query_count = "SELECT COUNT(*) 
                    FROM kategorisasi_risiko k 
                    LEFT JOIN dil d ON k.Idpel = d.Idpel" . $where_sql;
    $stmt_count = $conn->prepare($query_count);
    $stmt_count->execute($params);
    $total_data = (int) $stmt_count->fetchColumn();
} catch (PDOException $e) {
    $total_data = 0;
    $pesan_error = "Gagal menghitung data: " . $e->getMessage();
}

$total_pages = ceil($total_data / $limit);
if ($total_pages < 1) $total_pages = 1;

// ---------------------------------------------------------
// 4. AMBIL DATA HASIL SURVEY (PAGINATED)
// ---------------------------------------------------------
try {
    $query_tampil = "SELECT k.Idpel, d.NamaPelanggan, k.PosisiSR, k.UnitUp, k.UnitAp, k.UnitUpi 
                     FROM kategorisasi_risiko k 
                     LEFT JOIN dil d ON k.Idpel = d.Idpel" 
                     . $where_sql . 
                     " ORDER BY k.Idpel ASC LIMIT :limit OFFSET :offset";

    $stmt_tampil = $conn->prepare($query_tampil);

    foreach ($params as $key => $val) {
        $stmt_tampil->bindValue($key, $val, PDO::PARAM_STR);
    }

    $stmt_tampil->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_tampil->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt_tampil->execute();
    $data_hasil = $stmt_tampil->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data_hasil = [];
    $pesan_error = "Gagal mengambil data survey: " . $e->getMessage();
}

// ---------------------------------------------------------
// 5. PANGGIL TAMPILAN FRONTEND
// ---------------------------------------------------------
include '../../templates/perencanaan/hasil_survey.php';
?>