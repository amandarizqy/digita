<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Dashboard Perencanaan</h4>
</div>

<div class="row g-4">
    <!-- Kolom Input -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-primary border-top border-3">
            <div class="card-header bg-white fw-bold text-primary">
                <i class="bi bi-box-arrow-in-right me-2"></i> Input
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="?module=perencanaan&action=upload_riwayat" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-upload text-muted me-2"></i> Unggah Riwayat Pelunasan
                    </a>
                    <a href="?module=perencanaan&action=input_kepentingan" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-pencil-square text-muted me-2"></i> Input Tingkat Kepentingan
                    </a>
                    <a href="?module=perencanaan&action=input_survey" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-ui-checks text-muted me-2"></i> Input Survey SR
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Proses -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-warning border-top border-3">
            <div class="card-header bg-white fw-bold text-warning">
                <i class="bi bi-gear-fill me-2"></i> Proses
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="?module=perencanaan&action=proses_risiko" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-diagram-3 text-muted me-2"></i> Klasifikasi Level Risiko
                    </a>
                    <a href="?module=perencanaan&action=proses_prioritas" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-sort-numeric-down text-muted me-2"></i> Skala Prioritas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Monitoring -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-success border-top border-3">
            <div class="card-header bg-white fw-bold text-success">
                <i class="bi bi-display me-2"></i> Monitoring
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="?module=perencanaan&action=hasil_kepentingan" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-bar-chart-line text-muted me-2"></i> Hasil tingkat kepentingan
                    </a>
                    <a href="?module=perencanaan&action=hasil_survey" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-clipboard-data text-muted me-2"></i> Hasil survei SR
                    </a>
                    <a href="?module=perencanaan&action=hasil_risiko" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-shield-check text-muted me-2"></i> Hasil klasifikasi level risiko
                    </a>
                    <a href="?module=perencanaan&action=hasil_prioritas" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-list-stars text-muted me-2"></i> Hasil skala prioritas
                    </a>
                    <a href="?module=perencanaan&action=data_riwayat" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-lightbulb text-muted me-2"></i> Data Riwayat Pelunasan
                </div>
            </div>
        </div>
    </div>
</div>