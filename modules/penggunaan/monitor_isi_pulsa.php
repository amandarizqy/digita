<?php
// modules/penggunaan/monitor_isi_pulsa.php  -  Monitoring Isi Pulsa (tabel `transaksi_pulsa`, hanya baca)
// Wilayah  : mengikuti kode unit session akun. Akun yang TIDAK punya AP / UP di session (mis. tingkat UPI) harus memilih filter unit
//            lebih dulu: Unit AP wajib (data baru tampil setelah dipilih), Unit UP opsional (kosong = semua UP di AP itu).
//            Pilihan unit dibatasi ke bawah unit akun (master_ap di bawah UPI, master_up di bawah AP).
// Periode  : data besar -> selalu satu tahun (TglTrx) dan, bila dipilih, satu bulan. Nomor SIM dari master_nomor.

$pesan_error = '';
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

// ---- langkah 1: filter unit (session + pilihan GET) ----
$sel_ap = $u['ap'] !== '' ? $u['ap'] : trim((string) ($_GET['unit_ap'] ?? ''));
$sel_up = $u['up'] !== '' ? $u['up'] : trim((string) ($_GET['unit_up'] ?? ''));
$opsi_ap = []; $opsi_up = [];
$bagian = explode(' › ', (string) $u['label']);
$nama_upi = $bagian[0] ?? $u['upi'];
$nama_ap  = $u['ap'] !== '' ? ($bagian[1] ?? $u['ap']) : '';
$nama_up  = $u['up'] !== '' ? ($bagian[2] ?? $u['up']) : '';

if ($unit_siap) {
    try {
        if ($u['ap'] === '') {   // pilih AP di bawah UPI akun
            $st = $conn->prepare("SELECT UnitAp, COALESCE(NULLIF(SingkatanNama,''), UnitAp) FROM master_ap WHERE UnitUpi = ? AND StatusData = 'AKTIF' ORDER BY UnitAp");
            $st->execute([$u['upi']]);
            $opsi_ap = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            if (isset($opsi_ap[$sel_ap])) $nama_ap = (string) $opsi_ap[$sel_ap]; else { $sel_ap = ''; $sel_up = ''; }
        }
        if ($u['up'] === '' && $sel_ap !== '') {   // pilih UP (opsional) di bawah AP terpilih
            $st = $conn->prepare("SELECT UnitUp, COALESCE(NULLIF(SingkatanNama,''), UnitUp) FROM master_up WHERE UnitAp = ? AND StatusData = 'AKTIF' ORDER BY UnitUp");
            $st->execute([$sel_ap]);
            $opsi_up = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            if (isset($opsi_up[$sel_up])) $nama_up = (string) $opsi_up[$sel_up]; else $sel_up = '';
        }
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat daftar unit: ' . pg_e($e->getMessage());
    }
}
$perlu_filter  = $unit_siap && $sel_ap === '';                 // akun tanpa AP: pilih AP dulu
$u_eff         = ['upi' => $u['upi'], 'ap' => $sel_ap, 'up' => $sel_up];   // wilayah efektif untuk semua query
$label_wilayah = $nama_upi . ($nama_ap !== '' ? ' › ' . $nama_ap : '') . ($nama_up !== '' ? ' › ' . $nama_up : '');
$par_unit      = ['unit_ap' => $u['ap'] === '' ? $sel_ap : '', 'unit_up' => $u['up'] === '' ? $sel_up : ''];   // ikut di semua tautan/form

// ---- filter periode & daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['AKTIF', 'TIDAK'], true) ? $_GET['status'] : '';
$bulan_f = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$daftar_tahun = []; $tahun = '';
$data = []; $total_data = 0;
$ringkas = ['trx' => 0, 'isi' => 0, 'sim' => 0];

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat ditampilkan.';
} elseif (!$perlu_filter && !$pesan_error) {
    try {
        // daftar tahun yang punya transaksi di wilayah efektif (cache session 5 menit; hasil KOSONG tidak di-cache)
        $ck = 'pg_thn_pulsa_' . md5($u_eff['upi'] . '|' . $u_eff['ap'] . '|' . $u_eff['up']);
        if (!empty($_SESSION[$ck]['v']) && $_SESSION[$ck]['t'] > time() - 300) {
            $daftar_tahun = $_SESSION[$ck]['v'];
        } else {
            $p0 = []; $sc0 = pg_scope($u_eff, 't', 'Kode', $p0);
            $st = $conn->prepare("SELECT DISTINCT YEAR(t.TglTrx) FROM transaksi_pulsa t WHERE t.TglTrx IS NOT NULL AND $sc0");
            $st->execute($p0);
            $daftar_tahun = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
            rsort($daftar_tahun);
            if ($daftar_tahun) $_SESSION[$ck] = ['t' => time(), 'v' => $daftar_tahun]; else unset($_SESSION[$ck]);
        }
        $tahun = (string) ($_GET['tahun'] ?? '');
        if (!in_array($tahun, $daftar_tahun, true)) $tahun = $daftar_tahun[0] ?? '';

        if ($tahun !== '') {
            // rentang tanggal sargable (bukan fungsi pada kolom)
            $awal  = $bulan_f !== '' ? "$tahun-$bulan_f-01" : "$tahun-01-01";
            $akhir = $bulan_f !== '' ? date('Y-m-d', strtotime("$awal +1 month")) : ((int) $tahun + 1) . '-01-01';

            $p = [':a' => $awal, ':b' => $akhir]; $scope = pg_scope($u_eff, 't', 'Kode', $p);
            $w = ['t.TglTrx >= :a', 't.TglTrx < :b', $scope];
            if ($status !== '') { $w[] = 't.StatusData = :st'; $p[':st'] = $status; }

            // ringkasan (periode terpilih, wilayah efektif)
            $st = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(t.IsiKredit),0), COUNT(DISTINCT t.SimId) FROM transaksi_pulsa t WHERE " . implode(' AND ', $w));
            $st->execute($p);
            [$ringkas['trx'], $ringkas['isi'], $ringkas['sim']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            if ($keyword !== '') {
                $w[] = '(t.NoTrx LIKE :k1 OR t.SimId LIKE :k2 OR m.NomorAkun LIKE :k3 OR t.NamaAkun LIKE :k4)';
                foreach (['k1', 'k2', 'k3', 'k4'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            $from = "FROM transaksi_pulsa t LEFT JOIN master_nomor m ON m.SimId = t.SimId WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT t.NoTrx, t.TglTrx, t.SimId, m.NomorAkun, t.IsiKredit, t.JumlahKredit, t.TglTempo, t.NamaAkun, t.KodeUp, t.StatusData, t.WaktuData
                                  $from ORDER BY t.TglTrx DESC, t.NoTrx DESC LIMIT :lim OFFSET :off");
            foreach ($p as $k => $v) $st->bindValue($k, $v);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
            $st->execute();
            $data = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat data isi pulsa: ' . pg_e($e->getMessage());
    }
}

$boleh_input = pga_boleh('isi_pulsa');

include __DIR__ . '/../../templates/penggunaan/monitor_isi_pulsa.php';