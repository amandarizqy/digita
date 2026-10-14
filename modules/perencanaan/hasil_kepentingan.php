<?php
// modules/perencanaan/hasil_kepentingan.php
// Monitoring: hasil Tingkat Kepentingan pelanggan (kolom LevelKepentingan pada kategorisasi_risiko).
//
// HAK AKSES BERDASARKAN TINGKAT UNIT PENGGUNA (UPI -> AP -> UP)
//   UPI (am.ui, sf.ui, mb.ui) : melihat SEMUA data di UPI-nya, dengan filter AP lalu UP.
//   AP  (am.ap, tl.ap, sf.ap) : melihat data di AP-nya, dengan filter UP di bawahnya.
//   UP  (ml.up, tl.up, sf.up) : TANPA filter, hanya data sesuai kode unit UP-nya.
// Tingkat ditentukan dari kolom unit akun di master_pengguna: punya UnitUp -> UP;
// punya UnitAp (tanpa UnitUp) -> AP; hanya UnitUpi -> UPI.

$pesan_error = "";
$periode     = pr_periode();

$keyword = trim($_GET['keyword'] ?? '');
$level   = in_array($_GET['level'] ?? '', ['RENDAH', 'MODERAT', 'TINGGI'], true) ? $_GET['level'] : '';
$limit   = in_array((int) ($_GET['limit'] ?? 0), [10, 25, 50, 100], true) ? (int) $_GET['limit'] : 10;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

// ------------------------------------------------------------------
// 1. Unit & tingkat akses pengguna (session)
// ------------------------------------------------------------------
$namaAkun = $_SESSION['NamaAkun'] ?? '';
$unitUp   = NULL;
$unitAp   = NULL;
$unitUpi  = NULL;

if (!empty($namaAkun)) {
    try {
        $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $st->execute([$namaAkun]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
            $unitUp  = $u['UnitUp']  ?: NULL;
            $unitAp  = $u['UnitAp']  ?: NULL;
            $unitUpi = $u['UnitUpi'] ?: NULL;
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal memuat unit session: " . $e->getMessage();
    }
}

if     (!empty($unitUp))  $tingkat_akses = 'UP';
elseif (!empty($unitAp))  $tingkat_akses = 'AP';
elseif (!empty($unitUpi)) $tingkat_akses = 'UPI';
else                      $tingkat_akses = null;

if ($tingkat_akses === null) {
    $pesan_error = trim($pesan_error . " Akun Anda belum memiliki unit (UPI/AP/UP), sehingga data tidak dapat ditampilkan.");
}

// ------------------------------------------------------------------
// 2. Pilihan filter unit sesuai tingkat akses (kode terkunci / dipilih)
// ------------------------------------------------------------------
$sel_ap   = '';
$sel_up   = '';
$opsi_ap  = [];   // [kode => SingkatanNama]
$opsi_up  = [];
$nama_upi = $unitUpi;
$nama_ap  = '';
$nama_up  = '';

$nama_singkat = function (string $tabel, string $kolom, $kode) use ($conn) {
    $st = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), $kolom) FROM $tabel WHERE $kolom = ?");
    $st->execute([$kode]);
    return $st->fetchColumn() ?: $kode;
};

if ($tingkat_akses !== null) {
    try {
        if (!empty($unitUpi)) $nama_upi = $nama_singkat('master_upi', 'UnitUpi', $unitUpi);

        if ($tingkat_akses === 'UP') {
            // Tanpa filter: terkunci pada unit sendiri
            $sel_ap  = (string) ($unitAp ?? '');
            $sel_up  = (string) $unitUp;
            $nama_ap = $unitAp ? $nama_singkat('master_ap', 'UnitAp', $unitAp) : '';
            $nama_up = $nama_singkat('master_up', 'UnitUp', $unitUp);

        } elseif ($tingkat_akses === 'AP') {
            // AP terkunci; boleh filter UP di bawahnya
            $sel_ap  = (string) $unitAp;
            $nama_ap = $nama_singkat('master_ap', 'UnitAp', $unitAp);
            $st = $conn->prepare("SELECT UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) FROM master_up WHERE UnitAp = :ap AND StatusData = 'AKTIF' ORDER BY UnitUp");
            $st->execute([':ap' => $unitAp]);
            $opsi_up = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            $sel_up  = trim((string) ($_GET['unit_up'] ?? ''));
            if ($sel_up !== '' && !isset($opsi_up[$sel_up])) $sel_up = '';
            $nama_up = $sel_up !== '' ? $opsi_up[$sel_up] : '';

        } else { // UPI: semua data di UPI-nya, filter AP lalu UP
            $st = $conn->prepare("SELECT UnitAp, COALESCE(NULLIF(SingkatanNama, ''), UnitAp) FROM master_ap WHERE UnitUpi = :upi AND StatusData = 'AKTIF' ORDER BY UnitAp");
            $st->execute([':upi' => $unitUpi]);
            $opsi_ap = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            $sel_ap  = trim((string) ($_GET['unit_ap'] ?? ''));
            if ($sel_ap !== '' && !isset($opsi_ap[$sel_ap])) $sel_ap = '';
            $nama_ap = $sel_ap !== '' ? $opsi_ap[$sel_ap] : '';

            if ($sel_ap !== '') {
                $st = $conn->prepare("SELECT UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) FROM master_up WHERE UnitAp = :ap AND StatusData = 'AKTIF' ORDER BY UnitUp");
                $st->execute([':ap' => $sel_ap]);
                $opsi_up = $st->fetchAll(PDO::FETCH_KEY_PAIR);
                $sel_up  = trim((string) ($_GET['unit_up'] ?? ''));
                if ($sel_up !== '' && !isset($opsi_up[$sel_up])) $sel_up = '';
                $nama_up = $sel_up !== '' ? $opsi_up[$sel_up] : '';
            }
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . " Gagal memuat daftar unit: " . $e->getMessage());
    }
}

$scope_label = ($nama_upi ?: '-')
             . ($nama_ap !== '' ? ' › ' . $nama_ap : ($tingkat_akses === 'UPI' ? ' › Semua AP' : ''))
             . ($nama_up !== '' ? ' › ' . $nama_up : (in_array($tingkat_akses, ['UPI', 'AP'], true) && ($tingkat_akses === 'AP' || $sel_ap !== '') ? ' › Semua UP' : ''));

// Kondisi SQL wilayah pada kolom UnitUpi/UnitAp/UnitUp milik alias tertentu
$build_scope = function (string $a) use ($tingkat_akses, $unitUpi, $sel_ap, $sel_up) {
    if ($tingkat_akses === null) return ['1 = 0', []];
    $c = []; $p = [];
    if (!empty($unitUpi)) { $c[] = "$a.UnitUpi = :s_upi"; $p[':s_upi'] = $unitUpi; }
    if ($sel_ap !== '')   { $c[] = "$a.UnitAp = :s_ap";   $p[':s_ap']  = $sel_ap; }
    if ($sel_up !== '')   { $c[] = "$a.UnitUp = :s_up";   $p[':s_up']  = $sel_up; }
    return [$c ? implode(' AND ', $c) : '1 = 1', $p];
};

// ------------------------------------------------------------------
// 3. Data
// ------------------------------------------------------------------
$per_level  = ['RENDAH' => 0, 'MODERAT' => 0, 'TINGGI' => 0];
$total_dil  = 0;
$list_hasil = [];
$total_rows = 0;
$offset     = 0;

try {
    // Populasi pelanggan di wilayah terpilih: DIL tidak punya kolom unit, jadi unit
    // pelanggan diambil dari pelunasan_ap2t (semua periode).
    [$sc_u_sql, $sc_u_par] = $build_scope('u');
    $st = $conn->prepare("
        SELECT COUNT(*) FROM dil d
        INNER JOIN (SELECT IdPel, MAX(UnitUp) AS UnitUp, MAX(UnitAp) AS UnitAp, MAX(UnitUpi) AS UnitUpi
                    FROM pelunasan_ap2t GROUP BY IdPel) u ON u.IdPel = d.Idpel
        WHERE $sc_u_sql
    ");
    $st->execute($sc_u_par);
    $total_dil = (int) $st->fetchColumn();

    [$sc_k_sql, $sc_k_par] = $build_scope('k');

    $st = $conn->prepare("
        SELECT k.LevelKepentingan, COUNT(*) FROM kategorisasi_risiko k
        WHERE k.Periode = :p AND k.LevelKepentingan IN ('RENDAH','MODERAT','TINGGI') AND $sc_k_sql
        GROUP BY k.LevelKepentingan
    ");
    $st->execute(array_merge([':p' => $periode], $sc_k_par));
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$lv, $j]) {
        $per_level[$lv] = (int) $j;
    }

    $where  = ["k.Periode = :periode", "k.LevelKepentingan IN ('RENDAH','MODERAT','TINGGI')", $sc_k_sql];
    $params = array_merge([':periode' => $periode], $sc_k_par);
    if ($keyword !== '') {
        $where[] = "(k.IdPel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1'] = $params[':kw2'] = '%' . $keyword . '%';
    }
    if ($level !== '') {
        $where[] = "k.LevelKepentingan = :level";
        $params[':level'] = $level;
    }
    $where_sql = ' WHERE ' . implode(' AND ', $where);

    $st = $conn->prepare("SELECT COUNT(*) FROM kategorisasi_risiko k LEFT JOIN dil d ON d.Idpel = k.IdPel $where_sql");
    $st->execute($params);
    $total_rows = (int) $st->fetchColumn();
    $hal        = min($hal, max(1, (int) ceil($total_rows / $limit)));
    $offset     = ($hal - 1) * $limit;

    $st = $conn->prepare("
        SELECT k.IdPel, d.NamaPelanggan, k.LevelKepentingan, k.PosisiSR, k.SkalaPrioritas,
               k.UnitUp, COALESCE(NULLIF(mu.SingkatanNama, ''), k.UnitUp) AS NamaUnitUp
        FROM kategorisasi_risiko k
        LEFT JOIN dil d ON d.Idpel = k.IdPel
        LEFT JOIN master_up mu ON mu.UnitUp = k.UnitUp
        $where_sql
        ORDER BY FIELD(k.LevelKepentingan, 'TINGGI', 'MODERAT', 'RENDAH'), k.IdPel ASC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $list_hasil = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Gagal memuat hasil tingkat kepentingan: " . $e->getMessage();
}

include __DIR__ . '/../../templates/perencanaan/hasil_kepentingan.php';