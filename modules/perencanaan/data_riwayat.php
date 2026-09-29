<?php
// modules/perencanaan/data_riwayat.php

$pesan_sukses = "";
$pesan_error = "";

// ---------------------------------------------------------
// 1. PROSES HAPUS DATA
// ---------------------------------------------------------
if (isset($_GET['act']) && $_GET['act'] == 'delete') {
    $idpel_delete = $_GET['idpel'] ?? '';
    $thbl_delete  = $_GET['thblrek'] ?? '';

    if (!empty($idpel_delete) && !empty($thbl_delete)) {
        try {
            $stmt_del = $conn->prepare("DELETE FROM pelunasan_ap2t WHERE IdPel = ? AND ThBlRek = ?");
            $stmt_del->execute([$idpel_delete, $thbl_delete]);

            if ($stmt_del->rowCount() > 0) {
                $pesan_sukses = "Data pelanggan $idpel_delete ($thbl_delete) berhasil dihapus!";
            } else {
                $pesan_error = "Data tidak ditemukan atau gagal dihapus.";
            }
        } catch (PDOException $e) {
            $pesan_error = "Gagal menghapus data: " . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------
// 2. TANGKAP PARAMETER PENCARIAN & PAGINASI
// ---------------------------------------------------------
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$limit   = isset($_GET['limit']) && in_array((int)$_GET['limit'], [10, 25, 50, 100]) ? (int)$_GET['limit'] : 10;
$page    = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset  = ($page - 1) * $limit;

$where_sql = "";
$params = [];

if (!empty($keyword)) {
    // Jika input berupa angka 12 digit (IdPel persis), gunakan pencarian langsung (Sangat Cepat)
    if (ctype_digit($keyword) && strlen($keyword) >= 11) {
        $where_sql = " WHERE IdPel = :kw_exact";
        $params[':kw_exact'] = $keyword;
    } else {
        // Gunakan Wildcard Kanan (keyword%) agar Index pada IdPel/UnitUp tetap berfungsi
        $where_sql = " WHERE (IdPel LIKE :kw OR ThBlRek LIKE :kw OR UnitUp LIKE :kw OR UnitAp LIKE :kw)";
        $params[':kw'] = $keyword . '%'; 
    }
}

// ---------------------------------------------------------
// 3. HITUNG TOTAL DATA (PAGINASI)
// ---------------------------------------------------------
try {
    $query_count = "SELECT COUNT(*) FROM pelunasan_ap2t" . $where_sql;
    $stmt_count  = $conn->prepare($query_count);
    $stmt_count->execute($params);
    $total_data  = (int) $stmt_count->fetchColumn();
} catch (PDOException $e) {
    $total_data  = 0;
}

$total_pages = ceil($total_data / $limit);
if ($total_pages < 1) $total_pages = 1;

// ---------------------------------------------------------
// 4. AMBIL DATA (AMBIL KOLOM YANG DIPERLUKAN SAJA)
// ---------------------------------------------------------
try {
    // Ganti SELECT * dengan kolom spesifik yang benar-benar ditampilkan di HTML
    $query_tampil = "SELECT IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi, WaktuData 
                     FROM pelunasan_ap2t" 
                     . $where_sql . 
                     " ORDER BY WaktuData DESC LIMIT :limit OFFSET :offset";
                     
    $stmt_tampil = $conn->prepare($query_tampil);

    foreach ($params as $key => $val) {
        $stmt_tampil->bindValue($key, $val, PDO::PARAM_STR);
    }

    $stmt_tampil->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_tampil->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt_tampil->execute();
    $data_riwayat = $stmt_tampil->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data_riwayat = [];
    $pesan_error  = "Gagal mengambil data: " . $e->getMessage();
}

// ---------------------------------------------------------
// 5. PANGGIL TAMPILAN FRONTEND
// ---------------------------------------------------------
include '../../templates/perencanaan/data_riwayat.php';
?>