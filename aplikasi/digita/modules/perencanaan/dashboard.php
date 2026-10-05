<?php
// modules/perencanaan/dashboard.php
// Data untuk halaman Ringkasan Perencanaan (alur kerja + sebaran prioritas).

$periode = pr_periode();
$p       = [':p' => $periode];

$total_dil = (int) pr_scalar($conn, "SELECT COUNT(*) FROM dil");

// Kemajuan tiap tahap alur kerja
$riwayat_pelanggan = (int) pr_scalar($conn, "SELECT COUNT(DISTINCT IdPel) FROM pelunasan_ap2t WHERE ThBlRek LIKE CONCAT(:p, '%')", $p);
$riwayat_baris     = (int) pr_scalar($conn, "SELECT COUNT(*) FROM pelunasan_ap2t WHERE ThBlRek LIKE CONCAT(:p, '%')", $p);
$kepentingan_ok    = (int) pr_scalar($conn, "SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND LevelKepentingan IN ('RENDAH','MODERAT','TINGGI')", $p);
$sr_ok             = (int) pr_scalar($conn, "SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND PosisiSR IS NOT NULL AND PosisiSR <> ''", $p);
$klasifikasi_ok    = (int) pr_scalar($conn, "SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND SkalaPrioritas BETWEEN 1 AND 9", $p);
$peringkat_ok      = (int) pr_scalar($conn, "SELECT COUNT(*) FROM hasil_prioritas WHERE Periode = :p", $p);

// Sebaran tingkat prioritas
$tier = ['tinggi' => 0, 'sedang' => 0, 'rendah' => 0];
$per_skala = array_fill(1, 9, 0);
try {
    $st = $conn->prepare("SELECT SkalaPrioritas, COUNT(*) FROM kategorisasi_risiko WHERE Periode = :p AND SkalaPrioritas BETWEEN 1 AND 9 GROUP BY SkalaPrioritas");
    $st->execute($p);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$n, $j]) {
        $per_skala[(int) $n] = (int) $j;
    }
} catch (PDOException $e) { /* tabel belum ada */ }
foreach ($per_skala as $n => $j) {
    if ($n >= 7)      $tier['tinggi'] += $j;
    elseif ($n >= 4)  $tier['sedang'] += $j;
    else              $tier['rendah'] += $j;
}

// Skala yang hasil peringkatnya tidak sinkron dengan hasil klasifikasi
$skala_usang = 0;
$skala_belum = 0;
try {
    $st = $conn->prepare("SELECT SkalaPrioritas, COUNT(*) FROM hasil_prioritas WHERE Periode = :p GROUP BY SkalaPrioritas");
    $st->execute($p);
    $ranked = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$n, $j]) {
        $ranked[(int) $n] = (int) $j;
    }
    foreach ($per_skala as $n => $j) {
        if ($j === 0) continue;
        if (empty($ranked[$n]))        $skala_belum++;
        elseif ($ranked[$n] !== $j)    $skala_usang++;
    }
} catch (PDOException $e) {
    $skala_belum = count(array_filter($per_skala));
}

$belum_sr          = max(0, $total_dil - $sr_ok);
$belum_kepentingan = max(0, $total_dil - $kepentingan_ok);
$belum_klasifikasi = max(0, $total_dil - $klasifikasi_ok);

include __DIR__ . '/../../templates/perencanaan/index.php';
