<?php
// functions/login_throttle.php
// Pembatasan percobaan login (anti tebak sandi) TANPA tabel database.
// Status disimpan sebagai file kecil di folder sementara server (satu file per kunci), dengan flock()
// agar aman bila ada beberapa permintaan bersamaan. Tidak bisa dihindari dengan menghapus cookie.
//
// Dua lapis kunci:
//   - "ua:<username>|<ip>" : pasangan akun + alamat IP     -> batas kecil (default 5)
//   - "ip:<ip>"            : seluruh percobaan dari satu IP -> batas lebih besar (default 20)
// Pasangan akun+IP dipakai (bukan akun saja) agar penyerang tidak bisa mengunci akun orang lain
// dari luar; pemilik akun yang login dari tempat lain tidak ikut terkunci.

const LT_BATAS_AKUN    = 5;    // gagal per (akun + IP) sebelum dikunci
const LT_BATAS_IP      = 20;   // gagal per IP sebelum dikunci
const LT_JENDELA_MENIT = 15;   // hitungan gagal dihitung ulang bila percobaan pertama sudah lebih tua dari ini
const LT_KUNCI_MENIT   = 15;   // lama penguncian

/** Folder penyimpanan status (dibuat otomatis). Ubah di sini bila ingin lokasi lain, misalnya __DIR__ . '/../storage/login'. */
function lt_folder(): string
{
    $d = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'digita_login_throttle';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

function lt_path(string $kunci): string
{
    return lt_folder() . DIRECTORY_SEPARATOR . hash('sha256', $kunci) . '.json';
}

/** Baca status satu kunci: ['gagal' => int, 'pertama' => unix, 'kunci_sampai' => unix|0]. */
function lt_baca(string $kunci): array
{
    $kosong = ['gagal' => 0, 'pertama' => 0, 'kunci_sampai' => 0];
    $p = lt_path($kunci);
    if (!is_file($p)) return $kosong;
    $fh = @fopen($p, 'r');
    if (!$fh) return $kosong;
    flock($fh, LOCK_SH);
    $isi = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $d = json_decode((string) $isi, true);
    return is_array($d) ? array_merge($kosong, $d) : $kosong;
}

/** Ubah status satu kunci secara atomik: $fn menerima array status lama dan mengembalikan yang baru. */
function lt_ubah(string $kunci, callable $fn): void
{
    $fh = @fopen(lt_path($kunci), 'c+');
    if (!$fh) { error_log('login_throttle: tidak bisa menulis ke ' . lt_folder()); return; }   // gagal-buka: pembatas dilewati, login tetap bisa
    flock($fh, LOCK_EX);
    $d = json_decode((string) stream_get_contents($fh), true);
    $d = is_array($d) ? array_merge(['gagal' => 0, 'pertama' => 0, 'kunci_sampai' => 0], $d) : ['gagal' => 0, 'pertama' => 0, 'kunci_sampai' => 0];
    $baru = $fn($d);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($baru));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

/** Sisa detik terkunci untuk salah satu kunci (0 = boleh mencoba). */
function lt_sisa_kunci(array $kunci): int
{
    $sisa = 0;
    foreach ($kunci as $k) {
        $d = lt_baca($k);
        $sisa = max($sisa, $d['kunci_sampai'] - time());
    }
    return max(0, $sisa);
}

/** Catat satu kegagalan; mengunci bila mencapai $batas. */
function lt_catat_gagal(string $kunci, int $batas): void
{
    lt_ubah($kunci, function (array $d) use ($batas) {
        $sekarang   = time();
        $kadaluarsa = $d['pertama'] === 0
                   || $d['pertama'] < $sekarang - LT_JENDELA_MENIT * 60
                   || ($d['kunci_sampai'] > 0 && $d['kunci_sampai'] <= $sekarang);
        if ($kadaluarsa) { $d['gagal'] = 0; $d['pertama'] = $sekarang; $d['kunci_sampai'] = 0; }
        $d['gagal']++;
        if ($d['gagal'] >= $batas) $d['kunci_sampai'] = $sekarang + LT_KUNCI_MENIT * 60;
        return $d;
    });

    // Bersih-bersih sesekali: hapus file status yang sudah lebih dari 1 hari
    if (random_int(1, 50) === 1) {
        foreach (glob(lt_folder() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) @unlink($f);
        }
    }
}

/** Jumlah gagal tercatat untuk satu kunci (0 bila belum ada / sudah kedaluwarsa). */
function lt_jumlah_gagal(string $kunci): int
{
    $d = lt_baca($kunci);
    if ($d['pertama'] < time() - LT_JENDELA_MENIT * 60 && $d['kunci_sampai'] <= time()) return 0;
    return (int) $d['gagal'];
}

/** Hapus catatan setelah login berhasil. */
function lt_reset(string $kunci): void
{
    @unlink(lt_path($kunci));
}

/** "X menit Y detik" yang ramah dibaca. */
function lt_format_sisa(int $detik): string
{
    $menit = intdiv($detik, 60);
    $sisa  = $detik % 60;
    if ($menit > 0 && $sisa > 0) return "$menit menit $sisa detik";
    if ($menit > 0)              return "$menit menit";
    return "$sisa detik";
}