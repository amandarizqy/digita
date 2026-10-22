<?php
// modules/perencanaan/hasil_survey.php
//
// HAK AKSES BERDASARKAN TINGKAT UNIT PENGGUNA (UPI -> AP -> UP)
//   UPI (am.ui, sf.ui, mb.ui) : melihat SEMUA data di UPI-nya, dengan filter AP lalu UP.
//   AP  (am.ap, tl.ap, sf.ap) : melihat data di AP-nya, dengan filter UP di bawahnya.
//   UP  (ml.up, tl.up, sf.up) : TANPA filter, hanya data sesuai kode unit UP-nya.
// Tingkat ditentukan dari kolom unit akun di master_pengguna: punya UnitUp -> UP;
// punya UnitAp (tanpa UnitUp) -> AP; hanya UnitUpi -> UPI.
// Aksi hapus juga dibatasi ke wilayah pengguna.

$pesan_sukses = "";
$pesan_error = "";

// ---------------------------------------------------------
// 0. UNIT & TINGKAT AKSES PENGGUNA (SESSION)
// ---------------------------------------------------------
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

// Pilihan filter unit sesuai tingkat akses
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

// Kondisi SQL wilayah pada kolom UnitUpi/UnitAp/UnitUp milik alias/tabel tertentu.
//  $dengan_filter = true  : memakai pilihan filter pengguna (untuk tampilan)
//  $dengan_filter = false : hanya batas hak akses tingkat pengguna (untuk aksi hapus)
$build_scope = function (string $a, bool $dengan_filter = true) use ($tingkat_akses, $unitUpi, $unitAp, $unitUp, $sel_ap, $sel_up) {
    if ($tingkat_akses === null) return ['1 = 0', []];
    if (!$dengan_filter) {
        $sel_ap = in_array($tingkat_akses, ['AP', 'UP'], true) ? (string) $unitAp : '';
        $sel_up = $tingkat_akses === 'UP' ? (string) $unitUp : '';
    }
    $c = []; $p = [];
    if (!empty($unitUpi)) { $c[] = "$a.UnitUpi = :s_upi"; $p[':s_upi'] = $unitUpi; }
    if ($sel_ap !== '')   { $c[] = "$a.UnitAp = :s_ap";   $p[':s_ap']  = $sel_ap; }
    if ($sel_up !== '')   { $c[] = "$a.UnitUp = :s_up";   $p[':s_up']  = $sel_up; }
    return [$c ? implode(' AND ', $c) : '1 = 1', $p];
};

// ---------------------------------------------------------
// 1. PROSES HAPUS DATA SURVEY (hanya untuk pelanggan di wilayah pengguna)
// ---------------------------------------------------------
if (isset($_GET['act']) && $_GET['act'] == 'delete') {
    $idpel_delete = trim($_GET['idpel'] ?? '');

    if (!empty($idpel_delete)) {
        try {
            [$sc_del_sql, $sc_del_par] = $build_scope('kategorisasi_risiko', false);
            $stmt_del = $conn->prepare("DELETE FROM kategorisasi_risiko WHERE Idpel = :idpel AND Periode = :periode AND $sc_del_sql");
            $stmt_del->execute(array_merge([':idpel' => $idpel_delete, ':periode' => pr_periode()], $sc_del_par));

            if ($stmt_del->rowCount() > 0) {
                $pesan_sukses = "Data survey untuk IdPel <strong>" . htmlspecialchars($idpel_delete) . "</strong> berhasil dihapus!";
            } else {
                $pesan_error = "Data tidak ditemukan, bukan di wilayah Anda, atau gagal dihapus.";
            }
        } catch (PDOException $e) {
            $pesan_error = "Gagal menghapus data: " . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------
// 2. PARAMETER FILTER, PENCARIAN & PAGINASI
// ---------------------------------------------------------
$keyword   = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$posisi_sr = isset($_GET['posisi_sr']) && in_array($_GET['posisi_sr'], ['0', '1']) ? $_GET['posisi_sr'] : '';
$limit     = isset($_GET['limit']) && in_array((int)$_GET['limit'], [10, 25, 50, 100]) ? (int)$_GET['limit'] : 10;
$page      = isset($_GET['hal']) && (int)$_GET['hal'] > 0 ? (int)$_GET['hal'] : 1;   // `page` dipakai router utama
$offset    = ($page - 1) * $limit;

[$sc_k_sql, $sc_k_par] = $build_scope('k');

$where_clauses = ["k.Periode = :periode_pr", "k.PosisiSR IS NOT NULL AND k.PosisiSR <> ''", $sc_k_sql];   // periode terpilih, sudah tersurvey, di wilayah pengguna
$params = array_merge([':periode_pr' => pr_periode()], $sc_k_par);

// Filter keyword pencarian (Optimasi Index Wildcard Kanan)
if (!empty($keyword)) {
    if (ctype_digit($keyword) && strlen($keyword) >= 11) {
        $where_clauses[] = "k.Idpel = :kw_exact";
        $params[':kw_exact'] = $keyword;
    } else {
        $where_clauses[] = "(k.Idpel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2 OR k.UnitUp LIKE :kw3 OR mu.SingkatanNama LIKE :kw4)";
        $params[':kw1'] = $params[':kw2'] = $params[':kw3'] = $params[':kw4'] = $keyword . '%';
    }
}

// Filter Posisi SR (0 = Mandiri, 1 = Terhubung)
if ($posisi_sr !== '') {
    $where_clauses[] = "k.PosisiSR = :posisi_sr";
    $params[':posisi_sr'] = $posisi_sr;
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

// ---------------------------------------------------------
// 3. HITUNG TOTAL DATA (CEPAT)
// ---------------------------------------------------------
try {
    $query_count = "SELECT COUNT(*) 
                    FROM kategorisasi_risiko k 
                    LEFT JOIN dil d ON k.Idpel = d.Idpel
                    LEFT JOIN master_up mu ON mu.UnitUp = k.UnitUp" . $where_sql;
    $stmt_count = $conn->prepare($query_count);
    $stmt_count->execute($params);
    $total_data = (int) $stmt_count->fetchColumn();
} catch (PDOException $e) {
    $total_data = 0;
    $pesan_error = "Gagal menghitung data: " . $e->getMessage();
}

$total_pages = ceil($total_data / $limit);
if ($total_pages < 1) $total_pages = 1;

// ---------------------------------------------------------
// 4. AMBIL DATA HASIL SURVEY (PAGINATED) -- unit ditampilkan dengan SingkatanNama
// ---------------------------------------------------------
try {
    $query_tampil = "SELECT k.Idpel, d.NamaPelanggan, k.PosisiSR, k.UnitUp, k.UnitAp, k.UnitUpi,
                            COALESCE(NULLIF(mu.SingkatanNama, ''), k.UnitUp)  AS NamaUnitUp,
                            COALESCE(NULLIF(ma.SingkatanNama, ''), k.UnitAp)  AS NamaUnitAp,
                            COALESCE(NULLIF(mi.SingkatanNama, ''), k.UnitUpi) AS NamaUnitUpi
                     FROM kategorisasi_risiko k 
                     LEFT JOIN dil d ON k.Idpel = d.Idpel
                     LEFT JOIN master_up  mu ON mu.UnitUp  = k.UnitUp
                     LEFT JOIN master_ap  ma ON ma.UnitAp  = k.UnitAp
                     LEFT JOIN master_upi mi ON mi.UnitUpi = k.UnitUpi"
                     . $where_sql . 
                     " ORDER BY k.Idpel ASC LIMIT :limit OFFSET :offset";

    $stmt_tampil = $conn->prepare($query_tampil);

    foreach ($params as $key => $val) {
        $stmt_tampil->bindValue($key, $val, PDO::PARAM_STR);
    }

    $stmt_tampil->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_tampil->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt_tampil->execute();
    $data_hasil = $stmt_tampil->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data_hasil = [];
    $pesan_error = "Gagal mengambil data survey: " . $e->getMessage();
}

// ---------------------------------------------------------
// 5. RINGKASAN KPI (mengikuti wilayah terpilih) + PANGGIL TAMPILAN FRONTEND
// ---------------------------------------------------------
$total_dil = $total_mandiri = $total_tergantung = 0;
try {
    // DIL tidak punya kolom unit -> unit pelanggan diambil dari pelunasan_ap2t (semua periode)
    // Populasi dihitung di blok sendiri: bila query ini gagal/lambat, hitungan di bawahnya tetap jalan.
    try {
        [$sc_u_sql, $sc_u_par] = $build_scope('p');
        $st = $conn->prepare("
            SELECT COUNT(DISTINCT p.IdPel) FROM pelunasan_ap2t p
            INNER JOIN dil d ON d.Idpel = p.IdPel
            WHERE $sc_u_sql
        ");
        $st->execute($sc_u_par);
        $total_dil = (int) $st->fetchColumn();
    } catch (PDOException $e) {
        $total_dil = 0;
        $pesan_error = trim(($pesan_error ?? '') . ' Gagal menghitung populasi pelanggan: ' . $e->getMessage());
    }

    $st = $conn->prepare("SELECT k.PosisiSR, COUNT(*) FROM kategorisasi_risiko k WHERE k.Periode = :p AND k.PosisiSR IN ('0','1') AND $sc_k_sql GROUP BY k.PosisiSR");
    $st->execute(array_merge([':p' => pr_periode()], $sc_k_par));
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$sr, $j]) {
        if ((string) $sr === '0') $total_mandiri    = (int) $j;
        if ((string) $sr === '1') $total_tergantung = (int) $j;
    }
} catch (PDOException $e) {
    if (empty($pesan_error)) $pesan_error = "Gagal memuat ringkasan: " . $e->getMessage();
}
$total_tersurvey = $total_mandiri + $total_tergantung;

include __DIR__ . '/../../templates/perencanaan/hasil_survey.php';
?>