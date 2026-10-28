<?php
// includes/navbar.php
require_once __DIR__ . '/../config/database.php'; // menyediakan $conn (PDO)

$kode_hak  = $_SESSION['KodeHak'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? '';

// 1. Ambil kode 3 tingkat unit dari master_pengguna (fallback ke session jika query gagal)
$kode_unit_user = [
    'UPI' => $_SESSION['UnitUpi'] ?? '',
    'AP'  => $_SESSION['UnitAp']  ?? '',
    'UP'  => $_SESSION['UnitUp']  ?? '',
];
if ($nama_akun !== '') {
    try {
        $stmt_kode = $conn->prepare("SELECT UnitUpi, UnitAp, UnitUp FROM master_pengguna WHERE NamaAkun = :akun LIMIT 1");
        $stmt_kode->execute([':akun' => $nama_akun]);
        if ($row = $stmt_kode->fetch(PDO::FETCH_ASSOC)) {
            $kode_unit_user = ['UPI' => $row['UnitUpi'], 'AP' => $row['UnitAp'], 'UP' => $row['UnitUp']];
        }
    } catch (PDOException $e) {
        error_log('navbar kode unit: ' . $e->getMessage());
    }
}
// Buang yang NULL / kosong supaya tidak ditampilkan
$kode_unit_user = array_filter($kode_unit_user, fn($v) => $v !== null && trim((string) $v) !== '');

// 2. Tentukan tingkat unit untuk nama singkatan berdasarkan kode hak.
// Default = UPI (AM.UI, MB.UI, SF.UI, SF.VD, SA.KP). Nama tabel/kolom ditulis tetap (bukan dari input user).
$tabel_unit = 'master_upi';
$kolom_unit = 'UnitUpi';
$kunci_unit = 'UPI';

if (in_array($kode_hak, ['AM.AP', 'TL.AP', 'SF.AP'], true)) {
    $tabel_unit = 'master_ap';
    $kolom_unit = 'UnitAp';
    $kunci_unit = 'AP';
} elseif (in_array($kode_hak, ['AM.UP', 'ML.UP', 'TL.UP', 'SF.UP'], true)) {
    $tabel_unit = 'master_up';
    $kolom_unit = 'UnitUp';
    $kunci_unit = 'UP';
}

$nama_unit = '';
$kode_unit = isset($kode_unit_user[$kunci_unit]) ? trim((string) $kode_unit_user[$kunci_unit]) : '';
if ($kode_unit !== '') {
    try {
        $stmt_unit = $conn->prepare("SELECT SingkatanNama FROM {$tabel_unit} WHERE {$kolom_unit} = :kode LIMIT 1");
        $stmt_unit->execute([':kode' => $kode_unit]);
        $nama_unit = (string) ($stmt_unit->fetchColumn() ?: '');
    } catch (PDOException $e) {
        error_log('navbar unit: ' . $e->getMessage());
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h6 text-secondary">
            <i class="bi bi-shield-lock-fill text-primary"></i> Digitalisasi Tata Kelola Aset Shuntrip
        </span>
        <div class="d-flex align-items-center">
            <?php if ($nama_unit !== ''): ?>
                <span class="badge bg-primary me-2 px-3 py-2">
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($nama_unit); ?>
                </span>
            <?php endif; ?>
            <?php foreach ($kode_unit_user as $label => $kode): ?>
                <span class="badge bg-light text-dark border me-2 px-2 py-2">
                    <?= htmlspecialchars($label) ?>: <?= htmlspecialchars(trim((string) $kode)) ?>
                </span>
            <?php endforeach; ?>
            <span class="badge bg-info text-dark me-3 px-3 py-2">
                Role: <?= htmlspecialchars($kode_hak !== '' ? $kode_hak : 'USER'); ?>
            </span>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="fw-semibold"><?= htmlspecialchars($_SESSION['NamaPengguna'] ?? 'User Aktif'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser1">
                    <!-- ABSOLUTE PATH UNTUK LOGOUT -->
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>