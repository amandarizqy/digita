<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <form action="index.php" method="GET" class="mb-3 px-4 pt-3">
            <input type="hidden" name="page" value="pemasangan">
            <input type="hidden" name="menu" value="eksekusi">
            <input type="hidden" name="sub" value="mutasi_pasang">
            <input type="hidden" name="view" value="daftar">
            <div class="input-group input-group-sm" style="max-width: 340px;">
                <input type="text" class="form-control" name="q" placeholder="Cari No Order SPK..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORM ORDER (SPK)</th>
                        <th class="fw-bold border-bottom-0 py-3">TGL ORDER</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">TOTAL TARGET</th>
                        <th class="fw-bold border-bottom-0 py-3">DITERBITKAN OLEH</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI EKSEKUSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_mutasi)): ?>
                        <?php foreach ($list_mutasi as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormOrder']) ?></td>
                                <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                                <td class="text-center fw-bold"><?= $row['TotalTarget'] ?> Pelanggan</td>
                                <td><i class="bi bi-person-badge me-1 text-muted"></i><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="text-center pe-4">
                                    <?php if ($row['StatusData'] === 'SELESAI'): ?>
                                        <span class="badge bg-success px-3 py-2"><i class="bi bi-check-all me-1"></i> Selesai / Arsip</span>
                                        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-success fw-bold px-3">
                                            <i class="bi bi-tools me-1"></i> Eksekusi Lapangan
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada Surat Perintah Kerja (SPK) untuk dieksekusi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>