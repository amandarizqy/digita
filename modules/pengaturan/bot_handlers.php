<?php
// modules/pengaturan/bot_handlers.php
// Logika unggah per JenisBot, dipakai oleh bot_cron.php (CLI). Tidak memakai $_SESSION.
// Aturan validasi & penyimpanan SAMA dengan halaman web-nya (lihat bot_jenis_daftar()['sumber']);
// bedanya: wilayah tujuan diambil dari lokasi folder + baris bot_akses, nama akun = 'BOT_<IdBot>', bukan dari akun yang login.
//
// Setiap handler mengembalikan:
//   ['status' => 'sukses'|'gagal'|'tidak_pasti', 'pesan' => string, 'tersimpan' => int, 'ditolak' => int, 'detail' => string[]]
//   sukses      : minimal 1 baris tersimpan   -> file dipindah ke folder berhasil
//   gagal       : file/isi ditolak permanen   -> file dipindah ke folder gagal (+ .txt alasan)
//   tidak_pasti : gangguan sementara (DB putus, deadlock) -> file dibiarkan, dicoba lagi pada putaran berikutnya
require_once __DIR__ . '/../../functions/xlsx_reader.php';
require_once __DIR__ . '/bot_helper.php';   // aturan folder & cakupan unit (satu sumber dengan bot.php & bot_cron.php)

const BH_CHUNK = 500;          // untuk SELECT ... IN (...) saja (baca, tidak mengunci baris)
const BH_PAKET = 100;          // untuk TULIS: tiap paket = 1 transaksi pendek, langsung commit
const BH_COBA  = 3;            // percobaan ulang paket yang kena deadlock / lock wait

/** Nilai ENUM dil.JenisShuntrip (sama dengan JENIS_SHUNTRIP di penggunaan/input_dil.php). */
const BH_JENIS_SHUNTRIP = ['MCCB', 'RELAY', 'ALARM', 'S41', 'AMI', 'SDU', '-'];
const BH_KOGOL = ['0', '1', '2', '3', '4'];

// ============================================================
// Normalisasi nilai (sama dengan fungsi rw_*/tul_*/pi_* di halaman web)
// ============================================================
function bh_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

function bh_idpel($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))                  $v = preg_replace('/\.0+$/', '', $v);
    elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))   $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
    else return null;
    if (strlen($v) === 11) $v = '0' . $v;                  // nol di depan hilang oleh Excel
    return preg_match('/^\d{12}$/', $v) ? $v : null;
}

function bh_bulan($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
}

/** null = kosong, false = tidak valid, string = Y-m-d */
function bh_tanggal($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))       return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m))   return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : false;
    if (is_numeric($v) && $v > 20000 && $v < 80000)             return gmdate('Y-m-d', ((int) $v - 25569) * 86400);   // tanggal serial Excel
    return false;
}

/** null = kosong, false = tidak valid, string = 'Y-m-d H:i:s' */
function bh_waktu($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $jam = '(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})' . $jam . '/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
    elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})' . $jam . '$#', $v, $m)) { $t = $m[1]; $b = $m[2]; $y = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $i = $s = 0; }
    elseif (is_numeric($v) && $v > 20000 && $v < 80000) {            // tanggal serial Excel (pecahan = jam)
        return gmdate('Y-m-d H:i:s', (int) round(((float) $v - 25569) * 86400));
    } else return false;
    if (!checkdate((int) $b, (int) $t, (int) $y) || (int) $h > 23 || (int) $i > 59 || (int) $s > 59) return false;
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $b, $t, $h, $i, $s);
}

/** Angka >= 0 (float). '' = 0. null = bukan angka. */
function bh_angka($v): ?float
{
    $v = trim((string) $v);
    return $v === '' ? 0.0 : (is_numeric($v) ? (float) $v : null);
}

/** Bilangan bulat >= 0. null = kosong / bukan angka. */
function bh_int($v): ?int
{
    $v = trim((string) $v);
    if ($v === '' || !is_numeric($v) || (float) $v < 0) return null;
    return (int) round((float) $v);
}

/** Rapikan teks sel: buang spasi ganda/tepi lalu potong sesuai panjang kolom. */
function bh_teks($v, int $maks): string
{
    return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $maks);
}

function bh_norm_sr($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return $v === '0' || $v === '1' ? $v : null;
}

function bh_norm_kepentingan($v): ?string
{
    $v = strtoupper(trim((string) $v));
    return in_array($v, ['RENDAH', 'MODERAT', 'TINGGI'], true) ? $v : null;
}

/** IdPel untuk saldo (dpp): angka ilmiah/".0" Excel dibereskan, TANPA pelengkap nol (wajib persis 12 digit). null = bukan angka. */
function bh_idpel_ketat($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))                  return preg_replace('/\.0+$/', '', $v);
    if (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))       return sprintf('%.0f', (float) $v);
    return null;
}

/** Alasan penolakan IdPel saldo (null bila valid: persis 12 digit). */
function bh_idpel_ketat_alasan(?string $v): ?string
{
    if ($v === null)     return 'IdPel harus berupa angka';
    if (strlen($v) < 12) return 'IdPel kurang dari 12 karakter, tidak disimpan';
    if (strlen($v) > 12) return 'IdPel lebih dari 12 karakter, tidak disimpan';
    return null;
}

/** ThBlRek saldo: YYYYMM atau YYYYMMDD. */
function bh_bulan_saldo($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])((0[1-9]|[12]\d|3[01]))?$/', $v) ? $v : null;
}

function bh_periode($v): ?int
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^\d{4}$/', $v) && $v >= 1901 && $v <= 2155 ? (int) $v : null;
}

/** Nama akun pengunggah yang dicatat di kolom NamaAkun (maks 20): 'BOT_<IdBot>'. Tabel bot_akses tidak punya kolom NamaAkun/KodeBot. */
function bh_akun(array $bot): string
{
    return mb_substr('BOT_' . (int) $bot['IdBot'], 0, 20);
}

// ============================================================
// Wilayah
// ============================================================
/** Peta unit yang boleh dipakai bot. Logika ada di bot_helper.php (bot_peta_unit) agar sama dengan cron & web. */
function bh_peta_unit(PDO $conn, string $upi, string $ap = '', string $up = ''): array
{
    return bot_peta_unit($conn, $upi, $ap, $up);
}

// ============================================================
// Pintu masuk
// ============================================================
/** Gangguan sementara yang pantas dicoba ulang (bukan kesalahan data). */
function bh_pdo_sementara(PDOException $e): bool
{
    $m = strtolower($e->getMessage());
    return in_array((string) $e->getCode(), ['40001', '08S01', '08001', '08004', '2002', '2006', '2013'], true)
        || strpos($m, 'gone away') !== false || strpos($m, 'lock wait') !== false
        || strpos($m, 'deadlock') !== false || strpos($m, 'lost connection') !== false;
}

// ============================================================
// Penulisan "per paket kecil, langsung commit"
// ------------------------------------------------------------
// Dulu: satu file = satu transaksi besar -> lock baris (dan lock FK ke tabel induk) ditahan sampai file selesai,
//       sehingga input lain ke tabel yang sama / tabel yang terhubung FK menunggu lama.
// Sekarang: tiap BH_PAKET baris ditulis & di-commit sendiri, lalu lock langsung dilepas.
//   - paket gagal karena deadlock/lock wait -> diulang (BH_COBA kali, jeda acak)
//   - paket gagal karena DATA (FK, duplikat, terlalu panjang, dsb.) -> diulang BARIS PER BARIS,
//     sehingga hanya baris yang bermasalah yang dihitung gagal; baris lain tetap masuk.
//   - gangguan sementara yang tidak kunjung reda -> exception dilempar (file = tidak_pasti, dicoba lagi nanti;
//     aman karena semua tulisan bersifat upsert / INSERT IGNORE sehingga idempoten).
// ============================================================
// ============================================================
// Laporan kemajuan (jumlah data yang sudah masuk) -> dipakai cron untuk memperbarui bot_akses_riwayat
// ============================================================
function &bh_progres_state(): array
{
    static $s = ['cb' => null, 'ok' => 0, 'gagal' => 0, 't' => 0.0];
    return $s;
}

/** Mulai penghitungan baru untuk satu file. $cb(int $berhasil, int $gagal) dipanggil paling sering 1x/detik. */
function bh_progres_atur(?callable $cb): void
{
    $s = &bh_progres_state();
    $s = ['cb' => $cb, 'ok' => 0, 'gagal' => 0, 't' => 0.0];
}

function bh_progres_lapor(bool $paksa = false): void
{
    $s = &bh_progres_state();
    if (!$s['cb']) return;
    $now = microtime(true);
    if (!$paksa && $now - $s['t'] < 1.0) return;
    $s['t'] = $now;
    try { ($s['cb'])($s['ok'], $s['gagal']); } catch (Throwable $e) { /* gagal melapor tidak boleh menghentikan proses data */ }
}

/** Tambah hitungan. Dipanggil di luar transaksi (setelah commit paket). */
function bh_progres_tambah(int $ok, int $gagal): void
{
    $s = &bh_progres_state();
    $s['ok'] += $ok; $s['gagal'] += $gagal;
    bh_progres_lapor();
}

/** Jalankan satu paket dalam satu transaksi pendek. @return int|null rowCount bila sukses, null bila gagal ($err terisi). */
function bh_eksekusi_paket(PDO $conn, callable $sql_untuk, array $paket, ?PDOException &$err = null, ?callable $setelah = null): ?int
{
    $err = null;
    for ($coba = 1; $coba <= BH_COBA; $coba++) {
        try {
            $conn->beginTransaction();
            $st = $conn->prepare($sql_untuk(count($paket)));
            $st->execute(array_merge(...$paket));
            $rc = $st->rowCount();
            if ($setelah) $setelah($conn, $paket);          // langkah tambahan dalam transaksi yang sama (mis. hapus transit_dil)
            $conn->commit();
            return $rc;
        } catch (PDOException $e) {
            try { if ($conn->inTransaction()) $conn->rollBack(); } catch (Throwable $x) { /* koneksi putus: abaikan */ }
            $err = $e;
            if (!bh_pdo_sementara($e)) return null;          // salah data: mengulang paket yang sama tidak ada gunanya
            usleep(150000 * $coba + random_int(0, 150000));  // jeda acak agar dua proses tidak bentrok lagi bersamaan
        }
    }
    return null;
}

/**
 * Tulis $rows (list parameter per baris) dalam paket kecil.
 * @param callable $sql_untuk fn(int $n): string  -> SQL lengkap untuk n baris (placeholder per baris sudah di dalamnya)
 * @param callable|null $setelah fn(PDO, array $paket): void -> dijalankan dalam transaksi paket yang sama
 * @param bool $lapor true -> tiap paket selesai, jumlah berhasil/gagal dilaporkan ke bh_progres_tambah (hanya untuk tulisan utama)
 * @return array ['ok' => int, 'gagal' => int, 'rc' => int (jumlah rowCount sukses), 'gagal_idx' => int[], 'pesan' => [idx => string]]
 * @throws PDOException bila gangguan sementara tidak kunjung reda
 */
function bh_sisip_aman(PDO $conn, array $rows, callable $sql_untuk, ?callable $setelah = null, int $ukuran = BH_PAKET, bool $lapor = false): array
{
    $h = ['ok' => 0, 'gagal' => 0, 'rc' => 0, 'gagal_idx' => [], 'pesan' => []];
    foreach (array_chunk($rows, $ukuran, true) as $paket) {
        $ok0 = $h['ok']; $gl0 = $h['gagal'];
        $rc = bh_eksekusi_paket($conn, $sql_untuk, array_values($paket), $err, $setelah);
        if ($rc !== null) {
            $h['ok'] += count($paket); $h['rc'] += $rc;
            if ($lapor) bh_progres_tambah(count($paket), 0);
            continue;
        }
        if ($err !== null && bh_pdo_sementara($err)) throw $err;       // bukan salah data -> serahkan ke status tidak_pasti

        foreach ($paket as $i => $prm) {                                // paket ditolak: cari baris yang bermasalah
            $rc = bh_eksekusi_paket($conn, $sql_untuk, [$prm], $err, $setelah);
            if ($rc !== null) { $h['ok']++; $h['rc'] += $rc; continue; }
            if ($err !== null && bh_pdo_sementara($err)) throw $err;
            $h['gagal']++;
            $h['gagal_idx'][] = $i;
            $h['pesan'][$i]   = mb_substr($err ? $err->getMessage() : 'gagal', 0, 120);
        }
        if ($lapor) bh_progres_tambah($h['ok'] - $ok0, $h['gagal'] - $gl0);
    }
    return $h;
}

/** Placeholder multi-baris: bh_ph(5)(3) => "(?,?,?,?,?),(?,?,?,?,?),(?,?,?,?,?)" */
function bh_ph(string $per_baris): callable
{
    return fn(int $n): string => implode(',', array_fill(0, $n, $per_baris));
}

function bh_hasil(string $status, string $pesan, int $tersimpan = 0, int $ditolak = 0, array $detail = []): array
{
    return ['status' => $status, 'pesan' => $pesan, 'tersimpan' => $tersimpan, 'ditolak' => $ditolak, 'detail' => $detail];
}

/**
 * Proses satu file untuk satu bot.
 * @param callable|null $progres fn(int $berhasil, int $gagal) untuk memperbarui riwayat selama file diproses
 * @param string $ap,$up  wilayah tujuan dari lokasi folder ('' = tidak ditentukan; hanya boleh untuk DIL yang diletakkan langsung di Direktori Utama)
 */
function bh_jalankan(PDO $conn, array $bot, string $jenis, string $file, string $ap, string $up, ?callable $progres = null): array
{
    bh_progres_atur($progres);        // $progres(int $berhasil, int $gagal): jumlah data yang sudah masuk, dilaporkan selama proses
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    // Jangan menunggu lock terlalu lama (bawaan MySQL 50 dtk): gagal cepat -> bh_eksekusi_paket mengulang.
    try { $conn->exec('SET SESSION innodb_lock_wait_timeout = 10'); } catch (PDOException $e) { /* abaikan */ }

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($file, false, null, 0, 2) !== 'PK') {
        return bh_hasil('gagal', 'Format file harus .xlsx (Excel).');
    }
    if (filesize($file) === 0) return bh_hasil('gagal', 'File kosong (0 byte).');

    try {
        switch ($jenis) {
            case 'PELUNASAN':   return bh_pelunasan($conn, $bot, $file, $ap, $up);
            case 'DIL':         return bh_dil($conn, $bot, $file, $ap, $up);
            case 'TUL':         return bh_tul($conn, $bot, $file, $ap, $up);
            case 'SALDO_TUNGGAKAN': return bh_saldo($conn, $bot, $file, $ap, $up);
            case 'SURVEY':      return bh_kategorisasi($conn, $bot, $file, $ap, $up, [
                                    'kolom' => 'PosisiSR', 'nama_excel' => 'PosisiSR', 'alias' => ['posisisr', 'sr'],
                                    'format' => '0 atau 1', 'norm' => 'bh_norm_sr', 'label' => 'Posisi SR']);
            case 'KEPENTINGAN': return bh_kategorisasi($conn, $bot, $file, $ap, $up, [
                                    'kolom' => 'LevelKepentingan', 'nama_excel' => 'LevelKepentingan', 'alias' => ['levelkepentingan', 'kepentingan'],
                                    'format' => 'RENDAH, MODERAT, atau TINGGI', 'norm' => 'bh_norm_kepentingan', 'label' => 'Level Kepentingan']);
        }
        return bh_hasil('gagal', "Jenis bot '$jenis' tidak dikenal.");
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $pesan = 'Kesalahan database: ' . mb_substr($e->getMessage(), 0, 150);
        return bh_hasil(bh_pdo_sementara($e) ? 'tidak_pasti' : 'gagal', $pesan);
    } catch (RuntimeException $e) {                        // isi/format file tidak sesuai -> file ditolak
        if ($conn->inTransaction()) $conn->rollBack();
        return bh_hasil('gagal', $e->getMessage());
    } catch (Throwable $e) {                               // kesalahan program: JANGAN membuang file, biarkan untuk dicoba lagi
        if ($conn->inTransaction()) $conn->rollBack();
        return bh_hasil('tidak_pasti', 'Kesalahan internal bot: ' . mb_substr($e->getMessage(), 0, 150));
    }
}

/** Wilayah tujuan wajib lengkap (AP + UP) untuk semua jenis kecuali DIL. */
function bh_wajib_wilayah(string $ap, string $up): void
{
    if ($ap === '' || $up === '') throw new RuntimeException('Wilayah tujuan (Unit AP / Unit UP) belum ditentukan oleh lokasi folder (bot level AP/UPI: taruh file di subfolder <KodeUP>).');
}

// ============================================================
// PELUNASAN -> pelunasan_ap2t  (+ kategorisasi_risiko baru)   [perencanaan/upload_riwayat.php]
// IdPel WAJIB sudah ada di dil: yang tidak ada dilewati dan dilaporkan (bot tidak pernah menambah dil).
// ============================================================
function bh_pelunasan(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    bh_wajib_wilayah($ap, $up);
    $upi   = trim((string) $bot['UnitUpi']);
    $akun  = bh_akun($bot);
    $peta_kol = [
        'idpel' => ['idpel'],
        'bulan' => ['bulan', 'thblrek'],            // -> ThBlRek
        'tgl'   => ['tgltransaksi', 'tglbayar'],    // -> TglBayar
        'rpbk'  => ['rpbk'],                        // -> RpBK
        'rptag' => ['rptagihan', 'rptag'],          // -> RpTag
    ];
    $nama_kol = ['idpel' => 'Idpel', 'bulan' => 'Bulan', 'tgl' => 'TglTransaksi', 'rpbk' => 'RpBK', 'rptag' => 'RpTagihan'];

    $baris = []; $kat = []; $detail = [];
    $dibaca = $tolak = $dup = $tidakDil = 0;
    $peta = null;

    foreach (bh_xlsx_baris($file) as $no => $sel) {
        if ($peta === null) {                                     // a. cari baris judul
            if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
            $kol = [];
            foreach ($sel as $huruf => $nilai) $kol[bh_header($nilai)] = $huruf;
            if (!isset($kol['idpel'])) continue;
            $peta = [];
            foreach ($peta_kol as $field => $alias) foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
            $kurang = array_diff(array_keys($peta_kol), array_keys($peta));
            if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $nama_kol[$k], $kurang)) . '.');
            continue;
        }
        if (trim(implode('', $sel)) === '') continue;             // b. baris data
        $g = fn($f) => $sel[$peta[$f]] ?? '';
        // sama dengan halaman web: baris tanpa Idpel DAN tanpa Bulan (footer cetakan di tengah/akhir file) dilewati, tidak dihitung gagal
        if (trim((string) $g('idpel')) === '' && trim((string) $g('bulan')) === '') continue;
        $dibaca++;

        $id  = bh_idpel($g('idpel'));
        $bln = bh_bulan($g('bulan'));
        $tgl = bh_tanggal($g('tgl'));
        $bk  = bh_angka($g('rpbk'));
        $tagRaw = trim((string) $g('rptag'));
        $tag = $tagRaw === '' ? null : bh_angka($tagRaw);

        $alasan = $id === null ? 'IdPel harus 12 digit angka'
                : ($bln === null ? 'Bulan harus format YYYYMM'
                : ($tgl === false ? 'TglTransaksi tidak valid'
                : ($bk === null ? 'RpBK bukan angka'
                : ($tag === null ? 'RpTagihan kosong / bukan angka' : null))));
        if ($alasan !== null) {
            $tolak++;
            if (count($detail) < 50) $detail[] = "Baris $no: IdPel " . trim((string) $g('idpel')) . ' - ' . $alasan;
            continue;
        }
        $kunci = $id . '|' . $bln;
        if (isset($baris[$kunci])) $dup++;                        // baris terakhir yang dipakai
        $baris[$kunci] = [$id, $bln, $tgl, (int) round($bk), (int) round($tag), $no];   // [5] = nomor baris file, untuk laporan
    }
    if ($peta === null) throw new RuntimeException('Baris judul kolom (Idpel, Bulan, ...) tidak ditemukan.');
    // IdPel WAJIB sudah terdaftar di DIL: yang tidak ada dilewati dan dilaporkan (tidak pernah ditambahkan ke DIL)
    if ($baris) {
        $ada = [];
        foreach (array_chunk(array_values(array_unique(array_column($baris, 0))), 1000) as $paket) {
            $st = $conn->prepare("SELECT IdPel FROM dil WHERE IdPel IN (" . implode(',', array_fill(0, count($paket), '?')) . ")");
            $st->execute($paket);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $ada[trim((string) $x)] = true;
        }
        foreach ($baris as $k => $b) {
            if (isset($ada[$b[0]])) continue;
            $tidakDil++;
            if (count($detail) < 50) $detail[] = 'Baris ' . $b[5] . ': IdPel ' . $b[0] . ' tidak terdaftar di DIL, tidak disimpan';
            unset($baris[$k]);
        }
    }
    // kategorisasi_risiko hanya untuk baris yang lolos cek DIL. Periode = tahun dari Bulan
    foreach ($baris as $b) {
        $thn = (int) substr($b[1], 0, 4);
        $kat[$b[0] . '|' . $thn] = [$b[0], $thn];
    }
    $ditolakTotal = $tolak + $tidakDil;
    if (!$baris) return bh_hasil('gagal', 'Tidak ada baris valid yang bisa disimpan' . ($ditolakTotal ? " ($ditolakTotal baris ditolak" . ($tidakDil ? ", $tidakDil di antaranya IdPel tidak ada di DIL" : '') . ').' : '.'), 0, $ditolakTotal, $detail);

    // Tulis per paket kecil (langsung commit). Urutan kunci tetap -> mengurangi deadlock antar proses.
    ksort($baris);
    $daftar = array_values($baris);
    $head = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, NamaAkun, UnitUp, UnitAp, UnitUpi, WaktuData) VALUES ";
    $tail = " ON DUPLICATE KEY UPDATE TglBayar = VALUES(TglBayar), RpBK = VALUES(RpBK), RpTag = VALUES(RpTag),
              NamaAkun = VALUES(NamaAkun), UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi),
              WaktuData = VALUES(WaktuData)";
    $rows = array_map(fn($b) => [$b[0], $b[1], $b[2], $b[3], $b[4], $akun, $up, $ap, $upi], $daftar);
    bh_progres_tambah(0, $ditolakTotal);                       // baris yang sudah ditolak validasi ikut dihitung gagal
    $r = bh_sisip_aman($conn, $rows, fn($n) => $head . bh_ph('(?,?,?,?,?,?,?,?,?,NOW())')($n) . $tail, null, BH_PAKET, true);
    foreach ($r['gagal_idx'] as $i) {
        if (count($detail) < 50) $detail[] = 'Baris ' . $daftar[$i][5] . ': IdPel ' . $daftar[$i][0] . ' - gagal disimpan (' . $r['pesan'][$i] . ')';
    }

    // kategorisasi_risiko: tambah (IdPel, Periode) yang belum ada; yang sudah ada TIDAK diubah. Hanya dari baris yang berhasil.
    $gagalSet = array_flip($r['gagal_idx']);
    $kat = [];
    foreach ($daftar as $i => $b) {
        if (isset($gagalSet[$i])) continue;
        $thn = (int) substr($b[1], 0, 4);
        $kat[$b[0] . '|' . $thn] = [$b[0], $thn, $up, $ap, $upi];
    }
    ksort($kat);
    $rk = bh_sisip_aman($conn, array_values($kat), fn($n) => 'INSERT IGNORE INTO kategorisasi_risiko (IdPel, Periode, UnitUp, UnitAp, UnitUpi) VALUES ' . bh_ph('(?,?,?,?,?)')($n));
    $katBaru = $rk['rc'];

    $simpan = $r['ok'];
    $gagalDb = $r['gagal'];
    $ditolakTotal += $gagalDb;
    if ($simpan === 0) return bh_hasil('gagal', "Tidak ada baris yang berhasil disimpan ($ditolakTotal baris ditolak/gagal).", 0, $ditolakTotal, $detail);

    $pesan = "$simpan baris pelunasan" . ($katBaru ? ", $katBaru kategori risiko baru" : '')
           . ($dup ? ", $dup dobel dalam file" : '') . ($tolak ? ", $tolak tidak valid" : '')
           . ($tidakDil ? ", $tidakDil dilewati (IdPel tidak ada di DIL)" : '')
           . ($gagalDb ? ", $gagalDb gagal saat disimpan ke database" : '') . ($rk['gagal'] ? ", {$rk['gagal']} kategori risiko gagal" : '');
    return bh_hasil('sukses', $pesan, $simpan, $ditolakTotal, $detail);
}

// ============================================================
// DIL -> transit_dil   [penggunaan/input_dil.php]
// Bot HANYA mengisi transit_dil. Pemindahan ke dil (StatusData = AKTIF) dikerjakan TRIGGER (upsert) di database.
// Setelah semua baris masuk, bot menghitung jumlahnya lalu menghapus data transit_dil
// berdasarkan NamaAkun + UnitUpi + pasangan UnitAp/UnitUp yang diproses (unit lain tidak ikut terhapus).
// JenisShuntrip, Kogol, dan MerekMeter hanya ikut diisi bila Excel memuat kolomnya (selain itu memakai nilai bawaan database).
// Kolom unit di file boleh kosong (diisi dari folder/bot), tetapi bila ada harus di dalam wilayah bot.
// ============================================================
function bh_dil(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    $upi  = trim((string) $bot['UnitUpi']);
    $akun = bh_akun($bot);
    $peta_unit = bh_peta_unit($conn, $upi, trim((string) ($bot['UnitAp'] ?? '')), trim((string) ($bot['UnitUp'] ?? '')));

    $kolom_transit = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter',
                      'NomorMeter', 'MerekMeter', 'Aktifasi', 'ProdukPrabayar', 'Kogol', 'JenisShuntrip', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi'];
    $kolom_opsional = ['JenisShuntrip', 'Kogol', 'MerekMeter'];                 // hanya diisi bila Excel memuat kolomnya
    $header  = [
        'IDPEL' => 'IdPel', 'NAMA' => 'Nama', 'ALAMAT' => 'Alamat', 'JENISMK' => 'JenisMk', 'NOTLP' => 'NomorTlp', 'NOHP' => 'NomorHp',
        'TARIF' => 'GolTar', 'DAYA' => 'Daya', 'TEGUKUR' => 'TegUkur', 'KDPEMBMETER' => 'KdPembMeter', 'NOMORKWH' => 'NomorMeter', 'NOMORMETER' => 'NomorMeter',
        'MEREKKWH' => 'MerekMeter', 'MEREKMETER' => 'MerekMeter',   // judul Excel: MEREKKWH (disimpan ke kolom MerekMeter)
        'AKTIFASI' => 'Aktifasi', 'KOGOL' => 'Kogol', 'JENISSHUNTRIP' => 'JenisShuntrip', 'PRODUKPRABAYAR' => 'ProdukPrabayar',
        'UNITUP' => 'UnitUp', 'UNITAP' => 'UnitAp', 'UNITUPI' => 'UnitUpi',
    ];
    $norm_id = function ($v): string {
        $v = trim((string) $v);
        return preg_match('/^\d{1,12}$/', $v) ? str_pad($v, 12, '0', STR_PAD_LEFT) : $v;
    };

    $cols = [];
    $masuk = 0;                                                   // baris yang berhasil masuk ke transit_dil (trigger yang upsert ke dil)
    $gagal_db = 0;                                                // baris yang ditolak database (FK, trigger, dsb.)
    $detail = []; $seen = []; $batch = []; $peta = [];
    $total = $salah = $dobel = 0;

    // Tiap paket: INSERT transit_dil (trigger upsert ke dil) + hapus baris transit paket itu, dalam SATU transaksi pendek.
    // Lock ke dil hanya ditahan selama satu paket, bukan selama seluruh file.
    $sdh_lapor = 0;                                               // baris ditolak validasi yang sudah dilaporkan ke progres
    $kirim = function (array $rows) use ($conn, &$cols, &$masuk, &$gagal_db, &$detail, &$salah, &$dobel, &$sdh_lapor, $akun, $upi) {
        if (!$rows) return;
        bh_progres_tambah(0, ($salah + $dobel) - $sdh_lapor); $sdh_lapor = $salah + $dobel;
        usort($rows, fn($a, $b) => strcmp((string) $a[0], (string) $b[0]));   // IdPel selalu kolom pertama ($cols[0] = 'IdPel'): urutan tetap -> kurangi deadlock
        $per_baris = '(' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW())';   // WaktuData = NOW()
        $sql = fn($n) => 'INSERT INTO transit_dil (' . implode(', ', $cols) . ', WaktuData) VALUES ' . implode(', ', array_fill(0, $n, $per_baris));
        $hapus = function (PDO $c, array $paket) use ($akun, $upi) {
            $ids = array_column($paket, 0);
            $c->prepare('DELETE FROM transit_dil WHERE NamaAkun = ? AND UnitUpi = ? AND IdPel IN (' . implode(',', array_fill(0, count($ids), '?')) . ')')
              ->execute(array_merge([$akun, $upi], $ids));
        };
        $r = bh_sisip_aman($conn, $rows, $sql, $hapus, BH_PAKET, true);
        $masuk    += $r['ok'];
        $gagal_db += $r['gagal'];
        foreach ($r['gagal_idx'] as $i) {
            if (count($detail) < 50) $detail[] = 'IdPel ' . $rows[$i][0] . ' - ditolak database (' . $r['pesan'][$i] . ')';
        }
    };


    {
        // Sisa import gagal (mis. proses sebelumnya mati): hanya milik akun ini, UnitUpi ini, dan (bila folder menentukan) AP/UP tujuan saja
        $sql = 'DELETE FROM transit_dil WHERE NamaAkun = ? AND UnitUpi = ?';
        $prm = [$akun, $upi];
        if ($ap !== '') { $sql .= ' AND UnitAp = ?'; $prm[] = $ap; }
        if ($up !== '') { $sql .= ' AND UnitUp = ?'; $prm[] = $up; }
        $conn->prepare($sql)->execute($prm);

        foreach (bh_xlsx_baris($file) as $no => $sel) {
            if (!$peta) {                                         // cari baris judul
                if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan di 30 baris pertama.');
                $tmp = [];
                foreach ($sel as $huruf => $isi) {
                    $kunci = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                    if (isset($header[$kunci]) && !isset($tmp[$header[$kunci]])) $tmp[$header[$kunci]] = $huruf;
                }
                if (isset($tmp['IdPel'], $tmp['Nama'])) {
                    $peta = $tmp;
                    // JenisShuntrip, Kogol & MerekMeter hanya disertakan bila Excel memuat kolomnya
                    $cols = array_values(array_filter($kolom_transit, fn($k) => !in_array($k, $kolom_opsional, true) || isset($peta[$k])));
                }
                continue;
            }
            $g = fn(string $k, int $m) => isset($peta[$k]) ? bh_teks($sel[$peta[$k]] ?? '', $m) : '';

            $idpel_mentah = trim((string) ($sel[$peta['IdPel']] ?? ''));
            $nama = $g('Nama', 50);
            if ($idpel_mentah === '' && $nama === '') continue;   // baris kosong
            $total++;
            $idpel = $norm_id($idpel_mentah);
            if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel) || $nama === '') {
                $salah++;
                if (count($detail) < 50) $detail[] = "Baris $no: IdPel/Nama tidak valid (" . ($idpel_mentah === '' ? 'kosong' : $idpel_mentah) . ')';
                continue;
            }
            if (isset($seen[$idpel])) {
                $dobel++;
                if (count($detail) < 50) $detail[] = "Baris $no: IdPel $idpel dobel di dalam file";
                continue;
            }

            // Unit baris: dari file bila ada, kalau kosong dari folder/bot. Harus di dalam wilayah bot.
            $f_upi = $g('UnitUpi', 2); $f_ap = $g('UnitAp', 5); $f_up = $g('UnitUp', 5);
            $r_upi = $f_upi !== '' ? $f_upi : $upi;
            $r_ap  = $f_ap  !== '' ? $f_ap  : $ap;
            $r_up  = $f_up  !== '' ? $f_up  : $up;
            $alasan = null;
            if (strcasecmp($r_upi, $upi) !== 0)                                   $alasan = "UnitUpi '$r_upi' di luar wilayah bot";
            elseif ($r_ap === '' || $r_up === '')                                 $alasan = 'UnitAp/UnitUp kosong di file dan tidak bisa ditentukan dari folder (isi kolom UNITAP/UNITUP, atau taruh file di subfolder <KodeUP>)';
            elseif (!isset($peta_unit['ap'][strtoupper($r_ap)]))                  $alasan = "UnitAp '$r_ap' tidak dikenal / di luar wilayah bot";
            elseif (!isset($peta_unit['up'][strtoupper($r_up)]))                  $alasan = "UnitUp '$r_up' tidak dikenal / di luar wilayah bot";
            else {
                $r_ap = $peta_unit['ap'][strtoupper($r_ap)];
                $u_up = $peta_unit['up'][strtoupper($r_up)];
                $r_up = $u_up['kode'];
                if (strcasecmp($u_up['ap'], $r_ap) !== 0)                         $alasan = "UnitUp '$r_up' bukan di bawah UnitAp '$r_ap'";
                elseif ($ap !== '' && strcasecmp($r_ap, $ap) !== 0)               $alasan = "UnitAp '$r_ap' tidak sama dengan folder tujuan ($ap)";
                elseif ($up !== '' && strcasecmp($r_up, $up) !== 0)               $alasan = "UnitUp '$r_up' tidak sama dengan folder tujuan ($up)";
            }
            if ($alasan !== null) {
                $salah++;
                if (count($detail) < 50) $detail[] = "Baris $no: IdPel $idpel - $alasan";
                continue;
            }
            $seen[$idpel] = true;

            $teg = strtoupper($g('TegUkur', 2));
            $sh  = strtoupper($g('JenisShuntrip', 10));
            $kg  = $g('Kogol', 5);
            if (preg_match('/^\d+\.0+$/', $kg)) $kg = preg_replace('/\.0+$/', '', $kg);   // "1.0" dari Excel -> "1"
            $baris = [
                'IdPel' => $idpel, 'Nama' => $nama, 'Alamat' => $g('Alamat', 100), 'JenisMk' => $g('JenisMk', 10),
                'NomorTlp' => $g('NomorTlp', 20), 'NomorHp' => $g('NomorHp', 20), 'GolTar' => $g('GolTar', 5),
                'Daya' => (int) round((float) str_replace(',', '.', $g('Daya', 15))),
                'TegUkur' => in_array($teg, ['TR', 'TM', 'TT'], true) ? $teg : null,
                'KdPembMeter' => $g('KdPembMeter', 1), 'NomorMeter' => $g('NomorMeter', 15), 'MerekMeter' => $g('MerekMeter', 50), 'Aktifasi' => $g('Aktifasi', 15),
                'ProdukPrabayar' => $g('ProdukPrabayar', 50),
                'Kogol' => in_array($kg, BH_KOGOL, true) ? $kg : null,
                'JenisShuntrip' => in_array($sh, BH_JENIS_SHUNTRIP, true) ? $sh : null,
                'NamaAkun' => $akun, 'UnitUp' => $r_up, 'UnitAp' => $r_ap, 'UnitUpi' => $r_upi,
            ];
            $batch[] = array_map(fn($c) => $baris[$c], $cols);    // urutan nilai selalu sama dengan $cols
            if (count($batch) >= BH_CHUNK) { $kirim($batch); $batch = []; }
        }
        if (!$peta) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan. Pastikan file adalah "Monitoring DIL Detail".');
        $kirim($batch);
    }

    // Berhasil = baris yang masuk. Gagal = baris yang TIDAK masuk (IdPel/Nama tidak valid, unit di luar wilayah, dobel dalam file).
    // Tidak ada pembedaan baru / sudah ada: IdPel yang sudah ada di DIL juga dihitung berhasil (di-upsert oleh trigger).
    $ditolak = $salah + $dobel + $gagal_db;
    if ($total === 0) return bh_hasil('gagal', 'File tidak berisi data pelanggan di bawah baris judul.');
    $pesan = "$masuk baris berhasil masuk ke DIL"
           . ($dobel ? ", $dobel dobel dalam file" : '') . ($salah ? ", $salah tidak valid" : '')
           . ($gagal_db ? ", $gagal_db ditolak database" : '');
    return bh_hasil($masuk > 0 ? 'sukses' : 'gagal', $pesan, $masuk, $ditolak, $detail);
}

// ============================================================
// TUL -> tul_601   [penggunaan/upload_tul.php]  (IdPel tidak dicek ke master_barang)
// Logika sama dengan halaman web; bedanya wilayah dari folder/bot dan NamaAkun = 'BOT_<IdBot>' (bh_akun), bukan akun yang login.
//
// NoTul baru        -> INSERT penuh (StatusData 'AKTIF').
// NoTul sudah ada   -> HANYA ThBlRekAwal, ThBlRekAkhir, Lembar, RpTag yang diperbarui.
//                      Kolom lain (WaktuCetak, IdPel, StandBaca, TglBaca, NamaAkun, unit, StatusData, WaktuData) tidak disentuh.
// NoTul milik wilayah lain -> ditolak (tidak menimpa data unit lain).
// ============================================================

/**
 * Baris footer/catatan kaki cetakan (mis. "Petugas Cetak : ...", "Page 1 of 1", "Dicetak oleh ...").
 * Footer bisa terdiri dari SATU sel (merge) atau BEBERAPA sel (mis. "Petugas Cetak" di satu kolom dan
 * "Page 1 of 1" di kolom lain). Sebuah baris dianggap footer bila:
 *   - TIDAK ada satu pun selnya yang berupa IdPel angka (12 digit, atau 11 digit yang nolnya hilang), DAN
 *   - (salah satu sel memuat pola footer cetakan, ATAU hanya satu sel terisi yang bukan IdPel alfanumerik 12 karakter).
 * Ini hanya cek per baris. Apakah baris itu benar-benar footer (tidak ada data lagi di bawahnya)
 * diputuskan oleh bh_xlsx_baris().
 */
function bh_baris_footer(array $sel): bool
{
    $isi = [];
    foreach ($sel as $v) {
        $v = trim((string) $v);
        if ($v !== '') $isi[] = $v;
    }
    if (!$isi) return false;                                          // baris kosong: urusan bh_xlsx_baris()
    foreach ($isi as $v) if (bh_idpel($v) !== null) return false;     // ada IdPel angka -> baris data, tetap diperiksa

    foreach ($isi as $v) {                                            // pola footer cetakan, berapa pun jumlah selnya
        if (preg_match('/petugas\s*cetak|dicetak|printed\s*(by|on)|\bpage\s*\d+\s*(of|dari|\/)\s*\d+|\bhalaman\s*\d+/iu', $v)) return true;
    }
    if (count($isi) !== 1) return false;
    return !preg_match('/^[A-Za-z0-9]{12}$/', $isi[0]);               // IdPel alfanumerik 12 karakter: tetap diperiksa
}

/**
 * Pembungkus xlsx_baris(): membuang footer di AKHIR file, yaitu baris-baris (bh_baris_footer) yang setelahnya
 * sudah tidak ada baris data lagi. Baris seperti ini tidak sampai ke handler, jadi tidak dihitung dibaca/gagal.
 * Baris satu-sel yang MASIH diikuti baris data tetap diteruskan apa adanya (mis. kop surat sebelum judul kolom,
 * atau data nyata yang kolom lainnya kosong), sehingga diperiksa dan bisa ditolak seperti baris biasa.
 * Nomor baris asli ($no) dipertahankan. Hanya baris kandidat footer & baris kosong di antaranya yang ditahan.
 */
function bh_xlsx_baris(string $file): Generator
{
    $tunda = [];   // no => sel: kandidat footer (dan baris kosong di sela-selanya) yang belum jelas nasibnya
    foreach (xlsx_baris($file) as $no => $sel) {
        if (bh_baris_footer($sel)) { $tunda[$no] = $sel; continue; }
        if ($tunda && trim(implode('', array_map('strval', $sel))) === '') { $tunda[$no] = $sel; continue; }
        foreach ($tunda as $n => $s) yield $n => $s;   // ternyata masih ada baris data di bawahnya -> bukan footer
        $tunda = [];
        yield $no => $sel;
    }
    // $tunda yang tersisa = footer di akhir file -> dibuang
}

function bh_tul(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    bh_wajib_wilayah($ap, $up);
    $upi  = trim((string) $bot['UnitUpi']);
    $akun = bh_akun($bot);                    // 'BOT_<IdBot>', sama dengan pelunasan, DIL, dan saldo
    $int_max = 2147483647;

    $wajib = [
        'notul'  => ['nomertulvi01', 'nomortulvi01', 'nomertul', 'nomortul', 'notul'],
        'cetak'  => ['tglpencetakantulvi01', 'tglpencetakan', 'waktucetak'],
        'idpel'  => ['idpelanggan', 'idpel'],
        'awal'   => ['bulanrekeningawal', 'thblrekawal'],
        'akhir'  => ['bulanrekeningakhir', 'thblrekakhir'],
        'lembar' => ['jumlahlembar', 'lembar'],
        'rptag'  => ['rptagihan', 'rptag'],
    ];
    $opsi = [
        'nama'   => ['namapelanggan', 'nama'],
        'stand'  => ['standpemutusan', 'standbaca'],
        'tglput' => ['tglpelaksanaanputus', 'tglbaca'],
    ];
    $nama_kol = ['notul' => 'Nomer Tul VI-01', 'cetak' => 'Tgl Pencetakan Tul VI-01', 'idpel' => 'Id Pelanggan',
                 'awal' => 'Bulan Rekening Awal', 'akhir' => 'Bulan Rekening Akhir', 'lembar' => 'Jumlah Lembar', 'rptag' => 'Rp. Tagihan'];

    $baris = []; $nama_dil = []; $detail = [];
    $dibaca = $tolak = $dup = $bentrok = 0;
    $peta = null;

    foreach (bh_xlsx_baris($file) as $no => $sel) {
        if ($peta === null) {                                     // laporan AP2T: kop surat + judul 2 tingkat; dipakai baris "Id Pelanggan"
            if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
            $kol = [];
            foreach ($sel as $huruf => $nilai) $kol[bh_header($nilai)] = $huruf;
            if (!isset($kol['idpelanggan']) && !isset($kol['idpel'])) continue;
            $peta = [];
            foreach ($wajib + $opsi as $field => $alias) foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
            $kurang = array_diff(array_keys($wajib), array_keys($peta));
            if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $nama_kol[$k], $kurang)) . '.');
            continue;
        }
        if (trim(implode('', $sel)) === '') continue;
        if (strtoupper(trim((string) reset($sel))) === 'TOTAL') continue;   // baris jumlah di akhir laporan
        $dibaca++;
        $g = fn($f) => isset($peta[$f]) ? ($sel[$peta[$f]] ?? '') : '';

        $notul = trim((string) $g('notul'));
        $id    = bh_idpel($g('idpel'));
        $cetak = bh_teks($g('cetak'), 50);             // WaktuCetak = TINYTEXT: simpan teks apa adanya (format dirapikan di database)
        if (is_numeric($cetak) && $cetak > 20000 && $cetak < 80000) $cetak = gmdate('Y-m-d H:i:s', (int) round(((float) $cetak - 25569) * 86400));   // sel Excel yang terbaca angka serial
        $awal  = bh_bulan($g('awal'));
        $akhir = bh_bulan($g('akhir'));
        $lbr   = bh_int($g('lembar'));
        $tag   = bh_int($g('rptag'));
        $stand = bh_int($g('stand'));                  // opsional: kosong / tidak valid -> NULL
        $tglp  = bh_waktu($g('tglput'));
        $tglp  = is_string($tglp) ? substr($tglp, 0, 10) : null;

        $alasan = ($notul === '' || mb_strlen($notul) > 30) ? 'Nomer Tul kosong atau lebih dari 30 karakter'
                : ($id === null ? 'IdPel harus 12 digit angka'
                : ($cetak === '' ? 'Tgl Pencetakan kosong'
                : ($awal === null ? 'Bulan Rekening Awal harus format YYYYMM'
                : ($akhir === null ? 'Bulan Rekening Akhir harus format YYYYMM'
                : ($awal > $akhir ? 'Bulan Rekening Awal lebih besar dari Akhir'
                : ($lbr === null || $lbr < 1 ? 'Jumlah Lembar kosong / bukan angka'
                : ($tag === null ? 'Rp. Tagihan kosong / bukan angka'
                : (max($lbr, $tag, (int) $stand) > $int_max ? 'Angka melebihi batas kolom (INT)' : null))))))));
        if ($alasan !== null) {
            $tolak++;
            if (count($detail) < 50) $detail[] = "Baris $no: Tul $notul / IdPel " . trim((string) $g('idpel')) . ' - ' . $alasan;
            continue;
        }
        if (isset($baris[$notul])) $dup++;                // baris terakhir yang dipakai
        $baris[$notul] = [$no, $notul, $cetak, $id, $awal, $akhir, $lbr, $tag, $stand, $tglp];
        $nm = trim(preg_replace('/\s+/', ' ', (string) $g('nama')));
        if ($nm !== '') $nama_dil[$id] = mb_substr($nm, 0, 50);
    }
    if ($peta === null) throw new RuntimeException('Baris judul kolom (Id Pelanggan, Nomer Tul VI-01, ...) tidak ditemukan.');

    $gagalDb = 0;
    if ($baris) {
        // (tanpa transaksi pembungkus: SELECT tidak perlu lock, UPDATE dil = 1 statement/paket, tul_601 per paket kecil)

        // c0. NoTul adalah kunci tunggal: tolak NoTul milik wilayah LAIN supaya tidak menimpa data unit lain.
        foreach (array_chunk(array_keys($baris), BH_CHUNK) as $paket) {
            $st = $conn->prepare('SELECT NoTul, UnitUp, UnitAp, UnitUpi FROM tul_601 WHERE NoTul IN (' . implode(',', array_fill(0, count($paket), '?')) . ')');
            $st->execute($paket);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ada) {
                if ((string) $ada['UnitUp'] === $up && (string) $ada['UnitAp'] === $ap && (string) $ada['UnitUpi'] === $upi) continue;
                $b = $baris[$ada['NoTul']];
                unset($baris[$ada['NoTul']]);
                $bentrok++; $tolak++;
                if (count($detail) < 50) $detail[] = "Baris $b[0]: Tul $b[1] - Nomer Tul sudah tercatat pada wilayah lain";
            }
        }

        // c1. Nama dari file hanya melengkapi DIL yang SUDAH ada dan namanya masih kosong,
        //     dan hanya untuk IdPel yang benar-benar disimpan (sama dengan halaman web).
        $nama_pakai = array_intersect_key($nama_dil, array_flip(array_map('strval', array_column($baris, 3))));
        ksort($nama_pakai);                                // urutan tetap -> kurangi deadlock dengan proses DIL
        foreach (array_chunk($nama_pakai, 100, true) as $paket) {
            $case = $param = [];
            foreach ($paket as $idn => $nm) { $case[] = 'WHEN ? THEN ?'; array_push($param, (string) $idn, $nm); }
            $in = implode(',', array_fill(0, count($paket), '?'));
            foreach (array_keys($paket) as $idn) $param[] = (string) $idn;
            $conn->prepare("UPDATE dil SET Nama = CASE IdPel " . implode(' ', $case) . " END WHERE IdPel IN ($in) AND (Nama IS NULL OR Nama = '')")
                 ->execute($param);
        }

        // c2. tul_601. NoTul baru -> INSERT penuh (StatusData 'AKTIF').
        //     NoTul yang sudah ada -> HANYA ThBlRekAwal, ThBlRekAkhir, Lembar, RpTag yang diperbarui (sama dengan halaman web).
        //     Kolom lain (WaktuCetak, IdPel, StandBaca, TglBaca, NamaAkun, unit, StatusData, WaktuData) tidak disentuh.
        $head = "INSERT INTO tul_601 (NoTul, WaktuCetak, IdPel, ThBlRekAwal, ThBlRekAkhir, Lembar, RpTag, StandBaca, TglBaca, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) VALUES ";
        $tail = " ON DUPLICATE KEY UPDATE ThBlRekAwal = VALUES(ThBlRekAwal), ThBlRekAkhir = VALUES(ThBlRekAkhir), Lembar = VALUES(Lembar), RpTag = VALUES(RpTag)";
        ksort($baris);
        $daftar = array_values($baris);
        $rows = array_map(fn($b) => [$b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $b[9], $akun, $up, $ap, $upi], $daftar);
        bh_progres_tambah(0, $tolak);                      // baris yang sudah ditolak validasi ikut dihitung gagal
        $r = bh_sisip_aman($conn, $rows, fn($n) => $head . bh_ph("(?,?,?,?,?,?,?,?,?,?,?,?,?,'AKTIF',NOW())")($n) . $tail, null, BH_PAKET, true);
        $gagalDb = $r['gagal'];
        foreach ($r['gagal_idx'] as $i) {
            if (count($detail) < 50) $detail[] = 'Baris ' . $daftar[$i][0] . ': Tul ' . $daftar[$i][1] . ' - gagal disimpan (' . $r['pesan'][$i] . ')';
            unset($baris[$daftar[$i][1]]);
        }
        $tolak += $gagalDb;
    }

    if (!$baris) {
        return bh_hasil('gagal', 'Tidak ada baris valid yang bisa disimpan' . ($tolak ? " ($tolak baris ditolak)." : '.'), 0, $tolak, $detail);
    }
    $pesan = count($baris) . ' baris TUL VI-01' . ($dup ? ", $dup dobel dalam file" : '')
           . ($bentrok ? ", $bentrok ditolak (wilayah lain)" : '')
           . ($gagalDb ? ", $gagalDb gagal saat disimpan ke database" : '')
           . (($tolak - $bentrok - $gagalDb) > 0 ? ', ' . ($tolak - $bentrok - $gagalDb) . ' tidak valid' : '');
    return bh_hasil('sukses', $pesan, count($baris), $tolak, $detail);
}

// ============================================================
// SALDO_TUNGGAKAN -> dpp   [penggunaan/input_saldo.php]
// Hanya MENAMBAH data: IdPel + ThBlRek yang sudah ada di dpp dilewati (tidak diperbarui).
// IdPel wajib persis 12 digit (tanpa pelengkap nol) dan wajib sudah ada di dil; bot tidak pernah menambah dil.
// ============================================================
function bh_saldo(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    bh_wajib_wilayah($ap, $up);
    $upi  = trim((string) $bot['UnitUpi']);
    $akun = bh_akun($bot);

    $alias    = ['idpel' => ['idpel'], 'bulan' => ['thblrek', 'bulan'], 'rp' => ['rptag', 'rptagihan', 'saldo']];
    $nama_kol = ['idpel' => 'IdPel', 'bulan' => 'ThBlRek', 'rp' => 'RpTag'];

    $peta = null; $baris = []; $detail = [];
    $dibaca = $salah = $dobel = $tidak_dil = $sudah_ada = 0;

    foreach (bh_xlsx_baris($file) as $no => $sel) {
        if ($peta === null) {                                     // cari baris judul (20 baris pertama)
            if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
            $kol = [];
            foreach ($sel as $h => $n) $kol[bh_header($n)] = $h;
            if (!isset($kol['idpel'])) continue;
            $peta = [];
            foreach ($alias as $fld => $al) foreach ($al as $a) if (isset($kol[$a])) { $peta[$fld] = $kol[$a]; break; }
            $kurang = array_diff(array_keys($alias), array_keys($peta));
            if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $nama_kol[$k], $kurang)) . '.');
            continue;
        }
        if (trim(implode('', $sel)) === '') continue;
        $dibaca++;
        $id = bh_idpel_ketat($sel[$peta['idpel']] ?? '');
        $bl = bh_bulan_saldo($sel[$peta['bulan']] ?? '');
        $rp = bh_int($sel[$peta['rp']] ?? '');
        $alasan = bh_idpel_ketat_alasan($id) ?? ($bl === null ? 'ThBlRek harus YYYYMM/YYYYMMDD' : ($rp === null ? 'RpTag kosong/bukan angka/negatif' : null));
        if ($alasan !== null) {
            $salah++;
            if (count($detail) < 50) $detail[] = "Baris $no: $alasan (" . trim((string) ($sel[$peta['idpel']] ?? '')) . ')';
            continue;
        }
        if (isset($baris[$id . '|' . $bl])) $dobel++;             // baris terakhir yang dipakai
        $baris[$id . '|' . $bl] = [$id, $bl, $rp, $no];
    }
    if ($peta === null) throw new RuntimeException('Baris judul kolom (IdPel, ThBlRek, RpTag) tidak ditemukan.');

    // IdPel WAJIB sudah terdaftar di DIL: yang tidak ada dilewati (tidak pernah ditambahkan ke DIL)
    if ($baris) {
        $ada = [];
        foreach (array_chunk(array_values(array_unique(array_column($baris, 0))), 1000) as $paket) {
            $st = $conn->prepare("SELECT IdPel FROM dil WHERE IdPel IN (" . implode(',', array_fill(0, count($paket), '?')) . ")");
            $st->execute($paket);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $ada[(string) $x] = true;
        }
        foreach ($baris as $k => $b) {
            if (isset($ada[$b[0]])) continue;
            $tidak_dil++;
            if (count($detail) < 50) $detail[] = 'Baris ' . $b[3] . ': IdPel ' . $b[0] . ' tidak terdaftar di DIL, tidak disimpan';
            unset($baris[$k]);
        }
    }

    // IdPel + ThBlRek yang sudah ada di dpp TIDAK diperbarui (dilewati)
    if ($baris) {
        $sudah = [];
        foreach (array_chunk(array_values($baris), BH_CHUNK) as $paket) {
            $st = $conn->prepare("SELECT IdPel, ThBlRek FROM dpp WHERE (IdPel, ThBlRek) IN (" . implode(',', array_fill(0, count($paket), '(?,?)')) . ")");
            $prm = [];
            foreach ($paket as $b) array_push($prm, $b[0], $b[1]);
            $st->execute($prm);
            foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) $sudah[trim((string) $r[0]) . '|' . trim((string) $r[1])] = true;
        }
        foreach ($baris as $k => $b) {
            if (!isset($sudah[$b[0] . '|' . $b[1]])) continue;
            $sudah_ada++;
            if (count($detail) < 50) $detail[] = 'Baris ' . $b[3] . ': IdPel ' . $b[0] . ' periode ' . $b[1] . ' sudah ada di DPP, tidak diperbarui';
            unset($baris[$k]);
        }
    }

    // INSERT IGNORE sebagai pengaman bila ada input bersamaan; yang dihitung = baris yang benar-benar masuk
    $baru = 0; $gagalDb = 0;
    if ($baris) {
        ksort($baris);
        $daftar = array_values($baris);
        $rows = array_map(fn($b) => [$b[0], $b[1], $b[2], $akun, $up, $ap, $upi], $daftar);
        bh_progres_tambah(0, $salah + $tidak_dil);          // baris yang sudah ditolak validasi ikut dihitung gagal
        $r = bh_sisip_aman($conn, $rows, fn($n) => 'INSERT IGNORE INTO dpp (IdPel, ThBlRek, RpTag, NamaAkun, UnitUp, UnitAp, UnitUpi, WaktuData) VALUES ' . bh_ph('(?,?,?,?,?,?,?,NOW())')($n), null, BH_PAKET, true);
        $baru    = $r['rc'];                       // INSERT IGNORE: rowCount = baris yang benar-benar baru masuk
        $gagalDb = $r['gagal'];
        foreach ($r['gagal_idx'] as $i) {
            if (count($detail) < 50) $detail[] = 'Baris ' . $daftar[$i][3] . ': IdPel ' . $daftar[$i][0] . ' periode ' . $daftar[$i][1] . ' - gagal disimpan (' . $r['pesan'][$i] . ')';
        }
    }

    $ditolak = $salah + $tidak_dil + $gagalDb;
    if ($baru + $sudah_ada === 0) {
        return bh_hasil('gagal', 'Tidak ada baris valid yang bisa disimpan' . ($ditolak ? " ($ditolak baris ditolak)." : '.'), 0, $ditolak, $detail);
    }
    $pesan = "$baru baris saldo baru disimpan"
           . ($sudah_ada ? ", $sudah_ada sudah ada di DPP (tidak diperbarui)" : '')
           . ($dobel ? ", $dobel dobel dalam file" : '')
           . ($salah ? ", $salah tidak valid" : '')
           . ($tidak_dil ? ", $tidak_dil dilewati (IdPel tidak ada di DIL)" : '')
           . ($gagalDb ? ", $gagalDb gagal saat disimpan ke database" : '');
    return bh_hasil('sukses', $pesan, $baru, $ditolak, $detail);
}

// ============================================================
// SURVEY (PosisiSR) & KEPENTINGAN (LevelKepentingan) -> kategorisasi_risiko   [functions/perencanaan_input.php]
// ============================================================
/**
 * Simpan pasangan [idpel, nilai] ke kolom $kolom (dari konfigurasi di bh_jalankan, bukan input pengguna).
 * $unit = [UnitUp, UnitAp, UnitUpi]; $tingkat = 'UP' (bot terikat UP) | 'AP'.
 * Baris lama di wilayah yang sama: hanya $kolom diperbarui. Baris lama milik unit LAIN: DILEWATI.
 * @return array [baru, diperbarui, dilewati, gagal]
 */
function bh_simpan_kategorisasi(PDO $conn, string $kolom, int $periode, array $pasangan, array $unit, string $tingkat): array
{
    $baru = $upd = $lewat = $gagal = 0;
    $beda = fn($lama, $target) => $lama !== null && $lama !== '' && (string) $lama !== (string) $target;
    usort($pasangan, fn($a, $b) => strcmp((string) $a[0], (string) $b[0]));   // urutan tetap -> kurangi deadlock

    foreach (array_chunk($pasangan, BH_CHUNK) as $paket) {
        // SELECT di luar transaksi: hanya membaca, tidak menahan lock
        $st = $conn->prepare("SELECT IdPel, UnitUp, UnitAp, UnitUpi FROM kategorisasi_risiko WHERE Periode = ? AND IdPel IN (" . implode(',', array_fill(0, count($paket), '?')) . ")");
        $st->execute(array_merge([$periode], array_column($paket, 0)));
        $ada = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $ada[(string) $r['IdPel']] = $r;

        $boleh = []; $jenis = [];
        foreach ($paket as [$id, $nilai]) {
            if (isset($ada[$id])) {
                $r = $ada[$id];
                $bentrok = $beda($r['UnitUpi'], $unit[2]) || $beda($r['UnitAp'], $unit[1]) || ($tingkat === 'UP' && $beda($r['UnitUp'], $unit[0]));
                if ($bentrok) { $lewat++; bh_progres_tambah(0, 1); continue; }
                $jenis[] = 'upd';
            } else {
                $jenis[] = 'baru';
            }
            $boleh[] = [$id, $periode, $nilai, $unit[0], $unit[1], $unit[2]];
        }
        if (!$boleh) continue;

        $tail = " ON DUPLICATE KEY UPDATE $kolom = VALUES($kolom),
                    UnitUp  = COALESCE(NULLIF(UnitUp, ''),  VALUES(UnitUp)),
                    UnitAp  = COALESCE(NULLIF(UnitAp, ''),  VALUES(UnitAp)),
                    UnitUpi = COALESCE(NULLIF(UnitUpi, ''), VALUES(UnitUpi))";
        $r = bh_sisip_aman($conn, $boleh, fn($n) => "INSERT INTO kategorisasi_risiko (IdPel, Periode, $kolom, UnitUp, UnitAp, UnitUpi) VALUES " . bh_ph('(?,?,?,?,?,?)')($n) . $tail, null, BH_PAKET, true);
        $gagal += $r['gagal'];
        $gagalSet = array_flip($r['gagal_idx']);
        foreach ($jenis as $i => $j) {
            if (isset($gagalSet[$i])) continue;
            if ($j === 'baru') $baru++; else $upd++;
        }
    }
    return [$baru, $upd, $lewat, $gagal];
}

function bh_kategorisasi(PDO $conn, array $bot, string $file, string $ap, string $up, array $cfg): array
{
    bh_wajib_wilayah($ap, $up);
    $upi     = trim((string) $bot['UnitUpi']);
    $tingkat = trim((string) ($bot['UnitUp'] ?? '')) !== '' ? 'UP' : 'AP';
    $norm    = $cfg['norm'];

    // Periode bawaan: tahun 4 digit di nama file (survey_2026.xlsx), jika tidak ada = tahun berjalan.
    $periode_file = preg_match('/(?<!\d)((?:19|20|21)\d{2})(?!\d)/', basename($file), $m) ? (int) $m[1] : (int) date('Y');

    $kolId = $kolNilai = $kolPer = null;
    $per = [];                                   // periode => [idpel => [idpel, nilai]]
    $detail = [];
    $dibaca = $tolak = $dup = 0;

    foreach (bh_xlsx_baris($file) as $no => $sel) {
        if ($kolId === null) {
            if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
            $kol = [];
            foreach ($sel as $huruf => $v) $kol[bh_header($v)] = $huruf;
            if (!isset($kol['idpel'])) continue;
            foreach ($cfg['alias'] as $a) if (isset($kol[$a])) { $kolNilai = $kol[$a]; break; }
            if ($kolNilai === null) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . $cfg['nama_excel'] . '.');
            foreach (['periode', 'tahun'] as $a) if (isset($kol[$a])) { $kolPer = $kol[$a]; break; }
            $kolId = $kol['idpel'];
            continue;
        }
        if (trim(implode('', $sel)) === '') continue;
        $dibaca++;
        $id    = bh_idpel($sel[$kolId] ?? '');
        $nilai = $norm($sel[$kolNilai] ?? '');
        $p     = $kolPer !== null && trim((string) ($sel[$kolPer] ?? '')) !== '' ? bh_periode($sel[$kolPer]) : $periode_file;
        $alasan = $id === null ? 'IdPel harus 12 digit angka'
                : ($nilai === null ? $cfg['nama_excel'] . ' harus ' . $cfg['format']
                : ($p === null ? 'Periode harus tahun 4 digit' : null));
        if ($alasan !== null) {
            $tolak++;
            if (count($detail) < 50) $detail[] = "Baris $no: IdPel " . trim((string) ($sel[$kolId] ?? '')) . ' - ' . $alasan;
            continue;
        }
        if (isset($per[$p][$id])) $dup++;
        $per[$p][$id] = [$id, $nilai];
    }
    if ($kolId === null) throw new RuntimeException('Baris judul kolom (Idpel, ' . $cfg['nama_excel'] . ') tidak ditemukan.');

    $baru = $upd = $lewat = $gagalDb = 0;
    bh_progres_tambah(0, $tolak);                    // baris yang sudah ditolak validasi ikut dihitung gagal
    foreach ($per as $p => $pasangan) {
        [$b, $u, $l, $g] = bh_simpan_kategorisasi($conn, $cfg['kolom'], (int) $p, array_values($pasangan), [$up, $ap, $upi], $tingkat);
        $baru += $b; $upd += $u; $lewat += $l; $gagalDb += $g;
    }
    if ($gagalDb) $detail[] = "$gagalDb pelanggan gagal disimpan oleh database.";
    $tersimpan = $baru + $upd;
    if ($lewat) $detail[] = "$lewat pelanggan dilewati karena tercatat di unit lain (di luar wilayah bot).";
    if ($tersimpan === 0) {
        return bh_hasil('gagal', $lewat ? 'Semua pelanggan pada file tercatat di unit lain, tidak ada yang disimpan.'
                                        : 'Tidak ada baris valid yang bisa disimpan' . ($tolak ? " ($tolak baris ditolak)." : '.'), 0, $tolak + $lewat, $detail);
    }
    $pesan = "$tersimpan data {$cfg['label']} (periode " . implode('/', array_keys($per)) . ": $baru baru, $upd diperbarui)"
           . ($dup ? ", $dup dobel dalam file" : '') . ($lewat ? ", $lewat dilewati (unit lain)" : '') . ($tolak ? ", $tolak ditolak" : '') . ($gagalDb ? ", $gagalDb gagal di database" : '');
    return bh_hasil('sukses', $pesan, $tersimpan, $tolak + $lewat + $gagalDb, $detail);
}