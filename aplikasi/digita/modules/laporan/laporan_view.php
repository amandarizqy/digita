<?php
// modules/laporan/index.php
// Dipanggil dari root index.php (?page=laporan). Root yang membungkus dengan layout,
// jadi file ini HANYA menampilkan isi halaman (JANGAN panggil base.php/session_start di sini).
require_once __DIR__ . '/../../config/database.php';
chdir(__DIR__);

// ---------------------------------------------------------
// KONFIGURASI LAPORAN
// Setiap key di bawah = satu ?action=... yang bisa diklik dari dashboard.
// 'table' => null artinya tabelnya belum ada / belum diberi tahu -> otomatis
// tampil pesan "belum tersedia", TIDAK membuat halaman blank.
// Begitu tabel aslinya siap, tinggal isi 'table', 'date_column' (boleh null
// kalau tabel tidak punya kolom tanggal), dan 'columns'. Tidak perlu ubah
// file lain (laporan_view.php / templates/laporan/*).
// ---------------------------------------------------------
$laporan_config = [

    // ============================ PERENCANAAN ============================
    'perencanaan_klasifikasi_risiko' => [
        'title'       => 'Hasil Klasifikasi Level Risiko',
        'table'       => 'kategorisasi_risiko',
        'date_column' => null,
        'columns' => [
            ['field' => 'IdPel',              'label' => 'IdPel'],
            ['field' => 'Periode',            'label' => 'Periode'],
            ['field' => 'LevelKemungkinan',   'label' => 'Level Kemungkinan'],
            ['field' => 'LevelDampak',        'label' => 'Level Dampak'],
            ['field' => 'Kuadran',            'label' => 'Kuadran'],
            ['field' => 'SkalaPrioritas',     'label' => 'Skala Prioritas'],
            ['field' => 'UnitUp',             'label' => 'Unit UP'],
        ],
    ],
    'perencanaan_tingkat_kepentingan' => [
        'title'       => 'Hasil Tingkat Kepentingan',
        'table'       => 'kategorisasi_risiko',
        'date_column' => null,
        'columns' => [
            ['field' => 'IdPel',            'label' => 'IdPel'],
            ['field' => 'Periode',          'label' => 'Periode'],
            ['field' => 'LevelKeterlambatan', 'label' => 'Level Keterlambatan'],
            ['field' => 'LevelKepentingan', 'label' => 'Level Kepentingan'],
            ['field' => 'UnitUp',           'label' => 'Unit UP'],
        ],
    ],
    'perencanaan_survei_sr' => [
        'title'       => 'Hasil Survei SR',
        'table'       => 'kategorisasi_risiko',
        'date_column' => null,
        'columns' => [
            ['field' => 'IdPel',    'label' => 'IdPel'],
            ['field' => 'Periode',  'label' => 'Periode'],
            ['field' => 'PosisiSR', 'label' => 'Posisi SR'],
            ['field' => 'UnitUp',   'label' => 'Unit UP'],
            ['field' => 'UnitAp',   'label' => 'Unit AP'],
            ['field' => 'UnitUpi',  'label' => 'Unit UPI'],
        ],
    ],
    'perencanaan_skala_prioritas' => [
        'title'       => 'Hasil Skala Prioritas',
        'table'       => 'hasil_prioritas',
        'date_column' => 'DiprosesPada',
        'columns' => [
            ['field' => 'IdPel',          'label' => 'IdPel'],
            ['field' => 'Periode',        'label' => 'Periode'],
            ['field' => 'SkalaPrioritas', 'label' => 'Skala Prioritas'],
            ['field' => 'Peringkat',      'label' => 'Peringkat'],
            ['field' => 'TotalTagihan',   'label' => 'Total Tagihan', 'format' => 'rupiah'],
            ['field' => 'SkorPrioritas',  'label' => 'Skor Prioritas'],
            ['field' => 'DiprosesPada',   'label' => 'Diproses Pada', 'format' => 'tanggal'],
        ],
    ],

    // ============================= PENGADAAN =============================
    'pengadaan_pengiriman' => [
        'title'       => 'Laporan Pengiriman',
        'table'       => 'formulir_pengiriman',
        'date_column' => 'TglFormulir',
        'columns' => [
            ['field' => 'NoFormulir',  'label' => 'No Formulir'],
            ['field' => 'TglFormulir', 'label' => 'Tgl Formulir', 'format' => 'tanggal_pendek'],
            ['field' => 'NamaAkun',    'label' => 'Petugas'],
            ['field' => 'KodeUp',      'label' => 'UP'],
            ['field' => 'KodeAp',      'label' => 'AP'],
            ['field' => 'KodeUpi',     'label' => 'UPI'],
            ['field' => 'StatusData',  'label' => 'Status'],
        ],
    ],
    'pengadaan_penerimaan' => ['title' => 'Laporan Penerimaan', 'table' => null],
    'pengadaan_aset'       => ['title' => 'Laporan Aset',       'table' => null],

    // ============================ PEMASANGAN ============================
    'pemasangan_status_pengujian'  => ['title' => 'Status Pengujian',   'table' => null],
    'pemasangan_riwayat_pengujian' => ['title' => 'Riwayat Pengujian',  'table' => null],
    'pemasangan_order_pasang'      => ['title' => 'Order Pasang',      'table' => null],
    'pemasangan_mutasi_pasang'     => ['title' => 'Mutasi Pasang',     'table' => null],

    // ============================ PENGGUNAAN =============================
    'penggunaan_dil'             => ['title' => 'Data Induk Pelanggan (DIL)', 'table' => null],
    'penggunaan_pelunasan'       => ['title' => 'Pelunasan',                  'table' => null],
    'penggunaan_pesan'           => ['title' => 'Pesan',                      'table' => null],
    'penggunaan_status_eksekusi' => ['title' => 'Status Eksekusi',            'table' => null],

    // =========================== PEMELIHARAAN ============================
    'pemeliharaan_order'   => ['title' => 'Order Pemeliharaan',  'table' => null],
    'pemeliharaan_mutasi'  => ['title' => 'Mutasi Pemeliharaan', 'table' => null],

    // ============================ PENGHAPUSAN ============================
    'penghapusan_order_bongkar'  => ['title' => 'Order Pembongkaran',  'table' => null],
    'penghapusan_mutasi_bongkar' => ['title' => 'Mutasi Pembongkaran', 'table' => null],
];

$jenis = $_GET['action'] ?? '';

if ($jenis !== '' && array_key_exists($jenis, $laporan_config)) {
    $cfg = $laporan_config[$jenis];
    include __DIR__ . '/laporan_view.php';
} else {
    include __DIR__ . '/../../templates/laporan/index.php';
}