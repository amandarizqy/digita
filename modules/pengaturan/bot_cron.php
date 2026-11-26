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
 *   riwayat           : tabel bot_akses_riwayat (satu baris + satu TiketBot per file yang selesai diproses)
 *   log               : <BOT_ROOT>/log/bot_cron.log
 *
 * Pemakaian (command line):
 *   php bot_cron.php                  -> sama dengan "jalan"
 *   php bot_cron.php jalan            -> TERUS-MENERUS: siapkan folder, unggah file yang ada, tidur, ulangi (tidak pernah selesai sendiri)
 *   php bot_cron.php jalan --tiap=30  -> idem, jeda antar putaran 30 detik (bawaan 30)
 *   php bot_cron.php sekali           -> satu putaran saja lalu selesai (perilaku "jalan" yang lama)
 *   php bot_cron.php berhenti         -> minta loop "jalan" berhenti (membuat file STOP), lalu menunggu sampai benar-benar berhenti
 *   php bot_cron.php berhenti --tunggu=300  -> idem, menunggu maksimal 300 detik (bawaan 120; 0 = tidak menunggu)
 *   php bot_cron.php kering           -> hanya tampilkan file yang akan diunggah (tanpa membuat folder / mengunggah)
 *   php bot_cron.php siapkan          -> hanya membuat folder untuk semua bot (aktif maupun tidak)
 *   php bot_cron.php jalan --bot=3    -> batasi pada satu bot (IdBot)
 *
 * Menghentikan mode terus-menerus dengan rapi: buat file kosong <BOT_ROOT>/log/STOP (dihapus otomatis setelah terbaca).
 *
 * Kode keluar: 0 = semua baik / dihentikan lewat STOP, 1 = ada bot yang GAGAL (mode sekali),
 *              2 = koneksi database putus, 3 = memori terlalu besar (keduanya: biarkan Task Scheduler menjalankan ulang).
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
const BC_JEDA_BAWAAN  = 30;   // detik antar putaran pada mode terus-menerus
const BC_MAKS_MEMORI  = 400 * 1048576;   // keluar (dan dijalankan ulang penjadwal) bila pemakaian memori melewati ini
const BC_TUNGGU_BERHENTI = 120;   // detik: lama perintah "berhenti" menunggu loop benar-benar selesai
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

function bc_catatan(string $nama, array $hasil, string $tiket = ''): string
{
    $t = "File    : $nama\r\n"
       . ($tiket !== '' ? "Tiket   : $tiket\r\n" : '')
       . "Waktu   : " . date('Y-m-d H:i:s') . "\r\nHasil   : " . $hasil['pesan'] . "\r\n";
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
    // Proses dari file terlama ke terbaru: untuk pelunasan (ON DUPLICATE KEY UPDATE) data terbaru harus menang.
    usort($tugas, fn($a, $b) => ((int) @filemtime($a['file'])) <=> ((int) @filemtime($b['file'])) ?: strcmp($a['file'], $b['file']));
    return [$tugas, $salah, $tunggu];
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

        // Riwayat dibuka SEBELUM file diproses; jumlah berhasil/gagal diperbarui selama proses berjalan (maks 1x/detik).
        $tiket = '';
        try {
            $tiket = bot_riwayat_mulai($conn, $bot, $t['ap'], $t['up'], $nama);
        } catch (Throwable $e) {
            bc_log('ERROR', "  Gagal membuka riwayat (jumlah tidak diperbarui selama proses): {$e->getMessage()}");
        }
        $lapor = $tiket === '' ? null : function (int $ok, int $gl) use ($conn, $tiket) { bot_riwayat_perbarui($conn, $tiket, $ok, $gl); };
        $h = bh_jalankan($conn, $bot, $k['jenis'], $t['file'], $t['ap'], $t['up'], $lapor);
        $terakhir = "$nama: " . $h['pesan'];

        if ($h['status'] === 'sukses' || $h['status'] === 'gagal') {
            $beruntun = 0;
            $h['status'] === 'sukses' ? $ok++ : $tidak++;

            // Riwayat: satu baris + satu TiketBot per file yang selesai diproses (berhasil maupun gagal).
            // Jumlah akhir ditulis SEBELUM file dipindah; kegagalan mencatat tidak menghentikan proses.
            try {
                if ($tiket !== '') bot_riwayat_perbarui($conn, $tiket, (int) $h['tersimpan'], (int) $h['ditolak'], true);
                else               $tiket = bot_catat_riwayat($conn, $bot, $t['ap'], $t['up'], (int) $h['tersimpan'], (int) $h['ditolak'], $nama);
                bc_log('INFO', "  Riwayat dicatat: tiket $tiket");
            } catch (Throwable $e) {
                bc_log('ERROR', "  Gagal mencatat riwayat: {$e->getMessage()}");
            }

            try {
                $tujuan = bc_pindah($t['file'], $root, $k['arsip'], $k['multi'] ? $t['up'] : '',
                                    $h['status'] === 'sukses' ? 'berhasil' : 'gagal', bc_catatan($nama, $h, $tiket));
                bc_log($h['status'] === 'sukses' ? 'INFO' : 'WARN', '  SELESAI -> ' . bc_rel($tujuan, $root) . " | {$h['pesan']}");
            } catch (Throwable $e) {
                // data sudah diproses tetapi file tidak bisa dipindah: akan terbaca lagi di putaran berikutnya
                bc_log('ERROR', "  Gagal memindahkan file (akan terbaca lagi): {$e->getMessage()}");
            }
        } else {
            $tunda++;
            if ($tiket !== '') {                       // file akan diulang & dicatat ulang: buang baris "PROSES" agar tidak dobel
                try { bot_riwayat_hapus($conn, $tiket); } catch (Throwable $e) { bc_log('ERROR', "  Gagal menghapus riwayat sementara: {$e->getMessage()}"); }
            }
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

const BC_BERKAS_KUNCI = 'digita_bot_cron.lock';

/** Perintah "berhenti": minta loop yang sedang berjalan berhenti dengan membuat file STOP, lalu tunggu sampai kunci dilepas. */
function bc_berhenti(string $root, int $tunggu): int
{
    $stop  = "$root/log/STOP";
    $kunci = @fopen(sys_get_temp_dir() . '/' . BC_BERKAS_KUNCI, 'c');

    // Bila kunci bisa langsung diambil, berarti tidak ada loop yang berjalan.
    if ($kunci && flock($kunci, LOCK_EX | LOCK_NB)) {
        flock($kunci, LOCK_UN);
        bc_log('INFO', 'Tidak ada bot_cron yang sedang berjalan, tidak ada yang perlu dihentikan.');
        return 0;
    }

    if (@file_put_contents($stop, date('Y-m-d H:i:s')) === false) {
        bc_log('ERROR', "Tidak bisa membuat file STOP: $stop");
        return 1;
    }
    bc_log('INFO', 'Permintaan berhenti dikirim (file STOP dibuat).');
    if ($tunggu <= 0) return 0;

    $batas = time() + $tunggu;
    while (time() < $batas) {
        sleep(1);
        if ($kunci && flock($kunci, LOCK_EX | LOCK_NB)) {
            flock($kunci, LOCK_UN);
            @unlink($stop);   // jaga-jaga bila belum terhapus oleh loop
            bc_log('INFO', 'bot_cron sudah berhenti. Aman untuk mematikan server.');
            return 0;
        }
    }
    bc_log('WARN', "bot_cron belum berhenti setelah $tunggu detik (mungkin sedang memproses file besar). File STOP tetap ada; loop akan berhenti setelah putaran ini.");
    return 1;
}

/** Mode terus-menerus: putaran -> tidur -> putaran ... sampai ada file STOP, koneksi DB putus, atau memori membengkak. */
function bc_loop(PDO $conn, string $root, ?int $id_bot, int $jeda): int
{
    $stop = "$root/log/STOP";
    if (is_file($stop)) @unlink($stop);   // sisa penghentian sebelumnya
    bc_log('INFO', "Mode terus-menerus dimulai (jeda $jeda detik). Untuk berhenti: buat file $stop");

    $putaran = 0;
    $denyut  = time();
    while (true) {
        if (is_file($stop)) { @unlink($stop); bc_log('INFO', 'File STOP ditemukan, berhenti.'); return 0; }

        try { $conn->query('SELECT 1'); }
        catch (Throwable $e) { bc_log('ERROR', 'Koneksi database putus: ' . $e->getMessage() . ' - keluar, penjadwal akan menjalankan ulang.'); return 2; }

        try { bc_jalan($conn, $root, false, $id_bot, BC_STABIL_DETIK); }
        catch (Throwable $e) { bc_log('ERROR', 'Putaran gagal: ' . $e->getMessage()); }
        $putaran++;

        gc_collect_cycles();
        if (memory_get_usage(true) > BC_MAKS_MEMORI) {
            bc_log('WARN', 'Memori ' . round(memory_get_usage(true) / 1048576) . ' MB melewati batas, keluar agar dijalankan ulang.');
            return 3;
        }
        if (time() - $denyut >= 600) {   // catatan "masih hidup" tiap 10 menit
            bc_log('INFO', "Masih berjalan ($putaran putaran, memori " . round(memory_get_usage(true) / 1048576) . ' MB).');
            $denyut = time();
        }

        for ($i = 0; $i < $jeda; $i++) {   // tidur per detik supaya STOP cepat terbaca
            if (is_file($stop)) break;
            sleep(1);
        }
    }
}

// ============================================================ main
$perintah = 'jalan';
$id_bot   = null;
$jeda     = BC_JEDA_BAWAAN;
$tunggu   = BC_TUNGGU_BERHENTI;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--bot=(\d+)$/', $a, $m))                              $id_bot = (int) $m[1];
    elseif (preg_match('/^--tiap=(\d+)$/', $a, $m))                         $jeda = max(5, (int) $m[1]);
    elseif (preg_match('/^--tunggu=(\d+)$/', $a, $m))                       $tunggu = (int) $m[1];
    elseif (in_array($a, ['jalan', 'sekali', 'kering', 'siapkan', 'berhenti'], true)) $perintah = $a;
    else { bc_log('ERROR', "Argumen tidak dikenal: $a (pakai: jalan | sekali | kering | siapkan | berhenti, opsional --bot=ID --tiap=DETIK --tunggu=DETIK)"); exit(1); }
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

// "berhenti" tidak ikut berebut kunci: ia hanya meminta loop yang berjalan untuk berhenti
if ($perintah === 'berhenti') exit(bc_berhenti($root, $tunggu));

// Cegah dua putaran berjalan bersamaan (file ganda / tabrakan transit_dil)
$kunci = null;
if (!$kering) {
    $kunci = @fopen(sys_get_temp_dir() . '/' . BC_BERKAS_KUNCI, 'c');
    if ($kunci && !flock($kunci, LOCK_EX | LOCK_NB)) {
        bc_log('INFO', 'Putaran sebelumnya masih berjalan, putaran ini dilewati.');
        exit(0);
    }
}

try {
    if ($perintah === 'siapkan')    $kode = bc_siapkan_semua($conn, $root, $id_bot);
    elseif ($perintah === 'jalan')  $kode = bc_loop($conn, $root, $id_bot, $jeda);
    else                            $kode = bc_jalan($conn, $root, $kering, $id_bot, BC_STABIL_DETIK);   // sekali / kering
} catch (Throwable $e) {
    bc_log('ERROR', 'Cron berhenti: ' . $e->getMessage());
    $kode = 1;
}
exit($kode);