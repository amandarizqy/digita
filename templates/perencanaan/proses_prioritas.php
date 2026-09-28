<!-- templates/perencanaan/proses_prioritas.php -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h4 class="mb-0">
        <i class="bi bi-sort-numeric-down-alt text-primary me-2"></i> Skala Prioritas Khusus
        <span class="badge bg-dark ms-2 align-middle"><i class="bi bi-calendar3 me-1"></i>Periode <?= htmlspecialchars($periode_filter) ?></span>
    </h4>
    <div class="d-flex gap-2">
        <a href="?module=perencanaan&action=hasil_prioritas" class="btn btn-outline-primary"><i class="bi bi-list-ol"></i> Lihat Hasil Prioritas</a>
        <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<!-- Alert -->
<?php if (!empty($pesan_sukses)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $pesan_sukses ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($tabel_belum_ada && empty($pesan_error)): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-database-exclamation me-2"></i>
        Tabel <code>hasil_prioritas</code> belum dibuat. Jalankan file <code>hasil_prioritas.sql</code> sekali di database sebelum memproses.
    </div>
<?php endif; ?>

<!-- Penjelasan cara kerja -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2"></i> CARA KERJA PEMERINGKATAN</h6>
        <button class="btn btn-sm btn-light text-primary fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCara">
            <i class="bi bi-chevron-down me-1"></i> Tampilkan / Sembunyikan
        </button>
    </div>
    <div class="collapse show" id="collapseCara">
        <div class="card-body bg-light">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-1-circle me-1"></i> Pilih Skala</h6>
                        <small class="text-muted">Pilih satu Skala Prioritas (1-9) hasil <strong>Proses Risiko</strong>. Seluruh pelanggan pada skala itu akan dibandingkan satu sama lain.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-2-circle me-1"></i> Tiga Indikator (Tagihan <?= htmlspecialchars($periode_filter) ?>)</h6>
                        <ul class="small mb-0 ps-3">
                            <li><strong>Nominal tagihan</strong> (RpTag + RpBK)</li>
                            <li><strong>Frekuensi telat</strong> (bayar setelah tgl 20)</li>
                            <li><strong>Tunggakan</strong> (bayar lewat bulan rekening / belum bayar)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-3-circle me-1"></i> Skor &amp; Peringkat</h6>
                        <small class="text-muted">Tiap indikator dinormalisasi 0-1 di dalam grup, digabung sesuai bobot menjadi skor 0-100. <strong>Peringkat 1 = paling perlu ditangani.</strong> Data yang identik berbagi peringkat yang sama.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="?module=perencanaan&action=proses_prioritas" id="formPrioritas">

    <!-- Langkah 1: pilih skala -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-grid-3x3-gap me-1"></i> 1. Pilih Skala Prioritas</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php for ($n = 9; $n >= 1; $n--):
                    $k        = $kartu_skala[$n];
                    $warna    = warnaSkalaPrioritas($n);
                    $disabled = ($k['status'] === 'kosong');
                    switch ($k['status']) {
                        case 'sudah': $status_txt = '✅ Sudah diperingkat (' . number_format($k['ranked']) . ')'; break;
                        case 'usang': $status_txt = '⚠️ Perlu update (' . number_format($k['ranked']) . ' dari ' . number_format($k['jumlah']) . ')'; break;
                        case 'belum': $status_txt = '⏳ Belum diperingkat'; break;
                        default:      $status_txt = '— Tidak ada pelanggan';
                    }
                ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <input type="radio" class="btn-check" name="skala_prioritas" id="skala_<?= $n ?>" value="<?= $n ?>" autocomplete="off"
                               <?= $skala_dipilih === $n ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                        <label class="btn btn-outline-<?= $warna ?> w-100 text-start p-3" for="skala_<?= $n ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-4 fw-bold">Prioritas <?= $n ?></span>
                                <span class="fw-semibold"><?= number_format($k['jumlah']) ?> pelanggan</span>
                            </div>
                            <small class="d-block opacity-75"><?= htmlspecialchars(labelSkalaPrioritas($n)) ?></small>
                            <small class="d-block mt-1 fw-semibold"><?= $status_txt ?></small>
                            <?php if (!empty($k['terakhir'])): ?>
                                <small class="d-block opacity-75">Terakhir: <?= htmlspecialchars($k['terakhir']) ?> · <?= htmlspecialchars(labelMetodePeringkat($k['metode'])) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Langkah 2: metode & bobot -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-sliders me-1"></i> 2. Metode Pemeringkatan</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-start">
                <div class="col-md-5">
                    <label for="metode" class="form-label fw-bold">Metode</label>
                    <select name="metode" id="metode" class="form-select">
                        <?php foreach ($metode_tersedia as $kode => $nama): ?>
                            <option value="<?= $kode ?>" <?= $metode_dipilih === $kode ? 'selected' : '' ?>><?= htmlspecialchars($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Metode selain "Skor Gabungan" hanya memakai satu indikator (bobot 100%).</small>
                </div>

                <div class="col-md-7" id="boxBobot">
                    <label class="form-label fw-bold">Bobot Skor Gabungan
                        <span class="ms-2 badge bg-success" id="totalBobot">100</span><span class="small text-muted"> / 100%</span>
                    </label>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" min="0" max="100" name="bobot_nominal" class="form-control bobot-input" value="<?= (int) $bobot_dipilih[0] ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Nominal tagihan</small>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" min="0" max="100" name="bobot_telat" class="form-control bobot-input" value="<?= (int) $bobot_dipilih[1] ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Frekuensi telat</small>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" min="0" max="100" name="bobot_tunggak" class="form-control bobot-input" value="<?= (int) $bobot_dipilih[2] ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Tunggakan</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted"><i class="bi bi-arrow-repeat me-1"></i> Memproses ulang sebuah skala akan mengganti hasil peringkat lama untuk skala tersebut.</small>
            <button type="submit" name="proses_prioritas" class="btn btn-primary px-4 fw-bold" id="btnProses">
                <i class="bi bi-lightning-charge-fill me-1"></i> Proses Pemeringkatan
            </button>
        </div>
    </div>
</form>

<!-- Pratinjau 10 teratas -->
<?php if ($skala_dipilih >= 1 && $skala_dipilih <= 9): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="fw-bold mb-0 text-secondary">
                <i class="bi bi-trophy me-1"></i> 10 Peringkat Teratas — Prioritas <?= $skala_dipilih ?>
            </h6>
            <a href="?module=perencanaan&action=hasil_prioritas&skala=<?= $skala_dipilih ?>" class="btn btn-sm btn-outline-primary">Lihat semua &rarr;</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:80px;">Peringkat</th>
                            <th>IdPel</th>
                            <th>Nama Pelanggan</th>
                            <th class="text-end">Total Tagihan</th>
                            <th class="text-center">Telat</th>
                            <th class="text-center">Lewat Bulan</th>
                            <th class="text-center">Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($preview_top)): foreach ($preview_top as $r): ?>
                            <tr>
                                <td class="text-center"><span class="badge <?= (int) $r['Peringkat'] === 1 ? 'bg-warning text-dark' : 'bg-dark' ?>">#<?= (int) $r['Peringkat'] ?></span></td>
                                <td><code><?= htmlspecialchars($r['IdPel']) ?></code></td>
                                <td><strong><?= htmlspecialchars($r['NamaPelanggan'] ?? '-') ?></strong></td>
                                <td class="text-end">Rp <?= number_format((float) $r['TotalTagihan'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= (int) $r['JumlahTelat'] ?></td>
                                <td class="text-center"><?= (int) $r['JumlahLewatBulan'] ?></td>
                                <td class="text-center fw-bold"><?= number_format((float) $r['SkorPrioritas'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Skala ini belum diperingkat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form   = document.getElementById('formPrioritas');
    const metode = document.getElementById('metode');
    const box    = document.getElementById('boxBobot');
    const inputs = document.querySelectorAll('.bobot-input');
    const total  = document.getElementById('totalBobot');

    function hitungTotal() {
        let s = 0;
        inputs.forEach(i => s += parseInt(i.value) || 0);
        total.textContent = s;
        total.className = 'ms-2 badge ' + (s === 100 ? 'bg-success' : 'bg-danger');
        return s === 100;
    }
    function tampilBobot() {
        box.style.display = (metode.value === 'gabungan') ? '' : 'none';
    }

    metode.addEventListener('change', tampilBobot);
    inputs.forEach(i => i.addEventListener('input', hitungTotal));
    tampilBobot();
    hitungTotal();

    form.addEventListener('submit', function (e) {
        const skala = form.querySelector('input[name="skala_prioritas"]:checked');
        if (!skala) {
            e.preventDefault();
            alert('Pilih salah satu Skala Prioritas (1-9) terlebih dahulu.');
            return;
        }
        if (metode.value === 'gabungan' && !hitungTotal()) {
            e.preventDefault();
            alert('Total bobot harus tepat 100%.');
            return;
        }
        if (!confirm('Proses pemeringkatan untuk Prioritas ' + skala.value + '? Hasil lama untuk skala ini akan diganti.')) {
            e.preventDefault();
        }
    });
});
</script>