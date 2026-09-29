<?php
// modules/perencanaan/hasil_prioritas.php
// Menampilkan hasil pemeringkatan yang dibuat di modul proses_prioritas.

require_once __DIR__ . '/../../functions/prioritas_helper.php';

$pesan_error     = "";
$tabel_belum_ada = false;
$periode_filter  = '2025';

// ------------------------------------------------------------------
// Filter dari URL
// ------------------------------------------------------------------
$skala_filter = isset($_GET['skala']) ? (int) $_GET['skala'] : 0;   // 0 = semua skala
if ($skala_filter < 0 || $skala_filter > 9) $skala_filter = 0;
$keyword  = trim($_GET['keyword'] ?? '');
$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));

// Ringkasan per skala
$ringkasan = [];
for ($n = 1; $n <= 9; $n++) {
    $ringkasan[$n] = [
        'jumlah_kategori' => 0, 'jumlah_hasil' => 0, 'total_tagihan' => 0.0, 'avg_skor' => 0.0,
        'terakhir' => null, 'oleh' => null, 'metode' => null, 'bobot' => [0, 0, 0], 'usang' => false,
    ];
}
$total_hasil_semua = 0;
$list_hasil        = [];
$total_rows        = 0;
$total_pages       = 1;

try {
    // Jumlah pelanggan per skala di kategorisasi_risiko (untuk deteksi hasil usang)
    $stmt_k = $conn->prepare("
        SELECT SkalaPrioritas, COUNT(*) AS jml
        FROM kategorisasi_risiko
        WHERE Periode = :periode AND SkalaPrioritas BETWEEN 1 AND 9
        GROUP BY SkalaPrioritas
    ");
    $stmt_k->execute([':periode' => $periode_filter]);
    foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ringkasan[(int) $r['SkalaPrioritas']]['jumlah_kategori'] = (int) $r['jml'];
    }

    // Ringkasan hasil per skala
    $stmt_h = $conn->prepare("
        SELECT SkalaPrioritas, COUNT(*) AS jml, SUM(TotalTagihan) AS total, AVG(SkorPrioritas) AS avg_skor,
               MAX(DiprosesPada) AS terakhir, MAX(DiprosesOleh) AS oleh, MAX(MetodePeringkat) AS metode,
               MAX(BobotNominal) AS bn, MAX(BobotTelat) AS bt, MAX(BobotTunggak) AS bk
        FROM hasil_prioritas
        WHERE Periode = :periode
        GROUP BY SkalaPrioritas
    ");
    $stmt_h->execute([':periode' => $periode_filter]);
    foreach ($stmt_h->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $n = (int) $r['SkalaPrioritas'];
        if ($n < 1 || $n > 9) continue;
        $ringkasan[$n]['jumlah_hasil']  = (int) $r['jml'];
        $ringkasan[$n]['total_tagihan'] = (float) $r['total'];
        $ringkasan[$n]['avg_skor']      = (float) $r['avg_skor'];
        $ringkasan[$n]['terakhir']      = $r['terakhir'];
        $ringkasan[$n]['oleh']          = $r['oleh'];
        $ringkasan[$n]['metode']        = $r['metode'];
        $ringkasan[$n]['bobot']         = [(int) $r['bn'], (int) $r['bt'], (int) $r['bk']];
        $total_hasil_semua             += (int) $r['jml'];
    }
    foreach ($ringkasan as $n => &$rg) {
        $rg['usang'] = ($rg['jumlah_hasil'] > 0 && $rg['jumlah_hasil'] !== $rg['jumlah_kategori']);
    }
    unset($rg);

    // Daftar detail (filter + pagination)
    $where  = ["h.Periode = :periode"];
    $params = [':periode' => $periode_filter];

    if ($skala_filter > 0) {
        $where[]           = "h.SkalaPrioritas = :skala";
        $params[':skala']  = $skala_filter;
    }
    if ($keyword !== '') {
        $where[]         = "(h.IdPel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1']  = '%' . $keyword . '%';
        $params[':kw2']  = '%' . $keyword . '%';
    }
    $where_sql = implode(' AND ', $where);

    $stmt_cnt = $conn->prepare("
        SELECT COUNT(*) 
        FROM hasil_prioritas h 
        INNER JOIN dil d ON d.Idpel = h.IdPel 
        WHERE $where_sql
    ");
    $stmt_cnt->execute($params);
    $total_rows  = (int) $stmt_cnt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    $stmt_list = $conn->prepare("
        SELECT h.Peringkat, h.IdPel, d.NamaPelanggan, h.SkalaPrioritas, h.TotalTagihan,
               h.JumlahTelat, h.JumlahLewatBulan, h.SkorPrioritas,
               k.LevelKepentingan, k.LevelKeterlambatan, k.LevelDampak, k.PosisiSR
        FROM hasil_prioritas h
        INNER JOIN dil d ON d.Idpel = h.IdPel
        LEFT JOIN kategorisasi_risiko k ON k.IdPel = h.IdPel AND k.Periode = h.Periode
        WHERE $where_sql
        ORDER BY h.SkalaPrioritas DESC, h.Peringkat ASC, h.IdPel ASC
        LIMIT " . (int) $per_page . " OFFSET " . (int) $offset
    );
    $stmt_list->execute($params);
    $list_hasil = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if ($e->getCode() === '42S02') {
        $tabel_belum_ada = true;
    } else {
        $pesan_error = "Gagal memuat hasil prioritas: " . $e->getMessage();
    }
}

// Helper URL yang mempertahankan filter aktif
$buat_url = function (array $override = []) use ($skala_filter, $keyword) {
    $q = array_merge([
        'module'  => 'perencanaan',
        'action'  => 'hasil_prioritas',
        'skala'   => $skala_filter,
        'keyword' => $keyword,
    ], $override);
    if ($q['keyword'] === '' || $q['keyword'] === null) unset($q['keyword']);
    if (empty($q['skala']))                             unset($q['skala']);
    return '?' . http_build_query($q);
};

include __DIR__ . '/../../templates/perencanaan/hasil_prioritas.php';
?>