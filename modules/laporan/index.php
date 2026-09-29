<?php
// modules/laporan/index.php
// Dipanggil dari root index.php (?page=laporan). Root yang membungkus dengan layout,
// jadi file ini HANYA menampilkan isi halaman (JANGAN panggil base.php/session_start di sini).
require_once __DIR__ . '/../../config/database.php';
chdir(__DIR__);

// ---------------------------------------------------------
// KONFIGURASI LAPORAN
// Kalau tabel modul lain (Pemasangan, Penggunaan, Pemeliharaan, Penghapusan)
// sudah dibuat, ganti 'table' => null dengan nama tabel aslinya, lalu isi 'columns'.
// ---------------------------------------------------------
$laporan_config = [
    'perencanaan' => [
        'title'       => 'Laporan Perencanaan',
        'table'       => 'pelunasan_ap',
        'date_column' => 'WaktuData',
        'columns' => [
            ['field' => 'IdPel',     'label' => 'IdPel'],
            ['field' => 'ThBlRek',   'label' => 'Thn/Bln Rek'],
            ['field' => 'TglBayar',  'label' => 'Tgl Bayar',   'format' => 'tanggal_pendek'],
            ['field' => 'RpBK',      'label' => 'RpBK',        'format' => 'rupiah'],
            ['field' => 'RpTag',     'label' => 'RpTag',       'format' => 'rupiah'],
            ['field' => 'UnitUp',    'label' => 'Unit UP'],
            ['field' => 'UnitAp',    'label' => 'Unit AP'],
            ['field' => 'WaktuData', 'label' => 'Waktu Input', 'format' => 'tanggal'],
        ],
    ],
    'pengadaan' => [
        'title'       => 'Laporan Pengadaan',
        'table'       => 'formulir_pembelian',
        'date_column' => 'TglBeli',
        'columns' => [
            ['field' => 'NoFormulir', 'label' => 'No Formulir'],
            ['field' => 'TglBeli',    'label' => 'Tgl Beli', 'format' => 'tanggal_pendek'],
            ['field' => 'NamaAkun',   'label' => 'Petugas'],
            ['field' => 'KodeUp',     'label' => 'UP'],
            ['field' => 'KodeAp',     'label' => 'AP'],
            ['field' => 'KodeUpi',    'label' => 'UPI'],
            ['field' => 'StatusData', 'label' => 'Status'],
        ],
    ],
    'pemasangan'   => ['title' => 'Laporan Pemasangan',   'table' => null],
    'penggunaan'   => ['title' => 'Laporan Penggunaan',   'table' => null],
    'pemeliharaan' => ['title' => 'Laporan Pemeliharaan', 'table' => null],
    'penghapusan'  => ['title' => 'Laporan Penghapusan',  'table' => null],
];

$jenis = $_GET['action'] ?? '';

if ($jenis !== '' && array_key_exists($jenis, $laporan_config)) {
    $cfg = $laporan_config[$jenis];
    include __DIR__ . '/laporan_view.php';
} else {
    include __DIR__ . '/../../templates/laporan/index.php';
}