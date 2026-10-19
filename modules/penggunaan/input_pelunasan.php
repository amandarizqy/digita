<?php
// modules/penggunaan/input_pelunasan.php  -  Input Pelunasan (tabel `pelunasan_ap2t`)
// Kolom: IdPel CHAR(12) PK | ThBlRek CHAR(7) PK | TglBayar DATE | RpBK INT | RpTag BIGINT | UnitUp | UnitAp | UnitUpi | WaktuData
// Pelunasan adalah kelanjutan Input Saldo: IdPel + ThBlRek WAJIB sudah ada di tabel `dpp` (tagihan) dalam wilayah akun.
// RpTag diambil dari tagihan di dpp. Unit: UPI dari session; AP / UP dari session bila ada, bila tidak dari baris dpp-nya.
// Alur: (1) cari IdPel -> (2) pilih periode tagihan, isi TglBayar & RpBK -> simpan. Simpan ulang pada periode sama = memperbarui.

$pesan_sukses = '';
$pesan_error  = '';

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

if (!function_exists('pl_idpel')) {
    function pl_idpel($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || !preg_match('/^\d{1,12}$/', $v)) return null;
        return str_pad($v, 12, '0', STR_PAD_LEFT);       // nol di depan
    }
}

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// SIMPAN (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idpel  = pl_idpel($_POST['idpel'] ?? '');
    $bulan  = trim((string) ($_POST['thblrek'] ?? ''));
    $tgl    = trim((string) ($_POST['tglbayar'] ?? ''));
    $rpbk_s = trim((string) ($_POST['rpbk'] ?? '0'));

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga pelunasan tidak dapat disimpan.';
    } elseif ($idpel === null || $bulan === '') {
        $pesan_error = 'IdPel dan periode tagihan wajib dipilih.';
    } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        $pesan_error = 'Tanggal bayar tidak valid.';
    } elseif ($tgl > date('Y-m-d')) {
        $pesan_error = 'Tanggal bayar tidak boleh melewati hari ini.';
    } elseif ($rpbk_s === '' || !ctype_digit($rpbk_s) || (float) $rpbk_s > 4294967295) {
        $pesan_error = 'Biaya keterlambatan (RpBK) harus angka bulat tidak negatif.';
    } else {
        try {
            // tagihan harus ada di dpp pada wilayah akun
            $p = [':id' => $idpel, ':bl' => $bulan];
            $scope = pg_scope($u, 'd', 'Kode', $p);
            $st = $conn->prepare("SELECT d.ThBlRek, d.RpTag, d.KodeUp, d.KodeAp, d.KodeUpi FROM dpp d WHERE d.IdPel = :id AND d.ThBlRek = :bl AND $scope LIMIT 1");
            $st->execute($p);
            $tg = $st->fetch(PDO::FETCH_ASSOC);
            if (!$tg) {
                $pesan_error = 'Tagihan IdPel ' . pg_e($idpel) . ' periode ' . pg_e($bulan) . ' tidak ditemukan di Saldo (dpp) pada wilayah Anda. Input Saldo terlebih dahulu.';
            } elseif (strlen(trim((string) $tg['ThBlRek'])) > 7) {
                $pesan_error = 'ThBlRek tagihan lebih dari 7 karakter, tidak dapat disimpan ke tabel pelunasan.';
            } else {
                $up  = $u['up'] !== '' ? $u['up'] : $tg['KodeUp'];
                $ap  = $u['ap'] !== '' ? $u['ap'] : $tg['KodeAp'];
                $upi = $u['upi'];
                $st = $conn->prepare("SELECT TglBayar FROM pelunasan_ap2t WHERE IdPel = ? AND ThBlRek = ?");
                $st->execute([$idpel, $tg['ThBlRek']]);
                $sudah = $st->fetchColumn();

                $st = $conn->prepare("INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi, WaktuData)
                                      VALUES (?,?,?,?,?,?,?,?,NOW())
                                      ON DUPLICATE KEY UPDATE TglBayar = VALUES(TglBayar), RpBK = VALUES(RpBK), RpTag = VALUES(RpTag),
                                                              UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi), WaktuData = NOW()");
                $st->execute([$idpel, $tg['ThBlRek'], $tgl, (int) $rpbk_s, (int) $tg['RpTag'], $up, $ap, $upi]);
                $pesan_sukses = 'Pelunasan IdPel <strong>' . pg_e($idpel) . '</strong> periode <strong>' . pg_e($tg['ThBlRek']) . '</strong> '
                              . ($sudah ? 'berhasil diperbarui.' : 'berhasil disimpan.');
            }
        } catch (PDOException $e) {
            $pesan_error = 'Gagal menyimpan: ' . pg_e($e->getMessage());
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_pelunasan', ['idpel' => $idpel ?? '']));
        exit;
    }
}

// ---------------------------------------------------------
// CARI TAGIHAN (GET ?idpel=)
// ---------------------------------------------------------
$idpel_cari = pl_idpel($_GET['idpel'] ?? '');
$cari_salah = ($_GET['idpel'] ?? '') !== '' && $idpel_cari === null;
$nama_cari  = '';
$tagihan    = [];
if ($unit_siap && $idpel_cari !== null) {
    try {
        $p = [':id' => $idpel_cari];
        $scope = pg_scope($u, 'd', 'Kode', $p);
        $st = $conn->prepare("SELECT d.ThBlRek, d.RpTag, p.TglBayar, p.RpBK
                              FROM dpp d
                              LEFT JOIN pelunasan_ap2t p ON p.IdPel = d.IdPel AND p.ThBlRek = d.ThBlRek
                              WHERE d.IdPel = :id AND $scope
                              ORDER BY d.ThBlRek DESC");
        $st->execute($p);
        $tagihan = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $conn->prepare("SELECT NamaPelanggan FROM dil WHERE IdPel = ?");
        $st->execute([$idpel_cari]);
        $nama_cari = (string) $st->fetchColumn();
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal mencari tagihan: ' . pg_e($e->getMessage()));
    }
}

$boleh_monitor = pga_boleh('monitor_pelunasan');

include __DIR__ . '/../../templates/penggunaan/input_pelunasan.php';