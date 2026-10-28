<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- TABS UNTUK SPK & FPK -->
<ul class="nav nav-tabs mb-3" id="orderTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-bold text-primary" id="spk-tab" data-bs-toggle="tab" data-bs-target="#spk" type="button" role="tab"><i class="bi bi-card-checklist me-1"></i> Data Form Order (SPK)</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-bold text-secondary" id="fpk-tab" data-bs-toggle="tab" data-bs-target="#fpk" type="button" role="tab"><i class="bi bi-file-earmark-person me-1"></i> Data FPK (Pasang)</button>
  </li>
</ul>

<div class="tab-content" id="orderTabContent">
  
  <!-- TAB SPK (ORDER) -->
  <div class="tab-pane fade show active" id="spk" role="tabpanel">
      <div class="card shadow mb-4 border-0">
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                      <thead class="table-light text-muted">
                          <tr>
                              <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORM ORDER (SPK)</th>
                              <th class="fw-bold border-bottom-0 py-3">TGL ORDER</th>
                              <th class="fw-bold border-bottom-0 py-3 text-center">TOTAL FPK</th>
                              <th class="fw-bold border-bottom-0 py-3 text-center">STATUS</th>
                              <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if (empty($list_order)): ?>
                              <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data Surat Perintah Kerja (Order).</td></tr>
                          <?php else: ?>
                              <?php foreach ($list_order as $row): ?>
                                  <tr>
                                        <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormOrder']) ?></td>
                                        <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                                        <td class="text-center fw-bold"><?= $row['TotalTarget'] ?> FPK</td>
                                        <td class="text-center">
                                            <?= ($row['StatusData'] == 'AKTIF') ? '<span class="badge bg-success bg-opacity-10 text-success border border-success">SPK Terbit</span>' : '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Draft</span>' ?>
                                        </td>
                                        <td class="text-center pe-4 d-flex justify-content-center gap-1">
                                            <a href="index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Detail</a>
                                            <?php if($row['StatusData'] == 'TIDAK'): ?>
                                                <a href="modules/pemasangan/proses_order.php?action=delete_draft&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus Draft SPK ini secara permanen?')"><i class="bi bi-trash"></i></a>
                                            <?php endif; ?>
                                        </td>
                                  </tr>
                              <?php endforeach; ?>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>

  <!-- TAB FPK -->
  <div class="tab-pane fade" id="fpk" role="tabpanel">
      <div class="d-flex justify-content-end mb-3">
          <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalFpkBaru">
              <i class="bi bi-plus-circle me-1"></i> FPK Baru
          </button>
      </div>
      <div class="card shadow mb-4 border-0">
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                      <thead class="table-light text-muted">
                          <tr>
                              <th class="fw-bold border-bottom-0 py-3 ps-4">NO FPK</th>
                              <th class="fw-bold border-bottom-0 py-3">ID PELANGGAN</th>
                              <th class="fw-bold border-bottom-0 py-3">ALAT (NO REF)</th>
                              <th class="fw-bold border-bottom-0 py-3 text-center">STATUS KETERKAITAN</th>
                              <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if (empty($list_fpk)): ?>
                              <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada FPK Pemasangan yang dibuat.</td></tr>
                          <?php else: ?>
                              <?php foreach ($list_fpk as $row): ?>
                                  <tr>
                                      <td class="fw-bold text-success ps-4"><?= htmlspecialchars($row['NoFpk']) ?></td>
                                      <td class="fw-bold text-dark"><?= htmlspecialchars($row['IdPel']) ?></td>
                                      <td><?= htmlspecialchars($row['NomorBarang']) ?></td>
                                      <td class="text-center">
                                          <?php if($row['TerikatOrder']): ?>
                                              <span class="badge bg-primary">SPK: <?= $row['TerikatOrder'] ?></span>
                                          <?php else: ?>
                                              <span class="badge bg-secondary">Belum masuk Order Pasang</span>
                                          <?php endif; ?>
                                      </td>
                                      <td class="text-center pe-4">
                                          <?php if(!$row['TerikatOrder']): ?>
                                              <a href="modules/pemasangan/proses_order.php?action=delete_fpk&no_fpk=<?= $row['NoFpk'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus FPK ini?')"><i class="bi bi-trash"></i></a>
                                          <?php endif; ?>
                                      </td>
                                  </tr>
                              <?php endforeach; ?>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- MODAL BUAT FPK BARU -->
<div class="modal fade" id="modalFpkBaru" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-success"><i class="bi bi-file-earmark-plus me-2"></i>Buat FPK Pasang Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="modules/pemasangan/proses_order.php?action=create_fpk" method="POST">
          <div class="modal-body p-4 row g-3">
              <div class="col-12">
                  <label class="form-label fw-bold small text-secondary">Pilih Target Pelanggan (Untuk Pasang Baru) *</label>
                  <select name="id_pel" class="select2-modal form-control" required style="width: 100%;">
                      <option value="">-- Cari Prioritas / IdPel --</option>
                      <?php foreach($list_pelanggan as $pel): ?> 
                          <option value="<?= htmlspecialchars($pel['IdPel']) ?>">
                              [Prioritas <?= $pel['SkalaPrioritas'] ?> - Peringkat <?= $pel['Peringkat'] ?>] IdPel: <?= $pel['IdPel'] ?>
                          </option> 
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="col-12">
                  <label class="form-label fw-bold small text-secondary">Jodohkan dengan Alat S41 (Lolos Uji) *</label>
                  <select name="no_ref" class="select2-modal form-control" required style="width: 100%;">
                      <option value="">-- Cari NoRef / IMEI --</option>
                      <?php foreach($list_alat as $alat): ?> 
                          <option value="<?= htmlspecialchars($alat['NoRef']) ?>">NoRef: <?= $alat['NoRef'] ?> (IMEI: <?= $alat['ImeiModem'] ?>)</option> 
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer bg-light">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan FPK</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.select2-modal').select2({ dropdownParent: $('#modalFpkBaru') });
});
</script>