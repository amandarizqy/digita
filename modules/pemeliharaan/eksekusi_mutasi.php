<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pesan_sukses_mut = '';
$pesan_error_mut  = '';

// 1. Fetch Pilihan SIM ID & Modem Aktif yang Siap Dipilih
$list_sim_mutasi = [];
$list_modem_mutasi = [];
try {
    $stmt_s = $conn->query("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' ORDER BY SimId ASC");
    $list_sim_mutasi = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    // Modem: sertakan nama (Keterangan) supaya familiar saat dipilih
    $stmt_m = $conn->query("SELECT ImeiModem, Keterangan FROM master_modem WHERE StatusData = 'AKTIF' ORDER BY Keterangan ASC, ImeiModem ASC");
    $list_modem_mutasi = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handler
}

// 2. Submit Mutasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_mutasi'])) {
    if ($_POST['action_mutasi'] === 'simpan_mutasi') {
        try {
            $no_form_pem = $_POST['no_form_pemeliharaan'];
            $sim_id_baru = !empty($_POST['sim_id']) ? $_POST['sim_id'] : null;
            $imei_baru   = !empty($_POST['imei_modem']) ? $_POST['imei_modem'] : null;

            $stmt = $conn->prepare("UPDATE frm_pemeliharaan SET 
                SimId = :sim_id, 
                ImeiModem = :imei, 
                NamaAkun = :nama_akun, 
                WaktuData = NOW() 
                WHERE NoFormPemeliharaan = :no_form");

            $stmt->execute([
                ':sim_id'    => $sim_id_baru,
                ':imei'      => $imei_baru,
                ':nama_akun' => $_SESSION['NamaAkun'] ?? 'PETUGAS MUTASI',
                ':no_form'   => $no_form_pem
            ]);

            $pesan_sukses_mut = "Data mutasi perangkat untuk $no_form_pem berhasil disimpan!";
        } catch (PDOException $e) {
            $pesan_error_mut = "Gagal memproses mutasi: " . $e->getMessage();
        }
    }
}

// 3. Fetch Data Mutasi (dengan nama modem)
$data_mutasi = [];
try {
    $sql_mut = "SELECT p.*, n.NomorAkun, m.Keterangan AS NamaModem 
                FROM frm_pemeliharaan p 
                LEFT JOIN master_nomor n ON p.SimId = n.SimId 
                LEFT JOIN master_modem m ON p.ImeiModem = m.ImeiModem 
                ORDER BY p.WaktuData DESC";
    $stmt_mut = $conn->query($sql_mut);
    $data_mutasi = $stmt_mut->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error_mut = "Error Database: " . $e->getMessage();
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .table-compact thead th {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #6c757d !important;
        padding: 0.6rem 0.35rem !important;
    }
    .table-compact tbody td {
        padding: 0.5rem 0.35rem !important;
        font-size: 0.81rem !important;
    }
    .select2-container--open {
        z-index: 99999 !important;
    }
</style>

<?php if (!empty($pesan_sukses_mut)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan_sukses_mut) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error_mut)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error_mut) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Riwayat Mutasi Pemeliharaan</h5>
        <div class="input-group style-search" style="width: 260px;">
            <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="searchMutasi" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data mutasi...">
        </div>
    </div>

    <div class="w-100">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMutasi">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width: 4%;">#</th>
                    <th style="width: 20%;">NO FORM PEMELIHARAAN</th>
                    <th style="width: 18%;">IDPEL / NO REF</th>
                    <th style="width: 20%;">SIM ID TERPASANG</th>
                    <th style="width: 18%;">MODEM TERPASANG</th>
                    <th style="width: 10%;">RELAY</th>
                    <th class="pe-2 text-center" style="width: 10%;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mutasi)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data mutasi.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mutasi as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
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
                                    <span class="text-muted fst-italic small">Tanpa SIM</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace">
                                <?php if (!empty($row['NamaModem'])): ?>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['NamaModem']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($row['KondisiRelay'] ?? '') === 'BERFUNGSI' ? 'success' : 'danger' ?>-subtle text-<?= ($row['KondisiRelay'] ?? '') === 'BERFUNGSI' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($row['KondisiRelay'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="pe-2 text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" data-bs-toggle="modal" data-bs-target="#modalMutasiPerangkat<?= $idx ?>">
                                    <i class="bi bi-arrow-left-right me-1"></i> Mutasi
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL MUTASI (satu per baris, diletakkan di luar tabel) -->
<?php foreach ($data_mutasi as $idx => $row): ?>
    <div class="modal fade modal-mutasi-class" id="modalMutasiPerangkat<?= $idx ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark">Mutasi / Ganti Perangkat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 text-start">
                        <input type="hidden" name="action_mutasi" value="simpan_mutasi">
                        <input type="hidden" name="no_form_pemeliharaan" value="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>">

                        <div class="mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO FORM PEMELIHARAAN</label>
                            <input type="text" class="form-control rounded-3 bg-light text-muted font-monospace fw-bold" value="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>" readonly>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="text-uppercase text-muted fw-bold small mb-2"><i class="bi bi-info-circle me-1"></i> Perangkat Terpasang Saat Ini</div>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted d-block">SIM ID:</span>
                                    <strong class="font-monospace text-dark"><?= htmlspecialchars($row['SimId'] ?? 'Tanpa SIM') ?></strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Modem:</span>
                                    <?php if (!empty($row['NamaModem'])): ?>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($row['NamaModem']) ?></strong>
                                    <?php endif; ?>
                                    <strong class="font-monospace text-dark"><?= htmlspecialchars($row['ImeiModem'] ?? 'Tanpa Modem') ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- SEARCHABLE SELECT2 SIM CARD -->
                        <div class="mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UPDATE SIM CARD BARU</label>
                            <select name="sim_id" class="form-select select2-searchable-sim" data-placeholder="-- Cari / Ketik SIM ID --" style="width: 100%;">
                                <option value="">-- Cari / Ketik SIM ID --</option>
                                <?php foreach ($list_sim_mutasi as $s): ?>
                                    <option value="<?= htmlspecialchars($s['SimId']); ?>" <?= (($row['SimId'] ?? '') === $s['SimId']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($s['SimId']); ?><?= !empty($s['NomorAkun']) ? ' (' . htmlspecialchars($s['NomorAkun']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- SEARCHABLE SELECT2 MODEM (NAMA - IMEI) -->
                        <div class="mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UPDATE MODEM BARU</label>
                            <select name="imei_modem" class="form-select select2-searchable-modem" data-placeholder="-- Cari nama / IMEI modem --" style="width: 100%;">
                                <option value="">-- Cari nama / IMEI modem --</option>
                                <?php foreach ($list_modem_mutasi as $m): ?>
                                    <option value="<?= htmlspecialchars($m['ImeiModem']); ?>" <?= (($row['ImeiModem'] ?? '') === $m['ImeiModem']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars((!empty($m['Keterangan']) ? $m['Keterangan'] : 'MODEM') . ' - ' . $m['ImeiModem']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer border-top p-3 justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4">Simpan Mutasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- jQuery (dimuat hanya kalau belum ada, dari CDN yang sama dengan Bootstrap) & Select2 -->
<script>
if (!window.jQuery) {
    document.write('<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"><\/script>');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pencarian di tabel
    const searchMut = document.getElementById("searchMutasi");
    if (searchMut) {
        searchMut.addEventListener("keyup", function() {
            const filter = searchMut.value.toLowerCase();
            document.querySelectorAll("#tableMutasi tbody tr").forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
            });
        });
    }

    // Aktifkan Select2 saat modal mutasi dibuka (satu kali per kotak)
    document.addEventListener("shown.bs.modal", function(ev) {
        const modalEl = ev.target;
        if (!modalEl.classList || !modalEl.classList.contains("modal-mutasi-class")) { return; }
        if (!window.jQuery || !jQuery.fn.select2) { return; }

        const $modal = jQuery(modalEl);
        $modal.find("select.select2-searchable-sim, select.select2-searchable-modem").each(function () {
            const $el = jQuery(this);
            if ($el.hasClass("select2-hidden-accessible")) { return; }
            $el.select2({
                theme: "bootstrap-5",
                dropdownParent: $modal,
                width: "100%",
                placeholder: $el.data("placeholder") || "-- Cari --",
                allowClear: true,
                language: { noResults: function () { return "Tidak ditemukan"; } }
            });
        });
    });
});
</script>