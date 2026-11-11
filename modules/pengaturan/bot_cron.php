<?php
/**
 * modules/pengaturan/bot_cron.php - Pelaksana cron bot_akses.
 *
 * Folder mengikuti kolom DirektoriUtama / DirektoriArsip di tabel bot_akses (diatur di halaman Daftar Bot),
 * relatif terhadap BOT_ROOT (config/bot.php). Aturan lengkapnya ada di bot_helper.php:
 *
 *   bot level UP      : file .xlsx langsung di <DirektoriUtama>/
 *   bot level AP/UPI  : file .xlsx di <DirektoriUtama>/<KodeUP>/   (khusus DIL boleh langsung di <DirektoriUtama>/)
 *   hasil             : <DirektoriArsip>[/<KodeUP>]/<berhasil|gagal>/<tanggal>/
 *   log               : <BOT_ROOT>/log/bot_cron.log
 *
 * Pemakaian (command line):
 *   php bot_cron.php                  -> sama dengan "jalan"
 *   php bot_cron.php jalan            -> siapkan folder, lalu unggah file yang ada
 *   php bot_cron.php kering           -> hanya tampilkan file yang akan diunggah (tanpa membuat folder / mengunggah)
 *   php bot_cron.php siapkan          -> hanya membuat folder untuk semua bot (aktif maupun tidak)
 *   php bot_cron.php jalan --bot=3    -> batasi pada satu bot (IdBot)
 *
 * Kode keluar: 0 = semua baik, 1 = ada bot yang GAGAL (supaya penjadwal / monitoring bisa mendeteksi).
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

const BC_STABIL_DETIK = 60;   // file harus tidak berubah selama ini sebelum diproses (menghindari file yang masih disalin)
const BC_MAKS_TUNDA   = 3;    // berhenti memproses satu bot setelah sekian gangguan sementara beruntun

// ============================================================ log
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

// ============================================================ root
/** Folder paling atas (BOT_ROOT dari config/bot.php). Dibuat bila belum ada dan $buat = true. */
function bc_root(bool $buat = true): string
{
    if (!defined('BOT_ROOT') || BOT_ROOT === '') throw new RuntimeException('BOT_ROOT belum didefinisikan (config/bot.php).');
    $dasar = BOT_ROOT;
    if (!is_dir($dasar)) {
        if (!$buat) return str_replace('\\', '/', $dasar);
        if (!@mkdir($dasar, 0775, true) && !is_dir($dasar)) throw new RuntimeException("Folder bot tidak bisa dibuat: $dasar");
        bc_log('INFO', 'Folder dibuat: ' . str_replace('\\', '/', realpath($dasar) ?: $dasar));
    }
    return str_replace('\\', '/', realpath($dasar) ?: $dasar);
}

// ============================================================ file
/** Daftar .xlsx di satu folder: ['siap' => [path], 'belum_stabil' => [nama]]. Subfolder (mis. Arsip) tidak dipindai. */
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

function bc_rel(string $path, string $root): string
{
    return strncmp($path, $root . '/', strlen($root) + 1) === 0 ? substr($path, strlen($root) + 1) : $path;
}

/** Pindahkan file ke <Arsip>[/<UP>]/<berhasil|gagal>/<tanggal>/. */
function bc_pindah(string $file, string $root, string $arsip, string $up, string $hasil, string $catatan = ''): string
{
    $dir = "$root/$arsip" . ($up !== '' ? "/$up" : '') . "/$hasil/" . date('Y-m-d');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Tidak bisa membuat folder $dir");
    $tujuan = $dir . '/' . date('His') . '_' . basename($file);
    if (file_exists($tujuan)) $tujuan = $dir . '/' . date('His') . '_' . substr(uniqid('', true), -6) . '_' . basename($file);
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

// ============================================================ konteks & tugas
/** Siapkan data satu bot. Kembalikan konteks, atau ['error' => pesan]. */
function bc_konteks_bot(PDO $conn, array $bot): array
{
    $jenis = trim((string) $bot['JenisBot']);
    $upi   = trim((string) $bot['UnitUpi']);

    if (!isset(bot_jenis_daftar()[$jenis])) return ['error' => "Jenis bot '$jenis' tidak dikenal."];
    if ($upi === '') return ['error' => 'Bot belum punya Unit UPI.'];

    $lok = bot_lokasi($bot);
    if (isset($lok['error'])) return ['error' => $lok['error']];

    $dilewati  = [];
    $daftar_up = bot_daftar_up($conn, $bot, $dilewati);
    foreach ($dilewati as $kode) bc_log('WARN', "[Bot #{$bot['IdBot']}] Kode UP '$kode' tidak valid untuk nama folder, dilewati.");
    if (!$daftar_up) return ['error' => 'Tidak ada UP aktif dalam cakupan bot ini (cek Unit AP/UP bot dan status master_up).'];

    return ['upi' => $upi, 'jenis' => $jenis, 'utama' => $lok['utama'], 'arsip' => $lok['arsip'], 'multi' => $lok['multi'], 'daftar_up' => $daftar_up];
}

/**
 * Cari file yang siap diunggah.
 * @return array [tugas, salah, tunggu]; tugas = [['file','ap','up'], ...]
 */
function bc_cari_tugas(string $root, array $k, int $stabil): array
{
    $tugas = $salah = $tunggu = [];
    $utama = "$root/{$k['utama']}";
    $jenis = $k['jenis'];

    // Bot level AP/UPI: file yang diletakkan langsung di Direktori Utama.
    // Hanya DIL yang boleh (unit diambil dari isi file); jenis lain harus di subfolder <KodeUP>.
    if ($k['multi']) {
        $r = bc_file_di($utama, $stabil);
        foreach ($r['belum_stabil'] as $n) $tunggu[] = "{$k['utama']}/$n";
        foreach ($r['siap'] as $f) {
            if ($jenis === 'DIL') $tugas[] = ['file' => $f, 'ap' => '', 'up' => ''];
            else $salah[] = $f;
        }
    }

    foreach ($k['daftar_up'] as $u) {
        $dir = $k['multi'] ? "$utama/{$u['up']}" : $utama;
        if (!is_dir($dir)) continue;
        $r = bc_file_di($dir, $stabil);
        foreach ($r['belum_stabil'] as $n) $tunggu[] = bc_rel("$dir/$n", $root);
        foreach ($r['siap'] as $f) {
            if ($jenis !== 'DIL' && ($u['ap'] === '' || $u['up'] === '')) { $salah[] = $f; continue; }
            $tugas[] = ['file' => $f, 'ap' => $u['ap'], 'up' => $u['up']];
        }
    }
    return [$tugas, $salah, $tunggu];
}

// ============================================================ status ke bot_akses
/** Kolom status yang benar-benar ada di bot_akses (lihat BOT_KOL_* di bot_helper.php). */
function bc_kolom_status(PDO $conn): array
{
    static $ada = null;
    if ($ada !== null) return $ada;
    $ada = [];
    foreach ([BOT_KOL_JALAN, BOT_KOL_STATUS, BOT_KOL_PESAN] as $kol) {
        $st = $conn->query('SHOW COLUMNS FROM bot_akses LIKE ' . $conn->quote($kol));
        if ($st && $st->fetch()) $ada[] = $kol;
    }
    return $ada;
}

/**
 * Catat hasil putaran. WaktuData tidak disentuh (itu waktu konfigurasi diubah dari halaman Daftar Bot).
 * Putaran tanpa file ('KOSONG') hanya memperbarui waktu terakhir jalan, supaya hasil proses terakhir tidak tertimpa.
 */
function bc_catat(PDO $conn, array $bot, array $r): void
{
    $ada = bc_kolom_status($conn);
    $set = []; $prm = [];
    if (in_array(BOT_KOL_JALAN, $ada, true)) $set[] = BOT_KOL_JALAN . ' = NOW()';
    if ($r['status'] !== 'KOSONG') {
        if (in_array(BOT_KOL_STATUS, $ada, true)) { $set[] = BOT_KOL_STATUS . ' = ?'; $prm[] = mb_substr($r['status'], 0, 10); }
        if (in_array(BOT_KOL_PESAN, $ada, true))  { $set[] = BOT_KOL_PESAN . ' = ?';  $prm[] = mb_substr($r['pesan'], 0, 255); }
    }
    if (!$set) return;
    $prm[] = $bot['IdBot'];
    $conn->prepare('UPDATE bot_akses SET ' . implode(', ', $set) . ' WHERE IdBot = ?')->execute($prm);
}

// ============================================================ proses satu bot
function bc_proses_bot(PDO $conn, array $bot, string $root, bool $kering, int $stabil): array
{
    $tag      = "[Bot #{$bot['IdBot']}]";
    $gagal    = fn(string $p) => ['status' => 'GAGAL', 'pesan' => $p];
    $kosong   = ['status' => 'KOSONG', 'pesan' => ''];

    $k = bc_konteks_bot($conn, $bot);
    if (isset($k['error'])) return $gagal($k['error']);

    if (!$kering) {
        [$dibuat, $sulit] = bot_siapkan_folder($root, $conn, $bot);
        foreach ($dibuat as $rel) bc_log('INFO', "$tag Folder dibuat: $rel");
        if ($sulit) return $gagal('Folder tidak bisa dibuat: ' . implode(', ', $sulit));
    }

    [$tugas, $salah, $tunggu] = bc_cari_tugas($root, $k, $stabil);
    $petunjuk = $k['multi'] ? "{$k['utama']}/<KodeUP>/" : "{$k['utama']}/";
    foreach ($salah as $f) bc_log('WARN', "$tag File salah tempat, tidak diproses (taruh di $petunjuk): " . bc_rel($f, $root));
    if ($tunggu) bc_log('INFO', "$tag " . count($tunggu) . ' file belum stabil (baru diubah), menunggu putaran berikutnya.');
    if (!$tugas) return $kosong;

    if ($kering) {
        foreach ($tugas as $t) bc_log('INFO', "[KERING] $tag " . bc_rel($t['file'], $root) . ' -> AP ' . ($t['ap'] ?: '-') . ' / UP ' . ($t['up'] ?: '-'));
        return $kosong;
    }

    $ok = $tidak = $tunda = $beruntun = 0;
    $terakhir = '';
    foreach ($tugas as $t) {
        $nama = basename($t['file']);
        bc_log('INFO', "$tag Mengunggah: " . bc_rel($t['file'], $root) . ' (' . $k['jenis'] . ')');
        $h = bh_jalankan($conn, $bot, $k['jenis'], $t['file'], $t['ap'], $t['up']);
        $terakhir = "$nama: " . $h['pesan'];

        if ($h['status'] === 'sukses' || $h['status'] === 'gagal') {
            $beruntun = 0;
            $h['status'] === 'sukses' ? $ok++ : $tidak++;
            try {
                $tujuan = bc_pindah($t['file'], $root, $k['arsip'], $k['multi'] ? $t['up'] : '',
                                    $h['status'] === 'sukses' ? 'berhasil' : 'gagal', bc_catatan($nama, $h));
                bc_log($h['status'] === 'sukses' ? 'INFO' : 'WARN', '  SELESAI -> ' . bc_rel($tujuan, $root) . " | {$h['pesan']}");
            } catch (Throwable $e) {
                // data sudah diproses tetapi file tidak bisa dipindah: akan terbaca lagi di putaran berikutnya
                bc_log('ERROR', "  Gagal memindahkan file (akan terbaca lagi): {$e->getMessage()}");
            }
        } else {
            $tunda++;
            bc_log('WARN', "  DITUNDA ({$h['status']}), file dibiarkan di tempatnya | {$h['pesan']}");
            if (++$beruntun >= BC_MAKS_TUNDA) {
                bc_log('ERROR', "$tag " . BC_MAKS_TUNDA . ' gangguan sementara beruntun, bot dihentikan untuk putaran ini.');
                break;
            }
        }
    }

    $status = ($tidak === 0 && $tunda === 0) ? 'BERHASIL'
            : ($ok === 0 && $tidak > 0 && $tunda === 0 ? 'GAGAL'
            : ($ok === 0 && $tidak === 0 ? 'TUNDA' : 'SEBAGIAN'));
    $pesan = ($ok + $tidak + $tunda) . " file: $ok berhasil, $tidak gagal, $tunda ditunda. Terakhir - $terakhir";
    return ['status' => $status, 'pesan' => $pesan];
}

// ============================================================ perintah
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
    if (!$bots) { bc_log('INFO', 'Tidak ada bot berstatus AKTIF' . ($id_bot !== null ? " dengan IdBot $id_bot" : '') . '.'); return 0; }

    $kembali = 0;
    foreach ($bots as $bot) {
        try {
            $r = bc_proses_bot($conn, $bot, $root, $kering, $stabil);
        } catch (Throwable $e) {
            $r = ['status' => 'GAGAL', 'pesan' => 'Kesalahan tak terduga: ' . $e->getMessage()];
        }
        if ($r['status'] === 'GAGAL') { bc_log('ERROR', "[Bot #{$bot['IdBot']}] {$r['pesan']}"); $kembali = 1; }
        if (!$kering) {
            try { bc_catat($conn, $bot, $r); }
            catch (Throwable $e) { bc_log('ERROR', "[Bot #{$bot['IdBot']}] Gagal mencatat status: {$e->getMessage()}"); }
        }
    }
    return $kembali;
}

/** Perintah "siapkan": buat folder untuk semua bot (aktif maupun tidak) tanpa mengunggah apa pun. */
function bc_siapkan_semua(PDO $conn, string $root, ?int $id_bot): int
{
    $bots = bc_ambil_bot($conn, false, $id_bot);
    if (!$bots) { bc_log('INFO', 'Belum ada bot di tabel bot_akses.'); return 0; }

    $kembali = 0;
    foreach ($bots as $bot) {
        $tag = "[Bot #{$bot['IdBot']}]";
        $k = bc_konteks_bot($conn, $bot);
        if (isset($k['error'])) { bc_log('ERROR', "$tag {$k['error']}"); $kembali = 1; continue; }

        [$dibuat, $sulit] = bot_siapkan_folder($root, $conn, $bot);
        foreach ($dibuat as $rel) bc_log('INFO', "$tag Folder dibuat: $rel");
        if ($sulit) { bc_log('ERROR', "$tag Folder tidak bisa dibuat: " . implode(', ', $sulit)); $kembali = 1; }
        if (!$dibuat && !$sulit) bc_log('INFO', "$tag Semua folder sudah ada.");
    }
    return $kembali;
}

// ============================================================ main
$perintah = 'jalan';
$id_bot   = null;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--bot=(\d+)$/', $a, $m))                      $id_bot = (int) $m[1];
    elseif (in_array($a, ['jalan', 'kering', 'siapkan'], true))     $perintah = $a;
    else { bc_log('ERROR', "Argumen tidak dikenal: $a (pakai: jalan | kering | siapkan, opsional --bot=ID)"); exit(1); }
}
$kering = $perintah === 'kering';

try {
    $root = bc_root(!$kering);
} catch (Throwable $e) {
    bc_log('ERROR', $e->getMessage());
    exit(1);
}

// Log ke berkas: <root>/log/bot_cron.log (dibuat bila perlu; mode kering tidak membuat apa pun)
$dir_log = $root . '/log';
if (!$kering && !is_dir($dir_log)) @mkdir($dir_log, 0775, true);
$GLOBALS['BC_LOG'] = is_dir($dir_log) ? $dir_log . '/bot_cron.log' : '';

// Cegah dua putaran berjalan bersamaan (file ganda / tabrakan transit_dil)
$kunci = null;
if (!$kering) {
    $kunci = @fopen(sys_get_temp_dir() . '/digita_bot_cron.lock', 'c');
    if ($kunci && !flock($kunci, LOCK_EX | LOCK_NB)) {
        bc_log('INFO', 'Putaran sebelumnya masih berjalan, putaran ini dilewati.');
        exit(0);
    }
}

try {
    $kode = $perintah === 'siapkan'
        ? bc_siapkan_semua($conn, $root, $id_bot)
        : bc_jalan($conn, $root, $kering, $id_bot, BC_STABIL_DETIK);
} catch (Throwable $e) {
    bc_log('ERROR', 'Cron berhenti: ' . $e->getMessage());
    $kode = 1;
}
exit($kode);