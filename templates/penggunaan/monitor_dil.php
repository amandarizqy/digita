<?php
// templates/penggunaan/monitor_dil.php  -  Monitoring DIL
// Variabel dari modules/penggunaan/monitor_dil.php:
//   $data, $total_data, $kpi, $keyword, $status, $limit, $hal, $csrf, $boleh_edit, $pesan_sukses, $pesan_error

$url_dil = fn(array $ubah = []) => pg_url('monitor_dil', array_merge(
    ['keyword' => $keyword, 'status' => $status, 'limit' => $limit == 10 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring DIL',
    'subtitle' => 'Daftar Induk Langganan: cari pelanggan' . ($boleh_edit ? ', ubah nama, serta aktifkan atau nonaktifkan.' : '.'),
    'action'   => 'monitor_dil',
    'buttons'  => $boleh_edit ? [['label' => 'Input DIL', 'icon' => 'bi-plus-lg', 'href' => pg_url('input_dil')]] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);

// kolom tersembunyi agar filter & halaman ikut terbawa saat POST
$tetap = '<input type="hidden" name="csrf" value="' . pg_e($csrf) . '">'
       . '<input type="hidden" name="keyword" value="' . pg_e($keyword) . '">'
       . '<input type="hidden" name="filter_status" value="' . pg_e($status) . '">'
       . '<input type="hidden" name="limit" value="' . (int) $limit . '">'
       . '<input type="hidden" name="hal" value="' . (int) $hal . '">';
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Pelanggan', $kpi['total'], 'text-dark', ''],
        ['Aktif', $kpi['AKTIF'], 'text-success', 'AKTIF'],
        ['Nonaktif', $kpi['TIDAK'], 'text-secondary', 'TIDAK'],
        ['Nama Belum Diisi', $kpi['KOSONG'], 'text-warning', 'KOSONG'],
    ] as [$lbl, $val, $cls, $filter]): ?>
    <div class="col-md-6 col-xl-3">
        <a href="<?= pg_e($url_dil(['status' => $filter, 'hal' => ''])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
                <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
                <h3 class="fw-bold mt-1 mb-0 <?= $cls ?>"><?= pg_n($val) ?></h3>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- DAFTAR -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_dil') ?>
            <div class="col-md-5">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel atau nama pelanggan...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua data</option>
                    <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                    <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="KOSONG" <?= $status === 'KOSONG' ? 'selected' : '' ?>>Nama belum diisi</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $status !== ''): ?>
                <a href="<?= pg_e(pg_url('monitor_dil')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">IdPel</th><th>Nama Pelanggan</th><th class="text-center">Index Prioritas</th>
                    <th class="text-center">Status</th><th>Waktu Data</th>
                    <?php if ($boleh_edit): ?><th class="text-end pe-4">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="<?= $boleh_edit ? 6 : 5 ?>" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $status !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data DIL.' ?>
                </td></tr>
            <?php else: foreach ($data as $r):
                $aktif = $r['StatusData'] === 'AKTIF';
                $nama_kosong = trim((string) $r['NamaPelanggan']) === '';
            ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td>
                        <?php if ($nama_kosong): ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50"><i class="bi bi-exclamation-triangle me-1"></i>Belum diisi</span>
                        <?php else: ?><?= pg_e($r['NamaPelanggan']) ?><?php endif; ?>
                    </td>
                    <td class="text-center"><?= $r['IndexPrioritas'] === null ? '<span class="text-muted">-</span>' : pg_e($r['IndexPrioritas']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50 px-2 py-1">
                            <?= $aktif ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= pg_e($r['WaktuData']) ?></td>
                    <?php if ($boleh_edit): ?>
                    <td class="text-end pe-4 text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-dil"
                                data-idpel="<?= pg_e($r['IdPel']) ?>" data-nama="<?= pg_e($r['NamaPelanggan']) ?>" data-status="<?= pg_e($r['StatusData']) ?>"
                                data-bs-toggle="modal" data-bs-target="#modalEditDil">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
                        <form method="POST" action="<?= pg_e(pg_url('monitor_dil')) ?>" class="d-inline"
                              onsubmit="return confirm('<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> IdPel <?= pg_e($r['IdPel']) ?>?');">
                            <?= $tetap ?>
                            <input type="hidden" name="aksi" value="status">
                            <input type="hidden" name="idpel" value="<?= pg_e($r['IdPel']) ?>">
                            <input type="hidden" name="status_baru" value="<?= $aktif ? 'TIDAK' : 'AKTIF' ?>">
                            <button type="submit" class="btn btn-sm <?= $aktif ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                <i class="bi <?= $aktif ? 'bi-slash-circle' : 'bi-check-circle' ?> me-1"></i><?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_dil(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<?php if ($boleh_edit): ?>
<!-- MODAL EDIT -->
<div class="modal fade" id="modalEditDil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('monitor_dil')) ?>" class="modal-content border-0 shadow rounded-4">
            <?= $tetap ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="idpel" id="edit_idpel_val">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                    <input type="text" class="form-control font-monospace bg-light" id="edit_idpel" disabled>
                    <div class="form-text">IdPel adalah kunci data dan tidak dapat diubah.</div>
                </div>
                <div class="mb-3">
                    <label for="edit_nama" class="form-label small fw-bold text-secondary text-uppercase">Nama Pelanggan</label>
                    <input type="text" class="form-control" id="edit_nama" name="nama" maxlength="50" required>
                </div>
                <div>
                    <label for="edit_status" class="form-label small fw-bold text-secondary text-uppercase">Status</label>
                    <select class="form-select" id="edit_status" name="status_baru">
                        <option value="AKTIF">AKTIF</option>
                        <option value="TIDAK">NONAKTIF</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.btn-edit-dil').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('edit_idpel').value     = b.dataset.idpel;
        document.getElementById('edit_idpel_val').value = b.dataset.idpel;
        document.getElementById('edit_nama').value      = b.dataset.nama || '';
        document.getElementById('edit_status').value    = b.dataset.status;
    });
});
</script>
<?php endif; ?>