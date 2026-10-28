<?php
// functions/pemeliharaan_helper.php
// Fungsi bersama modul Pemeliharaan & Helper Paginasi: pembatasan unit, validasi input, foto, nomor form, paginasi.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';

const PH_PETUGAS = ['IPM.001', 'IPM.002'];          // daftar petugas (satu tempat; dipakai form & validasi)
const PH_RELAY   = ['BERFUNGSI', 'TIDAK BERFUNGSI'];
const PH_POSISI  = ['PANGKAL', 'TENGAH', 'UJUNG'];
const PH_STATUS  = ['AKTIF', 'TIDAK'];
const PH_FOTO_MAKS_BYTE = 5 * 1024 * 1024;           // 5 MB per foto
const PH_PER_HALAMAN = 25;

function ph_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ph_role(): string
{
    return (string) ($_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST');
}

/** Role yang membaca seluruh data (tanpa batas unit). */
function ph_role_semua_unit(): bool
{
    $r = ph_role();
    $akh = substr($r, -3);
    return $r === 'SA.KP' || $akh === '.UI' || $akh === '.VD';
}

/**
 * Potongan WHERE pembatas unit sesuai role pengguna.
 * .AP -> hanya UP3-nya, .UP -> hanya ULP-nya. Role tingkat UI / SA.KP / vendor -> semua.
 * Bila akun tingkat AP/UP belum punya unit di session -> tidak melihat apa pun (aman).
 * @return array [sql (diawali " AND ..." atau ''), params]
 */
function ph_scope(string $alias = 'p'): array
{
    if (ph_role_semua_unit()) {
        return ['', []];
    }
    $akh = substr(ph_role(), -3);
    $ap  = trim((string) ($_SESSION['UnitAp'] ?? ''));
    $up  = trim((string) ($_SESSION['UnitUp'] ?? ''));
    if ($akh === '.AP' && $ap !== '') {
        return [" AND $alias.UnitAp = ?", [$ap]];
    }
    if ($akh === '.UP' && $up !== '') {
        return [" AND $alias.UnitUp = ?", [$up]];
    }
    return [' AND 1=0', []];
}

/** Ambil satu baris frm_pemeliharaan (tanpa BLOB) bila ada DAN berada dalam unit pengguna. */
function ph_ambil_baris(PDO $conn, string $no_form): ?array
{
    [$sc, $sp] = ph_scope('p');
    $st = $conn->prepare("SELECT p.NoFormPemeliharaan, p.NoFormOrder, p.NoRef, p.SimId, p.ImeiModem, p.UnitAp, p.UnitUp FROM frm_pemeliharaan p WHERE p.NoFormPemeliharaan = ?" . $sc);
    $st->execute(array_merge([$no_form], $sp));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/* ---------- Pesan sekali tampil (Post/Redirect/Get) ---------- */
function ph_flash_set(string $tipe, string $pesan): void
{
    $_SESSION['ph_flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ph_flash_ambil(): ?array
{
    $f = $_SESSION['ph_flash'] ?? null;
    unset($_SESSION['ph_flash']);
    return $f;
}

function ph_flash_html(): string
{
    $f = ph_flash_ambil();
    if (!$f) {
        return '';
    }
    $ok = ($f['tipe'] === 'sukses');
    return '<div class="alert alert-' . ($ok ? 'success' : 'danger') . ' alert-dismissible fade show rounded-3" role="alert">'
        . '<i class="bi ' . ($ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') . ' me-2"></i>' . ph_e($f['pesan'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function ph_redirect(string $sub, string $tipe, string $pesan): void
{
    ph_flash_set($tipe, $pesan);
    header('Location: index.php?page=pemeliharaan&menu=' . ph_menu_dari_sub($sub) . '&sub=' . urlencode($sub));
    exit;
}

/** Menu utama (eksekusi / monitoring) untuk sebuah sub-halaman. */
function ph_menu_dari_sub(string $sub): string
{
    return strpos($sub, 'monitoring_') === 0 ? 'monitoring' : 'eksekusi';
}

/* ---------- Validasi ---------- */
function ph_pilih(string $nilai, array $boleh, string $nama): array
{
    $v = strtoupper(trim($nilai));
    return in_array($v, $boleh, true) ? [$v, null] : [null, "$nama tidak valid."];
}

function ph_tanggal($v): ?string
{
    $v = trim((string) $v);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/** @return array [lat|null, long|null, error|null]  (keduanya diisi atau keduanya kosong) */
function ph_koordinat($lat, $long): array
{
    $lat  = str_replace(',', '.', trim((string) $lat));
    $long = str_replace(',', '.', trim((string) $long));
    if ($lat === '' && $long === '') {
        return [null, null, null];
    }
    if ($lat === '' || $long === '') {
        return [null, null, 'Latitude dan longitude harus diisi berpasangan.'];
    }
    if (!is_numeric($lat) || !is_numeric($long) || abs((float) $lat) > 90 || abs((float) $long) > 180) {
        return [null, null, 'Koordinat tidak valid (latitude -90..90, longitude -180..180).'];
    }
    return [substr($lat, 0, 50), substr($long, 0, 50), null];
}

/**
 * Baca & validasi satu file foto dari $_FILES.
 * @return array [blob|null, error|null]  (tidak ada file -> [null, null])
 */
function ph_foto_baca(string $field): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Unggah foto gagal (kode ' . (int) $f['error'] . '). Pastikan ukuran foto tidak melebihi batas server.'];
    }
    if ($f['size'] > PH_FOTO_MAKS_BYTE) {
        return [null, 'Ukuran foto maksimal ' . (PH_FOTO_MAKS_BYTE / 1048576) . ' MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'Foto harus berformat JPG, PNG, atau WEBP.'];
    }
    return [file_get_contents($f['tmp_name']), null];
}

/* ---------- Nomor form ---------- */
function ph_nomor_dasar(PDO $conn, string $unit_up, int $jumlah): array
{
    $unit = trim($unit_up);
    if (!preg_match('/^[A-Za-z0-9]{5}$/', $unit)) {
        throw new RuntimeException('Kode ULP tidak valid untuk nomor form.');
    }
    $tgl = date('Ymd');
    $maks = 0;
    foreach ([['frm_order_har', 'NoFormOrder', '-F.G'], ['frm_pemeliharaan', 'NoFormPemeliharaan', '-F.H']] as [$tabel, $kolom, $akhir]) {
        $st = $conn->prepare("SELECT MAX(SUBSTR($kolom, 6, 3)) FROM $tabel WHERE $kolom LIKE ?");
        $st->execute([$unit . '___' . $tgl . $akhir]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && ctype_digit((string) $v)) {
            $maks = max($maks, (int) $v);
        }
    }

    if ($maks + $jumlah > 999) {
        throw new RuntimeException('Nomor urut harian untuk ULP ini sudah habis (maksimal 999 per hari).');
    }
    $hasil = [];
    for ($i = 1; $i <= $jumlah; $i++) {
        $hasil[] = $unit . str_pad((string) ($maks + $i), 3, '0', STR_PAD_LEFT) . $tgl;
    }
    return $hasil;
}

function ph_duplikat(Throwable $e): bool
{
    return $e instanceof PDOException
        && ((string) $e->getCode() === '23000')
        && (stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false);
}

/* ---------- Pencarian + paginasi sisi server ---------- */
function ph_cari_sql(): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        return ['', []];
    }
    $like  = '%' . addcslashes($q, '%_\\') . '%';
    $kolom = ['p.NoFormPemeliharaan', 'p.NoFormOrder', 'p.IdPel', 'p.NoRef', 'p.SimId', 'p.ImeiModem', 'p.KdPetugas', 'p.Keterangan'];
    return [' AND (' . implode(' LIKE ? OR ', $kolom) . ' LIKE ?)', array_fill(0, count($kolom), $like)];
}

/**
 * Fungsi Paginasi Dinamis
 * Menerima parameter $order_by opsional agar tidak crash jika tabel tidak memiliki kolom NoFormPemeliharaan.
 */
function ph_daftar(PDO $conn, string $select, string $from, string $where = '', array $params = [], string $order_by = 'p.WaktuData DESC'): array
{
    [$sc, $sp] = ph_scope('p');
    $where .= $sc;
    $params = array_merge($params, $sp);

    [$cari_sql, $cari_par] = ph_cari_sql();
    $where .= $cari_sql;
    $params = array_merge($params, $cari_par);
    $q = trim((string) ($_GET['q'] ?? ''));

    $st = $conn->prepare("SELECT COUNT(*) FROM $from WHERE 1=1 $where");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $total_hal = max(1, (int) ceil($total / PH_PER_HALAMAN));
    $hal = min(max(1, (int) ($_GET['p'] ?? 1)), $total_hal);
    $off = ($hal - 1) * PH_PER_HALAMAN;

    $st = $conn->prepare("SELECT $select FROM $from WHERE 1=1 $where ORDER BY $order_by LIMIT " . PH_PER_HALAMAN . " OFFSET $off");
    $st->execute($params);
    return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $hal, $total_hal, $q];
}

function ph_kotak_cari(string $sub, string $q, string $placeholder = 'Cari no form, idpel, no ref, SIM, IMEI...'): string
{
    return '<form method="GET" action="index.php" class="input-group" style="width:300px;">'
        . '<input type="hidden" name="page" value="pemeliharaan"><input type="hidden" name="sub" value="' . ph_e($sub) . '">'
        . '<input type="text" name="q" value="' . ph_e($q) . '" class="form-control bg-light small" placeholder="' . ph_e($placeholder) . '">'
        . '<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>'
        . ($q !== '' ? '<a class="btn btn-outline-secondary" href="index.php?page=pemeliharaan&sub=' . ph_e(urlencode($sub)) . '" title="Hapus pencarian"><i class="bi bi-x-lg"></i></a>' : '')
        . '</form>';
}

function ph_pager(string $sub, int $total, int $hal, int $total_hal, string $q): string
{
    $mulai = $total > 0 ? ($hal - 1) * PH_PER_HALAMAN + 1 : 0;
    $akhir = min($hal * PH_PER_HALAMAN, $total);
    $url = function (int $p) use ($sub, $q) {
        return 'index.php?page=pemeliharaan&sub=' . urlencode($sub) . '&p=' . $p . ($q !== '' ? '&q=' . urlencode($q) : '');
    };
    $h = '<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-2 pt-3">'
        . '<div class="small text-muted">Menampilkan <b>' . $mulai . '</b> - <b>' . $akhir . '</b> dari <b>' . $total . '</b> data</div>';
    if ($total_hal > 1) {
        $h .= '<nav><ul class="pagination pagination-sm mb-0 gap-1">';
        $h .= '<li class="page-item ' . ($hal <= 1 ? 'disabled' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($hal - 1)) . '">&laquo;</a></li>';
        $dari = max(1, $hal - 2);
        $ke   = min($total_hal, $hal + 2);
        if ($dari > 1) {
            $h .= '<li class="page-item"><a class="page-link rounded-3" href="' . ph_e($url(1)) . '">1</a></li>' . ($dari > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
        }
        for ($i = $dari; $i <= $ke; $i++) {
            $h .= '<li class="page-item ' . ($i === $hal ? 'active' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($i)) . '">' . $i . '</a></li>';
        }
        if ($ke < $total_hal) {
            $h .= ($ke < $total_hal - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '')
                . '<li class="page-item"><a class="page-link rounded-3" href="' . ph_e($url($total_hal)) . '">' . $total_hal . '</a></li>';
        }
        $h .= '<li class="page-item ' . ($hal >= $total_hal ? 'disabled' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($hal + 1)) . '">&raquo;</a></li>';
        $h .= '</ul></nav>';
    }
    return $h . '</div>';
}

/** 
 * Fungsi Badge Relay yang mengenali penamaan status dari master_barang
 */
function ph_badge_relay($relay): string
{
    $relay = strtoupper(trim((string) $relay));
    
    // Mengecek jika string berisi kata BERFUNGSI, MENYALA, atau NC (Normal Closed)
    if ($relay === 'BERFUNGSI' || strpos($relay, 'MENYALA') !== false || strpos($relay, 'NC') !== false) {
        return '<span class="badge bg-success-subtle text-success">BERFUNGSI</span>';
    }
    
    // Mengecek jika string berisi TIDAK BERFUNGSI atau PADAM
    if ($relay === 'TIDAK BERFUNGSI' || strpos($relay, 'PADAM') !== false) {
        return '<span class="badge bg-danger-subtle text-danger">TIDAK BERFUNGSI</span>';
    }
    
    return '<span class="badge bg-secondary-subtle text-secondary">BELUM DIISI</span>';
}

function ph_badge_status($status): string
{
    return (($status ?? '') === 'AKTIF')
        ? '<span class="badge bg-success-subtle text-success">SELESAI</span>'
        : '<span class="badge bg-warning-subtle text-warning-emphasis">PENDING</span>';
}

/* ---------- Hak akses per sub-menu ---------- */
function ph_akses_order(): bool
{
    return in_array(ph_role(), ['SA.KP', 'TL.UP'], true);
}

function ph_akses_mutasi(): bool
{
    return in_array(ph_role(), ['SA.KP', 'SF.UP'], true);
}

function ph_akses_monitoring(): bool
{
    return in_array(ph_role(), ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'], true);
}

function ph_tombol_export(string $type, string $q): string
{
    $url = BASE_URL . '/modules/pemeliharaan/export_excel.php?type=' . urlencode($type) . ($q !== '' ? '&q=' . urlencode($q) : '');
    return '<iframe id="ph-unduh" style="display:none" title="unduh"></iframe>'
         . '<button type="button" class="btn btn-outline-success rounded-3 fw-medium" data-url="' . ph_e($url) . '" '
         . 'onclick="document.getElementById(\'ph-unduh\').src=this.dataset.url">'
         . '<i class="bi bi-file-earmark-excel me-1"></i> Export Excel</button>';
}

function ph_xl($v): string
{
    $v = (string) ($v ?? '');
    if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) {
        $v = "'" . $v;
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}