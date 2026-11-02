<?php
// functions/pemeliharaan_helper.php
// Fungsi bersama modul Pemeliharaan (skema: frm_order, frm_pk, frm_pk_detil, frm_ba, frm_ba_detil, master_barang).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';

const PH_PETUGAS = ['IPM.001', 'IPM.002'];          
const PH_LAMPU   = ['MENYALA' => 'MENYALA (BERFUNGSI)', 'TIDAK' => 'TIDAK (PERLU MUTASI)'];  
const PH_POSISI  = ['PANGKAL', 'TENGAH', 'UJUNG'];  
const PH_STATUS  = ['AKTIF', 'TIDAK'];
const PH_FOTO_MAKS_BYTE = 5 * 1024 * 1024;           // 5 MB
const PH_PER_HALAMAN = 25;

function ph_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ph_role(): string
{
    return strtoupper(trim((string) ($_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? $_SESSION['level'] ?? 'GUEST')));
}

function ph_role_semua_unit(): bool
{
    $r = ph_role();
    $akh = substr($r, -3);
    return $r === 'SA.KP' || $r === 'SA' || $akh === '.UI' || $akh === '.VD' || strpos($r, 'ADMIN') !== false;
}

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

function ph_menu_dari_sub(string $sub): string
{
    return strpos($sub, 'monitoring_') === 0 ? 'monitoring' : 'eksekusi';
}

/* ---------- Validasi ---------- */
function ph_tanggal($v): ?string
{
    $v = trim((string) $v);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

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
        return [null, null, 'Koordinat tidak valid.'];
    }
    return [substr($lat, 0, 50), substr($long, 0, 50), null];
}

function ph_foto_baca(string $field): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Unggah foto gagal.'];
    }
    if ($f['size'] > PH_FOTO_MAKS_BYTE) {
        return [null, 'Ukuran foto maksimal 5 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'Foto harus berformat JPG, PNG, atau WEBP.'];
    }
    return [file_get_contents($f['tmp_name']), null];
}

/** Pesan error untuk pengguna: RuntimeException apa adanya; error database hanya dirinci untuk SA.KP. */
function ph_pesan_error(Throwable $e): string
{
    if ($e instanceof RuntimeException) {
        return $e->getMessage();
    }
    return ph_role() === 'SA.KP' ? 'Kesalahan database: ' . $e->getMessage() : 'Kesalahan database.';
}

/** Nilai aman untuk sel Excel (HTML .xls): escape HTML; sel nomor diberi style teks di export_excel.php */
function ph_xl($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ph_duplikat(Throwable $e): bool
{
    return $e instanceof PDOException
        && ((string) $e->getCode() === '23000')
        && (stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false);
}

function ph_modem_punya_nama(PDO $conn): bool
{
    static $ada = null;
    if ($ada === null) {
        try {
            $conn->query('SELECT Nama FROM master_modem LIMIT 0');
            $ada = true;
        } catch (Throwable $e) {
            $ada = false;
        }
    }
    return $ada;
}

function ph_sql_nama_modem(PDO $conn, string $alias = 'mm'): string
{
    return ph_modem_punya_nama($conn) ? "$alias.Nama" : 'NULL';
}

function ph_label_modem($imei, $nama): string
{
    $imei = trim((string) $imei);
    $nama = trim((string) $nama);
    return $nama !== '' ? "$nama — $imei" : $imei;
}

function ph_nomor_sim(PDO $conn, $sim_id): ?string
{
    static $cache = [];
    $sim_id = trim((string) ($sim_id ?? ''));
    if ($sim_id === '') return null;
    if (!array_key_exists($sim_id, $cache)) {
        try {
            $st = $conn->prepare('SELECT NomorAkun FROM master_nomor WHERE SimId = ? LIMIT 1');
            $st->execute([$sim_id]);
            $v = trim((string) $st->fetchColumn());
            $cache[$sim_id] = $v !== '' ? substr($v, 0, 15) : null;
        } catch (Throwable $e) {
            $cache[$sim_id] = null;
        }
    }
    return $cache[$sim_id];
}

const PH_DIL_KOLOM_NAMA = ['Nama', 'NamaPelanggan', 'NamaPel', 'NamaPlg'];
function ph_nama_pelanggan(PDO $conn, $id_pel): ?string
{
    static $cache = [];
    $id_pel = trim((string) ($id_pel ?? ''));
    if ($id_pel === '') return null;
    if (!array_key_exists($id_pel, $cache)) {
        $cache[$id_pel] = null;
        foreach (PH_DIL_KOLOM_NAMA as $kol) {
            try {
                $st = $conn->prepare("SELECT `$kol` FROM dil WHERE IdPel = ? LIMIT 1");
                $st->execute([$id_pel]);
                $v = trim((string) $st->fetchColumn());
                if ($v !== '') {
                    $cache[$id_pel] = $v;
                    break;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }
    return $cache[$id_pel];
}

function ph_no_form(PDO $conn, string $tabel, string $kolom, string $unit_up, string $tgl, string $akhiran): string
{
    $unit_up = trim($unit_up);
    if (!preg_match('/^[A-Za-z0-9]{5}$/', $unit_up)) {
        throw new RuntimeException('Kode UP pelanggan tidak valid.');
    }
    $tgl8 = date('Ymd', strtotime($tgl));
    $st = $conn->prepare("SELECT MAX(CAST(SUBSTR(`$kolom`, 6, 3) AS UNSIGNED)) FROM `$tabel` WHERE `$kolom` LIKE ?");
    $st->execute([$unit_up . '___' . $tgl8 . $akhiran]);
    $n = (int) $st->fetchColumn() + 1;
    return $unit_up . str_pad((string) $n, 3, '0', STR_PAD_LEFT) . $tgl8 . $akhiran;
}

function ph_barang_pelanggan(PDO $conn, string $id_pel): array
{
    [$sc, $sp] = ph_scope('mb');
    $st = $conn->prepare("SELECT mb.NoRef, mb.SimId, mb.ImeiModem, mb.UnitUp, mb.UnitAp, mb.UnitUpi
                          FROM master_barang mb
                          WHERE mb.IdPel = ? AND mb.StatusData = 'AKTIF'" . $sc . " ORDER BY mb.NoRef ASC LIMIT 1");
    $st->execute(array_merge([$id_pel], $sp));
    $b = $st->fetch(PDO::FETCH_ASSOC);
    if (!$b) {
        throw new RuntimeException("ID Pelanggan $id_pel tidak memiliki perangkat aktif di unit Anda.");
    }
    return $b;
}

/**
 * UTAMA: Membuat FPK dengan langsung menyertakan string literal 'PEMELIHARAAN' dan 'TIDAK'.
 */
function ph_buat_pk(PDO $conn, array $p, array $b): string
{
    $up  = trim((string) ($b['UnitUp'] ?? ''));
    $ap  = trim((string) ($b['UnitAp'] ?? ''));
    $upi = trim((string) ($b['UnitUpi'] ?? ''));
    if ($up === '' || $ap === '' || $upi === '') {
        throw new RuntimeException('Kode unit pada master barang belum lengkap.');
    }
    $no_fpk = ph_no_form($conn, 'frm_pk', 'NoFpk', $up, $p['tgl'], '-F.F');

    // Menggunakan string langsung 'PEMELIHARAAN' dan 'TIDAK' di dalam query SQL
    $stIns = $conn->prepare("INSERT INTO frm_pk 
        (NoFpk, TglFpk, JenisPk, IdPel, LongDil, LatDil, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
        VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, ?, ?, ?, 'TIDAK', NOW())");
    
    $stIns->execute([
        $no_fpk, 
        $p['tgl'], 
        $p['id_pel'], 
        $p['long'], 
        $p['lat'], 
        $p['nama_akun'], 
        $up, 
        $ap, 
        $upi
    ]);

    $nomor = ph_nomor_sim($conn, $b['SimId'] ?? '');
    $conn->prepare("INSERT INTO frm_pk_detil (NoFpk, StatusBarang, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, 'DIL', ?, ?, ?, ?, ?)")
         ->execute([$no_fpk, $b['NoRef'], $b['SimId'], $b['ImeiModem'], $nomor, $nomor]);

    return $no_fpk;
}

function ph_kolom_cari(PDO $conn, bool $ada_pk = true, bool $ada_order = false, array $kolom_perangkat = ['bd.NoRef', 'bd.SimId', 'bd.ImeiModem']): array
{
    $k = [$ada_pk ? 'p.NoFpk' : 'b.NoFpk', 'b.NoFba', $ada_pk ? 'p.IdPel' : 'b.IdPel', 'b.KdPetugas'];
    if ($ada_order) $k[] = 'o.NoFormOrder';
    $k = array_merge($k, $kolom_perangkat);
    if (ph_modem_punya_nama($conn)) $k[] = 'mm.Nama';
    return $k;
}

function ph_cari_sql(array $kolom): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '' || !$kolom) return ['', []];
    $like = '%' . addcslashes($q, '%_\\') . '%';
    return [' AND (' . implode(' LIKE ? OR ', $kolom) . ' LIKE ?)', array_fill(0, count($kolom), $like)];
}

function ph_daftar(PDO $conn, string $select, string $from, string $where, array $params, array $opt): array
{
    [$sc, $sp] = ph_scope($opt['alias']);
    $where .= $sc;
    $params = array_merge($params, $sp);

    [$cari_sql, $cari_par] = ph_cari_sql($opt['cari'] ?? []);
    $where .= $cari_sql;
    $params = array_merge($params, $cari_par);
    $q = trim((string) ($_GET['q'] ?? ''));

    $st = $conn->prepare("SELECT COUNT(*) FROM $from WHERE 1=1 $where");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $total_hal = max(1, (int) ceil($total / PH_PER_HALAMAN));
    $hal = min(max(1, (int) ($_GET['p'] ?? 1)), $total_hal);
    $off = ($hal - 1) * PH_PER_HALAMAN;

    $st = $conn->prepare("SELECT $select FROM $from WHERE 1=1 $where ORDER BY {$opt['urut']} LIMIT " . PH_PER_HALAMAN . " OFFSET $off");
    $st->execute($params);
    return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $hal, $total_hal, $q];
}

function ph_kotak_cari(string $sub, string $q, string $placeholder = 'Cari no form, idpel, no ref...'): string
{
    return '<form method="GET" action="index.php" class="input-group" style="width:320px;">'
        . '<input type="hidden" name="page" value="pemeliharaan"><input type="hidden" name="menu" value="' . ph_e(ph_menu_dari_sub($sub)) . '"><input type="hidden" name="sub" value="' . ph_e($sub) . '">'
        . '<input type="text" name="q" value="' . ph_e($q) . '" class="form-control bg-light small" placeholder="' . ph_e($placeholder) . '">'
        . '<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>'
        . ($q !== '' ? '<a class="btn btn-outline-secondary" href="index.php?page=pemeliharaan&menu=' . ph_e(ph_menu_dari_sub($sub)) . '&sub=' . ph_e(urlencode($sub)) . '"><i class="bi bi-x-lg"></i></a>' : '')
        . '</form>';
}

function ph_pager(string $sub, int $total, int $hal, int $total_hal, string $q): string
{
    $mulai = $total > 0 ? ($hal - 1) * PH_PER_HALAMAN + 1 : 0;
    $akhir = min($hal * PH_PER_HALAMAN, $total);
    $url = function (int $p) use ($sub, $q) {
        return 'index.php?page=pemeliharaan&menu=' . ph_menu_dari_sub($sub) . '&sub=' . urlencode($sub) . '&p=' . $p . ($q !== '' ? '&q=' . urlencode($q) : '');
    };
    $h = '<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-2 pt-3">'
        . '<div class="small text-muted">Menampilkan <b>' . $mulai . '</b> - <b>' . $akhir . '</b> dari <b>' . $total . '</b> data</div>';
    if ($total_hal > 1) {
        $h .= '<nav><ul class="pagination pagination-sm mb-0 gap-1">';
        $h .= '<li class="page-item ' . ($hal <= 1 ? 'disabled' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($hal - 1)) . '">&laquo;</a></li>';
        $dari = max(1, $hal - 2);
        $ke   = min($total_hal, $hal + 2);
        for ($i = $dari; $i <= $ke; $i++) {
            $h .= '<li class="page-item ' . ($i === $hal ? 'active' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($i)) . '">' . $i . '</a></li>';
        }
        $h .= '<li class="page-item ' . ($hal >= $total_hal ? 'disabled' : '') . '"><a class="page-link rounded-3" href="' . ph_e($url($hal + 1)) . '">&raquo;</a></li>';
        $h .= '</ul></nav>';
    }
    return $h . '</div>';
}

function ph_badge_relay($relay): string
{
    $relay = strtoupper(trim((string) $relay));
    if ($relay === '') return '<span class="badge bg-secondary-subtle text-secondary">BELUM</span>';
    if ($relay === 'TIDAK' || strpos($relay, 'TIDAK') !== false) return '<span class="badge bg-danger-subtle text-danger">TIDAK BERFUNGSI</span>';
    return '<span class="badge bg-success-subtle text-success">BERFUNGSI</span>';
}

function ph_badge_status($status): string
{
    return (($status ?? '') === 'AKTIF')
        ? '<span class="badge bg-success-subtle text-success">SELESAI</span>'
        : '<span class="badge bg-warning-subtle text-warning-emphasis">PENDING</span>';
}

function ph_akses_order(): bool { return in_array(ph_role(), ['SA.KP', 'TL.UP'], true); }
function ph_akses_mutasi(): bool { return in_array(ph_role(), ['SA.KP', 'SF.UP'], true); }
function ph_akses_monitoring(): bool { return true; }