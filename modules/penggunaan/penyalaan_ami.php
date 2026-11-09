<?php
// modules/penggunaan/penyalaan_ami.php  -  Penyalaan AMI (tabel trx_order_nyala_ami)
// Form tiket: Sesi ID + [Create], lalu data tiket dan 8 tombol bantu (salin / buka PPOB / Lunas / buka MDMS / Ready / Gagal).
// Halaman ini berdiri sendiri (tidak memakai file bersama); tampilan: templates/penggunaan/penyalaan_ami.php
// Alur: isi / pilih ID tiket putus (tabel trx_order_putus_ami) -> data disalin dari tiket putus -> [Create] membuat tiket nyala + Sesi ID baru.
// Dibatasi wilayah akun (pg_scope). NamaAkun tiket = akun login.

// ---- PENGATURAN ----
$halaman    = 'penyalaan_ami';
$judul      = 'Penyalaan AMI';
$tabel      = 'trx_order_nyala_ami';
$pk         = 'TiketNyalaAmi';
$pk_len     = 21;
$prefix_id  = 'NAI';
$fk          = 'TiketPutusAmi';          // kolom rujukan ke tiket putus
$putus_tabel = 'trx_order_putus_ami';
// Tautan tombol. Isi URL lengkap bila sudah ada; '#' = belum diatur (tombol hanya menampilkan pesan).
$link = ['ppob' => '#', 'mdms' => '#', 'lunas' => '#'];

$pesan_sukses = $pesan_error = $pesan_info = '';
if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf      = $_SESSION['pg_csrf'];
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$akun      = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
$putus     = false;

// ---- pembantu lokal ----
$teks = fn($v, int $maks) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $maks);
/** ID Sesi: S + yymmddHHMMSS + '-' + 6 hex. ID tiket: prefix + YmdHis + hex acak, tepat $pk_len karakter. */
$buat_sesi = fn() => 'S' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
$buat_id   = function () use ($prefix_id, $pk_len) {
    $dasar = $prefix_id . date('YmdHis');
    return $dasar . strtoupper(substr(bin2hex(random_bytes(8)), 0, max(0, $pk_len - strlen($dasar))));
};
/** Nama, nomor meter (NomorKwh) dan nomor HP dari DIL. */
$ambil_dil = function (string $idpel) use ($conn): array {
    $st = $conn->prepare("SELECT Nama, NomorKwh, NomorHp FROM dil WHERE IdPel = ?");
    $st->execute([$idpel]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['nama' => trim((string) ($r['Nama'] ?? '')), 'meter' => trim((string) ($r['NomorKwh'] ?? '')), 'hp' => trim((string) ($r['NomorHp'] ?? ''))];
};

/**
 * Cari bahan form dari ID tiket putus (tabel trx_order_putus_ami): NoTul, IdPel, periode, meter, HP & unit disalin dari tiket putus.
 * Dibatasi wilayah akun.
 * @return array ['ok' => bool, 'pesan' => string, 'data' => array]
 */
$cari = function (string $kunci) use ($conn, $u, $tabel, $pk, $fk, $putus_tabel, $ambil_dil): array {
    $kunci = trim($kunci);
    if ($kunci === '') return ['ok' => false, 'pesan' => '', 'data' => []];
    $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
    $st = $conn->prepare("SELECT t.NoTul, t.IdPel, t.ThblRek, t.NomorMeter, t.NomorHp, t.StatusData, t.UnitUp, t.UnitAp, t.UnitUpi FROM `$putus_tabel` t WHERE t.`$fk` = :k AND $sc LIMIT 1");
    $st->execute([':k' => $kunci] + $p);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return ['ok' => false, 'pesan' => 'Tiket putus tidak ditemukan di wilayah Anda.', 'data' => []];
    $dil = $ambil_dil((string) $r['IdPel']);
    $st  = $conn->prepare("SELECT `$pk` FROM `$tabel` WHERE `$fk` = ? AND StatusData = 'AKTIF' LIMIT 1");
    $st->execute([$kunci]);
    $ada = $st->fetchColumn();
    $pesan = $ada ? "Sudah ada tiket nyala AKTIF untuk tiket putus ini ($ada)." : ($r['StatusData'] !== 'AKTIF' ? 'Tiket putus ini berstatus TIDAK aktif.' : '');
    return ['ok' => true, 'pesan' => $pesan, 'data' => [
        'notul' => (string) $r['NoTul'], 'idpel' => $r['IdPel'], 'thblrek' => (string) $r['ThblRek'], 'nama' => $dil['nama'],
        'meter' => trim((string) $r['NomorMeter']) ?: $dil['meter'], 'hp' => trim((string) $r['NomorHp']) ?: $dil['hp'],
        'unit' => [$r['UnitUp'], $r['UnitAp'], $r['UnitUpi']], 'duplikat' => (bool) $ada,
    ]];
};

// ---- hasil proses sebelumnya (post-redirect-get) ----
if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? ''; $pesan_info = $fl['info'] ?? '';
}

// ---- pencarian bahan form (JSON, dipanggil JavaScript halaman) ----
if (isset($_GET['cari'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode($unit_siap ? $cari((string) $_GET['cari']) : ['ok' => false, 'pesan' => 'Unit akun belum diatur.', 'data' => []], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'pesan' => 'Gagal mencari: ' . $e->getMessage(), 'data' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- isian form (dipulihkan bila Create ditolak) ----
$isi = ['jenis' => 'PREVENTIF', 'notul' => '', 'tiket_putus' => '', 'meter' => '', 'hp' => ''];

// ---------------------------------------------------------
// POST: create | ready | gagal
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $tiket_tuju = '';
    $info_post  = '';
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Unit akun Anda belum diatur, sehingga tidak bisa membuat tiket.';
    } elseif ($aksi === 'create') {
        $isi['jenis']       = strtoupper(trim((string) ($_POST['jenis'] ?? 'PREVENTIF')));
        $isi['notul']       = $teks($_POST['notul'] ?? '', 30);
        $isi['tiket_putus'] = $teks($_POST['tiket_putus'] ?? '', 23);
        $isi['meter']       = $teks($_POST['meter'] ?? '', 15);
        $isi['hp']          = $teks($_POST['hp'] ?? '', 20);
        $kunci = $isi['tiket_putus'];
        try {
            if (!in_array($isi['jenis'], ['PREVENTIF', 'KOREKTIF'], true)) {
                $pesan_error = 'Jenis tiket harus PREVENTIF atau KOREKTIF.';
            } elseif ($kunci === '') {
                $pesan_error = 'Tiket putus wajib diisi.';
            } else {
                $h = $cari($kunci);
                if (!$h['ok'])                  $pesan_error = pg_e($h['pesan']);
                elseif ($h['data']['duplikat']) $pesan_error = pg_e($h['pesan']) . ' Tiket baru tidak dibuat.';
                else {
                    $d     = $h['data'];
                    $meter = $isi['meter'] !== '' ? $isi['meter'] : $teks($d['meter'], 15);
                    $hp    = $isi['hp'] !== ''    ? $isi['hp']    : $teks($d['hp'], 20);
                    $st    = $conn->prepare("INSERT INTO `$tabel` (`$pk`, JenisTiket, `$fk`, IdPel, ThblRek, NomorMeter, NomorHp, Sesi, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', NOW())");
                    $sesi   = $buat_sesi();
                    $dibuat = '';
                    for ($i = 0; $i < 5 && $dibuat === ''; $i++) {
                        $id = $buat_id();
                        try {
                            $st->execute([$id, $isi['jenis'], $kunci, $d['idpel'], $teks($d['thblrek'], 8), $meter, $hp, $sesi, $akun, $d['unit'][0], $d['unit'][1], $d['unit'][2]]);
                            $dibuat = $id;
                        } catch (PDOException $e) {
                            if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;     // ID kembar -> coba ID baru; error lain -> lempar
                        }
                    }
                    if ($dibuat === '') {
                        $pesan_error = 'Gagal membuat ID tiket unik (atau kunci tiket sudah dipakai). Coba lagi.';
                    } else {
                        $pesan_sukses = 'Tiket <strong>' . pg_e($dibuat) . '</strong> berhasil dibuat dengan Sesi ID <strong>' . pg_e($sesi) . '</strong>.';
                        $tiket_tuju   = $dibuat;
                        if ($d['hp'] === '' && $hp === '')       $info_post = 'Nomor HP kosong (belum ada di DIL).';
                        if ($d['meter'] === '' && $meter === '') $info_post = trim($info_post . ' Nomor meter kosong (belum ada di DIL).');
                    }
                }
            }
        } catch (PDOException $e) {
            $pesan_error = ($e->errorInfo[1] ?? 0) === 1452
                ? 'Tiket ditolak database: data rujukan (tiket putus / akun / unit) tidak valid.'
                : 'Gagal menyimpan tiket: ' . pg_e($e->getMessage());
        } catch (Throwable $e) {
            $pesan_error = 'Gagal memproses: ' . pg_e($e->getMessage());
        }
    } elseif ($aksi === 'ready' || $aksi === 'gagal') {
        $t = $teks($_POST['tiket'] ?? '', 23);
        try {
            $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
            $st = $conn->prepare("SELECT t.StatusData FROM `$tabel` t WHERE t.`$pk` = :id AND $sc LIMIT 1");
            $st->execute([':id' => $t] + $p);
            $status = $st->fetchColumn();
            if ($status === false)       $pesan_error = 'Tiket tidak ditemukan di wilayah Anda.';
            elseif ($status !== 'AKTIF') $pesan_error = 'Tiket ini sudah tidak aktif.';
            else {
                // >>> TITIK SAMBUNG: simpan hasil tindakan di sini ($aksi = 'ready' | 'gagal', $t = ID tiket, $akun = akun login).
                //     Belum ada tujuan penyimpanan (tabel tiket hanya punya StatusData), jadi belum ada data yang diubah.
                $label = ($aksi === 'ready' ? 'Ready ' : 'Gagal ') . 'ON';
                $pesan_info = "Tombol <strong>$label</strong> untuk tiket <strong>" . pg_e($t) . '</strong> belum terhubung ke penyimpanan (tujuan tabel/kolom belum ditentukan), sehingga belum ada data yang diubah.';
            }
            $tiket_tuju = $t;
        } catch (Throwable $e) {
            $pesan_error = 'Gagal memproses: ' . pg_e($e->getMessage());
        }
    } else {
        $pesan_error = 'Aksi tidak dikenal.';
    }

    // Post-Redirect-Get: berhasil -> buka tiketnya. Ditolak -> tetap di form dengan isian terpulihkan (tanpa redirect).
    if ($pesan_error === '' && !headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => '', 'info' => trim($pesan_info . ' ' . $info_post)];
        header('Location: ' . pg_url($halaman, ['tiket' => $tiket_tuju]));
        exit;
    }
    if ($aksi !== 'create') $isi = ['jenis' => 'PREVENTIF', 'notul' => '', 'tiket_putus' => '', 'meter' => '', 'hp' => ''];
}

// ---------------------------------------------------------
// TAMPIL: tiket yang dibuka (?tiket=ID) atau form kosong
// ---------------------------------------------------------
$row = null;
$tiket_id = $teks($_GET['tiket'] ?? '', 23);
if ($unit_siap && $tiket_id !== '') {
    try {
        $p  = []; $sc = pg_scope($u, 't', 'Unit', $p);
        $st = $conn->prepare("SELECT t.*, p.NoTul AS NoTulTampil FROM `$tabel` t LEFT JOIN `$putus_tabel` p ON p.`$fk` = t.`$fk` WHERE t.`$pk` = :id AND $sc LIMIT 1");
        $st->execute([':id' => $tiket_id] + $p);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) $row['nama'] = $ambil_dil((string) $row['IdPel'])['nama'];
        else      $pesan_error = trim($pesan_error . ' Tiket ' . pg_e($tiket_id) . ' tidak ditemukan di wilayah Anda.');
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat tiket: ' . pg_e($e->getMessage()));
    }
}

// Saran isian: tiket putus AKTIF (wilayah akun) yang belum punya tiket nyala AKTIF
$daftar_putus = [];
if ($unit_siap && !$row) {
    try {
        $p  = []; $sc = pg_scope($u, 'p', 'Unit', $p);
        $st = $conn->prepare("SELECT p.`$fk` AS id, p.IdPel FROM `$putus_tabel` p
                              WHERE p.StatusData = 'AKTIF' AND $sc
                                AND NOT EXISTS (SELECT 1 FROM `$tabel` n WHERE n.`$fk` = p.`$fk` AND n.StatusData = 'AKTIF')
                              ORDER BY p.WaktuData DESC LIMIT 50");
        $st->execute($p);
        $daftar_putus = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* saran saja; abaikan bila gagal */ }
}

include __DIR__ . '/../../templates/penggunaan/penyalaan_ami.php';