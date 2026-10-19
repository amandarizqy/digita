<?php
// modules/penggunaan/monitor_pelunasan.php  -  Monitoring Pelunasan (tabel `pelunasan_ap2t`), hanya baca.
// Data besar -> SELALU dibatasi satu tahun (awalan ThBlRek) dan, bila dipilih, satu bulan.
// Wilayah WAJIB mengikuti kode unit session akun: UnitUpi selalu, + UnitAp / UnitUp bila akun terikat (lihat pg_scope()).
// Tagihan belum lunas = baris dpp (periode yang sama) tanpa pelunasan.

$pesan_error = '';
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

$keyword = trim($_GET['keyword'] ?? '');
$bulan_f = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$limit   = (int) ($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 10;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$daftar_tahun = [];
$tahun        = '';
$data = []; $total_data = 0;
$ringkas = ['lunas' => 0, 'rp_tag' => 0, 'rp_bk' => 0, 'belum' => 0];

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data pelunasan tidak dapat ditampilkan.';
} else {
    try {
        // ---- daftar tahun yang punya data di wilayah akun (pelunasan + tagihan dpp); cache session 5 menit per wilayah ----
        $ck = 'pg_tahun_pl_' . md5($u['upi'] . '|' . $u['ap'] . '|' . $u['up']);
        if (!empty($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 300) {
            $daftar_tahun = $_SESSION[$ck]['v'];
        } else {
            $p1 = []; $sc1 = pg_scope($u, 'p', 'Unit', $p1);
            $st = $conn->prepare("SELECT DISTINCT LEFT(p.ThBlRek, 4) FROM pelunasan_ap2t p WHERE $sc1");
            $st->execute($p1);
            $th = $st->fetchAll(PDO::FETCH_COLUMN);

            $p2 = []; $sc2 = pg_scope($u, 'd', 'Kode', $p2);
            $st = $conn->prepare("SELECT DISTINCT LEFT(d.ThBlRek, 4) FROM dpp d WHERE $sc2");
            $st->execute($p2);
            $th = array_merge($th, $st->fetchAll(PDO::FETCH_COLUMN));

            $daftar_tahun = array_values(array_unique(array_filter(array_map('strval', $th), fn($y) => preg_match('/^\d{4}$/', $y))));
            rsort($daftar_tahun);
            $_SESSION[$ck] = ['t' => time(), 'v' => $daftar_tahun];
        }

        // tahun terpilih: harus ada di daftar; default = tahun terbaru
        $tahun = (string) ($_GET['tahun'] ?? '');
        if (!in_array($tahun, $daftar_tahun, true)) $tahun = $daftar_tahun[0] ?? '';

        if ($tahun !== '') {
            $periode = $tahun . $bulan_f;                    // awalan ThBlRek: YYYY atau YYYYMM
            $like    = $periode . '%';

            // ---- ringkasan (tahun/bulan terpilih, wilayah akun) ----
            $p = [':per' => $like]; $scope = pg_scope($u, 'p', 'Unit', $p);
            $st = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(p.RpTag),0), COALESCE(SUM(p.RpBK),0) FROM pelunasan_ap2t p WHERE p.ThBlRek LIKE :per AND $scope");
            $st->execute($p);
            [$ringkas['lunas'], $ringkas['rp_tag'], $ringkas['rp_bk']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            $pd = [':per' => $like]; $scope_d = pg_scope($u, 'd', 'Kode', $pd);
            $st = $conn->prepare("SELECT COUNT(*) FROM dpp d LEFT JOIN pelunasan_ap2t p ON p.IdPel = d.IdPel AND p.ThBlRek = d.ThBlRek
                                  WHERE d.ThBlRek LIKE :per AND p.IdPel IS NULL AND $scope_d");
            $st->execute($pd);
            $ringkas['belum'] = (int) $st->fetchColumn();

            // ---- daftar ----
            $w = ['p.ThBlRek LIKE :per', $scope]; $p2 = $p;
            if ($keyword !== '') { $w[] = '(p.IdPel LIKE :k1 OR l.NamaPelanggan LIKE :k2)'; $p2[':k1'] = $p2[':k2'] = '%' . $keyword . '%'; }
            $where = 'WHERE ' . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*) FROM pelunasan_ap2t p LEFT JOIN dil l ON l.IdPel = p.IdPel $where");
            $st->execute($p2);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT p.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, p.ThBlRek, p.TglBayar, p.RpTag, p.RpBK, p.UnitUp, p.WaktuData
                                  FROM pelunasan_ap2t p LEFT JOIN dil l ON l.IdPel = p.IdPel $where
                                  ORDER BY p.ThBlRek DESC, p.IdPel LIMIT :lim OFFSET :off");
            foreach ($p2 as $k => $v) $st->bindValue($k, $v);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
            $st->execute();
            $data = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat data pelunasan: ' . pg_e($e->getMessage());
    }
}

$boleh_input = pga_boleh('input_pelunasan');

include __DIR__ . '/../../templates/penggunaan/monitor_pelunasan.php';