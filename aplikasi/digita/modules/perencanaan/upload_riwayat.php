<?php
// modules/perencanaan/upload_riwayat.php
// Unggah XLSX riwayat pelunasan -> tabel pelunasan_ap2t.
// Cepat: file dibaca streaming, disimpan per 500 baris (1 query) dalam 1 transaksi.
// Kunci unik (IdPel, ThBlRek): data yang sudah ada otomatis DIPERBARUI, bukan dobel.
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$riwayat_chunk = 500;

// ---- PETA KOLOM: kolom tabel => nama kolom di Excel (huruf kecil, tanpa spasi). Ubah di sini bila perlu.
$riwayat_peta = [
    'idpel' => ['idpel'],
    'bulan' => ['bulan', 'thblrek'],            // -> ThBlRek
    'tgl'   => ['tgltransaksi', 'tglbayar'],    // -> TglBayar
    'rpbk'  => ['rpbk'],                        // -> RpBK
    'rptag' => ['rptagihan', 'rptag'],          // -> RpTag
];
$riwayat_nama = ['idpel' => 'Idpel', 'bulan' => 'Bulan', 'tgl' => 'TglTransaksi', 'rpbk' => 'RpBK', 'rptag' => 'RpTagihan'];

// ---- Normalisasi nilai ------------------------------------------------------------
if (!function_exists('rw_idpel')) {
function rw_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

function rw_idpel($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))            $v = preg_replace('/\.0+$/', '', $v);
    elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v)) $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
    else return null;
    if (strlen($v) === 11) $v = '0' . $v;           // nol di depan hilang oleh Excel
    return preg_match('/^\d{12}$/', $v) ? $v : null;
}

function rw_bulan($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
}

/** null = kosong, false = tidak valid, string = Y-m-d */
function rw_tanggal($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))       return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m))   return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : false;
    if (is_numeric($v) && $v > 20000 && $v < 80000)             return gmdate('Y-m-d', ((int) $v - 25569) * 86400);   // tanggal serial Excel
    return false;
}

function rw_angka($v): ?float
{
    $v = trim((string) $v);
    return $v === '' ? 0.0 : (is_numeric($v) ? (float) $v : null);
}
}

// ---- 1. Unit dari akun yang login ------------------------------------------------
$pesan_sukses = $pesan_error = '';
$hasil   = null;     // ringkasan setelah unggah
$ditolak = [];       // contoh baris ditolak (maks 50)

$unitUp  = $_SESSION['UnitUp']  ?? '';
$unitAp  = $_SESSION['UnitAp']  ?? '';
$unitUpi = $_SESSION['UnitUpi'] ?? '';
if (($unitUp === '' || $unitAp === '' || $unitUpi === '') && !empty($_SESSION['NamaAkun'])) {
    try {
        $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $st->execute([$_SESSION['NamaAkun']]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
            $unitUp  = $unitUp  ?: (string) $u['UnitUp'];
            $unitAp  = $unitAp  ?: (string) $u['UnitAp'];
            $unitUpi = $unitUpi ?: (string) $u['UnitUpi'];
        }
    } catch (PDOException $e) { /* ditangani di bawah */ }
}
$unit_siap = $unitUp !== '' && $unitAp !== '' && $unitUpi !== '';

// ---- 2. Proses unggah -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['file_xlsx'] ?? null;

    if (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki UnitUp / UnitAp / UnitUpi di master pengguna.';
    } elseif ($f === null || in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $pesan_error = 'File melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ').';
    } elseif ($f['error'] !== UPLOAD_ERR_OK) {
        $pesan_error = 'Pilih file .xlsx terlebih dahulu.';
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($f['tmp_name'], false, null, 0, 2) !== 'PK') {
        $pesan_error = 'Format file harus .xlsx (Excel).';
    } else {
        set_time_limit(300);
        $t0 = microtime(true);
        $baris = [];            // dedup per IdPel|ThBlRek
        $kat   = [];            // unik per IdPel|Periode (tahun) -> kategorisasi_risiko
        $katBaru = 0;
        $dibaca = $tolak = $duplikat = 0;
        $peta = null;

        try {
            foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                // a. cari baris judul kolom
                if ($peta === null) {
                    if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                    $kol = [];
                    foreach ($sel as $huruf => $nilai) $kol[rw_header($nilai)] = $huruf;
                    if (!isset($kol['idpel'])) continue;

                    $peta = [];
                    foreach ($riwayat_peta as $field => $alias) {
                        foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
                    }
                    $kurang = array_diff(array_keys($riwayat_peta), array_keys($peta));
                    if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $riwayat_nama[$k], $kurang)) . '.');
                    continue;
                }

                // b. baris data
                if (trim(implode('', $sel)) === '') continue;
                $dibaca++;
                $g = fn($field) => $sel[$peta[$field]] ?? '';

                $id  = rw_idpel($g('idpel'));
                $bln = rw_bulan($g('bulan'));
                $tgl = rw_tanggal($g('tgl'));
                $bk  = rw_angka($g('rpbk'));
                $tagRaw = trim((string) $g('rptag'));
                $tag = $tagRaw === '' ? null : rw_angka($tagRaw);

                $alasan = $id === null ? 'IdPel harus 12 digit angka'
                        : ($bln === null ? 'Bulan harus format YYYYMM'
                        : ($tgl === false ? 'TglTransaksi tidak valid'
                        : ($bk === null ? 'RpBK bukan angka'
                        : ($tag === null ? 'RpTagihan kosong / bukan angka' : null))));
                if ($alasan !== null) {
                    $tolak++;
                    if (count($ditolak) < 50) $ditolak[] = [$no, (string) $g('idpel'), $alasan];
                    continue;
                }

                $kunci = $id . '|' . $bln;
                if (isset($baris[$kunci])) $duplikat++;          // baris terakhir yang dipakai
                $baris[$kunci] = [$id, $bln, $tgl, (int) round($bk), (int) round($tag)];
                $thn = (int) substr($bln, 0, 4);                 // Periode = tahun dari Bulan
                $kat[$id . '|' . $thn] = [$id, $thn];
            }
            if ($peta === null) throw new RuntimeException('Baris judul kolom (Idpel, Bulan, ...) tidak ditemukan.');

            // c. simpan batch
            if ($baris) {
                $head = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi) VALUES ";
                $tail = " ON DUPLICATE KEY UPDATE TglBayar = VALUES(TglBayar), RpBK = VALUES(RpBK), RpTag = VALUES(RpTag),
                          UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi)";
                $conn->beginTransaction();
                foreach (array_chunk(array_values($baris), $riwayat_chunk) as $paket) {
                    $stmt  = $conn->prepare($head . implode(',', array_fill(0, count($paket), '(?,?,?,?,?,?,?,?)')) . $tail);
                    $param = [];
                    foreach ($paket as $b) array_push($param, $b[0], $b[1], $b[2], $b[3], $b[4], $unitUp, $unitAp, $unitUpi);
                    $stmt->execute($param);
                }

                // d. kategorisasi_risiko: tambah (IdPel, Periode) yang belum ada. Yang sudah ada TIDAK diubah
                //    (Posisi SR, Kepentingan, dan hasil klasifikasinya aman). INSERT IGNORE: kunci ganda dilewati tanpa error.
                foreach (array_chunk(array_values($kat), $riwayat_chunk) as $paket) {
                    $stmt  = $conn->prepare("INSERT IGNORE INTO kategorisasi_risiko (IdPel, Periode, UnitUp, UnitAp, UnitUpi) VALUES "
                           . implode(',', array_fill(0, count($paket), '(?,?,?,?,?)')));
                    $param = [];
                    foreach ($paket as $k) array_push($param, $k[0], $k[1], $unitUp, $unitAp, $unitUpi);
                    $stmt->execute($param);
                    $katBaru += $stmt->rowCount();
                }
                $conn->commit();
            }

            $hasil = ['dibaca' => $dibaca, 'tersimpan' => count($baris), 'duplikat' => $duplikat, 'ditolak' => $tolak,
                      'durasi' => round(microtime(true) - $t0, 2), 'nama' => $f['name'], 'kat_baru' => $katBaru, 'kat_total' => count($kat)];
            $pesan_sukses = count($baris) > 0
                ? 'Berhasil memproses ' . number_format(count($baris), 0, ',', '.') . ' baris riwayat pelunasan; '
                  . number_format($katBaru, 0, ',', '.') . ' pelanggan-periode baru ditambahkan ke kategorisasi_risiko.'
                : '';
            if (!$baris) $pesan_error = 'Tidak ada baris valid yang bisa disimpan.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal memproses file: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../templates/perencanaan/upload_riwayat.php';