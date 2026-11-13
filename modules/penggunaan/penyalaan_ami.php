<?php
// modules/penggunaan/penyalaan_ami.php  -  Penyalaan AMI (tiket: trx_order_nyala_ami, hasil tindakan: trx_tindakan_nyala_ami)
// Halaman berdiri sendiri; tampilan: templates/penggunaan/penyalaan_ami.php
//
// Alur:
//   1. [Create]  -> buat SessionID baru (disimpan di session login, per halaman).
//   2. Ambil tiket: pertama coba tiket AKTIF terlama yang belum diambil
//        UPDATE trx_order_nyala_ami SET Sesi = SessionID ... ORDER BY TiketNyalaAmi ASC LIMIT 1
//      bila tidak ada, BUAT tiket baru atas nama SessionID: tiket PREVENTIF dari tiket putus AKTIF (trx_order_putus_ami) yang belum punya tiket nyala
//   3. Tampilkan tiket milik SessionID yang belum ditindak.
//   4. Tombol hasil menulis ke trx_tindakan_nyala_ami:  Ready ON = NYALA, gagal ON = GAGAL, Lunas = LUNAS  (nilai NYALA/GAGAL/LUNAS = asumsi, sesuaikan enum tabel)
//   5. Kembali ke langkah 2 (tiket berikutnya diambil/dibuat otomatis dengan SessionID yang sama).
// ID tiket dibuat oleh TRIGGER database (BEFORE INSERT pada tabel tiket). PHP tidak membuat ID: INSERT memakai nilai kosong, lalu ID dibaca kembali.
// Semua dibatasi wilayah akun (pg_scope). NamaAkun tiket & tindakan = akun login.

// ---- PENGATURAN ----
$halaman        = 'penyalaan_ami';
$judul          = 'Penyalaan AMI';
$tabel          = 'trx_order_nyala_ami';          // tabel tiket
$pk             = 'TiketNyalaAmi';             // kunci tiket
$thbl_len       = 8;             // panjang kolom ThblRek pada tabel tiket
$tindakan_tabel = 'trx_tindakan_nyala_ami';           // hasil tindakan (kunci = $pk, + JenisTindakan, NamaAkun, WaktuData)
$jenis_tindakan = [                     // tombol => nilai JenisTindakan
    'ready_on'  => 'NYALA',             // Ready ON : meter sudah ON  -> penyalaan berhasil
    'gagal_on' => 'GAGAL',             // gagal ON: meter masih OFF -> penyalaan gagal
    'lunas'     => 'LUNAS',             // Lunas
];
$fk             = 'TiketPutusAmi';          // kolom rujukan ke tiket putus
$putus_tabel    = 'trx_order_putus_ami';   // tabel tiket putus (sumber tiket nyala; juga untuk menampilkan No TUL)
// FK tiket nyala merujuk ke tabel TINDAKAN putus: tiket nyala hanya bisa dibuat untuk tiket putus yang sudah punya tindakan.
$putus_tindakan_tabel  = 'trx_tindakan_putus_ami';
$putus_tindakan_syarat = ['PADAM'];      // jenis tindakan putus yang boleh dinyalakan (hanya meter yang benar-benar diputus)
$syarat_putus = " AND EXISTS (SELECT 1 FROM `$putus_tindakan_tabel` pt WHERE pt.`$fk` = p.`$fk` AND pt.JenisTindakan IN ("
              . implode(',', array_map(fn($j) => $conn->quote($j), $putus_tindakan_syarat)) . "))";
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

/** Langkah 2a: tandai tiket AKTIF yang belum diambil & belum ditindak dengan SessionID (yang terlama). true bila ada yang terambil. */
$klaim = function (string $sesi_id) use ($conn, $u, $tabel, $pk, $tindakan_tabel): bool {
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = :sesi
                          WHERE t.StatusData = 'AKTIF' AND (t.Sesi IS NULL OR t.Sesi = '') AND $sc
                            AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`)
                          ORDER BY t.`$pk` ASC LIMIT 1");
    $st->execute([':sesi' => $sesi_id] + $p);
    return $st->rowCount() === 1;
};

/** Langkah 2b: BUAT tiket baru atas nama SessionID. ID tiket diisi TRIGGER database, lalu dibaca kembali. '' bila tidak ada sumber. Panggil di dalam transaksi. */
$buat_tiket = function (string $sesi_id) use ($conn, $u, $akun, $tabel, $pk, $thbl_len, $fk, $putus_tabel, $syarat_putus): string {
    // Sumber: tiket putus AKTIF di wilayah akun, sudah ditindak PADAM, dan belum punya tiket nyala AKTIF.
    $p  = []; $sc = pg_scope($u, 'p', 'Unit', $p);
    $st = $conn->prepare("SELECT p.`$fk` AS rujukan, p.IdPel, p.ThblRek, p.NomorMeter, p.NomorHp, p.UnitUp, p.UnitAp, p.UnitUpi
                          FROM `$putus_tabel` p
                          WHERE p.StatusData = 'AKTIF' AND $sc $syarat_putus
                            AND NOT EXISTS (SELECT 1 FROM `$tabel` n WHERE n.`$fk` = p.`$fk` AND n.StatusData = 'AKTIF')
                          ORDER BY p.`$fk` ASC LIMIT 1 FOR UPDATE");
    $st->execute($p);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return '';
    $meter = mb_substr(trim((string) $r['NomorMeter']), 0, 15);
    $hp    = mb_substr(trim((string) $r['NomorHp']), 0, 20);
    // Kolom kunci diisi '' : nilai sebenarnya ditetapkan trigger BEFORE INSERT.
    $ins = $conn->prepare("INSERT INTO `$tabel` (`$pk`, JenisTiket, `$fk`, IdPel, ThblRek, NomorMeter, NomorHp, Sesi, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                           VALUES ('', 'PREVENTIF', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', NOW())");
    for ($coba = 0; $coba < 10; $coba++) {
        try {
            $ins->execute([$r['rujukan'], $r['IdPel'], mb_substr((string) $r['ThblRek'], 0, $thbl_len), $meter, $hp, $sesi_id, $akun, $r['UnitUp'], $r['UnitAp'], $r['UnitUpi']]);
            $q = $conn->prepare("SELECT `$pk` FROM `$tabel` WHERE `$fk` = ? AND Sesi = ? AND StatusData = 'AKTIF' ORDER BY `$pk` DESC LIMIT 1");
            $q->execute([$r['rujukan'], $sesi_id]);
            $id = (string) $q->fetchColumn();
            if ($id === '') throw new RuntimeException('Tiket tersimpan tetapi ID dari trigger tidak terbaca.');
            return $id;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;          // nomor urut trigger bentrok dengan sesi lain -> coba lagi (trigger hitung ulang)
        }
    }
    throw new RuntimeException('Gagal mendapatkan nomor tiket unik. Coba lagi.');
};

/** Langkah 2: ambil tiket antrean terlama; bila tak ada, buat baru. Hasil: '' (tidak ada) | 'ambil' | 'baru'. */
$ambil_berikutnya = function (string $sesi_id) use ($klaim, $buat_tiket): string {
    if ($klaim($sesi_id)) return 'ambil';
    return $buat_tiket($sesi_id) !== '' ? 'baru' : '';
};

/** Langkah 3: tiket milik SessionID yang belum ditindak (null bila tidak ada). */
$tiket_sekarang = function (string $sesi_id) use ($conn, $u, $tabel, $pk, $tindakan_tabel, $fk, $putus_tabel): ?array {
    if ($sesi_id === '') return null;
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT t.*, p.NoTul AS NoTulTampil FROM `$tabel` t LEFT JOIN `$putus_tabel` p ON p.`$fk` = t.`$fk` WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF' AND $sc AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`) ORDER BY t.`$pk` ASC LIMIT 1");
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

$pesan_habis = 'Tidak ada tiket putus yang bisa dibuatkan tiket nyala. Syaratnya: tiket putus sudah ditindak (gagal ON = PADAM) dan belum punya tiket nyala.';

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
                $st = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = '' WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF'
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
        // jumlah yang masih bisa diambil/dibuat di wilayah akun
        $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
        $st = $conn->prepare("SELECT COUNT(*) FROM `$tabel` t WHERE t.StatusData = 'AKTIF' AND (t.Sesi IS NULL OR t.Sesi = '') AND $sc
                              AND NOT EXISTS (SELECT 1 FROM `$tindakan_tabel` x WHERE x.`$pk` = t.`$pk`)");
        $st->execute($p);
        $sisa = (int) $st->fetchColumn();
        // ditambah tiket putus yang belum punya tiket nyala (akan dibuatkan tiket saat Create)
        $p  = []; $sc = pg_scope($u, 'p', 'Unit', $p);
        $st = $conn->prepare("SELECT COUNT(*) FROM `$putus_tabel` p WHERE p.StatusData = 'AKTIF' AND $sc $syarat_putus AND NOT EXISTS (SELECT 1 FROM `$tabel` n WHERE n.`$fk` = p.`$fk` AND n.StatusData = 'AKTIF')");
        $st->execute($p);
        $sisa += (int) $st->fetchColumn();
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat tiket: ' . pg_e($e->getMessage()));
    }
}

include __DIR__ . '/../../templates/penggunaan/penyalaan_ami.php';