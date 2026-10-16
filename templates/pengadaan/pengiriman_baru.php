<?php
// Ambil unit UPI user yang sedang login (misal: '56')
$user_upi = $_SESSION['UnitUpi'] ?? '56';
$tgl_hari_ini = date('Y-m-d');
$tgl_format = date('Ymd');

// Generate nomor formulir otomatis langsung di halaman ini agar pasti terkirim
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_pengiriman WHERE TglFormulir = ?");
$stmt_count->execute([$tgl_hari_ini]);
$jumlah_hari_ini = (int) $stmt_count->fetchColumn();
$urutan_baru = $jumlah_hari_ini + 1;
$urutan_str = str_pad($urutan_baru, 3, '0', STR_PAD_LEFT);
$no_formulir_otomatis = $user_upi . $urutan_str . $tgl_format . '-F.B';

// Ambil daftar Unit AP yang berada di bawah UPI tersebut
$stmt_ap = $conn->prepare("SELECT * FROM master_ap WHERE UnitUpi = ? ORDER BY NamaUnit ASC");
$stmt_ap->execute([$user_upi]);
$list_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar Unit UP yang berada di bawah UPI tersebut
$stmt_up = $conn->prepare("SELECT * FROM master_up WHERE UnitUpi = ? ORDER BY NamaUnit ASC");
$stmt_up->execute([$user_upi]);
$list_up = $stmt_up->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-truck me-2"></i>Buat Formulir Pengiriman Baru</h6>
    </div>
    <div class="card-body">
        <form action="modules/pengadaan/proses_pengiriman.php?action=create_draft" method="POST">
            <!-- KIRIM NoFormulir SECARA EKSPLISIT KE BACKEND -->
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tanggal Pengiriman</label>
                    <input type="date" class="form-control" name="tgl_form" value="<?= $tgl_hari_ini ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pilih Jenis Tujuan Pengiriman</label>
                    <select class="form-select" id="jenis_tujuan" onchange="toggleTujuan(this.value)" required>
                        <option value="">-- Pilih Level Tujuan --</option>
                        <option value="AP">Area Pelayanan (AP)</option>
                        <option value="UP">Unit Pelayanan (UP)</option>
                    </select>
                </div>
            </div>

            <!-- Dropdown Unit AP (Hanya tampil jika memilih AP) -->
            <div class="row mb-3" id="wrapper_ap" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Pilih Unit Area Pelayanan (AP Tujuan)</label>
                    <select class="form-select" name="kode_ap" id="select_ap">
                        <option value="">-- Pilih Unit AP --</option>
                        <?php foreach ($list_ap as $ap): ?>
                            <option value="<?= $ap['UnitAp'] ?>"><?= htmlspecialchars($ap['NamaUnit']) ?> (Kode: <?= $ap['UnitAp'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Dropdown Unit UP (Hanya tampil jika memilih UP) -->
            <div class="row mb-3" id="wrapper_up" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Pilih Unit Pelayanan (UP Tujuan)</label>
                    <select class="form-select" name="kode_up" id="select_up">
                        <option value="">-- Pilih Unit UP --</option>
                        <?php foreach ($list_up as $up): ?>
                            <option value="<?= $up['UnitUp'] ?>"><?= htmlspecialchars($up['NamaUnit']) ?> (Kode: <?= $up['UnitUp'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="index.php?page=pengadaan&menu=pengiriman&view=daftar" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan & Buat Draft</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTujuan(nilai) {
    const wrapAp = document.getElementById('wrapper_ap');
    const wrapUp = document.getElementById('wrapper_up');
    const selectAp = document.getElementById('select_ap');
    const selectUp = document.getElementById('select_up');

    if (nilai === 'AP') {
        wrapAp.style.display = 'block';
        wrapUp.style.display = 'none';
        selectAp.disabled = false;
        selectAp.required = true;
        selectUp.disabled = true;  // Matikan input UP agar tidak ikut terkirim
        selectUp.required = false;
        selectUp.value = '';
    } else if (nilai === 'UP') {
        wrapAp.style.display = 'none';
        wrapUp.style.display = 'block';
        selectAp.disabled = true;   // Matikan input AP agar tidak ikut terkirim
        selectAp.required = false;
        selectAp.value = '';
        selectUp.disabled = false;
        selectUp.required = true;
    } else {
        wrapAp.style.display = 'none';
        wrapUp.style.display = 'none';
        selectAp.disabled = false;
        selectAp.required = false;
        selectUp.disabled = false;
        selectUp.required = false;
    }
}
</script>