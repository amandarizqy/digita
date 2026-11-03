<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_monitoring()) {
    echo '<div class="alert alert-warning">Akses ditolak.</div>';
    return;
}

$sub_ini = 'monitoring_order';
$data_mon_order = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';

try {
    $nama_sql = ph_sql_nama_modem($conn);
    [$data_mon_order, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "o.NoFormOrder, p.NoFpk, b.NoFba, p.TglFpk, COALESCE(b.KdPetugas, o.KdPetugas) AS KdPetugas, p.IdPel, pd.NoRef,
         COALESCE(bd.SimId, mb.SimId) AS SimId, COALESCE(bd.ImeiModem, mb.ImeiModem) AS ImeiModem, $nama_sql AS NamaModem,
         COALESCE(bd.NomorBarang, pd.NomorBarang) AS KontakS41, bd.LampuIndikator AS KondisiRelay, COALESCE(b.StatusData, p.StatusData) AS StatusData,
         (bd.FotoTerpasang IS NOT NULL) AS AdaFotoPasang, (bd.FotoBarang IS NOT NULL) AS AdaFotoBarang",
        "frm_pk p
         LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
         LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN master_barang mb ON mb.NoRef = pd.NoRef
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
         LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN master_modem mm ON mm.ImeiModem = COALESCE(bd.ImeiModem, mb.ImeiModem)",
        "AND p.JenisPk = 'PEMELIHARAAN'",
        [],
        ['alias' => 'p', 'urut' => 'p.WaktuData DESC, p.NoFpk DESC',
         'cari' => ph_kolom_cari($conn, true, true, ['pd.NoRef', 'COALESCE(bd.SimId, mb.SimId)', 'COALESCE(bd.ImeiModem, mb.ImeiModem)'])]
    );
} catch (PDOException $e) {}

$url_cetak = BASE_URL . '/modules/pemeliharaan/cetak_ba.php';
$url_foto  = BASE_URL . '/modules/pemeliharaan/foto.php';
$url_cetak_order = BASE_URL . '/modules/pemeliharaan/cetak_order.php';
$url_cetak_laporan = BASE_URL . '/modules/pemeliharaan/cetak_laporan.php?type=order';
?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
    <?= ph_card_header('Daftar Monitoring Order Pemeliharaan', $sub_ini, $q) ?>
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ph-table">
            <thead class="table-light text-secondary text-uppercase ph-thead">
                <tr>
                    <th class="ps-4" style="width:40px;">#</th>
                    <th style="width:17%;">NO ORDER / FPK / BA</th>
                    <th style="width:10%;">TGL FPK</th>
                    <th style="width:15%;">IDPEL / NO REF</th>
                    <th style="width:12%;">SIM ID</th>
                    <th style="width:15%;">MODEM</th>
                    <th style="width:10%;">INDIKATOR</th>
                    <th style="width:7%;">STATUS</th>
                    <th class="text-end pe-4">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_order)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">Belum ada data monitoring pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_order as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-4 text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="small text-secondary font-monospace">ORDER: <?= ph_e($row['NoFormOrder'] ?? '-') ?>
                                    <?php if (!empty($row['NoFormOrder'])): ?>
                                        <a href="<?= ph_e($url_cetak_order . '?no_order=' . urlencode($row['NoFormOrder'])) ?>" target="_blank" class="ms-1 text-decoration-none"><i class="bi bi-printer"></i></a>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= ph_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td><?= ph_e($row['TglFpk']) ?></td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></small></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= ph_e($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace text-muted"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td><?= ph_badge_relay($row['KondisiRelay']) ?></td>
                            <td><?= ph_badge_status($row['StatusData']) ?></td>
                            <td class="text-end pe-4">
                                <?php if (!empty($row['NoFba'])): ?>
                                    <a href="<?= ph_e($url_cetak . '?no_fba=' . urlencode($row['NoFba'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" title="Cetak BA"><i class="bi bi-printer-fill"></i></a>
                                    <?php if (!empty($row['AdaFotoPasang']) || !empty($row['AdaFotoBarang'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1 btn-lihat-foto" title="Lihat foto"
                                                data-nofba="<?= ph_e($row['NoFba']) ?>" data-pasang="<?= !empty($row['AdaFotoPasang']) ? '1' : '0' ?>" data-barang="<?= !empty($row['AdaFotoBarang']) ? '1' : '0' ?>">
                                            <i class="bi bi-images"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Belum BA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL FOTO: kedua foto (terpasang & barang) tampil berdampingan -->
<div class="modal fade" id="modalFoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-images me-2 text-primary"></i>Foto Berita Acara <span id="fotoNoBa" class="font-monospace small text-muted"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6 text-center">
                        <div class="fw-bold small text-uppercase text-muted mb-2">Foto Terpasang (Lapangan)</div>
                        <div id="fotoPasangBox" class="border rounded-3 bg-light d-flex align-items-center justify-content-center" style="min-height:260px;"></div>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="fw-bold small text-uppercase text-muted mb-2">Foto Barang (Perangkat)</div>
                        <div id="fotoBarangBox" class="border rounded-3 bg-light d-flex align-items-center justify-content-center" style="min-height:260px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var urlFoto = <?= json_encode($url_foto) ?>;
    var modalEl = document.getElementById("modalFoto");
    if (!modalEl || !window.bootstrap) { return; }
    var modal = new bootstrap.Modal(modalEl);
    function isi(boxId, ada, jenis, no) {
        var box = document.getElementById(boxId);
        box.innerHTML = "";
        if (ada) {
            var a = document.createElement("a");
            a.href = urlFoto + "?jenis=" + jenis + "&no=" + encodeURIComponent(no);
            a.target = "_blank"; a.rel = "noopener";
            var img = document.createElement("img");
            img.src = a.href; img.alt = jenis; img.className = "img-fluid rounded-3";
            img.style.maxHeight = "420px";
            a.appendChild(img); box.appendChild(a);
        } else {
            box.innerHTML = '<span class="text-muted small fst-italic">Belum ada foto</span>';
        }
    }
    document.querySelectorAll(".btn-lihat-foto").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var d = this.dataset;
            document.getElementById("fotoNoBa").textContent = d.nofba;
            isi("fotoPasangBox", d.pasang === "1", "pasang", d.nofba);
            isi("fotoBarangBox", d.barang === "1", "barang", d.nofba);
            modal.show();
        });
    });
});
</script>