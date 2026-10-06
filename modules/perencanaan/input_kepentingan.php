<?php
// modules/perencanaan/input_kepentingan.php  -  Input Tingkat Kepentingan (upload XLSX / satu-satu)
require_once __DIR__ . '/../../functions/perencanaan_input.php';

$cfg = [
    'action'      => 'input_kepentingan',
    'link_hasil'  => 'hasil_kepentingan',
    'judul'       => 'Input Tingkat Kepentingan',
    'subtitle'    => 'Isi Level Kepentingan pelanggan per periode. Data disimpan ke tabel kategorisasi_risiko.',
    'kolom'       => 'LevelKepentingan',
    'label_nilai' => 'Level Kepentingan',
    'nama_excel'  => 'LevelKepentingan',
    'alias_excel' => ['levelkepentingan', 'kepentingan'],
    'format_excel'=> 'RENDAH, MODERAT, atau TINGGI',
    'opsi'        => ['RENDAH' => 'Rendah', 'MODERAT' => 'Moderat', 'TINGGI' => 'Tinggi'],
    'normalisasi' => 'pi_norm_kepentingan',
];
$state = pi_proses($conn, $cfg);
include __DIR__ . '/../../templates/perencanaan/input_dua_cara.php';
