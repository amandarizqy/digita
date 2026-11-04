<?php
// modules/pengaturan/bot_helper.php
// Fungsi bersama: halaman Daftar Bot (bot.php, web) dan pelaksana cron (bot_cron.php, CLI).
// Tidak memakai $_SESSION supaya aman dipanggil dari command line.

// Lokasi folder bot bisa diatur di config/bot.php (opsional):  <?php define('BOT_ROOT', 'D:/ftp/digita');
// File itu dibaca oleh web DAN cron, jadi keduanya selalu memakai folder yang sama.
if (!defined('BOT_ROOT') && is_file(__DIR__ . '/../../config/bot.php')) {
    require_once __DIR__ . '/../../config/bot.php';
}

if (!function_exists('bot_jenis_daftar')) {

    /**
     * Jenis bot = nilai ENUM kolom pengaturan_bot.JenisBot. SATU-SATUNYA sumber label & petunjuk format.
     * Tabel tujuan mengikuti halaman web yang sudah ada (file di kolom 'sumber' = logika yang dipindahkan ke bot).
     */
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
                'format' => 'Excel "Monitoring DIL Detail": IDPEL, NAMA, ALAMAT, TARIF, DAYA, UNITUP, UNITAP, UNITUPI, dst. Kolom unit boleh kosong (diisi dari folder/bot).',
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
                'format' => 'Kolom: Idpel, PosisiSR (0/1), Periode (tahun; opsional). Tanpa kolom Periode: tahun dari nama file (mis. survey_2026.xlsx), jika tidak ada = tahun berjalan.',
                'sumber' => 'perencanaan/input_survey.php',
            ],
            'KEPENTINGAN' => [
                'label'  => 'Upload Tingkat Kepentingan',
                'tabel'  => 'kategorisasi_risiko (LevelKepentingan)',
                'format' => 'Kolom: Idpel, LevelKepentingan (RENDAH/MODERAT/TINGGI), Periode (tahun; opsional, aturan sama dengan Survey SR).',
                'sumber' => 'perencanaan/input_kepentingan.php',
            ],
        ];
    }

    /** Folder bot di server: htdocs/ftp/digita (atau isi BOT_ROOT bila ingin lokasi lain). */
    function bot_root(): array
    {
        clearstatcache(true);   // buang cache realpath: folder yang baru dihapus/dibuat langsung terbaca
        $dasar = defined('BOT_ROOT') ? BOT_ROOT : __DIR__ . '/../../../../ftp/digita';
        $nyata = realpath($dasar);
        return [$nyata !== false ? str_replace('\\', '/', $nyata) : '', $dasar];
    }

    /** Rapikan folder relatif. null = tidak valid (kosong, ada "..", huruf aneh, atau terlalu panjang). */
    function bot_norm_folder(string $s): ?string
    {
        $s = trim(str_replace('\\', '/', $s), " \t\n\r\0\x0B/");
        if ($s === '' || strlen($s) > 150) return null;
        return preg_match('~^[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-]+)*$~', $s) ? $s : null;
    }

    /** true bila $anak sama dengan atau berada di dalam $induk. */
    function bot_di_dalam(string $anak, string $induk): bool
    {
        $a = strtolower($anak);
        $i = strtolower($induk);
        return $a === $i || strpos($a, $i . '/') === 0;
    }

    /** Buat folder bila belum ada (hanya di dalam folder bot). Kembalikan daftar yang gagal dibuat. */
    function bot_buat_folder(string $root, array $relatif): array
    {
        if (!is_dir($root)) return $relatif;   // jangan pernah membuat folder induk bot sendiri
        $gagal = [];
        foreach ($relatif as $r) {
            $abs = $root . '/' . $r;
            if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) { $gagal[] = $r; continue; }
            $nyata = realpath($abs);
            if ($nyata === false || strpos(str_replace('\\', '/', $nyata) . '/', $root . '/') !== 0) $gagal[] = $r;
        }
        return $gagal;
    }
}
