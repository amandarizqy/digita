<?php
// functions/csrf.php
// Perlindungan CSRF sederhana berbasis token di session.
// - Token disisipkan otomatis ke semua <form method="POST"> lewat templates/layouts/base.php.
// - Controller memanggil csrf_verify() sebelum memproses POST.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** true bila token yang dikirim (POST atau header) cocok dengan token session. */
function csrf_valid(): bool
{
    $kirim = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($kirim) && $kirim !== '' && hash_equals(csrf_token(), $kirim);
}

/** Hentikan request bila bukan POST yang sah atau token salah. */
function csrf_verify(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_valid()) {
        http_response_code(403);
        echo "<script>alert('Permintaan ditolak: sesi tidak valid atau kedaluwarsa. Muat ulang halaman lalu coba lagi.'); window.history.back();</script>";
        exit;
    }
}