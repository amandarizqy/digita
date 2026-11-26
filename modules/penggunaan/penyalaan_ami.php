<?php
// modules/penggunaan/penyalaan_ami.php  -  Penyalaan AMI (tiket: trx_order_nyala_ami, hasil tindakan: trx_tindakan_nyala_ami)
// Halaman berdiri sendiri; tampilan: templates/penggunaan/penyalaan_ami.php
//
// Alur:
//   1. [Create]  -> buat SessionID baru (session login, per halaman), walau belum ada tiket. Selama sesi ada, Create nonaktif; hapus sesi dahulu untuk membuat yang baru.
//   2. Ambil tiket: pilih tiket terdepan dari view (urut: SkalaPrioritas 9->1, lalu Peringkat 1->n) yang belum diambil, lalu UPDATE tabel tiket SET Sesi = SessionID.
//      Semua tiket penyalaan berasal dari view; halaman ini TIDAK membuat tiket.
//   3. Tampilkan tiket milik SessionID yang belum ditindak.
//   4. Tombol hasil menulis ke trx_tindakan_nyala_ami:  Ready ON = NYALA, gagal ON = GAGAL  (nilai NYALA/GAGAL = asumsi, sesuaikan enum tabel)
//   5. Kembali ke langkah 2 (tiket berikutnya diambil otomatis dari view dengan SessionID yang sama).
// Semua dibatasi wilayah akun (pg_scope). NamaAkun tiket & tindakan = akun login.

// ---- PENGATURAN ----
$halaman        = 'penyalaan_ami';
$judul          = 'Penyalaan AMI';
$tabel          = 'trx_order_nyala_ami';          // tabel tiket
$view           = 'vw_tiket_nyala_ami_open';   // view tiket terbuka (dibaca untuk antrean, hitungan & tiket sesi); UPDATE Sesi tetap ke $tabel
$urut_view      = 'v.SkalaPrioritas DESC, v.Peringkat ASC';   // antrean: SkalaPrioritas tertinggi (9 -> 1) dahulu, lalu Peringkat terkecil (1, 2, 3, ...)
$pk             = 'TiketNyalaAmi';             // kunci tiket
$thbl_len       = 8;             // panjang kolom ThblRek pada tabel tiket
$tindakan_tabel = 'trx_tindakan_nyala_ami';           // hasil tindakan (kunci = $pk, + JenisTindakan, NamaAkun, WaktuData)
$jenis_tindakan = [                     // tombol => nilai JenisTindakan
    'ready_on'  => 'NYALA',             // Ready ON : meter sudah ON  -> penyalaan berhasil
    'gagal_on' => 'GAGAL',             // gagal ON: meter masih OFF -> penyalaan gagal
    'belum' => 'BELUM',               // Belum Lunas: hanya untuk tiket KOREKTIF
];
$fk             = 'TiketPutusAmi';          // kolom rujukan ke tiket putus
$putus_tabel    = 'trx_order_putus_ami';   // tabel tiket putus: HANYA untuk menampilkan No TUL (tidak ikut membentuk/menghitung tiket)
// Tautan tombol. Isi URL lengkap bila sudah ada; '#' = belum diatur (tombol hanya menampilkan pesan).
$link = ['ppob' => 'https://www.hotelmurah.com/pulsa/pln', 'mdms' => 'http://mdms.pln.co.id/meter'];

$pesan_sukses = $pesan_error = $pesan_info = '';
if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf      = $_SESSION['pg_csrf'];
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$akun      = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
$putus     = false;
$kunci_sesi = 'pg_sesi_' . $halaman;                  // SessionID aktif halaman ini
$sesi       = (string) ($_SESSION[$kunci_sesi] ?? '');

// ---- pembantu lokal ----
/** SessionID: S + yymmddHHMMSS + '-' + 6 hex acak. Contoh: S261106153012-A1B2C3 */
$buat_sesi = fn() => 'S' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

/** Langkah 2a: pilih tiket terdepan dari VIEW (belum punya Sesi, di wilayah akun), lalu tandai tabel tiket dengan SessionID.
 *  View tidak punya kolom Unit & tidak bisa di-UPDATE, jadi: SELECT dari view (di-JOIN ke tabel hanya untuk pg_scope), UPDATE ke tabel.
 *  UPDATE dijaga (Sesi masih kosong, AKTIF, belum ditindak); bila keduluan pengguna lain, ulangi memilih (maks 5 kali). true bila ada yang terambil. */
$cache_tiket = [];   // cache per-request hasil $tiket_sekarang (view berat, jangan dibaca berulang)
$klaim = function (string $sesi_id) use ($conn, $u, $tabel, $view, $urut_view, $pk, $tindakan_tabel, &$cache_tiket): bool {
    for ($coba = 0; $coba < 5; $coba++) {
        $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
        $st = $conn->prepare("SELECT v.`$pk` FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk`
                              WHERE (v.Sesi IS NULL OR v.Sesi = '') AND $sc
                              ORDER BY $urut_view, v.`$pk` ASC LIMIT 1");
        $st->execute($p);
        $id = $st->fetchColumn();
        if ($id === false) return false;
        $up = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = :sesi
                              WHERE t.`$pk` = :id AND t.StatusData = 'AKTIF' AND (t.Sesi IS NULL OR t.Sesi = '')
                                AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`)");
        $up->execute([':sesi' => $sesi_id, ':id' => $id]);
        if ($up->rowCount() === 1) { $cache_tiket = []; return true; }
    }
    return false;
};

/** Langkah 2b: BUAT tiket baru atas nama SessionID. ID tiket diisi TRIGGER database, lalu dibaca kembali. '' bila tidak ada sumber. Panggil di dalam transaksi. */
/** Langkah 2: ambil tiket antrean terdepan dari VIEW (SkalaPrioritas 9->1, lalu Peringkat 1->n). Hasil: '' (tidak ada) | 'ambil'. Tiket tidak dibuat dari tabel mana pun. */
$ambil_berikutnya = function (string $sesi_id) use ($klaim): string {
    return $klaim($sesi_id) ? 'ambil' : '';
};

$ambil_trx = function (string $sesi_id) use ($conn, $ambil_berikutnya): string {
    for ($coba = 1; ; $coba++) {
        $conn->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $conn->beginTransaction();
        try {
            $asal = $ambil_berikutnya($sesi_id);
            $conn->commit();
            return $asal;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $kode = $e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0;
            if ($coba < 4 && in_array($kode, [1213, 1205], true)) { usleep(random_int(80, 300) * 1000); continue; }   // deadlock / lock wait antar sesi: ulangi
            throw $e;
        }
    }
};

/** Langkah 3: tiket milik SessionID yang masih terbuka, dibaca dari VIEW (null bila tidak ada).
 *  Tabel tiket di-JOIN untuk wilayah akun (Unit) dan kolom rujukan $fk; tabel putus di-JOIN untuk No TUL. */
$tiket_sekarang = function (string $sesi_id) use ($conn, $u, $tabel, $view, $pk, $fk, $putus_tabel, &$cache_tiket): ?array {
    if ($sesi_id === '') return null;
    if (array_key_exists($sesi_id, $cache_tiket)) return $cache_tiket[$sesi_id];
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT v.*, t.`$fk`, p.NoTul AS NoTulTampil FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk` LEFT JOIN `$putus_tabel` p ON p.`$fk` = t.`$fk` WHERE v.Sesi = :sesi AND $sc ORDER BY v.`$pk` ASC LIMIT 1");
    $st->execute([':sesi' => $sesi_id] + $p);
    return $cache_tiket[$sesi_id] = ($st->fetch(PDO::FETCH_ASSOC) ?: null);
};

/** Cek ringan (tanpa VIEW, hanya tabel ber-indeks): tiket aktif milik sesi yang belum ditindak. Dipakai validasi saat tombol tindakan ditekan. */
$tiket_aktif = function (string $sesi_id) use ($conn, $u, $tabel, $pk, $tindakan_tabel): ?array {
    if ($sesi_id === '') return null;
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT t.`$pk`, t.JenisTiket FROM `$tabel` t WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF'
                          AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`) AND $sc ORDER BY t.`$pk` ASC LIMIT 1");
    $st->execute([':sesi' => $sesi_id] + $p);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
};

/** Nama, nomor meter (NomorMeter) dan nomor HP dari DIL (cadangan bila kolom di tiket kosong). */
$ambil_dil = function (string $idpel) use ($conn): array {
    $st = $conn->prepare("SELECT Nama, NomorMeter, NomorHp FROM dil WHERE IdPel = ?");
    $st->execute([$idpel]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['nama' => trim((string) ($r['Nama'] ?? '')), 'meter' => trim((string) ($r['NomorMeter'] ?? '')), 'hp' => trim((string) ($r['NomorHp'] ?? ''))];
};

$detail_fk = function (string $pesan): string {            // ambil nama tabel & constraint dari pesan MySQL untuk memudahkan pelacakan
    $tabel_anak = preg_match('/fails \(([^,]+),/', $pesan, $m1) ? $m1[1] : '';
    if (!preg_match('/CONSTRAINT (\S+) FOREIGN KEY \(([^)]*)\) REFERENCES (\S+) \(([^)]*)\)/', $pesan, $m)) return '';
    return ' <span class="small">Detail: tabel ' . pg_e($tabel_anak) . ', kolom ' . pg_e($m[2]) . ' harus ada di ' . pg_e($m[3]) . ' (' . pg_e($m[4]) . '), constraint ' . pg_e($m[1]) . '.</span>';
};

$pesan_habis = 'Tidak ada tiket penyalaan yang tersedia di antrean saat ini.';

/** Jumlah tiket yang bisa diambil sekarang: baris view tiket nyala yang belum punya Sesi. Dipakai tampilan halaman dan pengecekan berkala (?hitung=1). */
$hitung_sisa = function () use ($conn, $u, $tabel, $view, $pk): int {
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT COUNT(*) FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk` WHERE (v.Sesi IS NULL OR v.Sesi = '') AND $sc");
    $st->execute($p);
    return (int) $st->fetchColumn();
};

// ---- BATAS WAKTU TIKET: tiap tiket yang tampil diberi $batas_diam detik (5 menit), seperti mengerjakan tugas ber-timer ----
// Hitung mundur mulai saat tiket pertama kali muncul di sesi ini dan MULAI ULANG tiap berganti tiket. Waktu habis (tiket belum ditindak):
// tiket dilepas kembali ke antrean, SessionID dibuang dari session login (sesi dihapus otomatis). Sesi tanpa tiket tidak berbatas waktu.
$batas_diam       = 300;                                 // detik (5 menit)
$kunci_aktif      = 'pg_mulai_' . $halaman;              // ['tiket' => id, 'mulai' => epoch] tiket yang sedang dihitung mundur
$sesi_kedaluwarsa = false;
$sisa_detik       = null;                                // sisa waktu tiket berjalan (detik) | null bila tidak ada tiket
/** Sisa detik untuk tiket $id; tiket baru (berbeda dari yang tercatat) memulai hitung mundur penuh. */
$timer_tiket = function (string $id) use ($kunci_aktif, $batas_diam): int {
    $m = $_SESSION[$kunci_aktif] ?? null;
    if (!is_array($m) || (string) ($m['tiket'] ?? '') !== $id || (int) ($m['mulai'] ?? 0) <= 0) {
        $m = ['tiket' => $id, 'mulai' => time()];
        $_SESSION[$kunci_aktif] = $m;
    }
    return max(0, $batas_diam - (time() - (int) $m['mulai']));
};
if ($unit_siap && $sesi !== '') {
    try {
        $ta = $tiket_aktif($sesi);
        if (!$ta) {
            unset($_SESSION[$kunci_aktif]);              // tidak ada tiket berjalan: tidak ada hitung mundur
        } else {
            $sisa_detik = $timer_tiket((string) $ta[$pk]);
            if ($sisa_detik <= 0) {
                $st = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = NULL WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF'
                                      AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`)");
                $st->execute([':sesi' => $sesi]);
                $dilepas = $st->rowCount();
                unset($_SESSION[$kunci_sesi], $_SESSION[$kunci_aktif]);
                $_SESSION['pg_flash']['info'] = trim(($_SESSION['pg_flash']['info'] ?? '') . ' Waktu ' . round($batas_diam / 60) . ' menit untuk tiket <strong>' . pg_e((string) $ta[$pk]) . '</strong> habis, sesi <strong>' . pg_e($sesi) . '</strong> dihapus otomatis.'
                                              . ($dilepas > 0 ? ' ' . pg_n($dilepas) . ' tiket yang belum selesai dilepas kembali ke antrean.' : ''));
                $sesi = ''; $sesi_kedaluwarsa = true; $sisa_detik = null;
            }
        }
    } catch (Throwable $e) { $sisa_detik = $sisa_detik === 0 ? 15 : $sisa_detik; /* gagal melepas: sesi dipertahankan, dicoba lagi sebentar lagi */ }
}

// ---- pengecekan berkala (JSON): jumlah tiket tersedia + tiket milik sesi ini; dipanggil JavaScript halaman tiap beberapa detik ----
// Letaknya SEBELUM pembacaan flash, supaya pengecekan berkala tidak menghabiskan pesan hasil proses.
if (isset($_GET['hitung'])) {
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) session_write_close();   // lepas kunci session agar halaman lain tidak menunggu
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    try {
        $t_saya = $unit_siap ? $tiket_sekarang($sesi) : null;
        // Sesi sudah ada tetapi belum memegang tiket (tadinya antrean kosong): ambil otomatis begitu tiket tersedia.
        if ($unit_siap && !$t_saya && $sesi !== '' && $ambil_trx($sesi) !== '') $t_saya = $tiket_sekarang($sesi);
        echo json_encode(['kedaluwarsa' => $sesi_kedaluwarsa, 'detik' => $sisa_detik, 'sesi' => $sesi, 'sisa' => $unit_siap ? $hitung_sisa() : null, 'tiket' => $t_saya ? (string) $t_saya[$pk] : '', 'waktu' => date('H:i:s')]);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['error' => 'gagal']);
    }
    exit;
}

// ---- hasil proses sebelumnya (post-redirect-get) ----
if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? ''; $pesan_info = $fl['info'] ?? '';
}

// ---------------------------------------------------------
// POST: create | ready | gagal | belum (KOREKTIF saja)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $info_baru = '';
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        } elseif (!$unit_siap) {
            $pesan_error = 'Unit akun Anda belum diatur, sehingga tidak bisa membuat tiket.';
        } elseif ($aksi === 'create') {
            if ($sesi !== '') {
                $pesan_error = 'Sesi <strong>' . pg_e($sesi) . '</strong> sudah ada. Hapus sesi dahulu bila ingin membuat sesi baru.';
            } else {
                // Sesi boleh dibuat walau antrean kosong. Tiket diambil bila ada; bila belum, diambil otomatis nanti selama sesi ini aktif.
                $sesi_id = $buat_sesi();                                            // langkah 1
                $_SESSION[$kunci_sesi] = $sesi_id;
                $asal = $ambil_trx($sesi_id);                                       // langkah 2
                if ($asal !== '') {
                    $t = $tiket_sekarang($sesi_id);                                 // langkah 3
                    $pesan_sukses = 'Sesi <strong>' . pg_e($sesi_id) . '</strong> dibuat. Tiket <strong>' . pg_e($t[$pk] ?? '') . '</strong> diambil dari antrean dan siap diproses.';
                } else {
                    $pesan_sukses = 'Sesi <strong>' . pg_e($sesi_id) . '</strong> dibuat.';
                    $info_baru = $pesan_habis;
                }
            }
        } elseif ($aksi === 'hapus_sesi') {
            // Hapus sesi: tiket sesi ini yang belum selesai (AKTIF, belum ditindak) dilepas kembali ke antrean (Sesi dikosongkan),
            // lalu SessionID dibuang dari session login. Tiket yang sudah ditindak tetap menyimpan Sesi-nya sebagai riwayat.
            if ($sesi === '') {
                $pesan_error = 'Tidak ada sesi aktif untuk dihapus.';
            } else {
                $st = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = NULL WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF'
                                      AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`)");
                $st->execute([':sesi' => $sesi]);
                $dilepas = $st->rowCount();
                unset($_SESSION[$kunci_sesi], $_SESSION[$kunci_aktif]);
                $pesan_sukses = 'Sesi <strong>' . pg_e($sesi) . '</strong> dihapus.'
                              . ($dilepas > 0 ? ' ' . pg_n($dilepas) . ' tiket yang belum selesai dilepas kembali ke antrean.' : '');
            }
        } elseif (isset($jenis_tindakan[$aksi])) {
            $t = $tiket_aktif($sesi);
            if (!$t || (string) $t[$pk] !== trim((string) ($_POST['tiket'] ?? ''))) {
                $pesan_error = 'Tiket yang Anda tindak bukan tiket aktif pada sesi ini (mungkin sudah diproses atau halaman kedaluwarsa). Muat ulang halaman.';
            } elseif ($aksi === 'belum' && strtoupper(trim((string) ($t['JenisTiket'] ?? ''))) !== 'KOREKTIF') {
                $pesan_error = 'Belum Lunas hanya berlaku untuk tiket KOREKTIF.';
            } else {
                // langkah 4: catat hasil tindakan (disimpan sendiri, tidak ikut batal bila pengambilan tiket berikutnya bermasalah)
                $conn->prepare("INSERT INTO `$tindakan_tabel` (`$pk`, JenisTindakan, NamaAkun, WaktuData) VALUES (?, ?, ?, NOW())")
                     ->execute([$t[$pk], $jenis_tindakan[$aksi], $akun]);
                $cache_tiket = [];
                $pesan_sukses = 'Tindakan <strong>' . pg_e($jenis_tindakan[$aksi]) . '</strong> dicatat untuk tiket <strong>' . pg_e($t[$pk]) . '</strong>.';
                // langkah 5 -> langkah 2: ambil/buat tiket berikutnya dalam transaksi terpisah
                try {
                    $asal = $ambil_trx($sesi);
                    $info_baru = $asal !== '' ? '' : $pesan_habis;
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    $info_baru = 'Tiket berikutnya belum bisa dibuat saat ini (' . pg_e($e->getMessage()) . '). Halaman akan mencoba lagi otomatis.';
                }
            }
        } else {
            $pesan_error = 'Aksi tidak dikenal.';
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $kode = $e->errorInfo[1] ?? 0;
        $pesan_error = $kode === 1062 ? 'Tiket ini sudah pernah dicatat tindakannya.'
                     : ($kode === 1452 ? 'Ditolak database (data rujukan tiket/akun/unit tidak valid).' . $detail_fk($e->getMessage())
                     : 'Gagal memproses: ' . pg_e($e->getMessage()));
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $pesan_error = 'Gagal memproses: ' . pg_e($e->getMessage());
    }

    // Post-Redirect-Get: refresh tidak mengulang proses.
    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'info' => trim($pesan_info . ' ' . $info_baru)];
        header('Location: ' . pg_url($halaman));
        exit;
    }
}

// ---------------------------------------------------------
// TAMPIL: tiket yang sedang diproses pada SessionID aktif
// ---------------------------------------------------------
// ---------------------------------------------------------
// Sesi SUDAH ADA (dibuat lewat tombol Create) tetapi belum memegang tiket: ambil tiket otomatis saat halaman dibuka / dimuat ulang.
// Halaman TIDAK membuat sesi sendiri. (Pengecekan berkala ?hitung=1 juga mengambil tiket begitu tersedia.)
// ---------------------------------------------------------
if ($unit_siap && $sesi !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!$tiket_sekarang($sesi) && $ambil_trx($sesi) !== '' && $pesan_sukses === '') {
            $pesan_sukses = 'Tiket diambil otomatis untuk sesi <strong>' . pg_e($sesi) . '</strong> dan siap diproses.';
        }
    } catch (Throwable $e) {
        $pesan_error = trim($pesan_error . ' Gagal mengambil tiket: ' . pg_e($e->getMessage()));
    }
}

$row = null; $sisa = null;
if ($unit_siap) {
    try {
        $row = $tiket_sekarang($sesi);
        $sisa_detik = $row ? $timer_tiket((string) $row[$pk]) : null;   // hitung mundur tiket berjalan (mulai ulang bila tiket baru)
        if ($row) {
            $d = $ambil_dil((string) $row['IdPel']);
            $row['nama']       = trim((string) ($row['Nama'] ?? ''));   // nama dari kolom Nama pada VIEW (bukan DIL)
            $row['meter_tpl']  = trim((string) $row['NomorMeter']) !== '' ? trim((string) $row['NomorMeter']) : $d['meter'];
            $row['hp_tpl']     = trim((string) $row['NomorHp']) !== '' ? trim((string) $row['NomorHp']) : $d['hp'];
        }
        $sisa = $hitung_sisa();
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat tiket: ' . pg_e($e->getMessage()));
    }
}

include __DIR__ . '/../../templates/penggunaan/penyalaan_ami.php';