<?php
// templates/pengaturan/tata_kelola.php -> TAMPILAN (view)
// Variabel dari controller: $judul, $gambar_ada, $pdf_ada, $url_gambar, $url_pdf, $nama_gambar, $nama_pdf
?>
<div class="container-fluid" style="max-width: 1100px;">
    <h4 class="fw-bold mb-1"><i class="bi bi-book me-2"></i><?= htmlspecialchars($judul) ?></h4>
    <p class="text-muted mb-4">Panduan tata kelola S41.</p>

    <!-- Gambar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body text-center">
            <?php if ($gambar_ada): ?>
                <img src="<?= htmlspecialchars($url_gambar) ?>" alt="<?= htmlspecialchars($judul) ?>"
                     class="img-fluid rounded">
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-image me-1"></i> Gambar belum tersedia.
                    Letakkan file <code>assets/img/<?= htmlspecialchars($nama_gambar) ?></code>.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PDF -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Dokumen PDF</span>
            <?php if ($pdf_ada): ?>
                <span>
                    <a href="<?= htmlspecialchars($url_pdf) ?>" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka di tab baru
                    </a>
                    <a href="<?= htmlspecialchars($url_pdf) ?>" download
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Unduh
                    </a>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if ($pdf_ada): ?>
                <iframe src="<?= htmlspecialchars($url_pdf) ?>#toolbar=1&navpanes=0"
                        title="<?= htmlspecialchars($judul) ?>"
                        style="width: 100%; height: 80vh; border: 0;">
                    Browser tidak mendukung tampilan PDF.
                    <a href="<?= htmlspecialchars($url_pdf) ?>">Unduh PDF</a>.
                </iframe>
            <?php else: ?>
                <div class="alert alert-warning m-3">
                    <i class="bi bi-file-earmark-x me-1"></i> File PDF belum tersedia.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>