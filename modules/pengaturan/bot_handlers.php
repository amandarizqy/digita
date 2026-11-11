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

const BH_CHUNK = 500;

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

function bh_hasil(string $status, string $pesan, int $tersimpan = 0, int $ditolak = 0, array $detail = []): array
{
    return ['status' => $status, 'pesan' => $pesan, 'tersimpan' => $tersimpan, 'ditolak' => $ditolak, 'detail' => $detail];
}

/**
 * Proses satu file untuk satu bot.
 * @param string $ap,$up  wilayah tujuan dari lokasi folder ('' = tidak ditentukan; hanya boleh untuk DIL yang diletakkan langsung di Direktori Utama)
 */
function bh_jalankan(PDO $conn, array $bot, string $jenis, string $file, string $ap, string $up): array
{
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($file, false, null, 0, 2) !== 'PK') {
        return bh_hasil('gagal', 'Format file harus .xlsx (Excel).');
    }
    if (filesize($file) === 0) return bh_hasil('gagal', 'File kosong (0 byte).');

    try {
        switch ($jenis) {
            case 'PELUNASAN':   return bh_pelunasan($conn, $bot, $file, $ap, $up);
            case 'DIL':         return bh_dil($conn, $bot, $file, $ap, $up);
            case 'TUL':         return bh_tul($conn, $bot, $file, $ap, $up);
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
// PELUNASAN -> pelunasan_ap2t  (+ dil baru, + kategorisasi_risiko baru)   [perencanaan/upload_riwayat.php]
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
    $dibaca = $tolak = $dup = 0;
    $peta = null;

    foreach (xlsx_baris($file) as $no => $sel) {
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
        $dibaca++;
        $g = fn($f) => $sel[$peta[$f]] ?? '';

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
        $baris[$kunci] = [$id, $bln, $tgl, (int) round($bk), (int) round($tag)];
        $kat[$id . '|' . (int) substr($bln, 0, 4)] = [$id, (int) substr($bln, 0, 4)];   // Periode = tahun dari Bulan
    }
    if ($peta === null) throw new RuntimeException('Baris judul kolom (Idpel, Bulan, ...) tidak ditemukan.');
    if (!$baris) return bh_hasil('gagal', 'Tidak ada baris valid yang bisa disimpan' . ($tolak ? " ($tolak baris ditolak)." : '.'), 0, $tolak, $detail);

    $dilBaru = $katBaru = 0;
    $head = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, NamaAkun, UnitUp, UnitAp, UnitUpi, WaktuData) VALUES ";
    $tail = " ON DUPLICATE KEY UPDATE TglBayar = VALUES(TglBayar), RpBK = VALUES(RpBK), RpTag = VALUES(RpTag),
              NamaAkun = VALUES(NamaAkun), UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi),
              WaktuData = VALUES(WaktuData)";
    $conn->beginTransaction();
    // DIL dulu: pelunasan_ap2t & kategorisasi_risiko punya foreign key IdPel -> dil.IdPel.
    // INSERT IGNORE: pelanggan yang sudah ada di DIL tidak diubah.
    foreach (array_chunk(array_values(array_unique(array_column($baris, 0))), BH_CHUNK) as $paket) {
        $stmt = $conn->prepare("INSERT IGNORE INTO dil (IdPel, Nama, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) VALUES "
              . implode(',', array_fill(0, count($paket), "(?, '', ?, ?, ?, ?, 'AKTIF', NOW())")));
        $param = [];
        foreach ($paket as $idd) array_push($param, $idd, $akun, $up, $ap, $upi);
        $stmt->execute($param);
        $dilBaru += $stmt->rowCount();
    }
    foreach (array_chunk(array_values($baris), BH_CHUNK) as $paket) {
        $stmt  = $conn->prepare($head . implode(',', array_fill(0, count($paket), '(?,?,?,?,?,?,?,?,?,NOW())')) . $tail);
        $param = [];
        foreach ($paket as $b) array_push($param, $b[0], $b[1], $b[2], $b[3], $b[4], $akun, $up, $ap, $upi);
        $stmt->execute($param);
    }
    // kategorisasi_risiko: tambah (IdPel, Periode) yang belum ada; yang sudah ada TIDAK diubah.
    foreach (array_chunk(array_values($kat), BH_CHUNK) as $paket) {
        $stmt  = $conn->prepare("INSERT IGNORE INTO kategorisasi_risiko (IdPel, Periode, UnitUp, UnitAp, UnitUpi) VALUES "
               . implode(',', array_fill(0, count($paket), '(?,?,?,?,?)')));
        $param = [];
        foreach ($paket as $k) array_push($param, $k[0], $k[1], $up, $ap, $upi);
        $stmt->execute($param);
        $katBaru += $stmt->rowCount();
    }
    $conn->commit();

    $pesan = count($baris) . ' baris pelunasan' . ($dilBaru ? ", $dilBaru pelanggan baru ke DIL" : '') . ($katBaru ? ", $katBaru kategori risiko baru" : '')
           . ($dup ? ", $dup dobel dalam file" : '') . ($tolak ? ", $tolak ditolak" : '');
    return bh_hasil('sukses', $pesan, count($baris), $tolak, $detail);
}

// ============================================================
// DIL -> dil (lewat transit_dil)   [penggunaan/input_dil.php]
// Kolom unit di file boleh kosong (diisi dari folder/bot) tetapi bila ada harus di dalam wilayah bot.
// ============================================================
function bh_dil(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    $upi  = trim((string) $bot['UnitUpi']);
    $akun = bh_akun($bot);
    $peta_unit = bh_peta_unit($conn, $upi, trim((string) ($bot['UnitAp'] ?? '')), trim((string) ($bot['UnitUp'] ?? '')));

    $kolom   = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter',
                'NomorMeter', 'Aktifasi', 'ProdukPrabayar', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi', 'StatusData', 'WaktuData'];
    $transit = array_values(array_diff($kolom, ['StatusData']));   // transit_dil tidak punya StatusData
    $header  = [
        'IDPEL' => 'IdPel', 'NAMA' => 'Nama', 'ALAMAT' => 'Alamat', 'JENISMK' => 'JenisMk', 'NOTLP' => 'NomorTlp', 'NOHP' => 'NomorHp',
        'TARIF' => 'GolTar', 'DAYA' => 'Daya', 'TEGUKUR' => 'TegUkur', 'KDPEMBMETER' => 'KdPembMeter', 'NOMORKWH' => 'NomorMeter', 'NOMORMETER' => 'NomorMeter',
        'AKTIFASI' => 'Aktifasi', 'PRODUKPRABAYAR' => 'ProdukPrabayar', 'UNITUP' => 'UnitUp', 'UNITAP' => 'UnitAp', 'UNITUPI' => 'UnitUpi',
    ];
    $norm_id = function ($v): string {
        $v = trim((string) $v);
        return preg_match('/^\d{1,12}$/', $v) ? str_pad($v, 12, '0', STR_PAD_LEFT) : $v;
    };

    $kol_dil   = implode(', ', $kolom);
    $kol_trans = implode(', ', $transit);
    $kol_pilih = implode(', ', array_map(fn($k) => $k === 'StatusData' ? "'AKTIF'" : $k, $kolom));
    $per_baris = '(' . implode(', ', array_fill(0, count($transit) - 1, '?')) . ', NOW())';   // WaktuData = NOW()
    $kirim = function (array $rows) use ($conn, $kol_trans, $per_baris) {
        if (!$rows) return;
        $conn->prepare("INSERT INTO transit_dil ($kol_trans) VALUES " . implode(', ', array_fill(0, count($rows), $per_baris)))
             ->execute(array_merge(...$rows));
    };

    $detail = []; $seen = []; $batch = []; $peta = [];
    $total = $salah = $dobel = 0;

    $conn->beginTransaction();
    $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ?")->execute([$akun]);   // sisa import yang gagal sebelumnya

    foreach (xlsx_baris($file) as $no => $sel) {
        if (!$peta) {                                             // cari baris judul
            if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan di 30 baris pertama.');
            $tmp = [];
            foreach ($sel as $huruf => $isi) {
                $kunci = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                if (isset($header[$kunci]) && !isset($tmp[$header[$kunci]])) $tmp[$header[$kunci]] = $huruf;
            }
            if (isset($tmp['IdPel'], $tmp['Nama'])) $peta = $tmp;
            continue;
        }
        $g = fn(string $k, int $m) => isset($peta[$k]) ? bh_teks($sel[$peta[$k]] ?? '', $m) : '';

        $idpel_mentah = trim((string) ($sel[$peta['IdPel']] ?? ''));
        $nama = $g('Nama', 50);
        if ($idpel_mentah === '' && $nama === '') continue;       // baris kosong
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
        $batch[] = [
            $idpel, $nama, $g('Alamat', 100), $g('JenisMk', 10), $g('NomorTlp', 20), $g('NomorHp', 20),
            $g('GolTar', 5), (int) round((float) str_replace(',', '.', $g('Daya', 15))),
            in_array($teg, ['TR', 'TM', 'TT'], true) ? $teg : null,
            $g('KdPembMeter', 1), $g('NomorMeter', 15), $g('Aktifasi', 15), $g('ProdukPrabayar', 50),
            $akun, $r_up, $r_ap, $r_upi,
        ];
        if (count($batch) >= BH_CHUNK) { $kirim($batch); $batch = []; }
    }
    if (!$peta) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan. Pastikan file adalah "Monitoring DIL Detail".');
    $kirim($batch);

    // transit_dil -> dil (IdPel yang sudah ada dilewati), lalu transit_dil dikosongkan
    $in = $conn->prepare("INSERT IGNORE INTO dil ($kol_dil) SELECT $kol_pilih FROM transit_dil WHERE NamaAkun = ?");
    $in->execute([$akun]);
    $ok = $in->rowCount();
    $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ?")->execute([$akun]);
    $conn->commit();

    $sudah_ada = max(0, count($seen) - $ok);
    $ditolak = $salah + $dobel;
    if ($total === 0) return bh_hasil('gagal', 'File tidak berisi data pelanggan di bawah baris judul.');
    $pesan = "$ok pelanggan baru ditambahkan ke DIL" . ($sudah_ada ? ", $sudah_ada sudah ada (dilewati)" : '')
           . ($dobel ? ", $dobel dobel dalam file" : '') . ($salah ? ", $salah tidak valid" : '');
    // Tidak ada yang baru DAN tidak ada yang sudah ada = semua baris ditolak
    return bh_hasil(($ok + $sudah_ada) > 0 ? 'sukses' : 'gagal', $pesan, $ok, $ditolak, $detail);
}

// ============================================================
// TUL -> tul_601   [penggunaan/upload_tul.php]  (IdPel tidak dicek ke master_barang)
// ============================================================
function bh_tul(PDO $conn, array $bot, string $file, string $ap, string $up): array
{
    bh_wajib_wilayah($ap, $up);
    $upi  = trim((string) $bot['UnitUpi']);
    $akun = bh_akun($bot);
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

    foreach (xlsx_baris($file) as $no => $sel) {
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
        $cetak = bh_waktu($g('cetak'));
        $awal  = bh_bulan($g('awal'));
        $akhir = bh_bulan($g('akhir'));
        $lbr   = bh_int($g('lembar'));
        $tag   = bh_int($g('rptag'));
        $stand = bh_int($g('stand'));                  // opsional: kosong / tidak valid -> NULL
        $tglp  = bh_waktu($g('tglput'));
        $tglp  = is_string($tglp) ? substr($tglp, 0, 10) : null;

        $alasan = ($notul === '' || mb_strlen($notul) > 30) ? 'Nomer Tul kosong atau lebih dari 30 karakter'
                : ($id === null ? 'IdPel harus 12 digit angka'
                : (!is_string($cetak) ? 'Tgl Pencetakan kosong / tidak valid'
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

    if ($baris) {
        $conn->beginTransaction();

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

        // c1. Nama dari file hanya melengkapi DIL yang SUDAH ada dan namanya masih kosong. IdPel tidak dicek ke master_barang.
        foreach (array_chunk($nama_dil, BH_CHUNK, true) as $paket) {
            $case = $param = [];
            foreach ($paket as $idn => $nm) { $case[] = 'WHEN ? THEN ?'; array_push($param, (string) $idn, $nm); }
            $in = implode(',', array_fill(0, count($paket), '?'));
            foreach (array_keys($paket) as $idn) $param[] = (string) $idn;
            $conn->prepare("UPDATE dil SET Nama = CASE IdPel " . implode(' ', $case) . " END WHERE IdPel IN ($in) AND (Nama IS NULL OR Nama = '')")
                 ->execute($param);
        }

        // c2. tul_601. StatusData baris lama TIDAK diubah; StandBaca/TglBaca lama dipertahankan bila file baru kosong.
        $head = "INSERT INTO tul_601 (NoTul, WaktuCetak, IdPel, ThBlRekAwal, ThBlRekAkhir, Lembar, RpTag, StandBaca, TglBaca, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) VALUES ";
        $tail = " ON DUPLICATE KEY UPDATE WaktuCetak = VALUES(WaktuCetak), IdPel = VALUES(IdPel), ThBlRekAwal = VALUES(ThBlRekAwal), ThBlRekAkhir = VALUES(ThBlRekAkhir),
                  Lembar = VALUES(Lembar), RpTag = VALUES(RpTag), StandBaca = COALESCE(VALUES(StandBaca), StandBaca), TglBaca = COALESCE(VALUES(TglBaca), TglBaca),
                  NamaAkun = VALUES(NamaAkun), UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi), WaktuData = VALUES(WaktuData)";
        foreach (array_chunk(array_values($baris), BH_CHUNK) as $paket) {
            $stmt  = $conn->prepare($head . implode(',', array_fill(0, count($paket), "(?,?,?,?,?,?,?,?,?,?,?,?,?,'AKTIF',NOW())")) . $tail);
            $param = [];
            foreach ($paket as $b) array_push($param, $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $b[9], $akun, $up, $ap, $upi);
            $stmt->execute($param);
        }
        $conn->commit();
    }

    if (!$baris) {
        return bh_hasil('gagal', 'Tidak ada baris valid yang bisa disimpan' . ($tolak ? " ($tolak baris ditolak)." : '.'), 0, $tolak, $detail);
    }
    $pesan = count($baris) . ' baris TUL VI-01' . ($dup ? ", $dup dobel dalam file" : '')
           . ($bentrok ? ", $bentrok ditolak (wilayah lain)" : '')
           . (($tolak - $bentrok) > 0 ? ', ' . ($tolak - $bentrok) . ' tidak valid' : '');
    return bh_hasil('sukses', $pesan, count($baris), $tolak, $detail);
}

// ============================================================
// SURVEY (PosisiSR) & KEPENTINGAN (LevelKepentingan) -> kategorisasi_risiko   [functions/perencanaan_input.php]
// ============================================================
/**
 * Simpan pasangan [idpel, nilai] ke kolom $kolom (dari konfigurasi di bh_jalankan, bukan input pengguna).
 * $unit = [UnitUp, UnitAp, UnitUpi]; $tingkat = 'UP' (bot terikat UP) | 'AP'.
 * Baris lama di wilayah yang sama: hanya $kolom diperbarui. Baris lama milik unit LAIN: DILEWATI.
 * @return array [baru, diperbarui, dilewati]
 */
function bh_simpan_kategorisasi(PDO $conn, string $kolom, int $periode, array $pasangan, array $unit, string $tingkat): array
{
    $baru = $upd = $lewat = 0;
    $beda = fn($lama, $target) => $lama !== null && $lama !== '' && (string) $lama !== (string) $target;

    $conn->beginTransaction();
    foreach (array_chunk($pasangan, BH_CHUNK) as $paket) {
        $st = $conn->prepare("SELECT IdPel, UnitUp, UnitAp, UnitUpi FROM kategorisasi_risiko WHERE Periode = ? AND IdPel IN (" . implode(',', array_fill(0, count($paket), '?')) . ")");
        $st->execute(array_merge([$periode], array_column($paket, 0)));
        $ada = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $ada[(string) $r['IdPel']] = $r;

        $boleh = [];
        foreach ($paket as [$id, $nilai]) {
            if (isset($ada[$id])) {
                $r = $ada[$id];
                $bentrok = $beda($r['UnitUpi'], $unit[2]) || $beda($r['UnitAp'], $unit[1]) || ($tingkat === 'UP' && $beda($r['UnitUp'], $unit[0]));
                if ($bentrok) { $lewat++; continue; }
                $upd++;
            } else {
                $baru++;
            }
            $boleh[] = [$id, $nilai];
        }
        if (!$boleh) continue;

        $stmt = $conn->prepare("INSERT INTO kategorisasi_risiko (IdPel, Periode, $kolom, UnitUp, UnitAp, UnitUpi) VALUES "
              . implode(',', array_fill(0, count($boleh), '(?,?,?,?,?,?)'))
              . " ON DUPLICATE KEY UPDATE $kolom = VALUES($kolom),
                    UnitUp  = COALESCE(NULLIF(UnitUp, ''),  VALUES(UnitUp)),
                    UnitAp  = COALESCE(NULLIF(UnitAp, ''),  VALUES(UnitAp)),
                    UnitUpi = COALESCE(NULLIF(UnitUpi, ''), VALUES(UnitUpi))");
        $param = [];
        foreach ($boleh as [$id, $nilai]) array_push($param, $id, $periode, $nilai, $unit[0], $unit[1], $unit[2]);
        $stmt->execute($param);
    }
    $conn->commit();
    return [$baru, $upd, $lewat];
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

    foreach (xlsx_baris($file) as $no => $sel) {
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

    $baru = $upd = $lewat = 0;
    foreach ($per as $p => $pasangan) {
        [$b, $u, $l] = bh_simpan_kategorisasi($conn, $cfg['kolom'], (int) $p, array_values($pasangan), [$up, $ap, $upi], $tingkat);
        $baru += $b; $upd += $u; $lewat += $l;
    }
    $tersimpan = $baru + $upd;
    if ($lewat) $detail[] = "$lewat pelanggan dilewati karena tercatat di unit lain (di luar wilayah bot).";
    if ($tersimpan === 0) {
        return bh_hasil('gagal', $lewat ? 'Semua pelanggan pada file tercatat di unit lain, tidak ada yang disimpan.'
                                        : 'Tidak ada baris valid yang bisa disimpan' . ($tolak ? " ($tolak baris ditolak)." : '.'), 0, $tolak + $lewat, $detail);
    }
    $pesan = "$tersimpan data {$cfg['label']} (periode " . implode('/', array_keys($per)) . ": $baru baru, $upd diperbarui)"
           . ($dup ? ", $dup dobel dalam file" : '') . ($lewat ? ", $lewat dilewati (unit lain)" : '') . ($tolak ? ", $tolak ditolak" : '');
    return bh_hasil('sukses', $pesan, $tersimpan, $tolak + $lewat, $detail);
}