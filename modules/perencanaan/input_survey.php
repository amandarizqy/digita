<?php
// modules/perencanaan/input_survey.php  -  Input Survey SR / Posisi SR (upload XLSX / satu-satu)
require_once __DIR__ . '/../../functions/perencanaan_input.php';

$cfg = [
    'action'      => 'input_survey',
    'link_hasil'  => 'hasil_survey',
    'judul'       => 'Input Survey SR',
    'subtitle'    => 'Isi Posisi SR pelanggan per periode. Data disimpan ke tabel kategorisasi_risiko.',
    'kolom'       => 'PosisiSR',
    'label_nilai' => 'Posisi SR',
    'nama_excel'  => 'PosisiSR',
    'alias_excel' => ['posisisr', 'sr'],
    'format_excel'=> '0 atau 1',
    'opsi'        => ['0' => '0 — Tidak tergantung', '1' => '1 — Bergantung'],
    'normalisasi' => 'pi_norm_sr',
];
$state = pi_proses($conn, $cfg);
include __DIR__ . '/../../templates/perencanaan/input_dua_cara.php';
