<?php
// modules/perencanaan/index.php
// Dipanggil dari root index.php (?page=perencanaan). Root yang membungkus dengan layout,
// jadi file ini HANYA menampilkan isi halaman (JANGAN panggil base.php di sini).
require_once __DIR__ . '/../../config/database.php';
chdir(__DIR__);   // supaya include relatif di file-file perencanaan tetap benar

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload_riwayat':     include __DIR__ . '/upload_riwayat.php'; break;
    case 'input_kepentingan':  include __DIR__ . '/input_kepentingan.php'; break;
    case 'input_survey':       include __DIR__ . '/input_survey.php'; break;
    case 'proses_risiko':      include __DIR__ . '/proses_risiko.php'; break;
    case 'proses_prioritas':   include __DIR__ . '/proses_prioritas.php'; break;
    case 'data_riwayat':       include __DIR__ . '/data_riwayat.php'; break;
    case 'hasil_kepentingan':  include __DIR__ . '/hasil_kepentingan.php'; break;
    case 'hasil_survey':       include __DIR__ . '/hasil_survey.php'; break;
    case 'hasil_risiko':       include __DIR__ . '/hasil_risiko.php'; break;
    case 'hasil_prioritas':    include __DIR__ . '/hasil_prioritas.php'; break;
    default:
        include __DIR__ . '/../../templates/perencanaan/index.php';
}