<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-list-ul me-1"></i>Daftar Aktivasi Perdana</h6>
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
                        <th>PROVIDER / PRODUK</th>
                        <th>JENIS</th>
                        <th>TGL AKTIVASI</th>
                        <th class="pe-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['list_kartu'])): foreach ($data['list_kartu'] as $row): ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                            <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                            <td><?= htmlspecialchars($row['NamaProvider']) ?> - <?= htmlspecialchars($row['NamaProduk']) ?></td>
                            <td><span class="badge bg-secondary px-2 py-1"><?= htmlspecialchars($row['JenisProduk']) ?></span></td>
                            <td><?= htmlspecialchars($row['TglAktifasi']) ?></td>
                            <td class="pe-4 text-center">
                                <button type="button" class="btn btn-sm btn-outline-success px-2 py-1 btn-isi-pulsa" 
                                        data-simid="<?= htmlspecialchars($row['SimId']) ?>"
                                        data-nomor="<?= htmlspecialchars($row['NomorAkun']) ?>"
                                        title="Isi Pulsa Cepat">
                                    <i class="bi bi-wallet2"></i> Isi Pulsa
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-sim fs-1 d-block mb-2 text-secondary"></i>Belum ada data aktivasi kartu perdana.
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
                    <!-- Tombol Sebelumnya -->
                    <li class="page-item <?= ($data['paging']['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar&limit=<?= $data['paging']['limit'] ?>&p=<?= $data['paging']['current_page'] - 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                            <i class="bi bi-chevron-left"></i> Prev
                        </a>
                    </li>

                    <?php 
                    // Logika rentang halaman agar tidak terlalu panjang jika data banyak
                    $current = $data['paging']['current_page'];
                    $total_p = $data['paging']['total_pages'];
                    $start = max(1, $current - 2);
                    $end = min($total_p, $current + 2);

                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=' . $sub . '&view=daftar&limit=' . $data['paging']['limit'] . '&p=1' . (!empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '') . '">1</a></li>';
                        if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }

                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $current) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar&limit=<?= $data['paging']['limit'] ?>&p=<?= $i ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php 
                    endfor; 

                    if ($end < $total_p) {
                        if ($end < $total_p - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="index.php?page=pengadaan&menu=kartu&sub=' . $sub . '&view=daftar&limit=' . $data['paging']['limit'] . '&p=' . $total_p . (!empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '') . '">' . $total_p . '</a></li>';
                    }
                    ?>

                    <!-- Tombol Selanjutnya -->
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

<!-- MODAL POPUP FORM ISI PULSA CEPAT -->
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
                  <label class="form-label fw-bold text-muted small">SIM ID (ICCID)</label>
                  <input type="text" class="form-control bg-light" name="sim_id" id="modal_sim_id" readonly required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">Nomor Kartu Tujuan</label>
                  <input type="text" class="form-control bg-light" id="modal_nomor_akun" readonly>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">Nominal Pengisian (Rp)</label>
                  <input type="number" class="form-control" name="nominal" placeholder="Contoh: 50000 / 100000" required autofocus>
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

<!-- SCRIPT JAVASCRIPT UNTUK MODAL ISI PULSA -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const modalEl = document.getElementById('modalIsiPulsa');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        
        document.querySelectorAll('.btn-isi-pulsa').forEach(button => {
            button.addEventListener('click', function() {
                let simId = this.getAttribute('data-simid');
                let nomor = this.getAttribute('data-nomor');
                
                document.getElementById('modal_sim_id').value = simId;
                document.getElementById('modal_nomor_akun').value = '+' + nomor;
                
                modal.show();
            });
        });
    }
});
</script>