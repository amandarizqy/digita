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
         (bd.FotoTerpasang IS NOT NULL) AS AdaFotoPasang, (bd.FotoBarang IS NOT NULL) AS AdaFotoBarang,
         (bk.FotoTerpasang IS NOT NULL) AS AdaBongkarTerpasang, (bk.FotoBarang IS NOT NULL) AS AdaBongkarBarang",
        "frm_pk p
         LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
         LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN master_barang mb ON mb.NoRef = pd.NoRef
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
         LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
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
                                <?php if (!empty($row['NoFormOrder']) || !empty($row['NoFba'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 btn-detail-order" title="Lihat detail FPK dan foto"
                                            data-order="<?= ph_e($row['NoFormOrder'] ?? '') ?>"
                                            data-fpk="<?= ph_e($row['NoFpk']) ?>"
                                            data-nofba="<?= ph_e($row['NoFba'] ?? '') ?>"
                                            data-bongkar-terpasang="<?= !empty($row['AdaBongkarTerpasang']) ? '1' : '0' ?>"
                                            data-bongkar-barang="<?= !empty($row['AdaBongkarBarang']) ? '1' : '0' ?>"
                                            data-pasang-terpasang="<?= !empty($row['AdaFotoPasang']) ? '1' : '0' ?>"
                                            data-pasang-barang="<?= !empty($row['AdaFotoBarang']) ? '1' : '0' ?>">
                                        <i class="bi bi-eye me-1"></i>Detail
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
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL DETAIL: Formulir Order + FPK, serta foto pembongkaran dan pemasangan -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-eye me-2"></i>Detail <span class="font-monospace small text-dark ms-2" id="dtFpk"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="fw-bold small text-uppercase text-muted mb-2">Formulir Order &amp; FPK <span class="font-monospace" id="dtOrder"></span></div>
                <div class="border rounded-3 overflow-hidden mb-4 bg-secondary-subtle">
                    <iframe id="dtFrame" title="Formulir Order" style="width:100%;height:70vh;min-height:70vh;border:0;background:#fff;display:block;"></iframe>
                    <div id="dtNoOrder" class="text-center text-muted small fst-italic py-4 d-none">FPK ini belum masuk ke dalam order.</div>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Tutup</button>
                <a href="#" target="_blank" rel="noopener" id="dtCetak" class="btn btn-info text-white fw-bold px-4 d-none"><i class="bi bi-printer me-1"></i> Cetak Order Ini</a>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var urlOrder = <?= json_encode($url_cetak_order) ?>;
    var modalEl  = document.getElementById("modalDetail");
    if (!modalEl || !window.bootstrap) { return; }
    var modal = new bootstrap.Modal(modalEl);
    var frameEl = document.getElementById("dtFrame");
    // Tinggi iframe mengikuti isi dokumen (min 70% tinggi layar) agar seluruh FPK (termasuk lampiran foto) terlihat
    function ukurFrame() {
        try {
            if (!frameEl.src || frameEl.src.indexOf("about:blank") !== -1) { return; }
            var doc = frameEl.contentDocument;
            if (!doc || !doc.documentElement) { return; }
            var tinggi = doc.documentElement.scrollHeight;
            if (tinggi > 300) { frameEl.style.height = (tinggi + 16) + "px"; }
        } catch (e) {}
    }
    frameEl.addEventListener("load", function () { ukurFrame(); [300, 1000, 2500].forEach(function (t) { setTimeout(ukurFrame, t); }); });
    modalEl.addEventListener("shown.bs.modal", ukurFrame);

    document.querySelectorAll(".btn-detail-order").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var d = this.dataset;
            document.getElementById("dtFpk").textContent = d.fpk;
            document.getElementById("dtOrder").textContent = d.order ? "(" + d.order + ")" : "";

            var cetak = document.getElementById("dtCetak");
            if (d.order) {
                cetak.href = urlOrder + "?no_order=" + encodeURIComponent(d.order);
                cetak.classList.remove("d-none");
            } else {
                cetak.classList.add("d-none");
            }

            var frame = document.getElementById("dtFrame"), kosong = document.getElementById("dtNoOrder");
            if (d.order) {
                frame.classList.remove("d-none"); kosong.classList.add("d-none");
                frame.src = urlOrder + "?no_order=" + encodeURIComponent(d.order) + "&preview=1";
            } else {
                frame.classList.add("d-none"); kosong.classList.remove("d-none");
            }

            modal.show();
        });
    });

    modalEl.addEventListener("hidden.bs.modal", function () {
        document.getElementById("dtFrame").src = "about:blank";
        frameEl.style.height = "70vh";
    });
});
</script>