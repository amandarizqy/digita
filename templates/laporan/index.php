<!-- templates/laporan/index.php -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-file-earmark-text me-2"></i>Dashboard Laporan</h4>
</div>

<?php
// Struktur kategori & submenu. 'key' HARUS sama persis dengan key
// di $laporan_config (modules/laporan/index.php) supaya link nyambung.
$kategori_laporan = [
    'perencanaan' => [
        'label' => 'Perencanaan', 'icon' => 'bi-bar-chart-steps', 'warna' => 'primary',
        'sub' => [
            'perencanaan_klasifikasi_risiko'  => 'Hasil Klasifikasi Level Risiko',
            'perencanaan_tingkat_kepentingan' => 'Hasil Tingkat Kepentingan',
            'perencanaan_survei_sr'           => 'Hasil Survei SR',
            'perencanaan_skala_prioritas'     => 'Hasil Skala Prioritas',
        ],
    ],
    'pengadaan' => [
        'label' => 'Pengadaan', 'icon' => 'bi-box-seam', 'warna' => 'success',
        'sub' => [
            'pengadaan_pengiriman' => 'Pengiriman',
            'pengadaan_penerimaan' => 'Penerimaan',
            'pengadaan_aset'       => 'Aset',
        ],
    ],
    'pemasangan' => [
        'label' => 'Pemasangan', 'icon' => 'bi-tools', 'warna' => 'warning',
        'sub' => [
            'pemasangan_status_pengujian'  => 'Status Pengujian',
            'pemasangan_riwayat_pengujian' => 'Riwayat Pengujian',
            'pemasangan_order_pasang'      => 'Order Pasang',
            'pemasangan_mutasi_pasang'     => 'Mutasi Pasang',
        ],
    ],
    'penggunaan' => [
        'label' => 'Penggunaan', 'icon' => 'bi-activity', 'warna' => 'info',
        'sub' => [
            'penggunaan_dil'             => 'Data Induk Pelanggan (DIL)',
            'penggunaan_pelunasan'       => 'Pelunasan',
            'penggunaan_pesan'           => 'Pesan',
            'penggunaan_status_eksekusi' => 'Status Eksekusi',
        ],
    ],
    'pemeliharaan' => [
        'label' => 'Pemeliharaan', 'icon' => 'bi-wrench', 'warna' => 'secondary',
        'sub' => [
            'pemeliharaan_order'  => 'Order Pemeliharaan',
            'pemeliharaan_mutasi' => 'Mutasi Pemeliharaan',
        ],
    ],
    'penghapusan' => [
        'label' => 'Penghapusan', 'icon' => 'bi-trash', 'warna' => 'danger',
        'sub' => [
            'penghapusan_order_bongkar'  => 'Order Pembongkaran',
            'penghapusan_mutasi_bongkar' => 'Mutasi Pembongkaran',
        ],
    ],
];
?>

<div class="accordion" id="accordionLaporan">
    <?php foreach ($kategori_laporan as $kkey => $kat): ?>
        <div class="accordion-item mb-2 border-<?= $kat['warna'] ?> border-start border-3">
            <h2 class="accordion-header" id="heading-<?= $kkey ?>">
                <button class="accordion-button collapsed fw-bold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse-<?= $kkey ?>"
                        aria-expanded="false" aria-controls="collapse-<?= $kkey ?>">
                    <i class="bi <?= $kat['icon'] ?> text-<?= $kat['warna'] ?> me-2"></i>
                    <?= htmlspecialchars($kat['label']) ?>
                </button>
            </h2>
            <div id="collapse-<?= $kkey ?>" class="accordion-collapse collapse"
                 aria-labelledby="heading-<?= $kkey ?>" data-bs-parent="#accordionLaporan">
                <div class="accordion-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($kat['sub'] as $action_key => $sub_label): ?>
                            <a href="?action=<?= urlencode($action_key) ?>"
                               class="list-group-item list-group-item-action py-3">
                                <i class="bi bi-chevron-right text-muted me-2"></i>
                                <?= htmlspecialchars($sub_label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>