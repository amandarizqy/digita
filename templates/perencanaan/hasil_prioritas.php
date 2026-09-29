<!-- templates/perencanaan/hasil_prioritas.php -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h4 class="mb-0">
        <i class="bi bi-list-ol text-primary me-2"></i> Hasil Skala Prioritas Khusus
        <span class="badge bg-dark ms-2 align-middle"><i class="bi bi-calendar3 me-1"></i>Periode <?= htmlspecialchars($periode_filter) ?></span>
    </h4>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
        <a href="?module=perencanaan&action=proses_prioritas<?= $skala_filter > 0 ? '&skala=' . $skala_filter : '' ?>" class="btn btn-primary"><i class="bi bi-lightning-charge-fill"></i> Proses Pemeringkatan</a>
        <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_error ?></div>
<?php endif; ?>
<?php if ($tabel_belum_ada): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-database-exclamation me-2"></i>
        Tabel <code>hasil_prioritas</code> belum dibuat. Jalankan file <code>hasil_prioritas.sql</code> sekali di database, lalu proses pemeringkatan lewat modul Skala Prioritas Khusus.
    </div>
<?php endif; ?>

<!-- Ringkasan per skala (klik untuk memfilter) -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3 col-xl">
        <a href="<?= $buat_url(['skala' => 0, 'page' => 1]) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?= $skala_filter === 0 ? 'border border-2 border-primary' : '' ?>">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted text-uppercase fw-semibold">Semua Skala</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($total_hasil_semua) ?></div>
                    <small class="text-muted">pelanggan diperingkat</small>
                </div>
            </div>
        </a>
    </div>
    <?php for ($n = 9; $n >= 1; $n--):
        $rg    = $ringkasan[$n];
        $warna = warnaSkalaPrioritas($n);
    ?>
        <div class="col-6 col-md-3 col-xl">
            <a href="<?= $buat_url(['skala' => $n, 'page' => 1]) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-<?= $warna ?> <?= $skala_filter === $n ? 'bg-' . $warna . ' bg-opacity-10 border border-2 border-' . $warna : '' ?>">
                    <div class="card-body py-2 text-center">
                        <div class="small text-uppercase fw-semibold text-<?= $warna === 'warning' ? 'warning-emphasis' : $warna ?>">Prioritas <?= $n ?></div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($rg['jumlah_hasil']) ?></div>
                        <?php if ($rg['jumlah_hasil'] === 0): ?>
                            <small class="text-muted"><?= $rg['jumlah_kategori'] > 0 ? '⏳ belum diperingkat' : '— kosong' ?></small>
                        <?php elseif ($rg['usang']): ?>
                            <small class="text-danger fw-semibold">⚠️ perlu update</small>
                        <?php else: ?>
                            <small class="text-muted">✅ terbaru</small>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endfor; ?>
</div>

<!-- Info skala terpilih -->
<?php if ($skala_filter > 0 && $ringkasan[$skala_filter]['jumlah_hasil'] > 0):
    $rg = $ringkasan[$skala_filter];
?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <h5 class="fw-bold mb-1">
                        <span class="badge bg-<?= warnaSkalaPrioritas($skala_filter) ?> <?= warnaSkalaPrioritas($skala_filter) === 'warning' ? 'text-dark' : '' ?>">Prioritas <?= $skala_filter ?></span>
                        <span class="ms-2 fs-6 text-muted"><?= htmlspecialchars(labelSkalaPrioritas($skala_filter)) ?></span>
                    </h5>
                    <small class="text-muted">
                        Metode: <strong><?= htmlspecialchars(labelMetodePeringkat($rg['metode'])) ?></strong>
                        <?php if ($rg['metode'] === 'gabungan'): ?>
                            (bobot: nominal <?= $rg['bobot'][0] ?>% · telat <?= $rg['bobot'][1] ?>% · tunggakan <?= $rg['bobot'][2] ?>%)
                        <?php endif; ?>
                        · Diproses <?= htmlspecialchars((string) $rg['terakhir']) ?><?= !empty($rg['oleh']) ? ' oleh ' . htmlspecialchars($rg['oleh']) : '' ?>
                    </small>
                </div>
                <div class="d-flex gap-4 text-center">
                    <div><div class="small text-muted">Pelanggan</div><div class="fw-bold fs-5"><?= number_format($rg['jumlah_hasil']) ?></div></div>
                    <div><div class="small text-muted">Total Tagihan</div><div class="fw-bold fs-5">Rp <?= number_format($rg['total_tagihan'], 0, ',', '.') ?></div></div>
                    <div><div class="small text-muted">Rata-rata Skor</div><div class="fw-bold fs-5"><?= number_format($rg['avg_skor'], 2, ',', '.') ?></div></div>
                </div>
            </div>
            <?php if ($rg['usang']): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Jumlah pelanggan pada skala ini di Proses Risiko (<?= number_format($rg['jumlah_kategori']) ?>) berbeda dengan hasil peringkat (<?= number_format($rg['jumlah_hasil']) ?>).
                    <a href="?module=perencanaan&action=proses_prioritas&skala=<?= $skala_filter ?>" class="alert-link">Proses ulang skala ini</a> agar peringkat sinkron.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Filter -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-center">
            <input type="hidden" name="module" value="perencanaan">
            <input type="hidden" name="action" value="hasil_prioritas">
            <div class="col-md-3">
                <select name="skala" class="form-select" onchange="this.form.submit()">
                    <option value="0" <?= $skala_filter === 0 ? 'selected' : '' ?>>-- Semua Skala Prioritas --</option>
                    <?php for ($n = 9; $n >= 1; $n--): ?>
                        <option value="<?= $n ?>" <?= $skala_filter === $n ? 'selected' : '' ?>>Prioritas <?= $n ?> (<?= number_format($ringkasan[$n]['jumlah_hasil']) ?>)</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword) ?>" placeholder="Cari IDPel / Nama Pelanggan...">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Cari</button>
                <a href="?module=perencanaan&action=hasil_prioritas" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel hasil -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-table me-1"></i> Daftar Peringkat</h6>
        <small class="text-muted"><?= number_format($total_rows) ?> data · halaman <?= $page ?> dari <?= $total_pages ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:90px;">Peringkat</th>
                        <?php if ($skala_filter === 0): ?><th class="text-center">Prioritas</th><?php endif; ?>
                        <th>IdPel</th>
                        <th>Nama Pelanggan</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-center">Telat</th>
                        <th class="text-center">Lewat Bulan</th>
                        <th class="text-center" style="min-width:150px;">Skor</th>
                        <th class="text-center">Kepentingan</th>
                        <th class="text-center">Posisi SR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_hasil)): foreach ($list_hasil as $r):
                        $rank  = (int) $r['Peringkat'];
                        $skor  = (float) $r['SkorPrioritas'];
                        $badge = $rank === 1 ? 'bg-warning text-dark' : ($rank <= 3 ? 'bg-secondary' : 'bg-dark');
                    ?>
                        <tr>
                            <td class="text-center"><span class="badge <?= $badge ?> fs-6">#<?= $rank ?></span></td>
                            <?php if ($skala_filter === 0): ?>
                                <td class="text-center">
                                    <span class="badge bg-<?= warnaSkalaPrioritas($r['SkalaPrioritas']) ?> <?= warnaSkalaPrioritas($r['SkalaPrioritas']) === 'warning' ? 'text-dark' : '' ?>">P<?= (int) $r['SkalaPrioritas'] ?></span>
                                </td>
                            <?php endif; ?>
                            <td><code><?= htmlspecialchars($r['IdPel']) ?></code></td>
                            <td><strong><?= htmlspecialchars($r['NamaPelanggan'] ?? '-') ?></strong></td>
                            <td class="text-end">Rp <?= number_format((float) $r['TotalTagihan'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= (int) $r['JumlahTelat'] ?></td>
                            <td class="text-center"><?= (int) $r['JumlahLewatBulan'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar bg-<?= warnaSkalaPrioritas($r['SkalaPrioritas']) ?>" style="width: <?= max(0, min(100, $skor)) ?>%"></div>
                                    </div>
                                    <span class="small fw-bold"><?= number_format($skor, 2, ',', '.') ?></span>
                                </div>
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['LevelKepentingan'] ?? '-') ?></span></td>
                            <td class="text-center">
                                <?php if ($r['PosisiSR'] === null || $r['PosisiSR'] === ''): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border"><?= $r['PosisiSR'] == '1' ? 'Tergantung' : 'Mandiri' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="<?= $skala_filter === 0 ? 10 : 9 ?>" class="text-center text-muted py-5">
                                <?php if ($tabel_belum_ada || $total_hasil_semua === 0): ?>
                                    Belum ada hasil pemeringkatan. Proses dulu lewat modul <a href="?module=perencanaan&action=proses_prioritas">Skala Prioritas Khusus</a>.
                                <?php else: ?>
                                    Data tidak ditemukan untuk filter ini.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1):
        $awal  = max(1, $page - 2);
        $akhir = min($total_pages, $page + 2);
    ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $buat_url(['page' => $page - 1]) ?>">&laquo;</a></li>
                    <?php if ($awal > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= $buat_url(['page' => 1]) ?>">1</a></li>
                        <?php if ($awal > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $awal; $i <= $akhir; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $buat_url(['page' => $i]) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($akhir < $total_pages): ?>
                        <?php if ($akhir < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= $buat_url(['page' => $total_pages]) ?>"><?= $total_pages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $buat_url(['page' => $page + 1]) ?>">&raquo;</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>