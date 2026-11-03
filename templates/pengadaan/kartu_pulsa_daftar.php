<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-wallet2 me-1"></i>Daftar Saldo, Pulsa & Masa Aktif Kartu S41</h6>
    </div>
    <div class="card-body p-0">
        
        <!-- Baris Filter Pencarian & Limit Paging -->
        <div class="d-flex justify-content-between align-items-center flex-wrap px-4 pt-3 pb-2 gap-2">
            <!-- Form Smart Search Kartu -->
            <form action="index.php" method="GET" class="input-group input-group-sm" style="max-width: 320px;">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="kartu">
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
                <input type="hidden" name="view" value="daftar">
                <input type="hidden" name="limit" value="<?= htmlspecialchars($data['paging']['limit'] ?? 10) ?>">

                <input type="text" class="form-control" name="q" placeholder="Cari SIM ID / No Kartu..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                <?php if (!empty($_GET['q'])): ?>
                    <a href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <!-- Dropdown Pilihan Jumlah Data Per Halaman -->
            <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="kartu">
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
                <input type="hidden" name="view" value="daftar">
                <?php if (!empty($_GET['q'])): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q']) ?>">
                <?php endif; ?>
                
                <label class="small text-muted fw-bold mb-0">Tampilkan:</label>
                <select name="limit" class="form-select form-select-sm" style="width: 75px;" onchange="this.form.submit()">
                    <option value="5" <?= (($data['paging']['limit'] ?? 10) == 5) ? 'selected' : '' ?>>5</option>
                    <option value="10" <?= (($data['paging']['limit'] ?? 10) == 10) ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= (($data['paging']['limit'] ?? 10) == 20) ? 'selected' : '' ?>>20</option>
                    <option value="30" <?= (($data['paging']['limit'] ?? 10) == 30) ? 'selected' : '' ?>>30</option>
                </select>
                <span class="small text-muted">data</span>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4 py-3">SIM ID (ICCID)</th>
                        <th>NOMOR KARTU</th>
                        <th>SALDO (Rp)</th>
                        <th>TGL ISI TERAKHIR</th>
                        <th>MASA AKTIF (ESTIMASI)</th>
                        <th class="pe-4 text-center">STATUS KARTU</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['list_pulsa'])): foreach ($data['list_pulsa'] as $row): 
                        $sisa_hari = $row['SisaHari'] ?? 0;
                        $jumlah_kredit = $row['JumlahKredit'] ?? 0;
                    ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                            <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                            <td class="fw-bold <?= $jumlah_kredit > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= $jumlah_kredit > 0 ? 'Rp ' . number_format($jumlah_kredit, 0, ',', '.') : 'Belum Pernah Diisi' ?>
                            </td>
                            <td><?= htmlspecialchars($row['TglPulsaTerakhir'] ?? '-') ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['MasaAktif'] ?? '-') ?></td>
                            <td class="text-center pe-4">
                                <?php if (empty($row['TglPulsaTerakhir'])): ?>
                                    <span class="badge bg-secondary px-3 py-2"><i class="bi bi-dash-circle me-1"></i> Belum Ada Data</span>
                                <?php elseif ($sisa_hari < 0): ?>
                                    <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i> Expired (Mati)</span>
                                <?php elseif ($sisa_hari <= 7): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i> Hampir Habis (<?= $sisa_hari ?> hr)</span>
                                <?php else: ?>
                                    <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Aktif (<?= $sisa_hari ?> hr lagi)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet fs-1 d-block mb-2 text-secondary"></i>Belum ada data kartu di unit ini.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER: INFORMASI TOTAL & NAVIGASI PAGINASI -->
        <?php if (!empty($data['paging']) && $data['paging']['total_pages'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap p-3 border-top bg-light">
            <div class="small text-muted mb-2 mb-md-0">
                Menampilkan halaman <strong><?= $data['paging']['current_page'] ?></strong> dari <strong><?= $data['paging']['total_pages'] ?></strong> (Total <strong><?= $data['paging']['total_data'] ?></strong> data)
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($data['paging']['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar&limit=<?= $data['paging']['limit'] ?>&p=<?= $data['paging']['current_page'] - 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                            <i class="bi bi-chevron-left"></i> Prev
                        </a>
                    </li>
                    <?php 
                    $current = $data['paging']['current_page'];
                    $total_p = $data['paging']['total_pages'];
                    $start = max(1, $current - 2);
                    $end = min($total_p, $current + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $current) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar&limit=<?= $data['paging']['limit'] ?>&p=<?= $i ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($data['paging']['current_page'] >= $data['paging']['total_pages']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar&limit=<?= $data['paging']['limit'] ?>&p=<?= $data['paging']['current_page'] + 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- DEPENDENCY SELECT2 UNTUK SMART SEARCH PADA MODAL ISI PULSA -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- MODAL POPUP FORM ISI PULSA DENGAN SMART SEARCH -->
<div class="modal fade" id="modalIsiPulsa" tabindex="-1" aria-labelledby="modalIsiPulsaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="modules/pengadaan/proses_kartu.php?action=pulsa" method="POST">
          <input type="hidden" name="metode" value="manual">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="modalIsiPulsaLabel"><i class="bi bi-wallet2 me-2"></i>Form Isi Pulsa Kartu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">Pilih / Cari SIM ID Tujuan</label>
                  <select class="form-select select2-simid" name="sim_id" id="modal_sim_id_select" required style="width: 100%;">
                      <option value="">-- Ketik atau Pilih SimId / No Akun --</option>
                      <?php if (!empty($data['all_kartu'])): foreach ($data['all_kartu'] as $kartu): ?>
                          <option value="<?= htmlspecialchars($kartu['SimId']) ?>">
                              SimId: <?= htmlspecialchars($kartu['SimId']) ?> (No: +<?= htmlspecialchars($kartu['NomorAkun']) ?>)
                          </option>
                      <?php endforeach; endif; ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">Nominal Pengisian (Rp)</label>
                  <input type="number" class="form-control" name="nominal" placeholder="Contoh: 50000 / 100000" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">Tanggal Isi Pulsa</label>
                  <input type="date" class="form-control" name="tgl_isi" value="<?= date('Y-m-d') ?>" required>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success btn-sm px-4"><i class="bi bi-check-circle me-1"></i>Simpan Saldo</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modalEl = document.getElementById('modalIsiPulsa');
    if (modalEl) {
        $(modalEl).on('shown.bs.modal', function () {
            $('.select2-simid').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalIsiPulsa'),
                placeholder: "-- Ketik atau Pilih SimId / No Akun --",
                allowClear: true,
                width: '100%'
            });
        });
    }
});
</script>