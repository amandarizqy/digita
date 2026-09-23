<!-- templates/perencanaan/upload_riwayat.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-upload"></i> Kelola Riwayat Pelunasan</h4>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Menampilkan Pesan Notifikasi -->
<?php if (!empty($pesan_sukses)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $pesan_sukses ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
    <!-- Kolom Form Input -->
    <div >
        <div class="card shadow-sm border-primary border-top border-3">
            <div class="card-header bg-white fw-bold">Input Data Baru</div>
            <div class="card-body">
                <form action="?module=perencanaan&action=upload_riwayat" method="POST">
                    <div class="mb-3">
                        <label class="form-label">ID Pelanggan (IdPel)</label>
                        <input type="text" name="IdPel" class="form-control" maxlength="12" required placeholder="Contoh: 517123456789">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ThBlRek</label>
                        <input type="text" name="ThBlRek" class="form-control" maxlength="6" placeholder="Contoh: 202609">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Bayar</label>
                        <input type="date" name="TglBayar" class="form-control">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">RpBK</label>
                            <input type="number" name="RpBK" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">RpTag</label>
                            <input type="number" name="RpTag" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="simpan_data" class="btn btn-primary w-100">Simpan Data</button>
                </form>
            </div>
        </div>
    </div>
