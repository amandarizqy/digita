<?php
// modules/penggunaan/input_perintah.php  -  Input Perintah (kirim manual ke perangkat lewat SMS Gateway)
// Sumber (tab) : 'putus' = tabel trx_order_putus, 'nyala' = tabel trx_order_nyala (tiket otomatis dari trigger saldo / pelunasan),
//                'barang' = VIEW `vw_terpasang` (perangkat manual, tanpa tiket). Semua dibatasi wilayah akun (kode unit session, pg_scope()).
//                Nomor tujuan = NomorBarang (pada tiket / view). Tabel tiket HANYA DIBACA; status tiket diubah oleh trigger.
// Perintah  : pilihan tetap di bawah (PLNOFF, PLNON, PLNSTATUS); TIDAK lagi dari tabel baku_outbox.
// Pengiriman: INSERT ke tabel `outbox` database gateway ($db_gateway) dengan Status = 'Reserved';
//             service Python (smsd/gateway_sms.py) mengambil Status 'Reserved', mengirim lewat modem, lalu mengubah
//             Status menjadi 'SendingOK' / 'SendingError'. Balasan perangkat masuk ke `inbox` (Processed = 'false').
// CreatorID : 'digita:<NamaAkun>:<NoRef>' atau 'digita:<NamaAkun>:<NoRef>:<Tiket>' (bila dikirim dari tiket) -> dipakai Monitoring Perintah.

$pesan_sukses = '';
$pesan_error  = '';
$maks_kirim   = 50;      // batas perangkat per sekali kirim

$sumber_barang = 'vw_terpasang';
$db_gateway    = 'smsd';
$tiket_cfg = [   // sumber tiket: tabel, kolom kunci, label, perintah yang disarankan
    'putus' => ['tabel' => 'trx_order_putus', 'pk' => 'TiketPutus', 'label' => 'Tiket Putus', 'saran' => 'PLNOFF'],
    'nyala' => ['tabel' => 'trx_order_nyala', 'pk' => 'TiketNyala', 'label' => 'Tiket Nyala', 'saran' => 'PLNON'],
];
$sumber = (string) ($_POST['sumber'] ?? $_GET['sumber'] ?? 'putus');
if (!isset($tiket_cfg[$sumber]) && $sumber !== 'barang') $sumber = 'putus';
$saran_cmd = $tiket_cfg[$sumber]['saran'] ?? '';
foreach ([$sumber_barang, $db_gateway] as $id) if (!preg_match('/^\w+$/', $id)) throw new RuntimeException('Nama view/database tidak valid.');

/** Pilihan perintah. 'bahaya' = berdampak langsung ke pelanggan (butuh konfirmasi tegas). */
$daftar_perintah = [
    'PLNOFF'    => ['label' => 'Matikan listrik',  'bahaya' => true],
    'PLNON'     => ['label' => 'Nyalakan listrik', 'bahaya' => true],
    'PLNSTATUS' => ['label' => 'Cek status',       'bahaya' => false],
];

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

/** Nomor telepon -> format +62xxxxxxxxxx; null bila tidak valid. */
if (!function_exists('pr_nomor')) {
    function pr_nomor($v): ?string
    {
        $v = preg_replace('/[\s\-().]/', '', trim((string) $v));
        if ($v === '') return null;
        if ($v[0] === '+')               $n = substr($v, 1);
        elseif (strpos($v, '62') === 0)  $n = $v;
        elseif ($v[0] === '0')           $n = '62' . substr($v, 1);
        else                             $n = '62' . $v;
        return preg_match('/^62[1-9]\d{7,12}$/', $n) ? '+' . $n : null;
    }

    /** Antrekan satu SMS ke outbox gateway dengan Status 'Reserved' (diambil service Python). */
    function pr_antrekan_sms(PDO $conn, string $db, string $tujuan, string $teks, string $pembuat): void
    {
        $st = $conn->prepare("INSERT INTO `$db`.`outbox` (DestinationNumber, TextDecoded, CreatorID, Status) VALUES (?, ?, ?, 'Reserved')");
        $st->execute([$tujuan, $teks, $pembuat]);
    }
}

// ---- filter daftar perangkat ----
$keyword = trim($_GET['keyword'] ?? '');
$relay   = in_array($_GET['relay'] ?? '', ['ON', 'OFF'], true) ? $_GET['relay'] : '';
$status  = in_array($_GET['status'] ?? 'AKTIF', ['AKTIF', 'TIDAK', 'SEMUA'], true) ? ($_GET['status'] ?? 'AKTIF') : 'AKTIF';   // status tiket (default AKTIF)
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// KIRIM PERINTAH (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cmd     = (string) ($_POST['cmd'] ?? '');
    $targets = array_values(array_unique(array_map('strval', (array) ($_POST['pilih'] ?? []))));
    $akun    = (string) ($_SESSION['NamaAkun'] ?? '');

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap || $akun === '') {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI / sesi tidak valid, sehingga perintah tidak dapat dikirim.';
    } elseif (!isset($daftar_perintah[$cmd])) {
        $pesan_error = 'Pilih perintah yang akan dikirim.';
    } elseif (!$targets) {
        $pesan_error = 'Pilih minimal satu perangkat.';
    } elseif (count($targets) > $maks_kirim) {
        $pesan_error = "Maksimal $maks_kirim perangkat per pengiriman.";
    } else {
        try {
            // target dibaca ulang dari database (bukan dari form) dan harus berada di wilayah akun
            $p = []; $ph = [];
            foreach ($targets as $i => $no) { $ph[] = ":n$i"; $p[":n$i"] = $no; }
            $daftar = [];   // [label, nomor, creatorID]
            if ($sumber === 'barang') {
                $scope = pg_scope($u, 'b', 'Unit', $p);
                $st = $conn->prepare("SELECT b.NoRef AS kode, MIN(b.NomorBarang) AS nomor FROM `$sumber_barang` b
                                      WHERE b.NoRef IN (" . implode(',', $ph) . ") AND $scope GROUP BY b.NoRef");
                $st->execute($p);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $daftar[(string) $r['kode']] = [(string) $r['kode'], $r['nomor'], "digita:$akun:" . $r['kode']];
            } else {
                $c = $tiket_cfg[$sumber]; $pk = $c['pk'];
                $scope = pg_scope($u, 't', 'Unit', $p);
                // NoRef perangkat dicari dari view lewat IdPel + NomorBarang tiket (untuk penelusuran di monitoring)
                $st = $conn->prepare("SELECT t.$pk AS kode, t.NomorBarang AS nomor, MIN(b.NoRef) AS NoRef
                                      FROM `{$c['tabel']}` t LEFT JOIN `$sumber_barang` b ON b.IdPel = t.IdPel AND b.NomorBarang = t.NomorBarang
                                      WHERE t.$pk IN (" . implode(',', $ph) . ") AND $scope GROUP BY t.$pk, t.NomorBarang");
                $st->execute($p);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $daftar[(string) $r['kode']] = [(string) $r['kode'], $r['nomor'], $r['NoRef'] === null ? null : "digita:$akun:" . $r['NoRef'] . ':' . $r['kode']];
                }
            }

            $ok = 0; $lewat = [];
            $hilang = array_diff($targets, array_keys($daftar));
            if ($hilang) $lewat[] = pg_n(count($hilang)) . ' data di luar wilayah Anda';

            $conn->beginTransaction();
            foreach ($daftar as [$label, $nomor, $creator]) {
                $hp = pr_nomor($nomor ?? '');
                if ($hp === null)      { $lewat[] = "$label tanpa nomor barang valid"; continue; }
                if ($creator === null) { $lewat[] = "$label perangkatnya tidak ditemukan di data terpasang"; continue; }
                pr_antrekan_sms($conn, $db_gateway, $hp, $cmd, mb_substr($creator, 0, 100));
                $ok++;
            }
            $conn->commit();

            if ($ok > 0) $pesan_sukses = 'Perintah <strong>' . pg_e($cmd) . '</strong> (' . pg_e($daftar_perintah[$cmd]['label']) . ') diantrekan untuk <strong>' . pg_n($ok) . '</strong> ' . ($sumber === 'barang' ? 'perangkat' : 'tiket') . '. Status pengiriman dapat dipantau di Monitoring Perintah.';
            if ($lewat)  $pesan_error  = 'Dilewati: ' . pg_e(implode('; ', array_slice($lewat, 0, 5))) . (count($lewat) > 5 ? '; …' : '') . '.';
            if ($ok === 0 && !$pesan_error) $pesan_error = 'Tidak ada perintah yang diantrekan.';
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal mengantrekan perintah, tidak ada yang terkirim: ' . pg_e($e->getMessage());
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_perintah', [
            'sumber' => $sumber === 'putus' ? '' : $sumber, 'status' => ($_POST['f_status'] ?? 'AKTIF') === 'AKTIF' ? '' : ($_POST['f_status'] ?? ''),
            'keyword' => $_POST['f_keyword'] ?? '', 'relay' => $_POST['f_relay'] ?? '',
            'limit' => ($_POST['f_limit'] ?? '') == 25 ? '' : ($_POST['f_limit'] ?? ''), 'hal' => ($_POST['f_hal'] ?? 1) > 1 ? $_POST['f_hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA (wilayah akun): tiket putus / tiket nyala / perangkat
// ---------------------------------------------------------
$data = []; $total_data = 0; $ringkas = ['total' => 0, 'a' => 0, 'b' => 0];   // a/b: tiket = aktif/nonaktif; barang = relay ON/OFF
if ($unit_siap) {
    try {
        if ($sumber === 'barang') {
            $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
            $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef), COUNT(DISTINCT CASE WHEN b.StatusRelay = 'ON' THEN b.NoRef END), COUNT(DISTINCT CASE WHEN b.StatusRelay = 'OFF' THEN b.NoRef END)
                                  FROM `$sumber_barang` b WHERE $scope");
            $st->execute($p);
            [$ringkas['total'], $ringkas['a'], $ringkas['b']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            $w = [$scope];
            if ($keyword !== '') {
                $w[] = '(b.NoRef LIKE :k1 OR b.IdPel LIKE :k2 OR b.NomorBarang LIKE :k3 OR b.ImeiModem LIKE :k4 OR l.NamaPelanggan LIKE :k5)';
                foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            if ($relay !== '') { $w[] = 'b.StatusRelay = :rl'; $p[':rl'] = $relay; }
            $from = "FROM `$sumber_barang` b LEFT JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT b.NoRef, MIN(b.NomorBarang) AS NomorBarang, MIN(b.ImeiModem) AS ImeiModem, MIN(b.StatusRelay) AS StatusRelay,
                                         MIN(b.IdPel) AS IdPel, MIN(NULLIF(l.NamaPelanggan,'')) AS NamaPelanggan, MIN(b.UnitUp) AS UnitUp
                                  $from GROUP BY b.NoRef ORDER BY b.NoRef LIMIT :lim OFFSET :off");
        } else {
            $c = $tiket_cfg[$sumber]; $pk = $c['pk']; $tb = $c['tabel'];
            $p = []; $scope = pg_scope($u, 't', 'Unit', $p);
            $st = $conn->prepare("SELECT COUNT(*), SUM(t.StatusData = 'AKTIF'), SUM(t.StatusData = 'TIDAK') FROM `$tb` t WHERE $scope");
            $st->execute($p);
            [$ringkas['total'], $ringkas['a'], $ringkas['b']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            $w = [$scope];
            if ($status !== 'SEMUA') { $w[] = 't.StatusData = :st'; $p[':st'] = $status; }
            if ($keyword !== '') {
                $w[] = "(t.$pk LIKE :k1 OR t.IdPel LIKE :k2 OR t.NomorBarang LIKE :k3 OR l.NamaPelanggan LIKE :k4)";
                foreach (['k1', 'k2', 'k3', 'k4'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            $from = "FROM `$tb` t LEFT JOIN dil l ON l.IdPel = t.IdPel" . ($sumber === 'putus' ? " LEFT JOIN trx_order_nyala n ON n.TiketPutus = t.TiketPutus" : '') . " WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(DISTINCT t.$pk) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT t.$pk AS Kode, t.JenisTiket, t.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, t.ThBlRek, t.NomorBarang, t.UnitUp, t.StatusData, t.WaktuData"
                                 . ($sumber === 'putus' ? ", MIN(n.TiketNyala) AS TiketNyala" : '')
                                 . " $from GROUP BY t.$pk, t.JenisTiket, t.IdPel, l.NamaPelanggan, t.ThBlRek, t.NomorBarang, t.UnitUp, t.StatusData, t.WaktuData
                                   ORDER BY t.WaktuData DESC, t.$pk DESC LIMIT :lim OFFSET :off");
        }
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat data: ' . pg_e($e->getMessage()));
    }
}

$boleh_monitor = pga_boleh('monitor_perintah');

include __DIR__ . '/../../templates/penggunaan/input_perintah.php';