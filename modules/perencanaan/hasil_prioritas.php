<?php
// modules/perencanaan/hasil_prioritas.php
// Menampilkan hasil pemeringkatan yang dibuat di modul proses_prioritas.
//
// HAK AKSES BERDASARKAN TINGKAT UNIT PENGGUNA (UPI -> AP -> UP)
//   UPI (am.ui, sf.ui, mb.ui) : melihat SEMUA data di UPI-nya, dengan filter AP lalu UP.
//   AP  (am.ap, tl.ap, sf.ap) : melihat data di AP-nya, dengan filter UP di bawahnya.
//   UP  (ml.up, tl.up, sf.up) : TANPA filter, hanya data sesuai kode unit UP-nya.
// Tingkat ditentukan dari kolom unit akun di master_pengguna.

require_once __DIR__ . '/../../functions/prioritas_helper.php';

$pesan_error     = "";
$tabel_belum_ada = false;
$periode_filter  = pr_periode();

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

// Filter unit yang dibawa di URL (hanya tingkat UPI/AP yang punya filter)
$unit_params = [];
if ($tingkat_akses === 'UPI' || $tingkat_akses === 'AP') {
    $unit_params = ['unit_ap' => $tingkat_akses === 'UPI' ? $sel_ap : '', 'unit_up' => $sel_up];
}

// ------------------------------------------------------------------
// Filter dari URL
// ------------------------------------------------------------------
$skala_filter = isset($_GET['skala']) ? (int) $_GET['skala'] : 0;   // 0 = semua skala
if ($skala_filter < 0 || $skala_filter > 9) $skala_filter = 0;
$keyword  = trim($_GET['keyword'] ?? '');
$per_page = 25;
$page     = max(1, (int) ($_GET['hal'] ?? 1));   // `page` dipakai router utama

// Ringkasan per skala
$ringkasan = [];
for ($n = 1; $n <= 9; $n++) {
    $ringkasan[$n] = [
        'jumlah_kategori' => 0, 'jumlah_hasil' => 0, 'total_tagihan' => 0.0, 'avg_skor' => 0.0,
        'terakhir' => null, 'oleh' => null, 'metode' => null, 'bobot' => [0, 0, 0], 'usang' => false,
    ];
}
$total_hasil_semua = 0;
$list_hasil        = [];
$total_rows        = 0;
$total_pages       = 1;

try {
    // Jumlah pelanggan per skala di kategorisasi_risiko (untuk deteksi hasil usang)
    [$sc_k_sql, $sc_k_par] = $build_scope('k');
    $stmt_k = $conn->prepare("
        SELECT k.SkalaPrioritas, COUNT(*) AS jml
        FROM kategorisasi_risiko k
        WHERE k.Periode = :periode AND k.SkalaPrioritas BETWEEN 1 AND 9 AND $sc_k_sql
        GROUP BY k.SkalaPrioritas
    ");
    $stmt_k->execute(array_merge([':periode' => $periode_filter], $sc_k_par));
    foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ringkasan[(int) $r['SkalaPrioritas']]['jumlah_kategori'] = (int) $r['jml'];
    }

    // Ringkasan hasil per skala
    [$sc_h_sql, $sc_h_par] = $build_scope('h');
    $stmt_h = $conn->prepare("
        SELECT h.SkalaPrioritas, COUNT(*) AS jml, SUM(h.TotalTagihan) AS total, AVG(h.SkorPrioritas) AS avg_skor,
               MAX(h.DiprosesPada) AS terakhir, MAX(h.DiprosesOleh) AS oleh, MAX(h.MetodePeringkat) AS metode,
               MAX(h.BobotNominal) AS bn, MAX(h.BobotTelat) AS bt, MAX(h.BobotTunggak) AS bk
        FROM hasil_prioritas h
        WHERE h.Periode = :periode AND $sc_h_sql
        GROUP BY h.SkalaPrioritas
    ");
    $stmt_h->execute(array_merge([':periode' => $periode_filter], $sc_h_par));
    foreach ($stmt_h->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $n = (int) $r['SkalaPrioritas'];
        if ($n < 1 || $n > 9) continue;
        $ringkasan[$n]['jumlah_hasil']  = (int) $r['jml'];
        $ringkasan[$n]['total_tagihan'] = (float) $r['total'];
        $ringkasan[$n]['avg_skor']      = (float) $r['avg_skor'];
        $ringkasan[$n]['terakhir']      = $r['terakhir'];
        $ringkasan[$n]['oleh']          = $r['oleh'];
        $ringkasan[$n]['metode']        = $r['metode'];
        $ringkasan[$n]['bobot']         = [(int) $r['bn'], (int) $r['bt'], (int) $r['bk']];
        $total_hasil_semua             += (int) $r['jml'];
    }
    foreach ($ringkasan as $n => &$rg) {
        $rg['usang'] = ($rg['jumlah_hasil'] > 0 && $rg['jumlah_hasil'] !== $rg['jumlah_kategori']);
    }
    unset($rg);

    // Daftar detail (filter + pagination)
    $where  = ["h.Periode = :periode", $sc_h_sql];
    $params = array_merge([':periode' => $periode_filter], $sc_h_par);

    if ($skala_filter > 0) {
        $where[]           = "h.SkalaPrioritas = :skala";
        $params[':skala']  = $skala_filter;
    }
    if ($keyword !== '') {
        $where[]         = "(h.IdPel LIKE :kw1 OR d.NamaPelanggan LIKE :kw2)";
        $params[':kw1']  = '%' . $keyword . '%';
        $params[':kw2']  = '%' . $keyword . '%';
    }
    $where_sql = implode(' AND ', $where);

    $stmt_cnt = $conn->prepare("
        SELECT COUNT(*) 
        FROM hasil_prioritas h 
        INNER JOIN dil d ON d.Idpel = h.IdPel 
        WHERE $where_sql
    ");
    $stmt_cnt->execute($params);
    $total_rows  = (int) $stmt_cnt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    $stmt_list = $conn->prepare("
        SELECT h.Peringkat, h.IdPel, d.NamaPelanggan, h.SkalaPrioritas, h.TotalTagihan,
               h.JumlahTelat, h.JumlahLewatBulan, h.SkorPrioritas,
               k.LevelKepentingan, k.LevelKeterlambatan, k.LevelDampak, k.PosisiSR,
               COALESCE(NULLIF(mu.SingkatanNama, ''), h.UnitUp) AS NamaUnitUp
        FROM hasil_prioritas h
        INNER JOIN dil d ON d.Idpel = h.IdPel
        LEFT JOIN master_up mu ON mu.UnitUp = h.UnitUp
        LEFT JOIN kategorisasi_risiko k ON k.IdPel = h.IdPel AND k.Periode = h.Periode
        WHERE $where_sql
        ORDER BY h.SkalaPrioritas DESC, h.Peringkat ASC, h.IdPel ASC
        LIMIT " . (int) $per_page . " OFFSET " . (int) $offset
    );
    $stmt_list->execute($params);
    $list_hasil = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if ($e->getCode() === '42S02') {
        $tabel_belum_ada = true;
    } else {
        $pesan_error = "Gagal memuat hasil prioritas: " . $e->getMessage();
    }
}

// Helper URL yang mempertahankan filter aktif
$buat_url = function (array $override = []) use ($skala_filter, $keyword, $unit_params) {
    return pr_url('hasil_prioritas', array_merge(['skala' => $skala_filter ?: '', 'keyword' => $keyword], $unit_params, $override));
};

include __DIR__ . '/../../templates/perencanaan/hasil_prioritas.php';
?>