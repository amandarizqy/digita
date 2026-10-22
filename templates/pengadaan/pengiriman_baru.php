<?php
$user_upi = $_SESSION['UnitUpi'] ?? '56';
$user_ap  = $_SESSION['UnitAp'] ?? '';
$is_ap    = ($kode_hak === 'AM.AP'); // Deteksi apakah yang login Asman AP

$tgl_hari_ini = date('Y-m-d');
$tgl_format = date('Ymd');

// Ambil daftar Unit AP (hanya untuk AM.UI)
$stmt_ap = $conn->prepare("SELECT * FROM master_ap WHERE UnitUpi = ? ORDER BY NamaUnit ASC");
$stmt_ap->execute([$user_upi]);
$list_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar Unit UP: Jika AM.AP, hanya ambil UP yang punya UnitAp sama dengan user yang login. Jika AM.UI, ambil semua se-UPI.
if ($is_ap) {
    $stmt_up = $conn->prepare("SELECT * FROM master_up WHERE UnitAp = ? ORDER BY NamaUnit ASC");
    $stmt_up->execute([$user_ap]);
} else {
    $stmt_up = $conn->prepare("SELECT * FROM master_up WHERE UnitUpi = ? ORDER BY NamaUnit ASC");
    $stmt_up->execute([$user_upi]);
}
$list_up = $stmt_up->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-truck me-2"></i>Buat Formulir Pengiriman Baru</h6>
    </div>
    <div class="card-body">
        <form action="modules/pengadaan/proses_pengiriman.php?action=create_draft" method="POST">
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Tanggal Pengiriman</label>
                    <input type="date" class="form-control" name="tgl_form" value="<?= $tgl_hari_ini ?>" readonly>
                </div>
            </div>

            <div class="row mb-3">
                <!-- Dropdown Unit AP Tujuan -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tujuan Unit Area Pelayanan (AP)</label>
                    <select class="form-select" name="kode_ap" id="select_ap" <?= $is_ap ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Unit AP (Opsional) --</option>
                        <?php foreach ($list_ap as $ap): ?>
                            <option value="<?= $ap['UnitAp'] ?>"><?= htmlspecialchars($ap['NamaUnit']) ?> (Kode: <?= $ap['UnitAp'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_ap): ?>
                        <input type="hidden" name="kode_ap" value="">
                        <small class="text-muted">Unit AP dikunci karena Anda login sebagai Asman AP.</small>
                    <?php endif; ?>
                </div>

                <!-- Dropdown Unit UP Tujuan -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tujuan Unit Pelayanan (UP)</label>
                    <select class="form-select" name="kode_up" id="select_up">
                        <option value="">-- Pilih Unit UP (Opsional) --</option>
                        <?php foreach ($list_up as $up): ?>
                            <option value="<?= $up['UnitUp'] ?>"><?= htmlspecialchars($up['NamaUnit']) ?> (Kode: <?= $up['UnitUp'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-text text-muted mb-3">*Catatan: Anda dapat memilih salah satu tujuan (AP atau UP). Jika salah satu dipilih, pilihan lainnya akan otomatis terkunci.</div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="index.php?page=pengadaan&menu=pengiriman&view=daftar" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan & Buat Draft</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAp = document.getElementById('select_ap');
    const selectUp = document.getElementById('select_up');

    function handleExclusiveSelection() {
        if (selectAp && selectAp.value !== "") {
            if(selectUp) { selectUp.value = ""; selectUp.disabled = true; }
        } else {
            if(selectUp) { selectUp.disabled = false; }
        }

        if (selectUp && selectUp.value !== "") {
            if(selectAp && !selectAp.disabled) { selectAp.value = ""; selectAp.disabled = true; }
        } else {
            if(selectAp && !selectAp.disabled) { selectAp.disabled = false; }
        }
    }

    if(selectAp) selectAp.addEventListener('change', handleExclusiveSelection);
    if(selectUp) selectUp.addEventListener('change', handleExclusiveSelection);
});
</script>