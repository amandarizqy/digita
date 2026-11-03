<?php
$unit_up = $_SESSION['UnitUp'] ?? '';
$is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
$view    = $_GET['view'] ?? 'daftar';
$q       = trim($_GET['q'] ?? '');

if ($view === 'daftar') {
    // Ambil Data FPK & Order Pemasangan
    $sql_fpk = "SELECT f.*, 
                       pd.NoRef as NomorBarangDetil, pd.SimId as SimIdDetil, pd.ImeiModem as ImeiModemDetil,
                       (SELECT NoFormOrder FROM frm_order_detil d WHERE d.NoFpk = f.NoFpk LIMIT 1) as TerikatOrder,
                       (SELECT o.TglOrder FROM frm_order o JOIN frm_order_detil d ON d.NoFormOrder = o.NoFormOrder WHERE d.NoFpk = f.NoFpk LIMIT 1) as TglOrder,
                       (SELECT o.NoFormOrder FROM frm_order_detil d JOIN frm_order o ON o.NoFormOrder = d.NoFormOrder WHERE d.NoFpk = f.NoFpk LIMIT 1) as NoFormOrder
                FROM frm_pk f 
                LEFT JOIN frm_pk_detil pd ON pd.NoFpk = f.NoFpk
                WHERE f.JenisPk = 'PEMASANGAN'";
    $params_fpk = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql_fpk .= " AND f.UnitUp = ?";
        $params_fpk[] = $unit_up;
    }
    if (!empty($q)) {
        $sql_fpk .= " AND (f.NoFpk LIKE ? OR f.IdPel LIKE ?)";
        $params_fpk[] = "%$q%";
        $params_fpk[] = "%$q%";
    }
    $sql_fpk .= " ORDER BY f.WaktuData DESC";
    
    $stmt_fpk = $conn->prepare($sql_fpk);
    $stmt_fpk->execute($params_fpk);
    $list_fpk = $stmt_fpk->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Data Pelanggan untuk Modal FPK (Berurut berdasarkan skala dan peringkat dari hasil_prioritas)
    $sql_pel = "SELECT IdPel, Peringkat, TotalTagihan, SkalaPrioritas FROM hasil_prioritas 
                WHERE IdPel NOT IN (SELECT IdPel FROM master_barang WHERE IdPel IS NOT NULL AND IdPel != '')
                  AND IdPel NOT IN (SELECT IdPel FROM frm_pk WHERE JenisPk = 'PEMASANGAN')";
    $params_pel = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql_pel .= " AND UnitUp = ?";
        $params_pel[] = $unit_up;
    }
    $sql_pel .= " ORDER BY SkalaPrioritas DESC, Peringkat ASC";
    $stmt_pelanggan = $conn->prepare($sql_pel);
    $stmt_pelanggan->execute($params_pel);
    $list_pelanggan = $stmt_pelanggan->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Data Alat untuk Modal FPK (HANYA alat yang sudah lolos uji pengujian & belum dipasang)
    $sql_al = "SELECT ed.NoRef, ed.ImeiModem, ed.SimId 
               FROM formulir_pengujian_detil ed
               JOIN formulir_pengujian e ON ed.NoFormulir = e.NoFormulir
               JOIN master_barang mb ON mb.NoRef = ed.NoRef
               WHERE e.StatusData = 'AKTIF'
                 AND (mb.IdPel IS NULL OR mb.IdPel = '')
                 AND ed.NoRef NOT IN (
                    SELECT pd.NoRef FROM frm_pk_detil pd 
                    JOIN frm_pk f ON pd.NoFpk = f.NoFpk 
                    WHERE f.JenisPk = 'PEMASANGAN' AND pd.NoRef IS NOT NULL
                 )";
    $params_al = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql_al .= " AND e.UnitUp = ?";
        $params_al[] = $unit_up;
    }
    $sql_al .= " GROUP BY ed.NoRef ORDER BY ed.NoRef ASC";
    $stmt_alat = $conn->prepare($sql_al);
    $stmt_alat->execute($params_al);
    $list_alat = $stmt_alat->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Panggil Dependency Select2 untuk Smart Search -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .select2-container .select2-selection--single { height: 38px; border: 1px solid #dee2e6; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    .select2-container { z-index: 99999 !important; }
</style>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-primary mb-1">Daftar FPK & Order Pemasangan</h5>
            <p class="text-muted small mb-0">Langkah 1: Buat FPK Pemasangan → Langkah 2: Gabungkan FPK ke dalam SPK (Tombol di kanan atas).</p>
        </div>
        
        <form action="index.php" method="GET" class="input-group input-group-sm" style="max-width: 320px;">
            <input type="hidden" name="page" value="pemasangan">
            <input type="hidden" name="menu" value="eksekusi">
            <input type="hidden" name="sub" value="order_pasang">
            <input type="text" class="form-control" name="q" placeholder="Cari no form, idpel..." value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FPK</th>
                    <th>TGL FPK</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>MODEM</th>
                    <th>NO ORDER / STATUS</th>
                    <th class="text-center pe-3" style="width:10%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_fpk)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data FPK Pemasangan.</td></tr>
                <?php else: ?>
                    <?php foreach ($list_fpk as $idx => $fpk): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($fpk['NoFpk']) ?></td>
                            <td><?= htmlspecialchars($fpk['TglFpk']) ?></td>
                            <td class="font-monospace small">
                                <div>IDPEL: <?= htmlspecialchars($fpk['IdPel']) ?></div>
                                <div class="text-muted">REF: <?= htmlspecialchars($fpk['NomorBarangDetil'] ?? '-') ?></div>
                            </td>
                            <td class="font-monospace text-info small"><?= htmlspecialchars($fpk['SimIdDetil'] ?? '-') ?></td>
                            <td class="font-monospace text-muted small"><?= htmlspecialchars($fpk['ImeiModemDetil'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($fpk['TerikatOrder'])): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success font-monospace"><?= htmlspecialchars($fpk['TerikatOrder']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning">Belum SPK</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <?php if (empty($fpk['TerikatOrder'])): ?>
                                    <a href="javascript:void(0);" onclick="confirmDeleteFpk('modules/pemasangan/proses_order.php?action=hapus_fpk&no_fpk=<?= urlencode($fpk['NoFpk']) ?>')" class="btn btn-sm btn-outline-danger px-2 py-1" title="Hapus FPK"><i class="bi bi-trash"></i></a>
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

<!-- MODAL TAMBAH FPK DENGAN SELECT2 SMART SEARCH -->
<div class="modal fade" id="modalTambahFpk" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="modules/pemasangan/proses_order.php?action=simpan_fpk" method="POST" class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-file-earmark-plus me-2"></i>Buat Formulir Permintaan Kerja (FPK) Pemasangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Tanggal FPK</label>
                    <input type="date" name="tgl_fpk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Pilih Pelanggan (Dari Hasil Prioritas)</label>
                    <select name="id_pel" class="form-select select2-fpk" required style="width: 100%;">
                        <option value="">-- Cari & Pilih IdPel (Skala Prioritas) --</option>
                        <?php foreach ($list_pelanggan as $pel): ?>
                            <option value="<?= htmlspecialchars($pel['IdPel']) ?>">
                                IdPel: <?= htmlspecialchars($pel['IdPel']) ?> | Skala: <?= htmlspecialchars($pel['SkalaPrioritas']) ?> | Peringkat: <?= htmlspecialchars($pel['Peringkat']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Pilih Perangkat S41 (Sudah Lolos Uji)</label>
                    <select name="no_ref" class="form-select select2-fpk" required style="width: 100%;">
                        <option value="">-- Cari & Pilih NoRef Alat S41 --</option>
                        <?php foreach ($list_alat as $alt): ?>
                            <option value="<?= htmlspecialchars($alt['NoRef']) ?>">
                                NoRef: <?= htmlspecialchars($alt['NoRef']) ?> (SIM: <?= htmlspecialchars($alt['SimId'] ?? 'Kosong') ?> | IMEI: <?= htmlspecialchars($alt['ImeiModem'] ?? 'Kosong') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning fw-bold px-4">Simpan FPK</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#modalTambahFpk').on('shown.bs.modal', function () {
        $('.select2-fpk').select2({
            dropdownParent: $('#modalTambahFpk'),
            placeholder: "Ketik untuk mencari...",
            allowClear: true
        });
    });
});
</script>

<?php 
} elseif ($view === 'baru') {
    $sql_bebas = "SELECT f.NoFpk, f.IdPel, pd.NoRef FROM frm_pk f 
                  LEFT JOIN frm_pk_detil pd ON pd.NoFpk = f.NoFpk
                  WHERE f.JenisPk = 'PEMASANGAN' AND f.NoFpk NOT IN (SELECT NoFpk FROM frm_order_detil)";
    $params_bebas = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql_bebas .= " AND f.UnitUp = ?";
        $params_bebas[] = $unit_up;
    }
    $stmt_fpk_bebas = $conn->prepare($sql_bebas);
    $stmt_fpk_bebas->execute($params_bebas);
    $list_fpk_bebas = $stmt_fpk_bebas->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-primary mb-0">Buat Surat Perintah Kerja (SPK / Order) Pemasangan</h5>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=order_pasang" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
    <form action="modules/pemasangan/proses_order.php?action=simpan_order" method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Tanggal Order</label>
            <input type="date" name="tgl_order" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small">Pilih FPK Pemasangan yang Belum Masuk Order</label>
            <div class="border rounded p-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                <?php if (empty($list_fpk_bebas)): ?>
                    <span class="text-muted small">Tidak ada FPK yang tersedia untuk dibuatkan order. Buat FPK terlebih dahulu.</span>
                <?php else: ?>
                    <?php foreach ($list_fpk_bebas as $fb): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="fpk_terpilih[]" value="<?= $fb['NoFpk'] ?>" id="fpk_<?= $fb['NoFpk'] ?>">
                            <label class="form-check-label font-monospace small" for="fpk_<?= $fb['NoFpk'] ?>">
                                <strong><?= $fb['NoFpk'] ?></strong> — IdPel: <?= $fb['IdPel'] ?> (Ref: <?= $fb['NoRef'] ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Order Baru</button>
        </div>
    </form>
</div>
<?php } ?>