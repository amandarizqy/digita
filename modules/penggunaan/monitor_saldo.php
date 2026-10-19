<?php
// modules/penggunaan/monitor_saldo.php  -  Monitoring Saldo (tabel `dpp`), hanya baca.
// Wilayah data mengikuti session akun: UPI, dan AP / UP bila akun terikat ke AP / UP.
// Fitur: ringkasan, cari IdPel / nama, filter ThBlRek, paginasi.

$pesan_error = '';

// ---- unit akun (session; cadangan master_pengguna) ----
$sUpi = (string) ($_SESSION['UnitUpi'] ?? '');
$sAp  = (string) ($_SESSION['UnitAp']  ?? '');
$sUp  = (string) ($_SESSION['UnitUp']  ?? '');
if ($sUpi === '' && !empty($_SESSION['NamaAkun'])) {
    try {
        $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $st->execute([$_SESSION['NamaAkun']]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) { $sUpi = (string) $u['UnitUpi']; $sAp = (string) $u['UnitAp']; $sUp = (string) $u['UnitUp']; }
    } catch (PDOException $e) { /* ditangani di bawah */ }
}
$unit_siap = $sUpi !== '';

// ---- filter ----
$keyword = trim($_GET['keyword'] ?? '');
$bulan_f = preg_match('/^\d{4,8}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';   // awalan: 2025 / 202501 / 20250115
$limit   = (int) ($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 10;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$data = []; $total_data = 0; $ringkas = ['baris' => 0, 'pelanggan' => 0, 'rp' => 0];
$label_wilayah = $sUpi;

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data saldo tidak dapat ditampilkan.';
} else {
    try {
        $one = function (string $sql, string $kode) use ($conn) { $st = $conn->prepare($sql); $st->execute([$kode]); return $st->fetchColumn() ?: $kode; };
        $label_wilayah = $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUpi) FROM master_upi WHERE UnitUpi = ?", $sUpi)
            . ($sAp !== '' ? ' › ' . $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitAp) FROM master_ap WHERE UnitAp = ?", $sAp) : '')
            . ($sUp !== '' ? ' › ' . $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUp) FROM master_up WHERE UnitUp = ?", $sUp) : '');

        $w = ['d.KodeUpi = :upi']; $p = [':upi' => $sUpi];
        if ($sAp !== '') { $w[] = 'd.KodeAp = :ap'; $p[':ap'] = $sAp; }
        if ($sUp !== '') { $w[] = 'd.KodeUp = :up'; $p[':up'] = $sUp; }
        $scope = implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(*), COUNT(DISTINCT d.IdPel), COALESCE(SUM(d.RpTag),0) FROM dpp d WHERE $scope");
        $st->execute($p);
        [$ringkas['baris'], $ringkas['pelanggan'], $ringkas['rp']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

        $w2 = [$scope]; $p2 = $p;
        if ($keyword !== '') { $w2[] = '(d.IdPel LIKE :k1 OR l.NamaPelanggan LIKE :k2)'; $p2[':k1'] = $p2[':k2'] = '%' . $keyword . '%'; }
        if ($bulan_f !== '') { $w2[] = 'd.ThBlRek LIKE :bl'; $p2[':bl'] = $bulan_f . '%'; }
        $where = 'WHERE ' . implode(' AND ', $w2);

        $st = $conn->prepare("SELECT COUNT(*) FROM dpp d LEFT JOIN dil l ON l.IdPel = d.IdPel $where");
        $st->execute($p2);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT d.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, d.ThBlRek, d.RpTag, d.KodeUp, d.WaktuData
                              FROM dpp d LEFT JOIN dil l ON l.IdPel = d.IdPel $where
                              ORDER BY d.WaktuData DESC, d.IdPel, d.ThBlRek LIMIT :lim OFFSET :off");
        foreach ($p2 as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat data saldo: ' . pg_e($e->getMessage());
    }
}

$boleh_input = pga_boleh('input_saldo');

include __DIR__ . '/../../templates/penggunaan/monitor_saldo.php';