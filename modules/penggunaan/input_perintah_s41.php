<?php
// modules/penggunaan/input_perintah.php  -  Input Perintah (kirim ke perangkat lewat SMS Gateway)
// Sumber (tab) : 'putus' = VIEW vw_tiket_putus_s41_open  (tiket putus yang masih open  -> perintah PLNOFF),
//                'nyala' = VIEW vw_tiket_nyala_s41_open  (tiket nyala yang masih open  -> perintah PLNON),
//                'barang' = VIEW vw_terpasang_s41 (perangkat manual, tanpa tiket). Semua dibatasi wilayah akun (kode unit session, pg_scope()).
//                Kedua view tiket sudah terurut, jadi dibaca apa adanya (tanpa ORDER BY / GROUP BY tambahan) dan dikirim sesuai urutan itu.
//                Nomor tujuan = NomorBarang. View HANYA DIBACA.
// Perintah  : pilihan tetap di bawah (PLNOFF, PLNON, PLNSTATUS); TIDAK lagi dari tabel baku_outbox.
// Pengiriman: INSERT ke tabel `sms_outbox` ($tabel_outbox) dengan Status = 'Reserved'; service Python (smsd/gateway_sms.py)
//             mengambil Status 'Reserved', mengirim lewat modem, lalu mengubah Status menjadi 'SendingOK' / 'SendingError'.
//             Balasan perangkat masuk ke `inbox` (Processed = 'false').
// CreatorID : 'digita:<NamaAkun>:<NoRef>' atau 'digita:<NamaAkun>:<NoRef>:<Tiket>' (bila dikirim dari tiket) -> dipakai Monitoring Perintah
//             dan juga untuk menandai tiket yang SUDAH diantrekan (CreatorID berakhiran ':<Tiket>' + TextDecoded = perintah).
// Tombol "Kirim Semua": mengantrekan perintah saran (PLNOFF / PLNON) untuk seluruh tiket open yang belum diantrekan (maks. $maks_kirim, urut view).

$pesan_sukses = '';
$pesan_error  = '';
$maks_kirim   = 50;      // batas perangkat/tiket per sekali kirim

$sumber_barang = 'vw_terpasang_s41';
$tabel_outbox  = 'sms_outbox';   // tabel antrean SMS; bila di database lain tulis 'namadb.sms_outbox'
$tiket_cfg = [   // sumber tiket: view open, kolom kunci, label, perintah yang disarankan
    'putus' => ['tabel' => 'vw_tiket_putus_s41_open', 'pk' => 'TiketPutusS41', 'label' => 'Tiket Putus', 'saran' => 'PLNOFF'],
    'nyala' => ['tabel' => 'vw_tiket_nyala_s41_open', 'pk' => 'TiketNyala',    'label' => 'Tiket Nyala', 'saran' => 'PLNON'],
];
$sumber = (string) ($_POST['sumber'] ?? $_GET['sumber'] ?? 'putus');
if (!isset($tiket_cfg[$sumber]) && $sumber !== 'barang') $sumber = 'putus';
$saran_cmd = $tiket_cfg[$sumber]['saran'] ?? '';
foreach ([$sumber_barang, $tiket_cfg['putus']['tabel'], $tiket_cfg['nyala']['tabel']] as $id) if (!preg_match('/^\w+$/', $id)) throw new RuntimeException('Nama view tidak valid.');
if (!preg_match('/^\w+(\.\w+)?$/', $tabel_outbox)) throw new RuntimeException('Nama tabel outbox tidak valid.');
$tb_out = '`' . str_replace('.', '`.`', $tabel_outbox) . '`';

/** Kondisi SQL status relay: nilai bisa 'ON'/'OFF' atau teks hasil balasan perangkat (mis. 'LISTRIK MENYALA - BERHASIL (RELAY NC)'). */
$sql_relay_on  = "(b.StatusRelay = 'ON'  OR b.StatusRelay LIKE '%MENYALA%' OR b.StatusRelay LIKE '%RELAY NC%')";
$sql_relay_off = "(b.StatusRelay = 'OFF' OR b.StatusRelay LIKE '%PADAM%' OR b.StatusRelay LIKE '%MATI%' OR b.StatusRelay LIKE '%RELAY NO%')";

/** SQL "tiket ini sudah diantrekan di sms_outbox untuk perintah tsb" ($kolom = kolom kunci tiket, mis. v.TiketPutusS41). */
$sql_antre = function (string $kolom, string $cmd) use ($conn, $tb_out): string {
    return "EXISTS (SELECT 1 FROM $tb_out o WHERE o.TextDecoded = " . $conn->quote($cmd)
         . " AND RIGHT(o.CreatorID, CHAR_LENGTH(CONCAT(':', $kolom))) = CONCAT(':', $kolom))";
};

/** Pilihan perintah. 'bahaya' = berdampak langsung ke pelanggan (butuh konfirmasi tegas). */
$daftar_perintah = [
    'PLNOFF'    => ['label' => 'Matikan listrik',  'bahaya' => true],
    'PLNON'     => ['label' => 'Nyalakan listrik', 'bahaya' => true],
    'PLNSTATUS' => ['label' => 'Cek status',       'bahaya' => false],
];

// Pembatasan per tab: Tiket Putus tidak boleh PLNON, Tiket Nyala tidak boleh PLNOFF (tab Perangkat manual: semua perintah).
// Dibuang dari daftar yang sama sehingga berlaku di dropdown sekaligus validasi saat POST.
if ($sumber === 'putus') unset($daftar_perintah['PLNON']);
elseif ($sumber === 'nyala') unset($daftar_perintah['PLNOFF']);

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

    /** Status relay -> true (menyala/ON), false (padam/OFF), null (belum diketahui). */
    function pr_relay_on($v): ?bool
    {
        $v = strtoupper(trim((string) $v));
        if ($v === '') return null;
        if ($v === 'OFF' || preg_match('/PADAM|MATI|RELAY NO\b/', $v)) return false;
        if ($v === 'ON'  || preg_match('/MENYALA|RELAY NC\b/', $v))    return true;
        return null;
    }

    /** Antrekan satu SMS ke tabel sms_outbox dengan Status 'Reserved' (diambil service Python). $tb = nama tabel ber-backtick. */
    function pr_antrekan_sms(PDO $conn, string $tb, string $tujuan, string $teks, string $pembuat): void
    {
        $st = $conn->prepare("INSERT INTO $tb (DestinationNumber, TextDecoded, CreatorID, Status) VALUES (?, ?, ?, 'Reserved')");
        $st->execute([$tujuan, $teks, $pembuat]);
    }
}

// ---- filter daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$relay   = in_array($_GET['relay'] ?? '', ['ON', 'OFF'], true) ? $_GET['relay'] : '';
// status antrean SMS tiket open: BELUM = belum diantrekan ke sms_outbox (default), SUDAH = sudah diantrekan, SEMUA = keduanya
$antrean = in_array($_GET['antrean'] ?? 'BELUM', ['BELUM', 'SUDAH', 'SEMUA'], true) ? ($_GET['antrean'] ?? 'BELUM') : 'BELUM';
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
    $semua   = $sumber !== 'barang' && ($_POST['semua'] ?? '') === '1';   // tombol "Kirim Semua": seluruh tiket open yang belum diantrekan
    if ($semua) $cmd = $saran_cmd;
    $csrf_ok = hash_equals($csrf, (string) ($_POST['csrf'] ?? ''));

    if ($csrf_ok && $semua && $unit_siap && isset($daftar_perintah[$cmd])) {
        try {
            // urutan view dipertahankan (view sudah terurut); hanya yang belum diantrekan, maks. $maks_kirim
            $c = $tiket_cfg[$sumber]; $pk = $c['pk']; $p = [];
            $scope = pg_scope($u, 'v', 'Unit', $p);
            $st = $conn->prepare("SELECT v.$pk FROM `{$c['tabel']}` v WHERE $scope AND NOT " . $sql_antre("v.$pk", $cmd) . ' LIMIT ' . (int) $maks_kirim);
            $st->execute($p);
            $targets = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            $targets = [];
            $pesan_error = 'Gagal membaca tiket open: ' . pg_e($e->getMessage());
        }
    }

    if (!$csrf_ok) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap || $akun === '') {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI / sesi tidak valid, sehingga perintah tidak dapat dikirim.';
    } elseif (!isset($daftar_perintah[$cmd])) {
        $pesan_error = 'Pilih perintah yang akan dikirim.';
    } elseif ($semua && !$targets) {
        $pesan_error = $pesan_error ?: 'Tidak ada tiket open yang belum diantrekan.';
    } elseif (!$targets) {
        $pesan_error = 'Pilih minimal satu perangkat.';
    } elseif (count($targets) > $maks_kirim) {
        $pesan_error = "Maksimal $maks_kirim perangkat per pengiriman.";
    } else {
        try {
            // target dibaca ulang dari database (bukan dari form) dan harus berada di wilayah akun
            $p = []; $ph = [];
            foreach ($targets as $i => $no) { $ph[] = ":n$i"; $p[":n$i"] = $no; }
            $daftar = [];   // kode => [label, nomor, creatorID, sudah]
            if ($sumber === 'barang') {
                $scope = pg_scope($u, 'b', 'Unit', $p);
                $st = $conn->prepare("SELECT b.NoRef AS kode, MIN(b.NomorBarang) AS nomor FROM `$sumber_barang` b
                                      WHERE b.NoRef IN (" . implode(',', $ph) . ") AND $scope GROUP BY b.NoRef");
                $st->execute($p);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $daftar[(string) $r['kode']] = [(string) $r['kode'], $r['nomor'], "digita:$akun:" . $r['kode'], 0];
            } else {
                $c = $tiket_cfg[$sumber]; $pk = $c['pk'];
                $scope = pg_scope($u, 't', 'Unit', $p);
                // NoRef perangkat dicari dari view terpasang lewat IdPel + NomorBarang tiket (untuk penelusuran di monitoring);
                // sudah = perintah yang sama untuk tiket ini sudah ada di sms_outbox
                $st = $conn->prepare("SELECT t.$pk AS kode, t.NomorBarang AS nomor, MIN(b.NoRef) AS NoRef, " . $sql_antre("t.$pk", $cmd) . " AS sudah
                                      FROM `{$c['tabel']}` t LEFT JOIN `$sumber_barang` b ON b.IdPel = t.IdPel AND b.NomorBarang = t.NomorBarang
                                      WHERE t.$pk IN (" . implode(',', $ph) . ") AND $scope GROUP BY t.$pk, t.NomorBarang");
                $st->execute($p);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $daftar[(string) $r['kode']] = [(string) $r['kode'], $r['nomor'], $r['NoRef'] === null ? null : "digita:$akun:" . $r['NoRef'] . ':' . $r['kode'], (int) $r['sudah']];
                }
            }

            $ok = 0; $ulang = 0; $lewat = [];
            $hilang = array_diff($targets, array_keys($daftar));
            if ($hilang) $lewat[] = pg_n(count($hilang)) . ' data di luar wilayah Anda';

            $conn->beginTransaction();
            foreach ($targets as $kode) {            // urutan sesuai tampilan / view
                if (!isset($daftar[$kode])) continue;
                [$label, $nomor, $creator, $sudah] = $daftar[$kode];
                $hp = pr_nomor($nomor ?? '');
                if ($hp === null)      { $lewat[] = "$label tanpa nomor barang valid"; continue; }
                if ($creator === null) { $lewat[] = "$label perangkatnya tidak ditemukan di data terpasang"; continue; }
                pr_antrekan_sms($conn, $tb_out, $hp, $cmd, mb_substr($creator, 0, 100));
                $ok++;
                if ($sudah) $ulang++;
            }
            $conn->commit();

            if ($ok > 0) $pesan_sukses = 'Perintah <strong>' . pg_e($cmd) . '</strong> (' . pg_e($daftar_perintah[$cmd]['label']) . ') diantrekan untuk <strong>' . pg_n($ok) . '</strong> ' . ($sumber === 'barang' ? 'perangkat' : 'tiket') . '. ' . ($ulang > 0 ? pg_n($ulang) . ' di antaranya <em>sudah pernah diantrekan</em> (dikirim ulang). ' : '') . 'Status pengiriman dapat dipantau di Monitoring Perintah.';
            if ($lewat)  $pesan_error  = 'Dilewati: ' . pg_e(implode('; ', array_slice($lewat, 0, 5))) . (count($lewat) > 5 ? '; …' : '') . '.';
            if ($ok === 0 && !$pesan_error) $pesan_error = 'Tidak ada perintah yang diantrekan.';
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal mengantrekan perintah, tidak ada yang terkirim: ' . pg_e($e->getMessage());
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_perintah_s41', [
            'sumber' => $sumber === 'putus' ? '' : $sumber,
            'antrean' => ($_POST['f_antrean'] ?? 'BELUM') === 'BELUM' ? '' : ($_POST['f_antrean'] ?? ''),
            'keyword' => $_POST['f_keyword'] ?? '', 'relay' => $_POST['f_relay'] ?? '',
            'limit' => ($_POST['f_limit'] ?? '') == 25 ? '' : ($_POST['f_limit'] ?? ''), 'hal' => ($_POST['f_hal'] ?? 1) > 1 ? $_POST['f_hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA (wilayah akun): tiket putus open / tiket nyala open / perangkat
// ---------------------------------------------------------
$data = []; $total_data = 0; $ringkas = ['total' => 0, 'a' => 0, 'b' => 0];   // a/b: tiket = belum/sudah diantrekan; barang = relay ON/OFF
if ($unit_siap) {
    try {
        if ($sumber === 'barang') {
            $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
            $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef), COUNT(DISTINCT CASE WHEN $sql_relay_on THEN b.NoRef END), COUNT(DISTINCT CASE WHEN $sql_relay_off THEN b.NoRef END)
                                  FROM `$sumber_barang` b WHERE $scope");
            $st->execute($p);
            [$ringkas['total'], $ringkas['a'], $ringkas['b']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            $w = [$scope];
            if ($keyword !== '') {
                $w[] = '(b.NoRef LIKE :k1 OR b.IdPel LIKE :k2 OR b.NomorBarang LIKE :k3 OR b.ImeiModem LIKE :k4 OR l.Nama LIKE :k5)';
                foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            if ($relay !== '') $w[] = $relay === 'ON' ? $sql_relay_on : $sql_relay_off;
            $from = "FROM `$sumber_barang` b LEFT JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT b.NoRef, MIN(b.NomorBarang) AS NomorBarang, MIN(b.ImeiModem) AS ImeiModem, MIN(b.StatusRelay) AS StatusRelay,
                                         MIN(b.IdPel) AS IdPel, MIN(NULLIF(l.Nama,'')) AS NamaPelanggan, MIN(b.UnitUp) AS UnitUp
                                  $from GROUP BY b.NoRef ORDER BY b.NoRef LIMIT :lim OFFSET :off");
        } else {
            $c = $tiket_cfg[$sumber]; $pk = $c['pk']; $vw = $c['tabel'];
            $ant = $sql_antre("v.$pk", $c['saran']);          // tiket sudah diantrekan di sms_outbox untuk perintah sarannya
            $p = []; $scope = pg_scope($u, 'v', 'Unit', $p);

            // ringkasan: total tiket open di wilayah, a = belum diantrekan, b = sudah diantrekan
            $st = $conn->prepare("SELECT COUNT(*), SUM(CASE WHEN $ant THEN 0 ELSE 1 END), SUM(CASE WHEN $ant THEN 1 ELSE 0 END) FROM `$vw` v WHERE $scope");
            $st->execute($p);
            [$ringkas['total'], $ringkas['a'], $ringkas['b']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

            $w = [$scope];
            if ($antrean === 'BELUM')     $w[] = "NOT $ant";
            elseif ($antrean === 'SUDAH') $w[] = $ant;
            if ($keyword !== '') {
                $w[] = "(v.$pk LIKE :k1 OR v.IdPel LIKE :k2 OR v.NomorBarang LIKE :k3 OR v.Nama LIKE :k4)";
                foreach (['k1', 'k2', 'k3', 'k4'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            $from = "FROM `$vw` v WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            // tanpa GROUP BY / ORDER BY: urutan mengikuti view. StatusSms/WaktuSms = SMS terakhir untuk tiket + perintah sarannya
            $cmdq = $conn->quote($c['saran']);
            $sub  = "FROM $tb_out o WHERE o.TextDecoded = $cmdq AND RIGHT(o.CreatorID, CHAR_LENGTH(CONCAT(':', v.$pk))) = CONCAT(':', v.$pk) ORDER BY o.ID DESC LIMIT 1";
            $st = $conn->prepare("SELECT v.$pk AS Kode, v.JenisTiket, v.IdPel, NULLIF(v.Nama,'') AS NamaPelanggan, v.ThblRek AS ThBlRek, v.NomorBarang,
                                         (SELECT o.Status $sub) AS StatusSms, (SELECT o.InsertIntoDB $sub) AS WaktuSms
                                  $from LIMIT :lim OFFSET :off");
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

$boleh_monitor = pga_boleh('monitor_perintah_s41');

include __DIR__ . '/../../templates/penggunaan/input_perintah_s41.php';