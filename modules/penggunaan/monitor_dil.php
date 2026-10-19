<?php
// modules/penggunaan/monitor_dil.php  -  Monitoring DIL (tabel `dil`)
// Fitur: cari, filter status / nama kosong, paginasi, edit nama & status (hanya akun yang berhak Input DIL).
// IdPel adalah kunci, jadi tidak dapat diubah.

$pesan_sukses = '';
$pesan_error  = '';

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

// Hanya akun dengan hak Input DIL yang boleh mengubah data; yang lain hanya melihat.
$boleh_edit = pga_boleh('input_dil');

// ---- filter daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['AKTIF', 'TIDAK', 'KOSONG'], true) ? $_GET['status'] : '';
$limit   = (int) ($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 10;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

// ---- pesan hasil proses sebelumnya (post-redirect-get) ----
if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? '';
    $pesan_error  = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// PROSES FORM (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi  = $_POST['aksi'] ?? '';
    $idpel = trim((string) ($_POST['idpel'] ?? ''));

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$boleh_edit) {
        $pesan_error = 'Anda tidak memiliki wewenang untuk mengubah data DIL.';
    } elseif ($aksi === 'edit') {
        $nama = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nama'] ?? '')));
        $baru = ($_POST['status_baru'] ?? '') === 'AKTIF' ? 'AKTIF' : 'TIDAK';
        if ($nama === '' || mb_strlen($nama) > 50) {
            $pesan_error = 'Nama pelanggan wajib diisi (maksimal 50 karakter).';
        } else {
            try {
                $st = $conn->prepare("UPDATE dil SET NamaPelanggan = ?, StatusData = ?, WaktuData = NOW() WHERE IdPel = ?");
                $st->execute([$nama, $baru, $idpel]);
                $pesan_sukses = 'Data IdPel <strong>' . pg_e($idpel) . '</strong> berhasil diperbarui.';
            } catch (PDOException $e) {
                $pesan_error = 'Gagal memperbarui data: ' . pg_e($e->getMessage());
            }
        }
    } elseif ($aksi === 'status') {
        $baru = ($_POST['status_baru'] ?? '') === 'AKTIF' ? 'AKTIF' : 'TIDAK';
        try {
            $st = $conn->prepare("UPDATE dil SET StatusData = ?, WaktuData = NOW() WHERE IdPel = ?");
            $st->execute([$baru, $idpel]);
            $pesan_sukses = 'IdPel <strong>' . pg_e($idpel) . '</strong> ' . ($baru === 'AKTIF' ? 'diaktifkan.' : 'dinonaktifkan.');
        } catch (PDOException $e) {
            $pesan_error = 'Gagal mengubah status: ' . pg_e($e->getMessage());
        }
    }

    // Post-Redirect-Get: kembali ke daftar dengan filter yang sama
    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('monitor_dil', [
            'keyword' => $_POST['keyword'] ?? '', 'status' => $_POST['filter_status'] ?? '',
            'limit' => ($_POST['limit'] ?? '') == 10 ? '' : ($_POST['limit'] ?? ''), 'hal' => ($_POST['hal'] ?? '') > 1 ? $_POST['hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA
// ---------------------------------------------------------
$kpi = ['total' => 0, 'AKTIF' => 0, 'TIDAK' => 0, 'KOSONG' => 0];
$data = [];
$total_data = 0;
try {
    foreach ($conn->query("SELECT StatusData, COUNT(*) c FROM dil GROUP BY StatusData")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($kpi[$r['StatusData']])) $kpi[$r['StatusData']] = (int) $r['c'];
        $kpi['total'] += (int) $r['c'];
    }
    $kpi['KOSONG'] = (int) $conn->query("SELECT COUNT(*) FROM dil WHERE NamaPelanggan IS NULL OR NamaPelanggan = ''")->fetchColumn();

    $where = []; $p = [];
    if ($keyword !== '') {
        $where[] = '(IdPel LIKE :k1 OR NamaPelanggan LIKE :k2)';
        $p[':k1'] = $p[':k2'] = '%' . $keyword . '%';
    }
    if ($status === 'KOSONG') {
        $where[] = "(NamaPelanggan IS NULL OR NamaPelanggan = '')";
    } elseif ($status !== '') {
        $where[] = 'StatusData = :st';
        $p[':st'] = $status;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $st = $conn->prepare("SELECT COUNT(*) FROM dil $w");
    $st->execute($p);
    $total_data = (int) $st->fetchColumn();
    $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

    $st = $conn->prepare("SELECT IdPel, NamaPelanggan, IndexPrioritas, StatusData, WaktuData FROM dil $w ORDER BY WaktuData DESC, IdPel LIMIT :lim OFFSET :off");
    foreach ($p as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
    $st->execute();
    $data = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = trim($pesan_error . ' Gagal memuat data DIL: ' . pg_e($e->getMessage()));
}

include __DIR__ . '/../../templates/penggunaan/monitor_dil.php';