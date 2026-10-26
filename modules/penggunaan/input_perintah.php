<?php
// modules/penggunaan/input_perintah.php  -  Input Perintah (kirim perintah ke perangkat milik IdPel lewat SMS Gateway)
// Pilihan   : IdPel yang memiliki aset terpasang di VIEW `vw_terpasang` (NoRef, SimIdBarang, ImeiModem, StatusRelay, IdPel,
//             UnitUp/Ap/Upi, NomorBarang, ...), dibatasi wilayah akun (kode unit session, pg_scope()).
// Perintah  : PLNOFF, PLNON, PLNSTATUS (pilihan tetap). Nomor tujuan = NomorBarang tiap aset IdPel tersebut.
// Tiket     : PLNOFF -> tiket putus (tabel trx_order_putus); PLNON -> tiket nyala (tabel trx_order_nyala); PLNSTATUS -> tanpa tiket.
//             Petugas WAJIB memilih jenis tiket: PREVENTIF atau KOREKTIF. Aturan (sama seperti trigger saldo / pelunasan):
//             - tiket putus TIDAK dibuat baru bila IdPel sudah punya tiket putus AKTIF (SMS tetap dikirim dengan tiket yang ada);
//             - tiket nyala hanya dibuat bila IdPel punya tiket putus AKTIF, dan TIDAK dibuat baru bila tiket putus itu sudah punya tiket nyala.
//             Nomor tiket baru: 'T2.' + UnitUp + tanggal + 4 digit urut, mis. T2.56610 20261026 0001 (urut naik bila terbit di hari & unit yang sama;
//             dicek di trx_order_putus, trx_order_nyala, dan antrean SMS). Nomor tiket dibawa di CreatorID SMS: 'digita:<NamaAkun>:<NoRef>:<Tiket>'.
//             Dasar periode tiket putus = ThBlRek terbaru IdPel di tabel dpp; tiket nyala memakai ThBlRek tiket putusnya.
// Pengiriman: INSERT ke $tabel_outbox (format smsd: DestinationNumber, TextDecoded, CreatorID, [Status]). Bila tabel punya kolom
//             Status, diisi 'Reserved'. Ganti $tabel_outbox (boleh 'database.tabel') bila antrean SMS ada di tempat lain.

$pesan_sukses = '';
$pesan_error  = '';
$maks_kirim   = 50;      // batas IdPel per sekali kirim

$sumber_barang   = 'vw_terpasang';
$tabel_outbox    = 'sms_outbox';
$format_tgl_tiket = 'Ymd';   // 'Ymd' = yyyymmdd (sesuai tiket yang ada, total 20 karakter); 'ymd' = yymmdd
$prefix_tiket    = ['putus' => 'T2.', 'nyala' => 'T2.'];   // awalan nomor tiket per jenis
$app_kirim_sms   = true;   // false bila trigger pada trx_order_putus / trx_order_nyala sudah mengirim SMS sendiri (hindari SMS ganda)
if (!preg_match('/^\w+$/', $sumber_barang) || !preg_match('/^\w+(\.\w+)?$/', $tabel_outbox)) throw new RuntimeException('Nama view/tabel tidak valid.');
$q_outbox = '`' . str_replace('.', '`.`', $tabel_outbox) . '`';

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

if (!function_exists('pr_nomor')) {
    /** Nomor telepon -> format +62xxxxxxxxxx; null bila tidak valid. */
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

    /**
     * Nomor tiket berikutnya: awalan (mis. 'T2.') + UnitUp + tanggal + 4 digit urut. Urutan terakhir dicari dari trx_order_putus,
     * trx_order_nyala, dan CreatorID antrean SMS dengan awalan yang sama, lalu ditambah 1. $cache menjaga urutan dalam satu proses.
     * Panggil hanya saat GET_LOCK 'digita_tiket_perintah' dipegang agar dua petugas tidak mendapat nomor yang sama.
     */
    function pr_nomor_tiket(PDO $conn, string $q_outbox, string $prefix, string $unitUp, string $tgl, array &$cache): string
    {
        $awal = $prefix . $unitUp . $tgl;
        if (!isset($cache[$awal])) {
            $st = $conn->prepare("SELECT MAX(CAST(RIGHT(TRIM(k), 4) AS UNSIGNED)) FROM (
                                      SELECT TiketPutus AS k FROM trx_order_putus WHERE TiketPutus LIKE :a
                                      UNION ALL SELECT TiketNyala FROM trx_order_nyala WHERE TiketNyala LIKE :b
                                      UNION ALL SELECT SUBSTRING_INDEX(CreatorID, ':', -1) FROM $q_outbox WHERE CreatorID LIKE :c
                                  ) t");
            $st->execute([':a' => $awal . '%', ':b' => $awal . '%', ':c' => '%:' . $awal . '%']);
            $cache[$awal] = (int) $st->fetchColumn();
        }
        if ($cache[$awal] >= 9999) throw new RuntimeException("Urutan tiket $awal sudah mencapai 9999.");
        return $awal . str_pad((string) ++$cache[$awal], 4, '0', STR_PAD_LEFT);
    }
}

// ---- filter daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$relay   = in_array($_GET['relay'] ?? '', ['ON', 'OFF'], true) ? $_GET['relay'] : '';
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
    $targets = array_values(array_unique(array_filter(array_map('strval', (array) ($_POST['pilih'] ?? [])), fn($x) => preg_match('/^\d{12}$/', $x))));
    $jenis   = (string) ($_POST['jenis'] ?? '');
    $akun    = (string) ($_SESSION['NamaAkun'] ?? '');
    $dikunci = false;

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap || $akun === '') {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI / sesi tidak valid, sehingga perintah tidak dapat dikirim.';
    } elseif (!isset($daftar_perintah[$cmd])) {
        $pesan_error = 'Pilih perintah yang akan dikirim.';
    } elseif (in_array($cmd, ['PLNOFF', 'PLNON'], true) && !in_array($jenis, ['PREVENTIF', 'KOREKTIF'], true)) {
        $pesan_error = 'Pilih jenis tiket: PREVENTIF atau KOREKTIF.';
    } elseif (!$targets) {
        $pesan_error = 'Pilih minimal satu IdPel.';
    } elseif (count($targets) > $maks_kirim) {
        $pesan_error = "Maksimal $maks_kirim IdPel per pengiriman.";
    } else {
        try {
            // aset IdPel dibaca ulang dari view (bukan dari form) dan harus berada di wilayah akun
            $p = []; $ph = [];
            foreach ($targets as $i => $id) { $ph[] = ":n$i"; $p[":n$i"] = $id; }
            $scope = pg_scope($u, 'b', 'Unit', $p);
            $st = $conn->prepare("SELECT b.IdPel, b.NoRef, b.SimIdBarang, b.ImeiModem, b.NomorBarang, b.NomorModem, b.UnitUp, b.UnitAp, b.UnitUpi FROM `$sumber_barang` b
                                  WHERE b.IdPel IN (" . implode(',', $ph) . ") AND $scope
                                  GROUP BY b.IdPel, b.NoRef, b.SimIdBarang, b.ImeiModem, b.NomorBarang, b.NomorModem, b.UnitUp, b.UnitAp, b.UnitUpi ORDER BY b.IdPel, b.NoRef");
            $st->execute($p);
            $aset = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $aset[$r['IdPel']][] = $r;

            $lewat = [];
            $hilang = array_diff($targets, array_keys($aset));
            if ($hilang) $lewat[] = pg_n(count($hilang)) . ' IdPel di luar wilayah / tanpa aset';

            // kolom Status pada antrean SMS (ada pada antrean gateway: diisi 'Reserved' agar diambil service pengirim)
            $ada_status = (bool) $conn->query("SHOW COLUMNS FROM $q_outbox LIKE 'Status'")->fetch();
            $sql_kirim  = "INSERT INTO $q_outbox (DestinationNumber, TextDecoded, CreatorID" . ($ada_status ? ", Status" : "") . ") VALUES (?, ?, ?" . ($ada_status ? ", 'Reserved'" : "") . ")";

            // kunci agar nomor tiket tidak kembar bila dua petugas mengirim bersamaan
            $dikunci = (int) $conn->query("SELECT GET_LOCK('digita_tiket_perintah', 10)")->fetchColumn() === 1;
            if (!$dikunci) throw new RuntimeException('Sistem sedang menerbitkan tiket lain. Coba lagi sebentar.');

            $cache = []; $tiket_baru = []; $pakai_lama = 0; $sms = 0; $diproses = 0;
            $tgl_tiket = date($format_tgl_tiket);
            $t = fn($v) => mb_substr((string) $v, 0, 20);   // kolom tiket bertipe VARCHAR(20)
            $conn->beginTransaction();
            $ins = $conn->prepare($sql_kirim);
            foreach ($aset as $idpel => $daftar) {
                $valid = [];
                foreach ($daftar as $a) if (($hp = pr_nomor($a['NomorBarang'] ?? '')) !== null) $valid[] = [$a['NoRef'], $hp];
                if (!$valid) { $lewat[] = "$idpel tanpa nomor barang valid"; continue; }

                $a0 = $daftar[0]; $tiket = null;
                $kolom_aset = [$t($a0['SimIdBarang']), $t($a0['ImeiModem']), $t($a0['NomorBarang']), $t($a0['NomorModem'])];
                $unit_aset  = [trim((string) $a0['UnitUp']), trim((string) $a0['UnitAp']), trim((string) $a0['UnitUpi'])];

                if ($cmd === 'PLNOFF') {
                    // tiket putus: tidak dibuat baru bila IdPel sudah punya tiket putus aktif
                    $st = $conn->prepare("SELECT TiketPutus, ThBlRek FROM trx_order_putus WHERE IdPel = ? AND StatusData = 'AKTIF' ORDER BY WaktuData DESC LIMIT 1");
                    $st->execute([$idpel]);
                    $pt_lama = $st->fetch(PDO::FETCH_ASSOC);
                    
                    $tiket = $pt_lama['TiketPutus'] ?? null;
                    if ($tiket !== null) {
                        $pakai_lama++;
                    } else {
                        // Ambil ThBlRek dari tiket putus/nyala terakhir milik IdPel tersebut, atau gunakan bulan berjalan (Ym) jika belum ada riwayat sama sekali
                        $st = $conn->prepare("SELECT ThBlRek FROM trx_order_putus WHERE IdPel = ? AND ThBlRek IS NOT NULL AND ThBlRek != '' ORDER BY WaktuData DESC LIMIT 1");
                        $st->execute([$idpel]);
                        $periode = (string) $st->fetchColumn();
                        
                        if ($periode === '') {
                            $st = $conn->prepare("SELECT ThBlRek FROM trx_order_nyala WHERE IdPel = ? AND ThBlRek IS NOT NULL AND ThBlRek != '' ORDER BY WaktuData DESC LIMIT 1");
                            $st->execute([$idpel]);
                            $periode = (string) $st->fetchColumn();
                        }
                        
                        // Fallback ke bulan berjalan (format Ym atau YmRek) jika sama sekali tidak ada riwayat tiket
                        if ($periode === '') {
                            $periode = date('Ym');
                        }

                        $tiket = pr_nomor_tiket($conn, $q_outbox, $prefix_tiket['putus'], $unit_aset[0], $tgl_tiket, $cache);
                        $conn->prepare("INSERT INTO trx_order_putus (TiketPutus, JenisTiket, IdPel, ThBlRek, SimId, ImeiModem, NomorBarang, NomorModem, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'AKTIF', NOW())")
                             ->execute(array_merge([$tiket, $jenis, $idpel, $periode], $kolom_aset, [$akun], $unit_aset));
                        $tiket_baru[] = $tiket;
                    }
                } if ($cmd === 'PLNON') {
                    // tiket nyala: hanya bila ada tiket putus aktif
                    $st = $conn->prepare("SELECT TiketPutus, ThBlRek FROM trx_order_putus WHERE IdPel = ? AND StatusData = 'AKTIF' ORDER BY WaktuData DESC LIMIT 1");
                    $st->execute([$idpel]);
                    $pt = $st->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$pt) { 
                        $lewat[] = "$idpel belum punya tiket putus aktif (tiket nyala hanya dibuat setelah tiket putus)"; 
                        continue; 
                    }
                    
                    $tiket_putus_aktif = $pt['TiketPutus'];

                    // Cek apakah tiket putus tersebut sudah punya tiket nyala
                    $st = $conn->prepare("SELECT TiketNyala FROM trx_order_nyala WHERE TiketPutus = ? ORDER BY WaktuData DESC LIMIT 1");
                    $st->execute([$tiket_putus_aktif]);
                    $tiket = $st->fetchColumn() ?: null;

                    if ($tiket !== null) {
                        $pakai_lama++;
                    } else {
                        $tiket = pr_nomor_tiket($conn, $q_outbox, $prefix_tiket['nyala'], $unit_aset[0], $tgl_tiket, $cache);
                        
                        // 1. Insert tiket nyala baru dengan StatusData = 'AKTIF'
                        $conn->prepare("INSERT INTO trx_order_nyala (TiketNyala, JenisTiket, TiketPutus, IdPel, ThBlRek, SimId, ImeiModem, NomorBarang, NomorModem, UnitUp, UnitAp, UnitUpi, NamaAkun, StatusData, WaktuData)
                                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'AKTIF', NOW())")
                             ->execute(array_merge([$tiket, $jenis, $tiket_putus_aktif, $idpel, trim((string) $pt['ThBlRek'])], $kolom_aset, $unit_aset, [$akun]));
                        
                        // 2. Ubah StatusData pada tiket putus yang bersangkutan menjadi 'TIDAK' sesuai aturan baru
                        $conn->prepare("UPDATE trx_order_putus SET StatusData = 'TIDAK' WHERE TiketPutus = ?")
                             ->execute([$tiket_putus_aktif]);

                        $tiket_baru[] = $tiket;
                    }
                }

                if ($app_kirim_sms) {
                    foreach ($valid as [$noref, $hp]) {
                        $ins->execute([$hp, $cmd, mb_substr($tiket !== null ? "digita:$akun:$noref:$tiket" : "digita:$akun:$noref", 0, 100)]);
                        $sms++;
                    }
                }
                $diproses++;
            }
            $conn->commit();

            if ($diproses > 0) {
                $pesan_sukses = 'Perintah <strong>' . pg_e($cmd) . '</strong> (' . pg_e($daftar_perintah[$cmd]['label']) . ') diproses untuk <strong>' . pg_n($diproses) . '</strong> IdPel'
                              . ($app_kirim_sms ? ' (' . pg_n($sms) . ' SMS)' : ' (SMS tidak dikirim aplikasi)') . '.';
                if ($tiket_baru) $pesan_sukses .= ' Tiket ' . strtolower($jenis) . ' baru: <span class="font-monospace">' . pg_e(implode(', ', array_slice($tiket_baru, 0, 3))) . (count($tiket_baru) > 3 ? ', …' : '') . '</span>.';
                if ($pakai_lama) $pesan_sukses .= ' ' . pg_n($pakai_lama) . ' IdPel memakai tiket yang sudah ada (tidak dibuat baru).';
            }
            if ($lewat) $pesan_error = 'Dilewati: ' . pg_e(implode('; ', array_slice($lewat, 0, 5))) . (count($lewat) > 5 ? '; …' : '') . '.';
            if (!$diproses && !$pesan_error) $pesan_error = 'Tidak ada perintah yang diproses.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal menerbitkan perintah, tidak ada yang terkirim: ' . pg_e($e->getMessage());
        } finally {
            if ($dikunci) $conn->query("SELECT RELEASE_LOCK('digita_tiket_perintah')");
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_perintah', [
            'keyword' => $_POST['f_keyword'] ?? '', 'relay' => $_POST['f_relay'] ?? '',
            'limit' => ($_POST['f_limit'] ?? '') == 25 ? '' : ($_POST['f_limit'] ?? ''), 'hal' => ($_POST['f_hal'] ?? 1) > 1 ? $_POST['f_hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA: IdPel yang memiliki aset terpasang (view, wilayah akun)
// ---------------------------------------------------------
$data = []; $total_data = 0; $ringkas = ['total' => 0, 'on' => 0, 'off' => 0];
if ($unit_siap) {
    try {
        $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
        $st = $conn->prepare("SELECT COUNT(DISTINCT b.IdPel), COUNT(DISTINCT CASE WHEN b.StatusRelay = 'ON' THEN b.IdPel END), COUNT(DISTINCT CASE WHEN b.StatusRelay = 'OFF' THEN b.IdPel END)
                              FROM `$sumber_barang` b WHERE b.IdPel IS NOT NULL AND $scope");
        $st->execute($p);
        [$ringkas['total'], $ringkas['on'], $ringkas['off']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

        $w = ['b.IdPel IS NOT NULL', $scope];
        if ($keyword !== '') {
            $w[] = '(b.IdPel LIKE :k1 OR b.NoRef LIKE :k2 OR b.NomorBarang LIKE :k3 OR b.ImeiModem LIKE :k4 OR l.NamaPelanggan LIKE :k5)';
            foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $k) $p[":$k"] = '%' . $keyword . '%';
        }
        if ($relay !== '') { $w[] = 'b.StatusRelay = :rl'; $p[':rl'] = $relay; }
        $from = "FROM `$sumber_barang` b LEFT JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(DISTINCT b.IdPel) $from");
        $st->execute($p);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT b.IdPel, MIN(NULLIF(l.NamaPelanggan,'')) AS NamaPelanggan, COUNT(DISTINCT b.NoRef) AS jml, MIN(b.NoRef) AS NoRef,
                                     MIN(b.NomorBarang) AS NomorBarang, MIN(b.ImeiModem) AS ImeiModem, MIN(b.StatusRelay) AS StatusRelay, MIN(b.UnitUp) AS UnitUp
                              $from GROUP BY b.IdPel ORDER BY b.IdPel LIMIT :lim OFFSET :off");
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