<?php
// modules/penggunaan/monitor_tul.php  -  Monitoring TUL VI-01 (tabel `tul_601`).
// Data dibatasi satu tahun (berdasarkan WaktuCetak) dan, bila dipilih, satu bulan.
// Wilayah WAJIB mengikuti kode unit session akun: UnitUpi selalu, + UnitAp / UnitUp bila akun terikat (lihat pg_scope()).
// Akun yang berhak Upload TUL dapat mengaktifkan / menonaktifkan sebuah TUL (StatusData); yang lain hanya melihat.

$pesan_sukses = $pesan_error = '';

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u           = pg_unit_akun($conn);
$unit_siap   = $u['upi'] !== '';
$boleh_ubah  = pga_boleh('upload_tul');

// ---- filter daftar ----
$keyword  = trim($_GET['keyword'] ?? '');
$bulan_f  = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$status_f = in_array($_GET['status'] ?? '', ['AKTIF', 'TIDAK'], true) ? $_GET['status'] : '';
$limit    = (int) ($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 10;
$hal      = max(1, (int) ($_GET['hal'] ?? 1));

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// UBAH STATUS (POST) - hanya TUL di wilayah akun
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notul = trim((string) ($_POST['notul'] ?? ''));
    $baru  = ($_POST['status_baru'] ?? '') === 'AKTIF' ? 'AKTIF' : 'TIDAK';

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$boleh_ubah) {
        $pesan_error = 'Anda tidak memiliki wewenang untuk mengubah data TUL.';
    } elseif (!$unit_siap || $notul === '') {
        $pesan_error = 'Data tidak valid.';
    } else {
        try {
            $p = [':st' => $baru, ':no' => $notul]; $sc = pg_scope($u, 't', 'Unit', $p);
            $st = $conn->prepare("UPDATE tul_601 t SET t.StatusData = :st, t.WaktuData = NOW() WHERE t.NoTul = :no AND $sc");
            $st->execute($p);
            $pesan_sukses = 'TUL <strong>' . pg_e($notul) . '</strong> ' . ($baru === 'AKTIF' ? 'diaktifkan.' : 'dinonaktifkan.');
        } catch (PDOException $e) {
            $pesan_error = 'Gagal mengubah status: ' . pg_e($e->getMessage());
        }
    }
    if (!headers_sent()) {   // Post-Redirect-Get: kembali ke daftar dengan filter yang sama
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('monitor_tul', [
            'tahun' => $_POST['tahun'] ?? '', 'bulan' => $_POST['bulan'] ?? '', 'status' => $_POST['filter_status'] ?? '', 'keyword' => $_POST['keyword'] ?? '',
            'limit' => ($_POST['limit'] ?? '') == 10 ? '' : ($_POST['limit'] ?? ''), 'hal' => ($_POST['hal'] ?? '') > 1 ? $_POST['hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA
// ---------------------------------------------------------
$daftar_tahun = []; $tahun_n = [];   // [tahun => jumlah TUL]
$tahun = '';
$data = []; $total_data = 0;
$ringkas = ['tul' => 0, 'pelanggan' => 0, 'rp_tag' => 0, 'lembar' => 0, 'aktif' => 0, 'tidak' => 0];

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data TUL tidak dapat ditampilkan.';
} else {
    try {
        $p1 = []; $sc1 = pg_scope($u, 't', 'Unit', $p1);
        $st = $conn->prepare("SELECT YEAR(t.WaktuCetak) AS th, COUNT(*) AS n FROM tul_601 t WHERE $sc1 GROUP BY YEAR(t.WaktuCetak) ORDER BY th DESC");
        $st->execute($p1);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if ((int) $r['th'] > 0) $tahun_n[(string) $r['th']] = (int) $r['n'];
        $daftar_tahun = array_map('strval', array_keys($tahun_n));

        $tahun = (string) ($_GET['tahun'] ?? '');
        if (!in_array($tahun, $daftar_tahun, true)) $tahun = $daftar_tahun[0] ?? '';

        if ($tahun !== '') {
            // rentang WaktuCetak [d1, d2) agar index tetap terpakai
            $d1 = new DateTimeImmutable($tahun . '-' . ($bulan_f ?: '01') . '-01 00:00:00');
            $d2 = $d1->modify($bulan_f ? '+1 month' : '+1 year');
            $p  = [':d1' => $d1->format('Y-m-d H:i:s'), ':d2' => $d2->format('Y-m-d H:i:s')];
            $scope = pg_scope($u, 't', 'Unit', $p);

            // ringkasan (tahun/bulan terpilih, wilayah akun; tidak ikut filter status / kata kunci)
            $st = $conn->prepare("SELECT COUNT(*), COUNT(DISTINCT t.IdPel), COALESCE(SUM(t.RpTag),0), COALESCE(SUM(t.Lembar),0),
                                         COALESCE(SUM(t.StatusData = 'AKTIF'),0), COALESCE(SUM(t.StatusData = 'TIDAK'),0)
                                  FROM tul_601 t WHERE t.WaktuCetak >= :d1 AND t.WaktuCetak < :d2 AND $scope");
            $st->execute($p);
            [$ringkas['tul'], $ringkas['pelanggan'], $ringkas['rp_tag'], $ringkas['lembar'], $ringkas['aktif'], $ringkas['tidak']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            // daftar
            $w = ['t.WaktuCetak >= :d1', 't.WaktuCetak < :d2', $scope]; $p2 = $p;
            if ($status_f !== '') { $w[] = 't.StatusData = :sf'; $p2[':sf'] = $status_f; }
            if ($keyword !== '')  { $w[] = '(t.IdPel LIKE :k1 OR t.NoTul LIKE :k2 OR l.NamaPelanggan LIKE :k3)'; $p2[':k1'] = $p2[':k2'] = $p2[':k3'] = '%' . $keyword . '%'; }
            $where = 'WHERE ' . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*) FROM tul_601 t LEFT JOIN dil l ON l.IdPel = t.IdPel $where");
            $st->execute($p2);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT t.NoTul, t.WaktuCetak, t.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, t.ThBlRekAwal, t.ThBlRekAkhir, t.Lembar, t.RpTag,
                                         t.StandBaca, t.TglBaca, t.NomorPengirim, t.UnitUp, t.StatusData, t.WaktuData
                                  FROM tul_601 t LEFT JOIN dil l ON l.IdPel = t.IdPel $where
                                  ORDER BY t.WaktuCetak DESC, t.NoTul LIMIT :lim OFFSET :off");
            foreach ($p2 as $k => $v) $st->bindValue($k, $v);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
            $st->execute();
            $data = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat data TUL: ' . pg_e($e->getMessage()));
    }
}

$boleh_upload = $boleh_ubah;

include __DIR__ . '/../../templates/penggunaan/monitor_tul.php';
