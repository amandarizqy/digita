<?php
// modules/penggunaan/pemutusan_amr.php  -  Pemutusan AMR (tiket: trx_order_putus_amr, hasil tindakan: trx_tindakan_putus_amr)
// Halaman berdiri sendiri; tampilan: templates/penggunaan/pemutusan_amr.php
//
// Alur:
//   1. [Create]  -> buat SessionID baru (disimpan di session login, per halaman).
//   2. Ambil tiket AKTIF terlama yang belum punya Sesi (tiket pemutusan dibuat TRIGGER database saat tul_601 diisi):
//        dipilih dari view vw_tiket_putus_amr_open (urut: $urut_view), lalu UPDATE tabel tiket SET Sesi = SessionID
//   3. Tampilkan tiket milik SessionID yang belum ditindak.
//   4. Tombol hasil menulis ke trx_tindakan_putus_amr:  Ready OFF = PADAM, Gagal OFF = GAGAL, Lunas = LUNAS
//   5. Kembali ke langkah 2 (tiket berikutnya diambil otomatis dengan SessionID yang sama).
// Tiket pemutusan dibuat TRIGGER database saat tabel tul_601 diisi (Sesi masih kosong). Halaman ini hanya MENGAMBIL dan MENGHITUNG tiket itu,
// dan mengecek jumlahnya berkala (?hitung=1) karena banyak pengguna memakainya bersamaan.
// Semua dibatasi wilayah akun (pg_scope). NamaAkun tiket & tindakan = akun login.

// ---- PENGATURAN ----
$halaman        = 'pemutusan_amr';
$judul          = 'Pemutusan AMR';
$tabel          = 'trx_order_putus_amr';          // tabel tiket
$view           = 'vw_tiket_putus_amr_open';   // view tiket terbuka (dibaca untuk antrean, hitungan & tiket sesi); UPDATE Sesi tetap ke $tabel
$urut_view      = 'v.NomorUrut ASC';            // urutan antrean dari kolom view (NomorUrut / Peringkat / SkalaPrioritas)
$pk             = 'TiketPutusAmr';             // kunci tiket
$thbl_len       = 8;             // panjang kolom ThblRek pada tabel tiket
$tindakan_tabel = 'trx_tindakan_putus_amr';           // hasil tindakan (kunci = $pk, + JenisTindakan, NamaAkun, WaktuData)
$jenis_tindakan = [                     // tombol => nilai JenisTindakan
    'ready' => 'PADAM',                 // Ready OFF: meter sudah OFF -> pemutusan berhasil
    'gagal' => 'GAGAL',                 // Gagal OFF: pemutusan gagal
    'lunas' => 'LUNAS',                 // Lunas
];
// Tautan tombol. Isi URL lengkap bila sudah ada; '#' = belum diatur (tombol hanya menampilkan pesan).
$link = ['ppob' => 'https://www.hotelmurah.com/pulsa/pln', 'amicon_mohon' => 'https://amicon.pln.co.id/#/shunttrip/request_pemutusan', 'amicon_setuju' => 'https://amicon.pln.co.id/#/shunttrip/app_niaga', 'amicon_eksekusi' => 'https://amicon.pln.co.id/#/shunttrip/app_TE'];

$pesan_sukses = $pesan_error = $pesan_info = '';
if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf      = $_SESSION['pg_csrf'];
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$akun      = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
$putus     = true;
$kunci_sesi = 'pg_sesi_' . $halaman;                  // SessionID aktif halaman ini
$sesi       = (string) ($_SESSION[$kunci_sesi] ?? '');

// ---- pembantu lokal ----
/** SessionID: S + yymmddHHMMSS + '-' + 6 hex acak. Contoh: S261106153012-A1B2C3 */
$buat_sesi = fn() => 'S' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

/** Langkah 2a: pilih tiket terdepan dari VIEW (belum punya Sesi, di wilayah akun), lalu tandai tabel tiket dengan SessionID.
 *  View tidak punya kolom Unit & tidak bisa di-UPDATE, jadi: SELECT dari view (di-JOIN ke tabel hanya untuk pg_scope), UPDATE ke tabel.
 *  UPDATE dijaga (Sesi masih kosong, AKTIF, belum ditindak); bila keduluan pengguna lain, ulangi memilih (maks 5 kali). true bila ada yang terambil. */
$klaim = function (string $sesi_id) use ($conn, $u, $tabel, $view, $urut_view, $pk, $tindakan_tabel): bool {
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
        if ($up->rowCount() === 1) return true;
    }
    return false;
};

/** Langkah 2: ambil tiket antrean terlama yang belum punya Sesi. Hasil: '' (tidak ada) | 'ambil'. */
$ambil_berikutnya = function (string $sesi_id) use ($klaim): string {
    return $klaim($sesi_id) ? 'ambil' : '';
};

/** Langkah 3: tiket milik SessionID yang masih terbuka, dibaca dari VIEW (null bila tidak ada). */
$tiket_sekarang = function (string $sesi_id) use ($conn, $u, $tabel, $view, $pk): ?array {
    if ($sesi_id === '') return null;
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT v.*, v.NoTul AS NoTulTampil FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk` WHERE v.Sesi = :sesi AND $sc ORDER BY v.`$pk` ASC LIMIT 1");
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

$pesan_habis = 'Belum ada tiket pemutusan yang tersedia di wilayah Anda. Tiket baru muncul otomatis setelah TUL diunggah (S41 > Upload TUL); jumlahnya dicek berkala.';

/** Jumlah tiket yang bisa diambil sekarang: baris VIEW yang belum punya Sesi, di wilayah akun.
 *  (Tiket pemutusan dibuat TRIGGER database saat tabel tul_601 diisi; halaman ini hanya menghitung dan mengambilnya.)
 *  Dipakai tampilan halaman dan pengecekan berkala (?hitung=1). */
$hitung_sisa = function () use ($conn, $u, $tabel, $view, $pk): int {
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT COUNT(*) FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk` WHERE (v.Sesi IS NULL OR v.Sesi = '') AND $sc");
    $st->execute($p);
    return (int) $st->fetchColumn();
};

// ---- pengecekan berkala (JSON): jumlah tiket tersedia + tiket milik sesi ini; dipanggil JavaScript halaman tiap beberapa detik ----
// Letaknya SEBELUM pembacaan flash, supaya pengecekan berkala tidak menghabiskan pesan hasil proses.
if (isset($_GET['hitung'])) {
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) session_write_close();   // lepas kunci session agar halaman lain tidak menunggu
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    try {
        $t_saya = $unit_siap ? $tiket_sekarang($sesi) : null;
        echo json_encode(['sisa' => $unit_siap ? $hitung_sisa() : null, 'tiket' => $t_saya ? (string) $t_saya[$pk] : '', 'waktu' => date('H:i:s')]);
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
// POST: create | ready | gagal | lunas
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
            if ($tiket_sekarang($sesi)) {
                $pesan_error = 'Masih ada tiket yang sedang diproses pada sesi ini. Selesaikan dengan salah satu tombol hasil dahulu.';
            } else {
                $sesi_baru = $buat_sesi();                                          // langkah 1
                $conn->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
                $conn->beginTransaction();
                try {
                    $asal = $ambil_berikutnya($sesi_baru);                          // langkah 2
                    $conn->commit();
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    throw $e;
                }
                if ($asal !== '') {
                    $_SESSION[$kunci_sesi] = $sesi_baru;
                    $t = $tiket_sekarang($sesi_baru);                               // langkah 3
                    $pesan_sukses = 'Sesi <strong>' . pg_e($sesi_baru) . '</strong> dimulai. Tiket <strong>' . pg_e($t[$pk] ?? '') . '</strong> '
                                  . ($asal === 'baru' ? 'dibuat dan siap diproses.' : 'diambil dari antrean dan siap diproses.');
                } else {
                    unset($_SESSION[$kunci_sesi]);
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
                unset($_SESSION[$kunci_sesi]);
                $pesan_sukses = 'Sesi <strong>' . pg_e($sesi) . '</strong> dihapus.'
                              . ($dilepas > 0 ? ' ' . pg_n($dilepas) . ' tiket yang belum selesai dilepas kembali ke antrean.' : '');
            }
        } elseif (isset($jenis_tindakan[$aksi])) {
            $t = $tiket_sekarang($sesi);
            if (!$t || (string) $t[$pk] !== trim((string) ($_POST['tiket'] ?? ''))) {
                $pesan_error = 'Tiket yang Anda tindak bukan tiket aktif pada sesi ini (mungkin sudah diproses atau halaman kedaluwarsa). Muat ulang halaman.';
            } else {
                $conn->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
                $conn->beginTransaction();
                try {
                    // langkah 4: catat hasil tindakan
                    $conn->prepare("INSERT INTO `$tindakan_tabel` (`$pk`, JenisTindakan, NamaAkun, WaktuData) VALUES (?, ?, ?, NOW())")
                         ->execute([$t[$pk], $jenis_tindakan[$aksi], $akun]);
                    $asal = $ambil_berikutnya($sesi);                               // langkah 5 -> langkah 2
                    $conn->commit();
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    throw $e;
                }
                $pesan_sukses = 'Tindakan <strong>' . pg_e($jenis_tindakan[$aksi]) . '</strong> dicatat untuk tiket <strong>' . pg_e($t[$pk]) . '</strong>.';
                $info_baru = $asal !== '' ? '' : $pesan_habis;
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
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'info' => $info_baru];
        header('Location: ' . pg_url($halaman));
        exit;
    }
}

// ---------------------------------------------------------
// TAMPIL: tiket yang sedang diproses pada SessionID aktif
// ---------------------------------------------------------
$row = null; $sisa = null;
if ($unit_siap) {
    try {
        $row = $tiket_sekarang($sesi);
        if ($row) {
            $d = $ambil_dil((string) $row['IdPel']);
            $row['nama']       = $d['nama'];
            $row['meter_tpl']  = trim((string) $row['NomorMeter']) !== '' ? trim((string) $row['NomorMeter']) : $d['meter'];
            $row['hp_tpl']     = trim((string) $row['NomorHp']) !== '' ? trim((string) $row['NomorHp']) : $d['hp'];
        }
        $sisa = $hitung_sisa();
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat tiket: ' . pg_e($e->getMessage()));
    }
}

include __DIR__ . '/../../templates/penggunaan/pemutusan_amr.php';