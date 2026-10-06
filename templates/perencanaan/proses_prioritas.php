<?php
// templates/perencanaan/proses_prioritas.php  -  Skala Prioritas (pemeringkatan)
// Variabel dari modules/perencanaan/proses_prioritas.php

$jml_terklasifikasi = array_sum(array_column($kartu_skala, 'jumlah'));
$cacah_status = ['sudah' => 0, 'usang' => 0, 'belum' => 0, 'kosong' => 0];
foreach ($kartu_skala as $k) {
    $cacah_status[$k['status']]++;
}
$skala_terisi = 9 - $cacah_status['kosong'];

echo pr_header([
    'title'    => 'Skala Prioritas',
    'subtitle' => 'Peringkatkan pelanggan di dalam satu skala (1-9) berdasarkan nominal tagihan, frekuensi telat, dan tunggakan.',
    'action'   => 'proses_prioritas',
    'periode'  => true,
    'buttons'  => [['label' => 'Lihat Hasil Prioritas', 'icon' => 'bi-list-ol', 'href' => pr_url('hasil_prioritas', ['skala' => $skala_dipilih > 0 ? $skala_dipilih : ''])]],
]);

if (!empty($pesan_sukses)) echo pr_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pr_alert('danger', $pesan_error);
if ($tabel_belum_ada && empty($pesan_error)) {
    echo pr_alert('warning', 'Tabel <code>hasil_prioritas</code> belum dibuat. Jalankan file <code>hasil_prioritas.sql</code> sekali di database sebelum memproses.');
}
?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Pelanggan Terklasifikasi', pr_n($jml_terklasifikasi), 'Tersebar di ' . $skala_terisi . ' dari 9 skala', 'bi-people', 'primary') ?>
    <?= pr_kpi('Skala Terbaru', $cacah_status['sudah'] . ' <span class="fs-6 fw-semibold text-muted">/ ' . $skala_terisi . '</span>', 'Peringkat sinkron dengan klasifikasi', 'bi-check2-circle', 'success', true) ?>
    <?= pr_kpi('Perlu Update', (string) $cacah_status['usang'], 'Jumlah pelanggan berubah sejak diperingkat', 'bi-arrow-repeat', 'danger', $cacah_status['usang'] > 0) ?>
    <?= pr_kpi('Belum Diperingkat', (string) $cacah_status['belum'], 'Ada pelanggan, belum pernah diproses', 'bi-hourglass-split', 'warning') ?>
</div>

<!-- CARA KERJA (dilipat) -->
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <button class="btn text-start d-flex justify-content-between align-items-center py-3 px-4 border-0 collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapseCara" aria-expanded="false" aria-controls="collapseCara">
        <span class="fw-bold text-primary fs-6"><i class="bi bi-info-circle me-2"></i>Cara Kerja Pemeringkatan</span>
        <span class="text-muted small">Tampilkan / sembunyikan <i class="bi bi-chevron-down ms-1"></i></span>
    </button>
    <div class="collapse" id="collapseCara">
        <div class="card-body border-top px-4 py-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-1-circle me-1"></i> Pilih Skala</h6>
                        <p class="small text-muted mb-0">Pilih satu Skala Prioritas (1-9) hasil <strong>Klasifikasi Level Risiko</strong>. Seluruh pelanggan pada skala itu dibandingkan satu sama lain.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-2-circle me-1"></i> Tiga Indikator (Tagihan <?= pr_e($periode_filter) ?>)</h6>
                        <ul class="small text-muted mb-0 ps-3">
                            <li><strong>Nominal tagihan</strong> (RpTag + RpBK)</li>
                            <li><strong>Frekuensi telat</strong> (bayar setelah tanggal 20)</li>
                            <li><strong>Tunggakan</strong> (bayar lewat bulan rekening / belum bayar)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-3-circle me-1"></i> Skor &amp; Peringkat</h6>
                        <p class="small text-muted mb-0">Tiap indikator dinormalisasi 0-1 di dalam grup, digabung sesuai bobot menjadi skor 0-100. <strong>Peringkat 1 = paling perlu ditangani.</strong> Data identik berbagi peringkat yang sama.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="<?= pr_e(pr_url('proses_prioritas')) ?>" id="formPrioritas">

    <!-- LANGKAH 1 -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold pr-step" style="background:#e7f1ff;color:#0d6efd;">1</span>
                <span class="fw-bold text-primary fs-6">Pilih Skala Prioritas</span>
            </div>
            <span class="text-muted small">Matriks <strong>Kemungkinan</strong> x <strong>Dampak</strong> · prioritas 9 = paling mendesak</span>
        </div>
        <div class="card-body px-4 py-4">
            <?php
            $data_grid = [];
            for ($n = 1; $n <= 9; $n++) {
                $k = $kartu_skala[$n];
                switch ($k['status']) {
                    case 'sudah':  $note = pr_badge('SUDAH DIPERINGKAT', 'success', 'bi-check2-circle'); break;
                    case 'usang':  $note = pr_badge('PERLU UPDATE · ' . pr_n($k['ranked']) . ' DARI ' . pr_n($k['jumlah']), 'danger', 'bi-arrow-repeat'); break;
                    case 'belum':  $note = pr_badge('BELUM DIPERINGKAT', 'warning', 'bi-hourglass-split'); break;
                    default:       $note = '<span class="text-muted">Tidak ada pelanggan</span>';
                }
                $note .= '<div class="text-muted d-md-none mt-1">' . pr_e(labelSkalaPrioritas($n)) . '</div>';
                if (!empty($k['terakhir'])) {
                    $note .= '<div class="text-muted mt-1" style="font-size:.72rem;">' . pr_e(labelMetodePeringkat($k['metode'])) . ' · ' . pr_e($k['terakhir']) . '</div>';
                }
                $data_grid[$n] = ['count' => $k['jumlah'], 'note' => $note, 'disabled' => $k['status'] === 'kosong'];
            }
            echo pr_skala_grid($data_grid, 'radio', $skala_dipilih);
            ?>
        </div>
    </div>

    <!-- LANGKAH 2 -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-2">
            <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold pr-step" style="background:#e7f1ff;color:#0d6efd;">2</span>
            <span class="fw-bold text-primary fs-6">Metode Pemeringkatan</span>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <label for="metode" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">Metode</label>
                    <select name="metode" id="metode" class="form-select">
                        <?php foreach ($metode_tersedia as $kode => $nama): ?>
                            <option value="<?= pr_e($kode) ?>" <?= $metode_dipilih === $kode ? 'selected' : '' ?>><?= pr_e($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Metode selain "Skor Gabungan" hanya memakai satu indikator (bobot 100%).</div>
                </div>

                <div class="col-lg-7" id="boxBobot">
                    <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">
                        Bobot Skor Gabungan
                        <span class="ms-2 badge bg-success" id="totalBobot">100</span><span class="text-muted text-lowercase fw-normal"> / 100%</span>
                    </label>
                    <div class="row g-2">
                        <?php foreach ([['bobot_nominal', 0, 'Nominal tagihan'], ['bobot_telat', 1, 'Frekuensi telat'], ['bobot_tunggak', 2, 'Tunggakan']] as [$nm, $ix, $lbl]): ?>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" min="0" max="100" name="<?= $nm ?>" class="form-control bobot-input" value="<?= (int) $bobot_dipilih[$ix] ?>" aria-label="<?= $lbl ?>">
                                <span class="input-group-text bg-light">%</span>
                            </div>
                            <div class="form-text"><?= $lbl ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i> Memproses ulang sebuah skala akan mengganti peringkat lama untuk skala tersebut.</span>
            <button type="submit" name="proses_prioritas" value="1" class="btn btn-primary btn-sm px-4 shadow-sm rounded-2" id="btnProses">
                <i class="bi bi-lightning-charge-fill me-1"></i> Proses Pemeringkatan
            </button>
        </div>
    </div>
</form>

<!-- PRATINJAU 10 TERATAS -->
<?php if ($skala_dipilih >= 1 && $skala_dipilih <= 9): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">10 Peringkat Teratas</span>
            <?= pr_badge_prioritas($skala_dipilih) ?>
        </div>
        <a href="<?= pr_e(pr_url('hasil_prioritas', ['skala' => $skala_dipilih])) ?>" class="btn btn-outline-primary btn-sm px-3">Lihat semua <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-uppercase pr-thead">
                    <tr>
                        <th class="ps-4" style="width:90px;">Peringkat</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-center">Telat</th>
                        <th class="text-center">Lewat Bulan</th>
                        <th class="text-end pe-4">Skor</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($preview_top)): foreach ($preview_top as $r): ?>
                    <tr>
                        <td class="ps-4"><?= pr_badge('#' . (int) $r['Peringkat'], (int) $r['Peringkat'] === 1 ? 'warning' : 'secondary') ?></td>
                        <td class="fw-bold text-dark font-monospace"><?= pr_e($r['IdPel']) ?></td>
                        <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($r['NamaPelanggan'] ?? '-') ?></td>
                        <td class="text-end font-monospace small"><?= pr_rp($r['TotalTagihan']) ?></td>
                        <td class="text-center"><?= (int) $r['JumlahTelat'] ?></td>
                        <td class="text-center"><?= (int) $r['JumlahLewatBulan'] ?></td>
                        <td class="text-end pe-4 fw-bold"><?= number_format((float) $r['SkorPrioritas'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; else: echo pr_empty_row(7, 'Skala ini belum diperingkat.', 'bi-trophy'); endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form   = document.getElementById('formPrioritas');
    var metode = document.getElementById('metode');
    var box    = document.getElementById('boxBobot');
    var inputs = document.querySelectorAll('.bobot-input');
    var total  = document.getElementById('totalBobot');

    function hitungTotal() {
        var s = 0;
        inputs.forEach(function (i) { s += parseInt(i.value) || 0; });
        total.textContent = s;
        total.className = 'ms-2 badge ' + (s === 100 ? 'bg-success' : 'bg-danger');
        return s === 100;
    }
    function tampilBobot() { box.style.display = (metode.value === 'gabungan') ? '' : 'none'; }

    metode.addEventListener('change', tampilBobot);
    inputs.forEach(function (i) { i.addEventListener('input', hitungTotal); });
    tampilBobot();
    hitungTotal();

    form.addEventListener('submit', function (e) {
        var skala = form.querySelector('input[name="skala_prioritas"]:checked');
        if (!skala) { e.preventDefault(); alert('Pilih salah satu Skala Prioritas (1-9) terlebih dahulu.'); return; }
        if (metode.value === 'gabungan' && !hitungTotal()) { e.preventDefault(); alert('Total bobot harus tepat 100%.'); return; }
        if (!confirm('Proses pemeringkatan untuk Prioritas ' + skala.value + '? Hasil lama untuk skala ini akan diganti.')) { e.preventDefault(); }
    });
});
</script>
