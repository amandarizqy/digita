<?php
// modules/perencanaan/hasil_risiko.php
// Monitoring: hasil klasifikasi level risiko (read-only).

$pesan_error = "";
$periode     = pr_periode();

// 1. Filter & paginasi (parameter `hal`; `page` dipakai router utama untuk nama modul)
$keyword      = trim($_GET['keyword'] ?? '');
$prioritas    = trim($_GET['prioritas'] ?? '');
$posisi_sr    = trim($_GET['posisi_sr'] ?? '');
$level_dampak = trim($_GET['level_dampak'] ?? '');
$tingkat      = in_array($_GET['tingkat'] ?? '', ['tinggi', 'sedang', 'rendah'], true) ? $_GET['tingkat'] : '';

$rentang_tingkat = ['tinggi' => [7, 9], 'sedang' => [4, 6], 'rendah' => [1, 3]];

$limit = 15;
$hal   = max(1, (int) ($_GET['hal'] ?? 1));

$list_hasil = [];
$total_rows = 0;
$per_skala  = array_fill(1, 9, 0);

try {
    // 2. Ringkasan per skala (dipakai KPI & pill tingkat)
    $st = $conn->prepare("SELECT SkalaPrioritas, COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND SkalaPrioritas BETWEEN 1 AND 9 GROUP BY SkalaPrioritas");
    $st->execute([':p' => $periode]);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$n, $j]) {
        $per_skala[(int) $n] = (int) $j;
    }

    // 3. Kondisi filter
    $where  = ["k.Periode = :periode", "k.SkalaPrioritas IS NOT NULL AND k.SkalaPrioritas != 0"];
    $params = [':periode' => $periode];

    if ($keyword !== '') {
        $where[] = "(d.Idpel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1'] = $params[':kw2'] = '%' . $keyword . '%';
    }
    if ($prioritas !== '' && ctype_digit($prioritas)) {
        $where[] = "k.SkalaPrioritas = :prioritas";
        $params[':prioritas'] = (int) $prioritas;
    }
    if ($tingkat !== '') {
        $where[] = "k.SkalaPrioritas BETWEEN :t_min AND :t_max";
        [$params[':t_min'], $params[':t_max']] = $rentang_tingkat[$tingkat];
    }
    if ($posisi_sr === '0' || $posisi_sr === '1') {
        $where[] = "k.PosisiSR = :posisi_sr";
        $params[':posisi_sr'] = $posisi_sr;
    }
    if (in_array($level_dampak, ['SANGAT RENDAH', 'MODERAT', 'SANGAT TINGGI'], true)) {
        $where[] = "k.LevelDampak = :level_dampak";
        $params[':level_dampak'] = $level_dampak;
    }
    $where_sql = " WHERE " . implode(' AND ', $where);

    // 4. Total & data
    $st = $conn->prepare("SELECT COUNT(*) FROM dil d INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel $where_sql");
    $st->execute($params);
    $total_rows = (int) $st->fetchColumn();
    $hal        = min($hal, max(1, (int) ceil($total_rows / $limit)));
    $offset     = ($hal - 1) * $limit;

    $st = $conn->prepare("
        SELECT d.Idpel, d.NamaPelanggan, k.Periode, k.PosisiSR, k.LevelKeterlambatan, k.LevelKepentingan,
               k.LevelKemungkinan, k.LevelDampak, k.Kuadran, k.SkalaPrioritas
        FROM dil d
        INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel
        $where_sql
        ORDER BY k.SkalaPrioritas DESC, d.Idpel ASC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $key => $val) {
        $st->bindValue($key, $val);
    }
    $st->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $list_hasil = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Gagal memuat hasil evaluasi risiko: " . $e->getMessage();
    $offset = 0;
}

$total_p_tinggi = $per_skala[7] + $per_skala[8] + $per_skala[9];
$total_p_sedang = $per_skala[4] + $per_skala[5] + $per_skala[6];
$total_p_rendah = $per_skala[1] + $per_skala[2] + $per_skala[3];

include __DIR__ . '/../../templates/perencanaan/hasil_risiko.php';
