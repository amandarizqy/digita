<?php
// modules/perencanaan/hasil_kepentingan.php
// Monitoring: hasil Tingkat Kepentingan pelanggan (kolom LevelKepentingan pada kategorisasi_risiko).

$pesan_error = "";
$periode     = pr_periode();

$keyword = trim($_GET['keyword'] ?? '');
$level   = in_array($_GET['level'] ?? '', ['RENDAH', 'MODERAT', 'TINGGI'], true) ? $_GET['level'] : '';
$limit   = in_array((int) ($_GET['limit'] ?? 0), [10, 25, 50, 100], true) ? (int) $_GET['limit'] : 10;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$per_level  = ['RENDAH' => 0, 'MODERAT' => 0, 'TINGGI' => 0];
$total_dil  = (int) pr_scalar($conn, "SELECT COUNT(*) FROM dil");
$list_hasil = [];
$total_rows = 0;
$offset     = 0;

try {
    $st = $conn->prepare("SELECT LevelKepentingan, COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND LevelKepentingan IN ('RENDAH','MODERAT','TINGGI') GROUP BY LevelKepentingan");
    $st->execute([':p' => $periode]);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$lv, $j]) {
        $per_level[$lv] = (int) $j;
    }

    $where  = ["k.Periode = :periode", "k.LevelKepentingan IN ('RENDAH','MODERAT','TINGGI')"];
    $params = [':periode' => $periode];
    if ($keyword !== '') {
        $where[] = "(k.IdPel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1'] = $params[':kw2'] = '%' . $keyword . '%';
    }
    if ($level !== '') {
        $where[] = "k.LevelKepentingan = :level";
        $params[':level'] = $level;
    }
    $where_sql = ' WHERE ' . implode(' AND ', $where);

    $st = $conn->prepare("SELECT COUNT(*) FROM kategorisasi_risiko k LEFT JOIN dil d ON d.Idpel = k.IdPel $where_sql");
    $st->execute($params);
    $total_rows = (int) $st->fetchColumn();
    $hal        = min($hal, max(1, (int) ceil($total_rows / $limit)));
    $offset     = ($hal - 1) * $limit;

    $st = $conn->prepare("
        SELECT k.IdPel, d.NamaPelanggan, k.LevelKepentingan, k.PosisiSR, k.SkalaPrioritas, k.UnitUp
        FROM kategorisasi_risiko k
        LEFT JOIN dil d ON d.Idpel = k.IdPel
        $where_sql
        ORDER BY FIELD(k.LevelKepentingan, 'TINGGI', 'MODERAT', 'RENDAH'), k.IdPel ASC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $list_hasil = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Gagal memuat hasil tingkat kepentingan: " . $e->getMessage();
}

include __DIR__ . '/../../templates/perencanaan/hasil_kepentingan.php';
