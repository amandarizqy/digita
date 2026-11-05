<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/penghapusan_helper.php';
require_once __DIR__ . '/../../functions/penghapusan_ui.php';

$boleh = [
    'order_penghapusan'  => pgh_akses_order(),
    'mutasi_penghapusan' => pgh_akses_mutasi(),
    'monitoring_order'    => pgh_akses_monitoring(),
    'monitoring_mutasi'   => pgh_akses_monitoring(),
];

$struktur = [
    'eksekusi' => [
        'label' => 'Eksekusi', 'icon' => 'bi-tools',
        'sub'   => [
            'order_penghapusan'  => ['Order Pembongkaran',  'bi-file-earmark-text', 'frm_penghapusan.php'],
            'mutasi_penghapusan' => ['Mutasi Pembongkaran', 'bi-arrow-left-right',  'eksekusi_mutasi.php'],
        ],
    ],
    'monitoring' => [
        'label' => 'Monitoring', 'icon' => 'bi-display',
        'sub'   => [
            'monitoring_order'  => ['Order Pembongkaran',  'bi-display',       'monitoring_order.php'],
            'monitoring_mutasi' => ['Mutasi Pembongkaran', 'bi-journal-check', 'monitoring_mutasi.php'],
        ],
    ],
];

// Semua sub-menu tetap ditampilkan (seperti Master); yang tidak boleh diakses ditandai terkunci (elemen ke-4)
foreach ($struktur as $kunci_menu => $m) {
    foreach ($m['sub'] as $kunci_sub => $info) {
        $struktur[$kunci_menu]['sub'][$kunci_sub][3] = empty($boleh[$kunci_sub]);
    }
}

// Sub-menu pertama yang boleh dibuka (dipakai bila alamat yang diminta terkunci / tidak dikenal)
$pertama_boleh = function (array $daftar_sub): string {
    foreach ($daftar_sub as $k => $info) {
        if (empty($info[3])) return (string) $k;
    }
    return '';
};

$sub  = (string) ($_GET['sub'] ?? '');
$menu = (string) ($_GET['menu'] ?? '');
if ($sub !== '') {
    $menu = pgh_menu_dari_sub($sub);
}
if (!isset($struktur[$menu]) || $pertama_boleh($struktur[$menu]['sub']) === '') {
    $menu = '';
    foreach ($struktur as $km => $m) {
        if ($pertama_boleh($m['sub']) !== '') { $menu = (string) $km; break; }
    }
}
if ($menu !== '' && (!isset($struktur[$menu]['sub'][$sub]) || !empty($struktur[$menu]['sub'][$sub][3]))) {
    $sub = $pertama_boleh($struktur[$menu]['sub']);
}
?>

<?php
// Judul, subjudul, dan tombol aksi (kanan atas) per sub-menu
$halaman = [
    'order_penghapusan'  => ['FPK & Order Penghapusan', 'Langkah 1: Buat FPK Penghapusan → Langkah 2: Gabungkan FPK ke dalam Surat Perintah Kerja (Order).'],
    'mutasi_penghapusan' => ['Eksekusi Mutasi Penghapusan', 'Langkah 3: Bongkar alat di lapangan, rekam kondisi lampu indikator, foto, lalu lepas alat dari pelanggan.'],
    'monitoring_order'    => ['Monitoring Order Pembongkaran', 'Rekapitulasi FPK, Order, dan Berita Acara penghapusan.'],
    'monitoring_mutasi'   => ['Monitoring Mutasi Pembongkaran', 'Riwayat pembongkaran dan penghapusan perangkat S41.'],
];
$tombol = [];
if ($sub === 'order_penghapusan') {
    $tombol = [
        ['type' => 'modal', 'label' => '1. Buat FPK',   'icon' => 'bi-tools',              'target' => 'modalBuatPK',    'class' => 'btn-primary'],
        ['type' => 'modal', 'label' => '2. Buat Order', 'icon' => 'bi-file-earmark-plus',  'target' => 'modalBuatOrder', 'class' => 'btn-primary'],
    ];
} elseif ($sub === 'monitoring_order' || $sub === 'monitoring_mutasi') {
    $tombol = [[
        'type' => 'link', 'label' => 'Cetak Laporan PDF', 'icon' => 'bi-printer', 'class' => 'btn-outline-primary',
        'href' => BASE_URL . '/modules/penghapusan/cetak_laporan.php?type=' . ($sub === 'monitoring_order' ? 'order' : 'mutasi'),
    ]];
}
?>

<?php if ($menu === '' || $sub === ''): ?>
    <div class="alert alert-warning">Peran Anda tidak memiliki akses ke sub-menu Penghapusan.</div>
<?php else: ?>
    <?= pgh_header([
        'title'    => $halaman[$sub][0] ?? 'Modul Penghapusan S41',
        'subtitle' => $halaman[$sub][1] ?? '',
        'tombol'   => $tombol,
    ], $struktur, $menu, $sub) ?>

    <?php include __DIR__ . '/' . $struktur[$menu]['sub'][$sub][2]; ?>
<?php endif; ?>
