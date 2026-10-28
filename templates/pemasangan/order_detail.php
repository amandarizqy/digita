<?php
$is_draft = ($formulir['StatusData'] === 'TIDAK');
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    #page-loader {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(255, 255, 255, 0.9);
        z-index: 9999; display: flex; flex-direction: column;
        justify-content: center; align-items: center;
    }
    .spinner-border { width: 3rem; height: 3rem; border-width: 0.25em; }
</style>

<div id="page-loader">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <h5 class="text-dark fw-bold">Memproses...</h5>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-primary mb-0"><i class="bi bi-card-checklist me-2"></i> Surat Perintah Kerja (Order Pasang)</h5>
            <p class="text-muted small mb-0 mt-1">Kumpulkan Formulir Perintah Kerja (FPK) ke dalam satu SPK.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border p-2 d-block mb-1">NO SPK: <span class="fw-bold text-primary"><?= htmlspecialchars($formulir['NoFormOrder']) ?></span></span>
            <?php if($is_draft): ?> <span class="badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i> DRAFT</span> <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-4 bg-light">
        <!-- FORM TAMBAH FPK KE DALAM SPK -->
        <?php if($is_draft): ?>
        <form action="modules/pemasangan/proses_order.php?action=add_item" method="POST" class="row g-3 align-items-end mb-4 bg-white p-3 rounded shadow-sm border" id="formTambahTarget">
            <input type="hidden" name="no_form_order" value="<?= htmlspecialchars($formulir['NoFormOrder']) ?>">
            
            <div class="col-md-9">
                <label class="form-label fw-bold small text-secondary">Masukkan FPK ke dalam SPK ini *</label>
                <select name="no_fpk" class="select2 form-control" required>
                    <option value="">-- Cari No FPK atau IdPel --</option>
                    <?php foreach($list_fpk_bebas as $fpk): ?> 
                        <option value="<?= htmlspecialchars($fpk['NoFpk']) ?>">
                            FPK: <?= htmlspecialchars($fpk['NoFpk']) ?> | IdPel: <?= htmlspecialchars($fpk['IdPel']) ?> | Alat: <?= htmlspecialchars($fpk['NoRef']) ?>
                        </option> 
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100 fw-bold" onclick="$('#page-loader').fadeIn();"><i class="bi bi-plus-circle me-2"></i>Tambahkan</button>
            </div>
        </form>
        <?php endif; ?>

        <!-- DAFTAR FPK DALAM SPK INI -->
        <h6 class="fw-bold text-dark mb-3">Daftar FPK dalam SPK Ini (<?= count($items) ?> FPK)</h6>
        <div class="table-responsive bg-white rounded border">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">NO</th>
                        <th class="py-3">NO FPK</th>
                        <th class="py-3">ID PELANGGAN</th>
                        <th class="py-3">ALAT (NO REF)</th>
                        <?php if($is_draft): ?><th class="text-center py-3">AKSI</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada FPK yang ditambahkan ke SPK ini.</td></tr>
                    <?php else: ?>
                        <?php foreach($items as $idx => $it): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-success"><?= htmlspecialchars($it['NoFpk']) ?></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($it['IdPel']) ?></td>
                            <td class="fw-bold text-dark"><i class="bi bi-hdd-network me-1"></i> <?= htmlspecialchars($it['NoRef']) ?></td>
                            <?php if($is_draft): ?>
                            <td class="text-center">
                                <a href="modules/pemasangan/proses_order.php?action=delete_item&no_form=<?= urlencode($formulir['NoFormOrder']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" 
                                   class="btn btn-sm btn-outline-danger" onclick="if(confirm('Keluarkan FPK ini dari SPK?')) { $('#page-loader').fadeIn(); return true; } return false;">
                                   <i class="bi bi-trash"></i>
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer bg-white p-4 d-flex justify-content-between">
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar" class="btn btn-light border text-secondary px-4 fw-bold" onclick="$('#page-loader').fadeIn();"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        
        <div class="d-flex gap-2">
            <?php if($is_draft): ?>
                <a href="modules/pemasangan/proses_order.php?action=delete_draft&no_form=<?= urlencode($formulir['NoFormOrder']) ?>" class="btn btn-outline-danger px-4 fw-bold" onclick="return confirm('Hapus Draft SPK ini secara permanen?')"><i class="bi bi-trash me-2"></i> Hapus Draft</a>
            <?php endif; ?>

            <?php if($is_draft && count($items) > 0): ?>
                <form action="modules/pemasangan/proses_order.php?action=execute_form" method="POST" class="m-0" onsubmit="$('#page-loader').fadeIn();">
                    <input type="hidden" name="no_form_order" value="<?= htmlspecialchars($formulir['NoFormOrder']) ?>">
                    <button type="submit" class="btn btn-primary px-4 fw-bold" onclick="return confirm('Finalisasi SPK ini? FPK di dalamnya akan diteruskan ke Petugas Lapangan.')"><i class="bi bi-send-check-fill me-2"></i> Terbitkan SPK</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(window).on('load', function() {
    $('.select2').select2({ width: '100%' });
    $('#page-loader').fadeOut(300);
});
</script>