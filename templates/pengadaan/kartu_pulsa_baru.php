<?php
// Ambil daftar kartu untuk pilihan Smart Search manual
$unit_ap = $_SESSION['UnitAp'] ?? '';
$unit_up = $_SESSION['UnitUp'] ?? '';
$kode_hak = $_SESSION['KodeHak'] ?? '';

if ($kode_hak === 'SA.KP') {
    $filter_q = "1=1";
} elseif ($kode_hak === 'TL.UP') {
    $filter_q = "KodeUp = '$unit_up'";
} else {
    $filter_q = "KodeAp = '$unit_ap' AND (KodeUp IS NULL OR KodeUp = '')";
}

$stmt_kartu = $conn->query("SELECT SimId, NomorAkun FROM master_nomor WHERE $filter_q ORDER BY SimId ASC");
$list_kartu_dropdown = $stmt_kartu->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow mb-4 border-0">
    <div class="card-body p-4">
        
        <ul class="nav nav-pills mb-4" id="pills-tab-pulsa" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#manual_pulsa" type="button"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link bg-success bg-opacity-10 text-success fw-bold ms-2 px-4 border border-success border-opacity-25" data-bs-toggle="pill" data-bs-target="#excel_pulsa" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Upload CSV</button>
            </li>
        </ul>
        
        <div class="tab-content" id="pills-tabContentPulsa">
            <!-- Form Manual dengan Select2 Smart Search -->
            <div class="tab-pane fade show active" id="manual_pulsa" role="tabpanel">
                <form action="modules/pengadaan/proses_kartu.php?action=pulsa" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">Cari / Pilih SIM ID Tujuan</label>
                            <select class="form-select select2-pulsa-baru" name="sim_id" required style="width: 100%;">
                                <option value="">-- Ketik / Pilih SimId --</option>
                                <?php foreach ($list_kartu_dropdown as $k): ?>
                                    <option value="<?= htmlspecialchars($k['SimId']) ?>">
                                        SimId: <?= htmlspecialchars($k['SimId']) ?> (No: +<?= htmlspecialchars($k['NomorAkun']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">Nominal Pengisian (Rp)</label>
                            <input type="number" class="form-control" name="nominal" placeholder="Contoh: 100000" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">Tanggal Isi Pulsa</label>
                            <input type="date" class="form-control" name="tgl_isi" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <hr class="text-secondary mb-4">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-wallet2 me-2"></i>Simpan Saldo</button>
                    </div>
                </form>
            </div>

            <!-- Form Upload Excel / CSV -->
            <div class="tab-pane fade" id="excel_pulsa" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Catat riwayat pengisian pulsa secara massal via CSV.<br>
                        Pastikan format header file CSV Anda adalah: <strong class="text-dark">SimId;Nominal;TglIsi</strong>
                    </div>
                </div>
                <form action="modules/pengadaan/proses_kartu.php?action=pulsa" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <div class="row align-items-end mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-muted small">Upload File CSV Isi Pulsa (Delimiter ';')</label>
                            <input type="file" class="form-control form-control-lg" name="file_excel" accept=".csv" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success btn-lg w-100"><i class="bi bi-cloud-upload me-2"></i>Proses Massal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- DEPENDENCY SELECT2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-pulsa-baru').select2({
        theme: 'bootstrap-5',
        placeholder: "-- Ketik / Pilih SimId --",
        allowClear: true,
        width: '100%'
    });
});
</script>