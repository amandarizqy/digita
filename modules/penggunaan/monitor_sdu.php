<?php
// modules/penggunaan/monitor_sdu.php  -  Monitoring SDU (hanya baca)
// Tab: Pemutusan  (trx_order_putus_sdu + trx_tindakan_putus_sdu)  |  Penyalaan  (trx_order_nyala_sdu + trx_tindakan_nyala_sdu)
// Status tiket (turunan, tanpa kolom khusus):
//   ANTREAN  = AKTIF, belum punya Sesi, belum ada tindakan      DIPROSES = AKTIF, sudah punya Sesi, belum ada tindakan
//   PADAM/NYALA, GAGAL, LUNAS = nilai JenisTindakan di tabel tindakan                  NONAKTIF = StatusData TIDAK (tanpa tindakan)
// Wilayah: mengikuti unit session akun (pg_scope()). Halaman berdiri sendiri; tampilan: templates/penggunaan/monitor_sdu.php

// ---- PENGATURAN ----
$halaman = 'monitor_sdu';
$judul   = 'Monitoring SDU';
$konfig  = [
    'putus' => ['tabel' => 'trx_order_putus_sdu', 'pk' => 'TiketPutusSdu', 'tindakan' => 'trx_tindakan_putus_sdu',
                'berhasil' => 'PADAM', 'label' => 'Pemutusan', 'ikon' => 'bi-plug'],
    'nyala' => ['tabel' => 'trx_order_nyala_sdu', 'pk' => 'TiketNyalaSdu', 'tindakan' => 'trx_tindakan_nyala_sdu',
                'berhasil' => 'NYALA', 'label' => 'Penyalaan', 'ikon' => 'bi-lightning-charge',
                'fk' => 'TiketPutusSdu', 'putus_tabel' => 'trx_order_putus_sdu'],
];

$pesan_error = '';
foreach ($konfig as $cfg) foreach (['tabel', 'pk', 'tindakan', 'fk', 'putus_tabel'] as $kunci)
    if (isset($cfg[$kunci]) && !preg_match('/^\w+$/', $cfg[$kunci])) throw new RuntimeException('Nama tabel/kolom tidak valid.');

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

// ---- parameter ----
$tab = (string) ($_GET['tab'] ?? 'putus');
if (!isset($konfig[$tab])) $tab = 'putus';
$c     = $konfig[$tab];
$nyala = $tab === 'nyala';
$tb = $c['tabel']; $pk = $c['pk']; $td = $c['tindakan'];

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$status  = (string) ($_GET['status'] ?? '');
$jenis   = strtoupper((string) ($_GET['jenis'] ?? ''));
$dari    = (string) ($_GET['dari'] ?? '');
$sampai  = (string) ($_GET['sampai'] ?? '');
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));
$tgl_ok  = fn(string $s): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4));
if (!$tgl_ok($dari))   $dari = '';
if (!$tgl_ok($sampai)) $sampai = '';
if (!in_array($jenis, ['PREVENTIF', 'KOREKTIF'], true)) $jenis = '';

// daftar status & kondisi SQL-nya (alias: t = tiket, a = tindakan)
$daftar_status = ['ANTREAN', 'DIPROSES', $c['berhasil'], 'GAGAL', 'LUNAS', 'NONAKTIF'];
if (!in_array($status, $daftar_status, true)) $status = '';
$kond = [
    'ANTREAN'      => "a.`$pk` IS NULL AND t.StatusData = 'AKTIF' AND (t.Sesi IS NULL OR t.Sesi = '')",
    'DIPROSES'     => "a.`$pk` IS NULL AND t.StatusData = 'AKTIF' AND t.Sesi IS NOT NULL AND t.Sesi <> ''",
    $c['berhasil'] => "a.JenisTindakan = " . $conn->quote($c['berhasil']),
    'GAGAL'        => "a.JenisTindakan = 'GAGAL'",
    'LUNAS'        => "a.JenisTindakan = 'LUNAS'",
    'NONAKTIF'     => "a.`$pk` IS NULL AND t.StatusData = 'TIDAK'",
];
/** Status satu baris hasil query (sama dengan kondisi di atas). */
$status_baris = function (array $r): string {
    if ($r['JenisTindakan'] !== null && $r['JenisTindakan'] !== '') return (string) $r['JenisTindakan'];
    if ($r['StatusData'] === 'TIDAK') return 'NONAKTIF';
    return trim((string) $r['Sesi']) !== '' ? 'DIPROSES' : 'ANTREAN';
};

$data = []; $total_data = 0; $kartu = []; $waktu_data = date('H:i:s');

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat ditampilkan.';
} else {
    try {
        $p = []; $scope = pg_scope($u, 't', 'Unit', $p);
        $dasar_ringan = "FROM `$tb` t LEFT JOIN `$td` a ON a.`$pk` = t.`$pk`";

        // ---- ringkasan (seluruh wilayah akun) ----
        $sel = ['COUNT(*)'];
        foreach ($daftar_status as $s) $sel[] = "COALESCE(SUM({$kond[$s]}), 0)";
        $st = $conn->prepare("SELECT " . implode(', ', $sel) . " $dasar_ringan WHERE $scope");
        $st->execute($p);
        $n = array_map('intval', $st->fetch(PDO::FETCH_NUM));
        $kartu = [['Total Tiket', $n[0], 'text-dark', '', 'bi-collection']];
        $ikon_status = ['ANTREAN' => 'bi-hourglass-split', 'DIPROSES' => 'bi-person-workspace', 'GAGAL' => 'bi-x-circle', 'LUNAS' => 'bi-cash-coin', 'NONAKTIF' => 'bi-slash-circle'];
        $warna_kartu = ['ANTREAN' => 'text-secondary', 'DIPROSES' => 'text-primary', 'GAGAL' => 'text-danger', 'LUNAS' => 'text-warning'];
        foreach ($daftar_status as $i => $s) {
            if ($s === 'NONAKTIF') continue;                               // jarang dipakai: tampil di filter & tabel saja
            $kartu[] = [ucfirst(strtolower($s)), $n[$i + 1], $warna_kartu[$s] ?? 'text-success', $s, $ikon_status[$s] ?? 'bi-check-circle'];
        }

        // ---- data tabel (dengan filter) ----
        $notul = $nyala ? 'pt.NoTul' : 't.NoTul';
        $dasar = $dasar_ringan
               . ($nyala ? " LEFT JOIN `{$c['putus_tabel']}` pt ON pt.`{$c['fk']}` = t.`{$c['fk']}`" : '')
               . " LEFT JOIN dil l ON l.IdPel = t.IdPel";
        $w = [$scope];
        if ($status !== '') $w[] = $kond[$status];
        if ($jenis !== '')  { $w[] = 't.JenisTiket = :jenis'; $p[':jenis'] = $jenis; }
        if ($dari !== '')   { $w[] = 't.WaktuData >= :dari';  $p[':dari'] = $dari . ' 00:00:00'; }
        if ($sampai !== '') { $w[] = 't.WaktuData < DATE_ADD(:sampai, INTERVAL 1 DAY)'; $p[':sampai'] = $sampai; }
        if ($keyword !== '') {
            $kolom_cari = ["t.`$pk`", 't.IdPel', 'l.Nama', $notul, 't.Sesi', 't.NamaAkun', 'a.NamaAkun', 't.NomorMeter', 't.NomorHp'];
            if ($nyala) $kolom_cari[] = "t.`{$c['fk']}`";
            $or = [];
            foreach ($kolom_cari as $i => $kol) { $or[] = "$kol LIKE :k$i"; $p[":k$i"] = '%' . $keyword . '%'; }
            $w[] = '(' . implode(' OR ', $or) . ')';
        }
        $dari_sql = $dasar . ' WHERE ' . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(*) $dari_sql");
        $st->execute($p);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT t.`$pk` AS Kode, " . ($nyala ? "t.`{$c['fk']}` AS TiketPutus, " : "NULL AS TiketPutus, ") . "t.JenisTiket, $notul AS NoTul, t.IdPel,
                                     NULLIF(l.Nama, '') AS NamaPelanggan, t.NomorMeter, t.NomorHp, t.ThblRek AS Periode, t.UnitUp, t.Sesi, t.NamaAkun AS Pengambil,
                                     t.StatusData, t.WaktuData, a.JenisTindakan, a.NamaAkun AS Penindak, a.WaktuData AS WaktuTindakan
                              $dari_sql ORDER BY t.WaktuData DESC, t.`$pk` DESC LIMIT :lim OFFSET :off");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as &$r) $r['Status'] = $status_baris($r);
        unset($r);
    } catch (Throwable $e) {
        $pesan_error = 'Gagal memuat data: ' . pg_e($e->getMessage())
                     . ' (periksa nama tabel/kolom, hak SELECT akun database, dan kesamaan collation bila error "Illegal mix of collations").';
    }
}

include __DIR__ . '/../../templates/penggunaan/monitor_sdu.php';