<?php
$active_tab = $_GET['active_tab'] ?? 'baru';
$is_draft = ($formulir['StatusData'] === 'TIDAK');
$unit_display = $formulir['UnitUp'] ?? $formulir['UnitAp'] ?? $formulir['UnitUpi'];

$edit_data = [];
if($active_tab !== 'baru') {
    foreach($items as $it) {
        if($it['NoRef'] === $active_tab) {
            $edit_data = $it; break;
        }
    }
}
$is_edit = !empty($edit_data);

// PERBAIKAN 3: Helper fungsi di-upgrade agar memiliki nilai default (Berhasil & 4 Detik) saat add baru
$v = function($key, $default = '') use ($is_edit, $edit_data) { 
    return $is_edit ? ($edit_data[$key] ?? '') : $default; 
};
$chk = function($key, $val, $default_checked = false) use ($is_edit, $edit_data) { 
    if ($is_edit) return (($edit_data[$key] ?? '') === $val) ? 'checked' : ''; 
    return $default_checked ? 'checked' : '';
};
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .select2-container .select2-selection--single { height: 38px; border: 1px solid #dee2e6; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
</style>

<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
            <i class="bi bi-layers text-secondary me-2"></i> 
            <span class="text-secondary fw-bold text-uppercase small">Formulir S41: <?= htmlspecialchars($formulir['NoFormulir']) ?></span>
        </div>
        <?php if(isset($_GET['alert']) && $_GET['alert'] == 'saved'): ?>
            <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle me-1"></i> Draft Tersimpan</span>
        <?php endif; ?>
    </div>
    
    <ul class="nav nav-pills gap-2" role="tablist">
        <?php foreach($items as $index => $item): ?>
            <li class="nav-item">
                <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=<?= urlencode($formulir['NoFormulir']) ?>&active_tab=<?= urlencode($item['NoRef']) ?>" 
                   class="nav-link rounded-pill border <?= ($active_tab === $item['NoRef']) ? 'active bg-primary' : 'bg-white text-secondary' ?>">
                    <i class="bi <?= ($active_tab === $item['NoRef']) ? 'bi-pen' : 'bi-file-text' ?> me-1"></i> Alat <?= $index + 1 ?>
                </a>
            </li>
        <?php endforeach; ?>
        
        <?php if($is_draft): ?>
            <li class="nav-item">
                <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=<?= urlencode($formulir['NoFormulir']) ?>&active_tab=baru" 
                   class="nav-link rounded-pill border <?= ($active_tab === 'baru') ? 'active bg-success border-success' : 'bg-white text-success border-success' ?>">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Page Alat Baru
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4 p-md-5">
        
        <form id="pengujianForm" action="modules/pemasangan/proses_pengujian.php?action=add_item" method="POST">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($formulir['NoFormulir']) ?>">
            
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h5 class="fw-bold text-primary mb-1">PT PLN (PERSERO)</h5>
                    <h4 class="fw-bold text-primary mb-0 text-uppercase">Lembar Pengujian <?= $is_edit ? 'Alat '.htmlspecialchars($active_tab) : 'Alat Baru' ?></h4>
                </div>
                <?php if($is_edit && $is_draft): ?>
                    <a href="javascript:void(0);" onclick="confirmDeleteHalaman('modules/pemasangan/proses_pengujian.php?action=delete_item&no_form=<?= urlencode($formulir['NoFormulir']) ?>&noref=<?= urlencode($active_tab) ?>')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus Halaman Ini</a>
                <?php endif; ?>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Unit Layanan (UP)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($unit_display) ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Tanggal Uji</label>
                    <input type="date" name="tgl_uji" class="form-control" value="<?= htmlspecialchars($formulir['TglUji']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Nomor Kontak Admin</label>
                    <input type="text" id="nomor_kontak" class="form-control bg-light" readonly placeholder="Terisi Otomatis">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Nomor Referensi Alat (NoRef) *</label>
                    <?php if($is_edit): ?>
                        <input type="text" name="noref" class="form-control fw-bold" value="<?= htmlspecialchars($v('NoRef')) ?>" readonly>
                    <?php else: ?>
                        <select name="noref" class="select2 w-100" required>
                            <option value="">-- Pilih NoRef --</option>
                            <?php foreach($list_alat as $alat): ?> <option value="<?= htmlspecialchars($alat['NoRef']) ?>"><?= htmlspecialchars($alat['NoRef']) ?></option> <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <!-- PERBAIKAN 4: Toggle Kelayakan Alat -->
                    <div class="mt-2 p-2 bg-light border border-secondary-subtle rounded-3">
                        <span class="small fw-bold text-dark d-block mb-1">Status Fisik/Fungsi Alat:</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input alat-toggle" type="radio" name="status_alat" value="LAYAK" checked>
                            <label class="form-check-label small text-success fw-bold">Layak (Normal)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input alat-toggle" type="radio" name="status_alat" value="TIDAK LAYAK">
                            <label class="form-check-label small text-danger fw-bold">Tidak Layak (Rusak)</label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Nomor SIM Card</label>
                    <select name="simid" id="simid_select" class="select2 w-100">
                        <option value="">-- Boleh Kosong (Draft) --</option>
                        <?php foreach($list_sim as $sim): ?> 
                            <option value="<?= htmlspecialchars($sim['SimId']) ?>" <?= ($v('SimId') == $sim['SimId']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sim['SimId']) ?> (<?= htmlspecialchars($sim['NomorAkun'] ?? 'Kosong') ?>)
                            </option> 
                        <?php endforeach; ?>
                    </select>

                    <!-- PERBAIKAN 4: Toggle Kelayakan SIM -->
                    <div class="mt-2 p-2 bg-light border border-secondary-subtle rounded-3">
                        <span class="small fw-bold text-dark d-block mb-1">Status SIM Card:</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status_sim" value="LAYAK" checked>
                            <label class="form-check-label small text-success fw-bold">Layak Aktif</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status_sim" value="TIDAK LAYAK">
                            <label class="form-check-label small text-danger fw-bold">Tidak Layak (Mati)</label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">IMEI Modem S41</label>
                    <select name="imei_modem" id="imei_modem_select" class="select2 w-100">
                        <option value="">-- Boleh Kosong (Draft) --</option>
                        <?php foreach($list_modem as $modem): ?> 
                            <option value="<?= htmlspecialchars($modem['ImeiModem']) ?>" <?= ($v('ImeiModem') == $modem['ImeiModem']) ? 'selected' : '' ?>><?= htmlspecialchars($modem['ImeiModem']) ?></option> 
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- KONDISI ADMIN & AWAL (Terisi Default True) -->
            <div class="border rounded p-3 mb-4 bg-light">
                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-person-gear me-2"></i>KONDISI ADMIN</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">SET ADMIN</label>
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="set_admin" value="BERHASIL" <?= $chk('SetAdmin', 'BERHASIL', true) ?>><label class="form-check-label small">Berhasil</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="set_admin" value="GAGAL" <?= $chk('SetAdmin', 'GAGAL') ?>><label class="form-check-label small">Gagal</label></div>
                            </div>
                            <input type="text" id="no_admin_set" class="form-control form-control-sm bg-white" readonly placeholder="Auto Dari Modem">
                        </div>
                    </div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">DEL ADMIN</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="del_admin" value="BERHASIL" <?= $chk('DelAdmin', 'BERHASIL', true) ?>><label class="form-check-label small">Berhasil</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="del_admin" value="GAGAL" <?= $chk('DelAdmin', 'GAGAL') ?>><label class="form-check-label small">Gagal</label></div></div></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">LIST ADMIN</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="list_admin" value="BERHASIL" <?= $chk('ListAdmin', 'BERHASIL', true) ?>><label class="form-check-label small">Berhasil</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="list_admin" value="GAGAL" <?= $chk('ListAdmin', 'GAGAL') ?>><label class="form-check-label small">Gagal</label></div></div></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">RESET ADMIN</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="reset_admin" value="BERHASIL" <?= $chk('ResetAdmin', 'BERHASIL', true) ?>><label class="form-check-label small">Berhasil</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="reset_admin" value="GAGAL" <?= $chk('ResetAdmin', 'GAGAL') ?>><label class="form-check-label small">Gagal</label></div></div></div>
                </div>
                
                <hr class="my-3">
                <h6 class="fw-bold text-info mb-3"><i class="bi bi-power me-2"></i>KONDISI AWAL</h6>
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label small fw-semibold">Cek Phasa</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="cek_phasa" value="ADA" <?= $chk('TeganganPhasa', 'ADA', true) ?>><label class="form-check-label small">Ada</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="cek_phasa" value="TIDAK" <?= $chk('TeganganPhasa', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Lampu Awal</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_awal" value="AKTIF" <?= $chk('LampuIndikator', 'AKTIF', true) ?>><label class="form-check-label small">Menyala</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_awal" value="TIDAK" <?= $chk('LampuIndikator', 'TIDAK') ?>><label class="form-check-label small">Mati</label></div></div></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Respon PLNSTATUS (Detik)</label><input type="number" name="detik_status_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikStatusAwal', '4')) ?>"></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Dijawab SMS?</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_awal" value="ADA" <?= $chk('JawabanStatusAwal', 'ADA', true) ?>><label class="form-check-label small">Ya</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_awal" value="TIDAK" <?= $chk('JawabanStatusAwal', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Isi Status</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_awal" value="SESUAI" <?= $chk('IsiStatusAwal', 'SESUAI', true) ?>><label class="form-check-label small">Sesuai</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_awal" value="TIDAK" <?= $chk('IsiStatusAwal', 'TIDAK') ?>><label class="form-check-label small">Tidak Sesuai</label></div></div></div>
                </div>
            </div>

            <!-- KONDISI PLNOFF & PLNON -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded p-3 h-100">
                        <h6 class="fw-bold text-danger mb-3"><i class="bi bi-toggle-off me-2"></i>KONDISI DIPUTUS SEMENTARA</h6>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small fw-semibold">Respon PLNOFF (Detik)</label><input type="number" name="detik_plnoff" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikPutus', '4')) ?>"></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Durasi Jawab SMS (Detik)</label><input type="number" name="durasi_jawab_off" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikJawabanPutus', '4')) ?>"></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Dijawab SMS?</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_plnoff" value="ADA" <?= $chk('JawabanPutus', 'ADA', true) ?>><label class="form-check-label small">Ya</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_plnoff" value="TIDAK" <?= $chk('JawabanPutus', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Lampu Beban</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_off" value="MENYALA" <?= $chk('LampuDiputus', 'MENYALA') ?>><label class="form-check-label small">Menyala</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_off" value="PADAM" <?= $chk('LampuDiputus', 'PADAM', true) ?>><label class="form-check-label small">Mati</label></div></div></div>
                        </div>
                        <hr class="my-2 border-danger-subtle">
                        <div class="row g-2">
                            <div class="col-12"><span class="small text-danger fw-semibold">Cek PLNSTATUS (Pasca Putus):</span></div>
                            <div class="col-4"><label class="form-label small">Respon (Detik)</label><input type="number" name="detik_status_off" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikStatusPutus', '4')) ?>"></div>
                            <div class="col-4"><label class="form-label small">Dijawab?</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_off" value="ADA" <?= $chk('JawabanStatusPutus', 'ADA', true) ?>><label class="form-check-label small">Ya</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_off" value="TIDAK" <?= $chk('JawabanStatusPutus', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                            <div class="col-4"><label class="form-label small">Isi Status</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_off" value="SESUAI" <?= $chk('IsiStatusPutus', 'SESUAI', true) ?>><label class="form-check-label small">Sesuai</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_off" value="TIDAK" <?= $chk('IsiStatusPutus', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mt-3 mt-md-0">
                    <div class="border border-success-subtle bg-success-subtle bg-opacity-10 rounded p-3 h-100">
                        <h6 class="fw-bold text-success mb-3"><i class="bi bi-toggle-on me-2"></i>KONDISI DINYALAKAN KEMBALI</h6>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small fw-semibold">Respon PLNON (Detik)</label><input type="number" name="detik_plnon" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikNyala', '4')) ?>"></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Durasi Jawab SMS (Detik)</label><input type="number" name="durasi_jawab_on" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikJawabanNyala', '4')) ?>"></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Dijawab SMS?</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_plnon" value="ADA" <?= $chk('JawabanNyala', 'ADA', true) ?>><label class="form-check-label small">Ya</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_plnon" value="TIDAK" <?= $chk('JawabanNyala', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Lampu Beban</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_on" value="MENYALA" <?= $chk('LampuMenyala', 'MENYALA', true) ?>><label class="form-check-label small">Menyala</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="lampu_on" value="PADAM" <?= $chk('LampuMenyala', 'PADAM') ?>><label class="form-check-label small">Mati</label></div></div></div>
                        </div>
                        <hr class="my-2 border-success-subtle">
                        <div class="row g-2">
                            <div class="col-12"><span class="small text-success fw-semibold">Cek PLNSTATUS (Pasca Nyala):</span></div>
                            <div class="col-4"><label class="form-label small">Respon (Detik)</label><input type="number" name="detik_status_on" class="form-control form-control-sm" value="<?= htmlspecialchars($v('DetikStatusNyala', '4')) ?>"></div>
                            <div class="col-4"><label class="form-label small">Dijawab?</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_on" value="ADA" <?= $chk('JawabanStatusNyala', 'ADA', true) ?>><label class="form-check-label small">Ya</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="jawab_status_on" value="TIDAK" <?= $chk('JawabanStatusNyala', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                            <div class="col-4"><label class="form-label small">Isi Status</label><div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_on" value="SESUAI" <?= $chk('IsiStatusNyala', 'SESUAI', true) ?>><label class="form-check-label small">Sesuai</label></div><div class="form-check form-check-inline"><input class="form-check-input test-radio" type="radio" name="isi_status_on" value="TIDAK" <?= $chk('IsiStatusNyala', 'TIDAK') ?>><label class="form-check-label small">Tidak</label></div></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label small fw-semibold">Catatan Pengujian</label>
                <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan khusus..."><?= htmlspecialchars($v('Catatan')) ?></textarea>
            </div>

            <?php if($is_draft): ?>
                <div class="mt-4 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-outline-primary px-5 fw-bold"><i class="bi bi-save me-2"></i>Simpan Draft Halaman Ini</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- TOMBOL 2: KEMBALI ATAU FINALISASI KESELURUHAN -->
    <div class="card-footer bg-light border-top p-4 d-flex justify-content-between align-items-center">
        <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar" class="btn btn-white border text-secondary px-4 fw-semibold shadow-sm"><i class="bi bi-arrow-left me-1"></i> Batal / Kembali ke Daftar Draft</a>
        
        <?php if($is_draft && count($items) > 0): ?>
            <form id="formFinalisasi" action="modules/pemasangan/proses_pengujian.php?action=execute_form" method="POST" class="m-0">
                <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($formulir['NoFormulir']) ?>">
                <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="confirmFinalisasi(<?= count($items) ?>)">
                    <i class="bi bi-cloud-check-fill me-2"></i> Finalisasi Seluruh Formulir (<?= count($items) ?> Alat)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });

    const dataSim = <?= json_encode($list_sim) ?>;
    const dataModem = <?= json_encode($list_modem) ?>;

    function triggerAutoFill() {
        const simVal = $('#simid_select').val();
        const sim = dataSim.find(s => s.SimId === simVal);
        $('#nomor_kontak').val(sim && sim.NomorAkun ? sim.NomorAkun : '');

        const modemVal = $('#imei_modem_select').val();
        const modem = dataModem.find(m => m.ImeiModem === modemVal);
        let adminNum = modem && modem.NomorAkun ? modem.NomorAkun : '';
        if (adminNum.startsWith('+62')) adminNum = adminNum.substring(3);
        else if (adminNum.startsWith('0')) adminNum = adminNum.substring(1);
        $('#no_admin_set').val(adminNum ? '+62 ' + adminNum : '');
    }

    $('#simid_select, #imei_modem_select').on('change', triggerAutoFill);
    triggerAutoFill(); 

    // PERBAIKAN 4: JavaScript Logika Pembalik Status (Layak / Tidak Layak)
    $('.alat-toggle').on('change', function() {
        if($(this).val() === 'TIDAK LAYAK') {
            // Semua opsi diset menjadi Gagal/Tidak/Padam
            $('input[name="set_admin"][value="GAGAL"]').prop('checked', true);
            $('input[name="del_admin"][value="GAGAL"]').prop('checked', true);
            $('input[name="list_admin"][value="GAGAL"]').prop('checked', true);
            $('input[name="reset_admin"][value="GAGAL"]').prop('checked', true);
            $('input[name="cek_phasa"][value="TIDAK"]').prop('checked', true);
            $('input[name="lampu_awal"][value="TIDAK"]').prop('checked', true);
            $('input[name="jawab_status_awal"][value="TIDAK"]').prop('checked', true);
            $('input[name="isi_status_awal"][value="TIDAK"]').prop('checked', true);
            
            // Ketika diputus (plnoff), lampu seharusnya Menyala (sebagai cadangan/error)
            $('input[name="jawab_plnoff"][value="TIDAK"]').prop('checked', true);
            $('input[name="lampu_off"][value="MENYALA"]').prop('checked', true); 
            $('input[name="jawab_status_off"][value="TIDAK"]').prop('checked', true);
            $('input[name="isi_status_off"][value="TIDAK"]').prop('checked', true);
            
            // Ketika dinyalakan (plnon), lampu seharusnya Padam (error)
            $('input[name="jawab_plnon"][value="TIDAK"]').prop('checked', true);
            $('input[name="lampu_on"][value="PADAM"]').prop('checked', true);
            $('input[name="jawab_status_on"][value="TIDAK"]').prop('checked', true);
            $('input[name="isi_status_on"][value="TIDAK"]').prop('checked', true);
        } else {
            // Kembali ke default normal (Berhasil/Sesuai/Ada/Menyala)
            $('input[name="set_admin"][value="BERHASIL"]').prop('checked', true);
            $('input[name="del_admin"][value="BERHASIL"]').prop('checked', true);
            $('input[name="list_admin"][value="BERHASIL"]').prop('checked', true);
            $('input[name="reset_admin"][value="BERHASIL"]').prop('checked', true);
            $('input[name="cek_phasa"][value="ADA"]').prop('checked', true);
            $('input[name="lampu_awal"][value="AKTIF"]').prop('checked', true);
            $('input[name="jawab_status_awal"][value="ADA"]').prop('checked', true);
            $('input[name="isi_status_awal"][value="SESUAI"]').prop('checked', true);
            
            $('input[name="jawab_plnoff"][value="ADA"]').prop('checked', true);
            $('input[name="lampu_off"][value="PADAM"]').prop('checked', true);
            $('input[name="jawab_status_off"][value="ADA"]').prop('checked', true);
            $('input[name="isi_status_off"][value="SESUAI"]').prop('checked', true);
            
            $('input[name="jawab_plnon"][value="ADA"]').prop('checked', true);
            $('input[name="lampu_on"][value="MENYALA"]').prop('checked', true);
            $('input[name="jawab_status_on"][value="ADA"]').prop('checked', true);
            $('input[name="isi_status_on"][value="SESUAI"]').prop('checked', true);
        }
    });
});

function confirmFinalisasi(totalAlat) {
    Swal.fire({
        title: 'Finalisasi Formulir?',
        text: "Formulir akan di-Finalisasi (Total " + totalAlat + " Alat). Formulir yang difinalisasi tidak bisa diedit lagi. Lanjutkan?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Finalisasi!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formFinalisasi').submit();
        }
    });
}

function confirmDeleteHalaman(url) {
    Swal.fire({
        title: 'Hapus Halaman Alat?',
        text: "Data pengujian untuk alat ini akan dihapus dari sistem.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>