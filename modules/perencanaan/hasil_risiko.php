<?php
// modules/perencanaan/hasil_risiko.php
// Monitoring: hasil klasifikasi level risiko (read-only).
//
// HAK AKSES BERDASARKAN TINGKAT UNIT PENGGUNA (UPI -> AP -> UP)
//   UPI (am.ui, sf.ui, mb.ui) : melihat SEMUA data di UPI-nya, dengan filter AP lalu UP.
//   AP  (am.ap, tl.ap, sf.ap) : melihat data di AP-nya, dengan filter UP di bawahnya.
//   UP  (ml.up, tl.up, sf.up) : TANPA filter, hanya data sesuai kode unit UP-nya.
// Tingkat ditentukan dari kolom unit akun di master_pengguna: punya UnitUp -> UP;
// punya UnitAp (tanpa UnitUp) -> AP; hanya UnitUpi -> UPI.

$pesan_error = "";
$periode     = pr_periode();

// ------------------------------------------------------------------
// 0. Unit & tingkat akses pengguna (session)
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
            $sel_ap  = (string) ($unitAp ?? '');
            $sel_up  = (string) $unitUp;
            $nama_ap = $unitAp ? $nama_singkat('master_ap', 'UnitAp', $unitAp) : '';
            $nama_up = $nama_singkat('master_up', 'UnitUp', $unitUp);

        } elseif ($tingkat_akses === 'AP') {
            $sel_ap  = (string) $unitAp;
            $nama_ap = $nama_singkat('master_ap', 'UnitAp', $unitAp);
            $st = $conn->prepare("SELECT UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) FROM master_up WHERE UnitAp = :ap AND StatusData = 'AKTIF' ORDER BY UnitUp");
            $st->execute([':ap' => $unitAp]);
            $opsi_up = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            $sel_up  = trim((string) ($_GET['unit_up'] ?? ''));
            if ($sel_up !== '' && !isset($opsi_up[$sel_up])) $sel_up = '';
            $nama_up = $sel_up !== '' ? $opsi_up[$sel_up] : '';

        } else { // UPI
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
// 1. Filter & paginasi (parameter `hal`; `page` dipakai router utama untuk nama modul)
// ------------------------------------------------------------------
$keyword      = trim($_GET['keyword'] ?? '');
$prioritas    = trim($_GET['prioritas'] ?? '');
$posisi_sr    = trim($_GET['posisi_sr'] ?? '');
$level_dampak = trim($_GET['level_dampak'] ?? '');
$tingkat      = in_array($_GET['tingkat'] ?? '', ['tinggi', 'sedang', 'rendah'], true) ? $_GET['tingkat'] : '';

$rentang_tingkat = ['tinggi' => [7, 9], 'sedang' => [4, 6], 'rendah' => [1, 3]];

$limit = 15;
$hal   = max(1, (int) ($_GET['hal'] ?? 1));

$list_hasil = [];
$total_rows = 0;
$offset     = 0;
$per_skala  = array_fill(1, 9, 0);

try {
    [$sc_k_sql, $sc_k_par] = $build_scope('k');

    // 2. Ringkasan per skala di wilayah terpilih (dipakai KPI & pill tingkat)
    $st = $conn->prepare("
        SELECT k.SkalaPrioritas, COUNT(*) FROM kategorisasi_risiko k
        WHERE k.Periode = :p AND k.SkalaPrioritas BETWEEN 1 AND 9 AND $sc_k_sql
        GROUP BY k.SkalaPrioritas
    ");
    $st->execute(array_merge([':p' => $periode], $sc_k_par));
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$n, $j]) {
        $per_skala[(int) $n] = (int) $j;
    }

    // 3. Kondisi filter
    $where  = ["k.Periode = :periode", "k.SkalaPrioritas IS NOT NULL AND k.SkalaPrioritas != 0", $sc_k_sql];
    $params = array_merge([':periode' => $periode], $sc_k_par);

    if ($keyword !== '') {
        $where[] = "(d.Idpel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1'] = $params[':kw2'] = '%' . $keyword . '%';
    }
    if ($prioritas !== '' && ctype_digit($prioritas)) {
        $where[] = "k.SkalaPrioritas = :prioritas";
        $params[':prioritas'] = (int) $prioritas;
    }
    if ($tingkat !== '') {
        $where[] = "k.SkalaPrioritas BETWEEN :t_min AND :t_max";
        [$params[':t_min'], $params[':t_max']] = $rentang_tingkat[$tingkat];
    }
    if ($posisi_sr === '0' || $posisi_sr === '1') {
        $where[] = "k.PosisiSR = :posisi_sr";
        $params[':posisi_sr'] = $posisi_sr;
    }
    if (in_array($level_dampak, ['SANGAT RENDAH', 'MODERAT', 'SANGAT TINGGI'], true)) {
        $where[] = "k.LevelDampak = :level_dampak";
        $params[':level_dampak'] = $level_dampak;
    }
    $where_sql = " WHERE " . implode(' AND ', $where);

    // 4. Total & data
    $st = $conn->prepare("SELECT COUNT(*) FROM dil d INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel $where_sql");
    $st->execute($params);
    $total_rows = (int) $st->fetchColumn();
    $hal        = min($hal, max(1, (int) ceil($total_rows / $limit)));
    $offset     = ($hal - 1) * $limit;

    $st = $conn->prepare("
        SELECT d.Idpel, d.NamaPelanggan, k.Periode, k.PosisiSR, k.LevelKeterlambatan, k.LevelKepentingan,
               k.LevelKemungkinan, k.LevelDampak, k.Kuadran, k.SkalaPrioritas,
               COALESCE(NULLIF(mu.SingkatanNama, ''), k.UnitUp) AS NamaUnitUp
        FROM dil d
        INNER JOIN kategorisasi_risiko k ON d.Idpel = k.IdPel
        LEFT JOIN master_up mu ON mu.UnitUp = k.UnitUp
        $where_sql
        ORDER BY k.SkalaPrioritas DESC, d.Idpel ASC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $key => $val) {
        $st->bindValue($key, $val);
    }
    $st->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $list_hasil = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Gagal memuat hasil evaluasi risiko: " . $e->getMessage();
    $offset = 0;
}

$total_p_tinggi = $per_skala[7] + $per_skala[8] + $per_skala[9];
$total_p_sedang = $per_skala[4] + $per_skala[5] + $per_skala[6];
$total_p_rendah = $per_skala[1] + $per_skala[2] + $per_skala[3];

include __DIR__ . '/../../templates/perencanaan/hasil_risiko.php';