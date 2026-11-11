<?php
// modules/pengaturan/bot_helper.php
// SUMBER TUNGGAL aturan bot: dipakai web (bot.php), cron (bot_cron.php) dan handler (bot_handlers.php),
// sehingga aturan folder, cakupan unit, dan kolom status tidak bisa berbeda antar file.
//
// ATURAN FOLDER (semua relatif terhadap BOT_ROOT, nilainya dari kolom bot_akses):
//   Direktori Utama = tempat file .xlsx diletakkan.
//       bot level UP      (UnitUp terisi)         : file langsung di <DirektoriUtama>/
//       bot level AP/UPI  (UnitUp kosong)         : file di <DirektoriUtama>/<KodeUP>/
//                                                   (khusus DIL: boleh langsung di <DirektoriUtama>/, unit diambil dari isi file)
//   Direktori Arsip = hasil proses:
//       bot level UP      : <DirektoriArsip>/<berhasil|gagal>/<Y-m-d>/
//       bot level AP/UPI  : <DirektoriArsip>/<KodeUP>/<berhasil|gagal>/<Y-m-d>/   (DIL tanpa UP: tanpa <KodeUP>)

if (!defined('BOT_ROOT') && is_file(__DIR__ . '/../../config/bot.php')) {
    require_once __DIR__ . '/../../config/bot.php';
}

// Kolom status terakhir di bot_akses (opsional; bila kolomnya tidak ada, cron hanya menulis log).
// Sesuaikan nama di sini bila kolom yang Anda buat berbeda.
const BOT_KOL_JALAN  = 'TerakhirJalan';    // DATETIME     : kapan cron terakhir memeriksa bot ini
const BOT_KOL_STATUS = 'StatusTerakhir';   // VARCHAR(10)  : BERHASIL | SEBAGIAN | GAGAL | TUNDA
const BOT_KOL_PESAN  = 'PesanTerakhir';    // VARCHAR(255) : ringkasan hasil proses terakhir

if (!function_exists('bot_jenis_daftar')) {

    /** Jenis bot = nilai ENUM kolom bot_akses.JenisBot. */
    function bot_jenis_daftar(): array
    {
        return [
            'PELUNASAN' => [
                'label'  => 'Upload Pelunasan (Riwayat)',
                'tabel'  => 'pelunasan_ap2t (+ dil, kategorisasi_risiko)',
                'format' => 'Kolom: Idpel, Bulan (YYYYMM), TglTransaksi, RpBK, RpTagihan.',
                'sumber' => 'perencanaan/upload_riwayat.php',
            ],
            'DIL' => [
                'label'  => 'Upload DIL',
                'tabel'  => 'dil',
                'format' => 'Excel "Monitoring DIL Detail": IDPEL, NAMA, ALAMAT, TARIF, DAYA, UNITUP, UNITAP, UNITUPI, dst.',
                'sumber' => 'penggunaan/input_dil.php',
            ],
            'TUL' => [
                'label'  => 'Upload TUL VI-01',
                'tabel'  => 'tul_601',
                'format' => 'Laporan AP2T "TUL VI-01 belum lunas": Nomer Tul VI-01, Tgl Pencetakan, Id Pelanggan, Bulan Rekening Awal/Akhir, Jumlah Lembar, Rp. Tagihan.',
                'sumber' => 'penggunaan/upload_tul.php',
            ],
            'SURVEY' => [
                'label'  => 'Upload Survey SR',
                'tabel'  => 'kategorisasi_risiko (PosisiSR)',
                'format' => 'Kolom: Idpel, PosisiSR (0/1), Periode (tahun; opsional).',
                'sumber' => 'perencanaan/input_survey.php',
            ],
            'KEPENTINGAN' => [
                'label'  => 'Upload Tingkat Kepentingan',
                'tabel'  => 'kategorisasi_risiko (LevelKepentingan)',
                'format' => 'Kolom: Idpel, LevelKepentingan (RENDAH/MODERAT/TINGGI), Periode (tahun; opsional).',
                'sumber' => 'perencanaan/input_kepentingan.php',
            ],
        ];
    }

    // ------------------------------------------------------------------ root & folder

    /** [path nyata root (kosong bila belum ada), path yang dikonfigurasi]. Satu-satunya tempat root dihitung. */
    function bot_root(): array
    {
        clearstatcache(true);
        $dasar = defined('BOT_ROOT') ? BOT_ROOT : '';
        $nyata = $dasar !== '' ? realpath($dasar) : false;
        return [$nyata !== false ? str_replace('\\', '/', $nyata) : '', $dasar];
    }

    /** Rapikan folder relatif. null = tidak valid. */
    function bot_norm_folder(string $s): ?string
    {
        $s = trim(str_replace('\\', '/', $s), " \t\n\r\0\x0B/");
        if ($s === '' || strlen($s) > 255) return null;
        return preg_match('~^[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-]+)*$~', $s) ? $s : null;
    }

    /** Folder "log" dicadangkan untuk berkas log cron. */
    function bot_folder_dilarang(string $rel): bool
    {
        return strtolower(explode('/', $rel)[0]) === 'log';
    }

    /** true bila $anak sama dengan atau berada di dalam $induk. */
    function bot_di_dalam(string $anak, string $induk): bool
    {
        $a = strtolower($anak);
        $i = strtolower($induk);
        return $a === $i || strpos($a, $i . '/') === 0;
    }

    /** Kode unit dipakai sebagai nama folder: hanya huruf dan angka (mencegah "..", "/", dsb). */
    function bot_kode_valid(string $kode): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{1,20}$/', $kode);
    }

    /** 'UP' | 'AP' | 'UPI' menurut kolom unit bot. */
    function bot_tingkat(array $bot): string
    {
        if (trim((string) ($bot['UnitUp'] ?? '')) !== '') return 'UP';
        if (trim((string) ($bot['UnitAp'] ?? '')) !== '') return 'AP';
        return 'UPI';
    }

    /**
     * Lokasi folder bot menurut barisnya di bot_akses.
     * @return array ['utama' => rel, 'arsip' => rel, 'multi' => bool]  atau  ['error' => pesan]
     *   multi = true  -> bot mencakup banyak UP, file diletakkan di subfolder <KodeUP>.
     */
    function bot_lokasi(array $bot): array
    {
        $utama = bot_norm_folder((string) ($bot['DirektoriUtama'] ?? ''));
        $arsip = bot_norm_folder((string) ($bot['DirektoriArsip'] ?? ''));
        if ($utama === null || $arsip === null) return ['error' => 'Direktori Utama / Arsip belum diisi atau tidak valid.'];
        if (bot_folder_dilarang($utama) || bot_folder_dilarang($arsip)) return ['error' => "Nama folder 'log' dicadangkan untuk berkas log cron."];
        if (strtolower($utama) === strtolower($arsip)) return ['error' => 'Direktori Utama dan Arsip harus berbeda.'];
        return ['utama' => $utama, 'arsip' => $arsip, 'multi' => bot_tingkat($bot) !== 'UP'];
    }

    /** Pastikan folder ada (dan benar-benar berada di dalam root). Yang baru dibuat dicatat di $dibuat, yang gagal di $gagal. */
    function bot_pastikan_dir(string $root, string $rel, array &$dibuat, array &$gagal): bool
    {
        $abs = $root . '/' . $rel;
        if (!is_dir($abs)) {
            if (!@mkdir($abs, 0775, true) && !is_dir($abs)) { $gagal[] = $rel; return false; }
            $dibuat[] = $rel;
        }
        $nyata = realpath($abs);
        $induk = realpath($root);
        if ($nyata === false || $induk === false) { $gagal[] = $rel; return false; }
        $n = str_replace('\\', '/', $nyata) . '/';
        $i = rtrim(str_replace('\\', '/', $induk), '/') . '/';
        if (strpos($n, $i) !== 0) { $gagal[] = $rel; return false; }
        return true;
    }

    // ------------------------------------------------------------------ cakupan unit

    /**
     * Peta unit yang boleh dipakai bot (hanya yang AKTIF dan di dalam wilayah bot).
     * @return array ['ap' => [KODE_BESAR => kodeAsli], 'up' => [KODE_BESAR => ['kode' => kodeAsli, 'ap' => kodeAp]]]
     */
    function bot_peta_unit(PDO $conn, string $upi, string $ap = '', string $up = ''): array
    {
        $peta = ['ap' => [], 'up' => []];
        $sql = "SELECT UnitAp FROM master_ap WHERE UnitUpi = ? AND StatusData = 'AKTIF'";
        $prm = [$upi];
        if ($ap !== '') { $sql .= ' AND UnitAp = ?'; $prm[] = $ap; }
        $st = $conn->prepare($sql);
        $st->execute($prm);
        $daftar_ap = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
        foreach ($daftar_ap as $k) $peta['ap'][strtoupper(trim($k))] = trim($k);
        if (!$daftar_ap) return $peta;

        $sql = "SELECT UnitAp, UnitUp FROM master_up WHERE UnitAp IN (" . implode(',', array_fill(0, count($daftar_ap), '?')) . ") AND StatusData = 'AKTIF'";
        $prm = $daftar_ap;
        if ($up !== '') { $sql .= ' AND UnitUp = ?'; $prm[] = $up; }
        $st = $conn->prepare($sql);
        $st->execute($prm);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $peta['up'][strtoupper(trim((string) $r['UnitUp']))] = ['kode' => trim((string) $r['UnitUp']), 'ap' => trim((string) $r['UnitAp'])];
        }
        return $peta;
    }

    /**
     * UP yang termasuk cakupan bot: [['up' => kode UP, 'ap' => kode AP], ...]
     *  - bot level UP  : hanya UP miliknya (harus aktif)
     *  - bot level AP  : semua UP aktif di bawah AP-nya
     *  - bot level UPI : semua UP aktif di bawah UPI-nya
     * Kode UP yang tidak valid sebagai nama folder dilewati dan dicatat di $dilewati.
     */
    function bot_daftar_up(PDO $conn, array $bot, array &$dilewati = []): array
    {
        $upi  = trim((string) ($bot['UnitUpi'] ?? ''));
        $ap_b = trim((string) ($bot['UnitAp'] ?? ''));
        $up_b = trim((string) ($bot['UnitUp'] ?? ''));
        $peta = bot_peta_unit($conn, $upi, $ap_b, $up_b);

        $hasil = [];
        foreach ($peta['up'] as $e) {
            $kode = (string) $e['kode'];
            if ($kode === '') continue;
            if (!bot_kode_valid($kode)) { $dilewati[] = $kode; continue; }
            $hasil[] = ['up' => $kode, 'ap' => (string) $e['ap']];
        }
        usort($hasil, fn($a, $b) => strcmp($a['up'], $b['up']));
        return $hasil;
    }

    /**
     * Validasi pilihan unit untuk bot baru/ubah. Melengkapi $ap bila hanya UP yang diisi.
     * @return string|null pesan kesalahan, null = valid
     */
    function bot_validasi_unit(PDO $conn, string $upi, string &$ap, string $up): ?string
    {
        if ($upi === '') return 'Unit UPI belum ditentukan. Silakan pilih unit wilayah terlebih dahulu.';

        $s = $conn->prepare('SELECT 1 FROM master_upi WHERE UnitUpi = ?');
        $s->execute([$upi]);
        if (!$s->fetchColumn()) return "Unit UPI '$upi' tidak ditemukan di master UPI.";

        if ($ap !== '') {
            $s = $conn->prepare('SELECT 1 FROM master_ap WHERE UnitAp = ? AND UnitUpi = ?');
            $s->execute([$ap, $upi]);
            if (!$s->fetchColumn()) return "Unit AP '$ap' tidak ditemukan di UPI '$upi'.";
        }
        if ($up !== '') {
            $sql = 'SELECT UnitAp FROM master_up WHERE UnitUp = ? AND UnitUpi = ?';
            $prm = [$up, $upi];
            if ($ap !== '') { $sql .= ' AND UnitAp = ?'; $prm[] = $ap; }
            $s = $conn->prepare($sql);
            $s->execute($prm);
            $baris = $s->fetchAll(PDO::FETCH_COLUMN);
            if (!$baris) return "Unit UP '$up' tidak ditemukan" . ($ap !== '' ? " di bawah AP '$ap'." : " di UPI '$upi'.");
            if ($ap === '') {
                if (count($baris) > 1) return "Unit UP '$up' ada di lebih dari satu AP. Pilih Unit AP terlebih dahulu.";
                $ap = trim((string) $baris[0]);
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ folder bot

    /**
     * Pastikan folder bot ada: Direktori Utama, Direktori Arsip, dan (bot level AP/UPI) subfolder <KodeUP> di Utama.
     * Subfolder arsip dibuat otomatis oleh cron saat file dipindahkan.
     * @return array [folder yang baru dibuat, folder yang gagal dibuat]  (path relatif terhadap root)
     */
    function bot_siapkan_folder(string $root, PDO $conn, array $bot): array
    {
        $dibuat = $gagal = [];
        if (!is_dir($root)) return [[], ['(folder bot tidak ditemukan)']];
        $lok = bot_lokasi($bot);
        if (isset($lok['error'])) return [[], [$lok['error']]];

        bot_pastikan_dir($root, $lok['arsip'], $dibuat, $gagal);
        if (!bot_pastikan_dir($root, $lok['utama'], $dibuat, $gagal)) return [$dibuat, $gagal];

        if ($lok['multi']) {
            foreach (bot_daftar_up($conn, $bot) as $u) {
                bot_pastikan_dir($root, $lok['utama'] . '/' . $u['up'], $dibuat, $gagal);
            }
        }
        return [$dibuat, $gagal];
    }
}