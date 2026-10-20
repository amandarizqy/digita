<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Query Data Monitoring Order Pemeliharaan
$data_mon_order = [];
try {
    $sql_mon = "SELECT p.*, n.NomorAkun 
                FROM frm_pemeliharaan p 
                LEFT JOIN master_nomor n ON p.SimId = n.SimId 
                ORDER BY p.WaktuData DESC";
    $stmt_mon = $conn->query($sql_mon);
    $data_mon_order = $stmt_mon->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handler
}
?>

<style>
    .table-compact thead th {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #6c757d !important;
        padding: 0.6rem 0.35rem !important;
        white-space: normal !important;
    }
    .table-compact tbody td {
        padding: 0.5rem 0.35rem !important;
        font-size: 0.81rem !important;
        white-space: normal !important;
        word-break: break-word !important;
    }
    .table-compact .badge {
        font-size: 0.7rem !important;
        padding: 0.3em 0.45em !important;
    }
    .preview-img-box {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }
</style>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Order Pemeliharaan</h5>
            <p class="text-muted small mb-0">Rekapitulasi status pengerjaan berita acara pemeliharaan.</p>
        </div>
        <div class="input-group style-search" style="width: 260px;">
            <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="searchMonOrder" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data monitoring...">
        </div>
    </div>

    <!-- Container Tabel Pas Layar -->
    <div class="w-100">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMonOrder">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width: 3%;">#</th>
                    <th style="width: 17%;">NO FORM PEMELIHARAAN</th>
                    <th style="width: 11%;">TGL PEMELIHARAAN</th>
                    <th style="width: 14%;">IDPEL / NO REF</th>
                    <th style="width: 13%;">SIM ID / NO AKUN</th>
                    <th style="width: 12%;">IMEI MODEM</th>
                    <th style="width: 9%;">KONDISI RELAY</th>
                    <th style="width: 8%;">STATUS DATA</th>
                    <th style="width: 10%;">WAKTU DATA</th>
                    <th class="pe-2 text-center" style="width: 3%;">FOTO</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_order)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada laporan order pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_order as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
                            <td class="text-secondary"><?= htmlspecialchars($row['TglPemeliharaan']) ?></td>
                            <td>
                                <div class="fw-bold text-dark">IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                <div class="small text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle font-monospace">
                                        <?= htmlspecialchars($row['SimId']) ?>
                                    </span>
                                    <?php if (!empty($row['NomorAkun'])): ?>
                                        <div class="small text-muted mt-1 font-monospace"><?= htmlspecialchars($row['NomorAkun']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= ($row['KondisiRelay'] ?? '') === 'BERFUNGSI' ? 'success' : 'danger' ?>-subtle text-<?= ($row['KondisiRelay'] ?? '') === 'BERFUNGSI' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($row['KondisiRelay'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success">TERKIRIM</span>
                            </td>
                            <td class="font-monospace text-secondary small"><?= htmlspecialchars($row['WaktuData'] ?? '-') ?></td>
                            <td class="pe-2 text-center">
                                <?php if (!empty($row['FotoPasang']) || !empty($row['FotoBarang'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 lh-1 rounded-2" data-bs-toggle="modal" data-bs-target="#modalFotoMon<?= $idx ?>" title="Lihat Foto Dokumentasi">
                                        <i class="bi bi-image"></i>
                                    </button>

                                    <!-- Pop-up Foto Dual (Foto Pasang & Foto Barang) -->
                                    <div class="modal fade" id="modalFotoMon<?= $idx ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 rounded-4 shadow">
                                                <div class="modal-header border-bottom py-3 px-4">
                                                    <div>
                                                        <h6 class="modal-title fw-bold text-dark mb-0">Foto Hasil Pemeliharaan</h6>
                                                        <small class="text-muted font-monospace">No Form: <?= htmlspecialchars($row['NoFormPemeliharaan']) ?></small>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="row g-3">
                                                        <!-- Foto Pasang / Lapangan -->
                                                        <div class="col-md-6 text-center">
                                                            <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-camera me-1"></i>Foto Pemasangan / Lapangan</label>
                                                            <?php if (!empty($row['FotoPasang'])): ?>
                                                                <img src="data:image/jpeg;base64,<?= base64_encode($row['FotoPasang']) ?>" class="preview-img-box shadow-sm" alt="Foto Pasang">
                                                            <?php else: ?>
                                                                <div class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                                                    <i class="bi bi-image me-2"></i>Tidak ada foto pemasangan
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Foto Fisik Barang / Perangkat -->
                                                        <div class="col-md-6 text-center">
                                                            <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-box-seam me-1"></i>Foto Fisik Perangkat / Barang</label>
                                                            <?php if (!empty($row['FotoBarang'])): ?>
                                                                <img src="data:image/jpeg;base64,<?= base64_encode($row['FotoBarang']) ?>" class="preview-img-box shadow-sm" alt="Foto Barang">
                                                            <?php else: ?>
                                                                <div class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                                                    <i class="bi bi-image me-2"></i>Tidak ada foto barang
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top p-3 justify-content-end">
                                                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchMon = document.getElementById("searchMonOrder");
    if (searchMon) {
        searchMon.addEventListener("keyup", function() {
            const filter = searchMon.value.toLowerCase();
            const rows = document.querySelectorAll("#tableMonOrder tbody tr");
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    }
});
</script>