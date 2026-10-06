<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-list-ul me-1"></i>Daftar Aktivasi Perdana</h6>
    </div>
    <div class="card-body p-0">
        <!-- Form Smart Search Kartu -->
        <form action="index.php" method="GET" class="mb-3 px-4 pt-3">
            <input type="hidden" name="page" value="pengadaan">
            <input type="hidden" name="menu" value="kartu">
            <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
            <input type="hidden" name="view" value="daftar">

            <div class="input-group input-group-sm" style="max-width: 320px;">
                <input type="text" class="form-control" name="q" placeholder="Cari SIM ID / No Kartu..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                <?php if (!empty($_GET['q'])): ?>
                    <a href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4 py-3">SIM ID (ICCID)</th>
                        <th>NOMOR KARTU</th>
                        <th>PROVIDER / PRODUK</th>
                        <th>JENIS</th>
                        <th class="pe-4">TGL AKTIVASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['list_kartu'])): foreach ($data['list_kartu'] as $row): ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                            <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                            <td><?= htmlspecialchars($row['NamaProvider']) ?> - <?= htmlspecialchars($row['NamaProduk']) ?></td>
                            <td><span class="badge bg-secondary px-2 py-1"><?= htmlspecialchars($row['JenisProduk']) ?></span></td>
                            <td class="pe-4"><?= htmlspecialchars($row['TglAktifasi']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-sim fs-1 d-block mb-2 text-secondary"></i>Belum ada data aktivasi kartu perdana.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>