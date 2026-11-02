<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit;
}

$no_fba = (string) ($_GET['no'] ?? '');
$kolom  = ['pasang' => 'FotoTerpasang', 'barang' => 'FotoBarang'][$_GET['jenis'] ?? ''] ?? null;

if ($no_fba === '' || $kolom === null) {
    http_response_code(400);
    exit;
}

[$sc, $sp] = ph_scope('b');
try {
    $st = $conn->prepare("SELECT bd.$kolom AS foto FROM frm_ba_detil bd JOIN frm_ba b ON b.NoFba = bd.NoFba WHERE bd.NoFba = ? AND bd.StatusPekerjaan = 'PASANG'" . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_fba], $sp));
    $foto = $st->fetchColumn();

    if (!$foto) {
        http_response_code(404);
        exit;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($foto);
    header('Content-Type: ' . (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? 'image/jpeg' : $mime));
    header('Content-Length: ' . strlen($foto));
    echo $foto;
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}