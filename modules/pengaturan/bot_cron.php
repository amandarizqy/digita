<?php
/**
 * modules/pengaturan/bot_cron.php - Pelaksana cron bot_akses.
 * Membaca daftar bot dari tabel bot_akses.
 *
 * Struktur folder (semua di dalam ftp/digitas):
 *   <UnitUpi>/<UnitUp>/<JenisBot>/          <- direktori utama: taruh file .xlsx di sini
 *   <UnitUpi>/<UnitUp>/<JenisBot>/Arsip/    <- direktori arsip: berhasil/<tanggal>/ dan gagal/<tanggal>/
 * Tingkat AP dilewati (tidak dibuat sebagai folder); kode AP hanya dipakai saat mengunggah.
 *
 * Pemakaian (command line):
 *   php bot_cron.php            -> sama dengan "jalan"
 *   php bot_cron.php jalan      -> siapkan folder, lalu unggah file yang ada
 *   php bot_cron.php kering     -> hanya tampilkan file yang akan diunggah (tanpa membuat folder / mengunggah)
 *   php bot_cron.php siapkan    -> hanya membuat folder untuk semua bot (aktif maupun tidak)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line (cron).');
}

if (!ini_get('date.timezone')) date_default_timezone_set('Asia/Jakarta');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../config/database.php';   // $conn (PDO)
require_once __DIR__ . '/bot_helper.php';
require_once __DIR__ . '/bot_handlers.php';

const BC_NAMA_ARSIP = 'Arsip';

function bc_log(string $level, string $pesan): void
{
    $baris = date('Y-m-d H:i:s') . " [$level] $pesan";
    echo $baris . PHP_EOL;
    $berkas = $GLOBALS['BC_LOG'] ?? '';
    if ($berkas === '') return;
    if (@filesize($berkas) > 2000000) {
        for ($i = 4; $i >= 1; $i--) if (is_file("$berkas.$i")) @rename("$berkas.$i", "$berkas." . ($i + 1));
        @rename($berkas, "$berkas.1");
    }
    @file_put_contents($berkas, $baris . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Folder paling atas: htdocs/ftp/digitas (atau isi konstanta BOT_ROOT). Dibuat bila belum ada. */
function bc_root(bool $buat = true): string
{
    $dasar = defined('BOT_ROOT') ? BOT_ROOT : __DIR__ . '/../../../../ftp/digitas';
    if (!is_dir($dasar)) {
        if (!$buat) return str_replace('\\', '/', $dasar);
        if (!@mkdir($dasar, 0775, true) && !is_dir($dasar)) throw new RuntimeException("Folder bot tidak bisa dibuat: $dasar");
        bc_log('INFO', 'Folder dibuat: ' . str_replace('\\', '/', realpath($dasar) ?: $dasar));
    }
    return str_replace('\\', '/', realpath($dasar) ?: $dasar);
}

/** Kode unit/jenis dipakai sebagai nama folder: hanya huruf dan angka (mencegah "..", "/", dsb). */
function bc_kode_valid(string $kode): bool
{
    return (bool) preg_match('/^[A-Za-z0-9]{1,20}$/', $kode);
}

function bc_file_di(string $dir, int $stabil): array
{
    $siap = $tunggu = [];
    $sekarang = time();
    foreach (@scandir($dir) ?: [] as $n) {
        $p = "$dir/$n";
        if (!is_file($p) || $n[0] === '.' || strncmp($n, '~$', 2) === 0) continue;
        if (strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'xlsx') continue;
        if ($sekarang - (int) @filemtime($p) < $stabil) { $tunggu[] = $n; continue; }
        $siap[] = $p;
    }
    return ['siap' => $siap, 'belum_stabil' => $tunggu];
}

/**
 * UP yang termasuk cakupan bot: [['up' => kode UP, 'ap' => kode AP], ...]
 *  - bot level UP  : hanya UP miliknya
 *  - bot level AP  : semua UP di bawah AP-nya
 *  - bot level UPI : semua UP di bawah UPI-nya
 * Kode AP tidak menjadi folder, hanya dibawa untuk keperluan unggah.
 */
function bc_daftar_up(array $bot, array $peta): array
{
    $ap_b = trim((string) $bot['UnitAp']);
    $up_b = trim((string) $bot['UnitUp']);

    if ($up_b !== '') {
        if ($ap_b === '') $ap_b = (string) ($peta['up'][strtoupper($up_b)]['ap'] ?? '');
        return bc_kode_valid($up_b) ? [['up' => $up_b, 'ap' => $ap_b]] : [];
    }

    $hasil = [];
    foreach ($peta['up'] ?? [] as $e) {
        $kode = (string) ($e['kode'] ?? '');
        $ap   = (string) ($e['ap'] ?? '');
        if ($kode === '') continue;
        if ($ap_b !== '') {
            if (strcasecmp($ap, $ap_b) !== 0) continue;
        } elseif (!isset($peta['ap'][strtoupper($ap)])) {
            continue;                                   // bot level UPI: hanya UP di bawah UPI ini
        }
        if (!bc_kode_valid($kode)) { bc_log('WARN', "[Bot #{$bot['IdBot']}] Kode UP '$kode' tidak valid untuk nama folder, dilewati."); continue; }
        $hasil[] = ['up' => $kode, 'ap' => $ap];
    }
    usort($hasil, fn($a, $b) => strcmp($a['up'], $b['up']));
    return $hasil;
}

/** Siapkan data yang dibutuhkan satu bot. Kembalikan ['upi','jenis','daftar_up'] atau ['error' => pesan]. */
function bc_konteks_bot(PDO $conn, array $bot): array
{
    $jenis = (string) $bot['JenisBot'];
    $upi   = trim((string) $bot['UnitUpi']);

    if (!isset(bot_jenis_daftar()[$jenis])) return ['error' => "Jenis bot '$jenis' tidak dikenal."];
    if ($upi === '') return ['error' => 'Bot belum punya Unit UPI.'];
    if (!bc_kode_valid($upi) || !bc_kode_valid($jenis)) return ['error' => 'Kode UPI/jenis bot tidak valid untuk nama folder.'];

    $peta      = bh_peta_unit($conn, $upi, trim((string) $bot['UnitAp']), trim((string) $bot['UnitUp']));
    $daftar_up = bc_daftar_up($bot, $peta);
    if (!$daftar_up) return ['error' => 'Tidak ada UP dalam cakupan bot ini.'];

    return ['upi' => $upi, 'jenis' => $jenis, 'daftar_up' => $daftar_up];
}

/**
 * Pastikan <root>/<UPI>/<UP>/<JENIS>/ dan <root>/<UPI>/<UP>/<JENIS>/Arsip/ ada.
 * Urutan: folder UPI -> folder UP -> folder jenis bot -> Arsip. Yang sudah ada dilewati, yang belum dibuat.
 * Kembalikan [daftar folder yang baru dibuat, daftar folder yang gagal dibuat] (path relatif terhadap root).
 */
function bc_siapkan_folder(string $root, string $upi, string $jenis, array $daftar_up): array
{
    $dibuat = $gagal = [];
    $pastikan = function (string $rel) use ($root, &$dibuat, &$gagal): bool {
        $abs = $root . '/' . $rel;
        if (is_dir($abs)) return true;                                    // sudah ada -> lewati
        if (@mkdir($abs, 0775, true) || is_dir($abs)) { $dibuat[] = $rel; return true; }
        $gagal[] = $rel;
        return false;
    };

    if (!$pastikan($upi)) return [$dibuat, $gagal];                       // 1. folder UPI
    foreach ($daftar_up as $u) {
        $rel_up = $upi . '/' . $u['up'];
        if (!$pastikan($rel_up)) continue;                                // 2. folder UP (tingkat AP dilewati)
        if (!$pastikan($rel_up . '/' . $jenis)) continue;                 // 3. folder jenis bot
        $pastikan($rel_up . '/' . $jenis . '/' . BC_NAMA_ARSIP);          // 4. folder Arsip
    }
    return [$dibuat, $gagal];
}

/** Cari file di <root>/<UPI>/<UP>/<JENIS>/ untuk semua UP dalam cakupan bot. */
function bc_cari_tugas(string $root, string $upi, string $jenis, array $daftar_up, int $stabil): array
{
    $tugas = $salah = $tunggu = [];
    foreach ($daftar_up as $u) {
        $dir_up = "$root/$upi/{$u['up']}";

        // file .xlsx yang diletakkan langsung di folder UP (bukan di folder jenis bot) = salah tempat
        foreach (bc_file_di($dir_up, 0)['siap'] as $f) $salah[] = $f;

        $dir = "$dir_up/$jenis";
        if (!is_dir($dir)) continue;
        $r = bc_file_di($dir, $stabil);
        foreach ($r['belum_stabil'] as $n) $tunggu[] = "{$u['up']}/$jenis/$n";
        foreach ($r['siap'] as $f) {
            if ($jenis !== 'DIL' && ($u['ap'] === '' || $u['up'] === '')) { $salah[] = $f; continue; }
            $tugas[] = ['file' => $f, 'ap' => $u['ap'], 'up' => $u['up']];
        }
    }
    return [$tugas, $salah, $tunggu];
}

/** Pindahkan file ke <UPI>/<UP>/<JENIS>/Arsip/<berhasil|gagal>/<tanggal>/. */
function bc_pindah(string $file, string $root, string $upi, string $up, string $jenis, string $hasil, string $catatan = ''): string
{
    $dir = "$root/$upi/$up/$jenis/" . BC_NAMA_ARSIP . "/$hasil/" . date('Y-m-d');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Tidak bisa membuat folder $dir");
    $tujuan = $dir . '/' . date('His') . '_' . basename($file);
    if (!@rename($file, $tujuan)) {
        if (!@copy($file, $tujuan)) throw new RuntimeException("Tidak bisa memindahkan file ke $tujuan");
        @unlink($file);
    }
    if ($catatan !== '') @file_put_contents($tujuan . '.txt', $catatan);
    return $tujuan;
}

function bc_catatan(string $nama, array $hasil): string
{
    $t = "File    : $nama\r\nWaktu   : " . date('Y-m-d H:i:s') . "\r\nHasil   : " . $hasil['pesan'] . "\r\n";
    if ($hasil['detail']) $t .= "\r\nBaris ditolak (maksimal 50 contoh):\r\n- " . implode("\r\n- ", $hasil['detail']) . "\r\n";
    return $t;
}

function bc_rel(string $path, string $root): string
{
    return strncmp($path, $root . '/', strlen($root) + 1) === 0 ? substr($path, strlen($root) + 1) : $path;
}

function bc_proses_bot(PDO $conn, array $bot, string $root, bool $kering, int $stabil): array
{
    $gagal = fn(string $p) => ['kerja' => true, 'status' => 'GAGAL', 'pesan' => $p];
    $tag   = "[Bot #{$bot['IdBot']}]";

    $k = bc_konteks_bot($conn, $bot);
    if (isset($k['error'])) return $gagal($k['error']);
    ['upi' => $upi, 'jenis' => $jenis, 'daftar_up' => $daftar_up] = $k;

    if (!$kering) {
        [$dibuat, $sulit] = bc_siapkan_folder($root, $upi, $jenis, $daftar_up);
        foreach ($dibuat as $rel) bc_log('INFO', "$tag Folder dibuat: $rel");
        if ($sulit) return $gagal('Folder tidak bisa dibuat: ' . implode(', ', $sulit));
    }

    [$tugas, $salah, $tunggu] = bc_cari_tugas($root, $upi, $jenis, $daftar_up, $stabil);
    foreach ($salah as $f) bc_log('WARN', "$tag File salah tempat, tidak diproses (taruh di $upi/<UP>/$jenis/): " . bc_rel($f, $root));
    if ($tunggu) bc_log('INFO', "$tag " . count($tunggu) . ' file belum stabil (baru diubah), menunggu putaran berikutnya.');
    if (!$tugas) return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];

    if ($kering) {
        foreach ($tugas as $t) bc_log('INFO', "[KERING] $tag " . bc_rel($t['file'], $root));
        return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];
    }

    foreach ($tugas as $t) {
        $nama = basename($t['file']);
        bc_log('INFO', "$tag Mengunggah: " . bc_rel($t['file'], $root));
        $h = bh_jalankan($conn, $bot, $jenis, $t['file'], $t['ap'], $t['up']);

        if ($h['status'] === 'sukses' || $h['status'] === 'gagal') {
            try {
                $tujuan = bc_pindah($t['file'], $root, $upi, $t['up'], $jenis,
                                    $h['status'] === 'sukses' ? 'berhasil' : 'gagal', bc_catatan($nama, $h));
                bc_log('INFO', "  SELESAI -> " . bc_rel($tujuan, $root) . " | {$h['pesan']}");
            } catch (Throwable $e) {
                bc_log('ERROR', "  Gagal memindahkan file: {$e->getMessage()}");
            }
        } else {
            bc_log('WARN', "  DITUNDA ({$h['status']}), file dibiarkan di tempatnya | {$h['pesan']}");
        }
    }

    return ['kerja' => true, 'status' => 'BERHASIL', 'pesan' => 'Proses selesai'];
}

function bc_ambil_bot(PDO $conn, bool $hanya_aktif, ?int $id_bot): array
{
    $w = []; $p = [];
    if ($hanya_aktif) $w[] = "StatusData = 'AKTIF'";
    if ($id_bot !== null) { $w[] = 'IdBot = ?'; $p[] = $id_bot; }
    $st = $conn->prepare('SELECT * FROM bot_akses' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY IdBot');
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function bc_jalan(PDO $conn, string $root, bool $kering, ?int $id_bot, int $stabil): int
{
    $bots = bc_ambil_bot($conn, true, $id_bot);
    if (!$bots) { bc_log('INFO', 'Tidak ada bot berstatus AKTIF.'); return 0; }

    foreach ($bots as $bot) {
        try {
            $r = bc_proses_bot($conn, $bot, $root, $kering, $stabil);
            if ($r['status'] === 'GAGAL') bc_log('ERROR', "[Bot #{$bot['IdBot']}] {$r['pesan']}");
            $conn->prepare("UPDATE bot_akses SET WaktuData = NOW() WHERE IdBot = ?")->execute([$bot['IdBot']]);
        } catch (Throwable $e) {
            bc_log('ERROR', "[Bot #{$bot['IdBot']}] " . $e->getMessage());
        }
    }
    return 0;
}

/** Perintah "siapkan": buat folder untuk semua bot (aktif maupun tidak) tanpa mengunggah apa pun. */
function bc_siapkan_semua(PDO $conn, string $root): int
{
    $bots = bc_ambil_bot($conn, false, null);
    if (!$bots) { bc_log('INFO', 'Belum ada bot di tabel bot_akses.'); return 0; }

    $kembali = 0;
    foreach ($bots as $bot) {
        $tag = "[Bot #{$bot['IdBot']}]";
        $k = bc_konteks_bot($conn, $bot);
        if (isset($k['error'])) { bc_log('ERROR', "$tag {$k['error']}"); $kembali = 1; continue; }

        [$dibuat, $sulit] = bc_siapkan_folder($root, $k['upi'], $k['jenis'], $k['daftar_up']);
        foreach ($dibuat as $rel) bc_log('INFO', "$tag Folder dibuat: $rel");
        if ($sulit) { bc_log('ERROR', "$tag Folder tidak bisa dibuat: " . implode(', ', $sulit)); $kembali = 1; }
        if (!$dibuat && !$sulit) bc_log('INFO', "$tag Semua folder sudah ada.");
    }
    return $kembali;
}

$perintah = $argv[1] ?? 'jalan';
if (!in_array($perintah, ['jalan', 'kering', 'siapkan'], true)) {
    bc_log('ERROR', "Perintah tidak dikenal: $perintah (pakai: jalan | kering | siapkan)");
    exit(1);
}

try {
    $root = bc_root($perintah !== 'kering');
} catch (Throwable $e) {
    bc_log('ERROR', $e->getMessage());
    exit(1);
}

exit($perintah === 'siapkan' ? bc_siapkan_semua($conn, $root) : bc_jalan($conn, $root, $perintah === 'kering', null, 60));