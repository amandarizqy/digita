<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <form action="index.php" method="GET" class="mb-3 px-4 pt-3">
            <input type="hidden" name="page" value="pemasangan">
            <input type="hidden" name="menu" value="pra_eksekusi">
            <input type="hidden" name="sub" value="pengujian">
            <input type="hidden" name="view" value="daftar">
            <div class="input-group input-group-sm" style="max-width: 340px;">
                <input type="text" class="form-control" name="q" placeholder="Cari No Formulir..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORMULIR</th>
                        <th class="fw-bold border-bottom-0 py-3">TGL UJI</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">TOTAL ALAT</th>
                        <th class="fw-bold border-bottom-0 py-3">PENGUJI</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_pengujian)): ?>
                        <?php foreach ($list_pengujian as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglUji']) ?></td>
                                <td class="text-center fw-bold"><?= $row['TotalAlat'] ?> Unit</td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="text-center">
                                    <?php if($row['StatusData'] == 'AKTIF'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2"><i class="bi bi-check-circle me-1"></i> Selesai</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2"><i class="bi bi-pencil-square me-1"></i> Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary" title="Buka Detail">
                                        <i class="bi <?= ($row['StatusData'] == 'AKTIF') ? 'bi-eye' : 'bi-pencil' ?>"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>