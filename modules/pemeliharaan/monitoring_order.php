<?php
// Sub-menu: Monitoring Order Pemeliharaan (hanya baca)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_monitoring()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Monitoring.</div>';
    return;
}

$sub_ini = 'monitoring_order';
$data_mon_order = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$pesan_error = '';
try {
    // BLOB foto TIDAK ikut diambil: cukup penanda ada/tidaknya, gambar dimuat lewat foto.php
    [$data_mon_order, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "p.NoFormPemeliharaan, p.TglPemeliharaan, p.IdPel, p.NoRef, p.SimId, p.ImeiModem, p.KondisiRelay, p.StatusData, p.WaktuData,
         (p.FotoPasang IS NOT NULL) AS AdaFotoPasang, (p.FotoBarang IS NOT NULL) AS AdaFotoBarang",
        "frm_pemeliharaan p"
    );
} catch (PDOException $e) {
    error_log('pemeliharaan monitoring_order: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data monitoring. Hubungi administrator.';
}
$url_foto = BASE_URL . '/modules/pemeliharaan/foto.php';
?>

<style>
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; white-space: normal !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; white-space: normal !important; word-break: break-word !important; }
    .table-compact .badge { font-size: 0.7rem !important; padding: 0.3em 0.45em !important; }
    .preview-img-box { width: 100%; height: 220px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; background-color: #f8f9fa; }
</style>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Order Pemeliharaan</h5>
            <p class="text-muted small mb-0">Rekapitulasi status pengerjaan berita acara pemeliharaan.</p>
        </div>
        <div class="d-flex gap-2">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            <?= ph_tombol_export('order', $q) ?>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMonOrder">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th style="width:17%;">NO FORM PEMELIHARAAN</th>
                    <th style="width:11%;">TGL PEMELIHARAAN</th>
                    <th style="width:14%;">IDPEL / NO REF</th>
                    <th style="width:13%;">SIM ID</th>
                    <th style="width:12%;">IMEI MODEM</th>
                    <th style="width:9%;">KONDISI RELAY</th>
                    <th style="width:8%;">STATUS</th>
                    <th style="width:10%;">WAKTU DATA</th>
                    <th class="pe-2 text-center" style="width:3%;">FOTO</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_order)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada laporan order pemeliharaan.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_order as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFormPemeliharaan']) ?></td>
                            <td class="text-secondary"><?= ph_e($row['TglPemeliharaan']) ?></td>
                            <td><div class="fw-bold text-dark">IDPEL: <?= ph_e($row['IdPel']) ?></div><div class="small text-muted">REF: <?= ph_e($row['NoRef']) ?></div></td>
                            <td>
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle font-monospace"><?= ph_e($row['SimId']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-muted"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td><?= ph_badge_relay($row['KondisiRelay']) ?></td>
                            <td><?= ph_badge_status($row['StatusData']) ?></td>
                            <td class="font-monospace text-secondary small"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                            <td class="pe-2 text-center">
                                <?php if (!empty($row['AdaFotoPasang']) || !empty($row['AdaFotoBarang'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 lh-1 rounded-2 btn-lihat-foto" title="Lihat Foto Dokumentasi"
                                            data-noform="<?= ph_e($row['NoFormPemeliharaan']) ?>"
                                            data-ada-pasang="<?= !empty($row['AdaFotoPasang']) ? '1' : '0' ?>"
                                            data-ada-barang="<?= !empty($row['AdaFotoBarang']) ? '1' : '0' ?>">
                                        <i class="bi bi-image"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- SATU MODAL FOTO (gambar dimuat saat dibuka) -->
<div class="modal fade" id="modalFotoMon" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <div>
                    <h6 class="modal-title fw-bold text-dark mb-0">Foto Hasil Pemeliharaan</h6>
                    <small class="text-muted font-monospace">No Form: <span id="fotoNoForm"></span></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-camera me-1"></i>Foto Pemasangan / Lapangan</label>
                        <img id="monImgPasang" class="preview-img-box shadow-sm d-none" alt="Foto Pasang">
                        <div id="monNoPasang" class="preview-img-box d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image me-2"></i>Tidak ada foto pemasangan</div>
                    </div>
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-box-seam me-1"></i>Foto Fisik Perangkat / Barang</label>
                        <img id="monImgBarang" class="preview-img-box shadow-sm d-none" alt="Foto Barang">
                        <div id="monNoBarang" class="preview-img-box d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image me-2"></i>Tidak ada foto barang</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3 justify-content-end"><button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var URL_FOTO = <?= json_encode($url_foto) ?>;
    function setFoto(imgId, noId, ada, jenis, noform) {
        var img = document.getElementById(imgId), no = document.getElementById(noId);
        if (ada === '1') {
            img.src = URL_FOTO + '?jenis=' + jenis + '&no=' + encodeURIComponent(noform);
            img.classList.remove('d-none'); no.classList.add('d-none');
        } else {
            img.removeAttribute('src'); img.classList.add('d-none'); no.classList.remove('d-none');
        }
    }
    document.querySelectorAll('.btn-lihat-foto').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nf = this.dataset.noform;
            document.getElementById('fotoNoForm').textContent = nf;
            setFoto('monImgPasang', 'monNoPasang', this.dataset.adaPasang, 'pasang', nf);
            setFoto('monImgBarang', 'monNoBarang', this.dataset.adaBarang, 'barang', nf);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFotoMon')).show();
        });
    });
});
</script>