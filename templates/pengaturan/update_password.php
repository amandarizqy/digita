<?php
// templates/pengaturan/update_password.php  -> TAMPILAN (view)
// Variabel dari controller: $errors (array), $success (bool), $csrf_token (string)
?>
<div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="w-100" style="max-width: 480px;">
        <div>
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-key-fill me-1"></i> Update Password
                </div>
                <div class="card-body">

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">Password berhasil diperbarui.</div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="password_lama">Password Lama</label>
                            <input type="password" class="form-control" id="password_lama"
                                   name="password_lama" autocomplete="current-password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password_baru">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru"
                                   name="password_baru" minlength="8" autocomplete="new-password" required>
                            <div class="form-text">Minimal 8 karakter, kombinasi huruf dan angka.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password_konfirm">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="password_konfirm"
                                   name="password_konfirm" minlength="8" autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Password
                        </button>
                    </form>

                    <hr>
                    <p class="small text-muted mb-0">
                        Lupa password lama? Hubungi administrator unit tingkat UID untuk mereset.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>