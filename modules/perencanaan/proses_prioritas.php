<?php
// modules/perencanaan/proses_prioritas.php
// Halaman Skala Prioritas Khusus: pengguna memilih 1 Skala Prioritas (1-9), lalu seluruh
// pelanggan pada skala itu DI WILAYAH PENGGUNA diperingkat. Hasil disimpan ke tabel hasil_prioritas.
//
// WILAYAH PENGGUNA (hierarki UPI -> AP -> UP):
//   - Pengguna tingkat UP : terkunci ke UnitUp miliknya.
//   - Pengguna tingkat AP : memproses seluruh UP di AP-nya, atau memilih satu UP.
//   - Akun tanpa UnitAp/UnitUp tidak dapat memproses/melihat data.
// Seluruh query kategorisasi_risiko & hasil_prioritas dibatasi ke wilayah tersebut.

require_once __DIR__ . '/../../functions/prioritas_helper.php';

$pesan_sukses    = "";
$pesan_error     = "";
$tabel_belum_ada = false;

// Periode yang diproses (sama dengan modul proses_risiko)
$periode_filter = pr_periode();

// ------------------------------------------------------------------
// 1. Unit pengguna dari session
// ------------------------------------------------------------------
$namaAkun = $_SESSION['NamaAkun'] ?? '';
$unitUp   = NULL;
$unitAp   = NULL;
$unitUpi  = NULL;

if (!empty($namaAkun)) {
    try {
        $stmt_user = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $stmt_user->execute([$namaAkun]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $unitUp  = $user_data['UnitUp']  ?: NULL;
            $unitAp  = $user_data['UnitAp']  ?: NULL;
            $unitUpi = $user_data['UnitUpi'] ?: NULL;
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal memuat unit session: " . $e->getMessage();
    }
}

// ------------------------------------------------------------------
// 1b. Wilayah kerja pengguna (UPI -> AP -> UP) + nama singkat untuk tampilan
// ------------------------------------------------------------------
$wilayah_valid = !empty($unitAp);          // minimal harus punya Unit AP (atau UP yang otomatis berinduk AP)
$up_terkunci   = !empty($unitUp);
$sel_up        = $up_terkunci ? $unitUp : trim((string) ($_POST['unit_up'] ?? $_GET['unit_up'] ?? ''));
$opsi_up       = [];                       // [kode => SingkatanNama]
$nama_upi      = $unitUpi;
$nama_ap       = $unitAp;
$nama_up       = $unitUp;

if (!$wilayah_valid) {
    $pesan_error = trim($pesan_error . " Akun Anda belum memiliki Unit AP / Unit UP, sehingga data tidak dapat ditampilkan atau diproses.");
} else {
    try {
        if (!empty($unitUpi)) {
            $st = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), UnitUpi) FROM master_upi WHERE UnitUpi = ?");
            $st->execute([$unitUpi]);
            $nama_upi = $st->fetchColumn() ?: $unitUpi;
        }

        $st = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), UnitAp) FROM master_ap WHERE UnitAp = ?");
        $st->execute([$unitAp]);
        $nama_ap = $st->fetchColumn() ?: $unitAp;

        if ($up_terkunci) {
            $st = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), UnitUp) FROM master_up WHERE UnitUp = ?");
            $st->execute([$unitUp]);
            $nama_up = $st->fetchColumn() ?: $unitUp;
        } else {
            // Pengguna tingkat AP: boleh memilih salah satu UP di bawah AP-nya
            $st = $conn->prepare("
                SELECT UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) AS Nama
                FROM master_up WHERE UnitAp = :ap AND StatusData = 'AKTIF' ORDER BY UnitUp
            ");
            $st->execute([':ap' => $unitAp]);
            $opsi_up = $st->fetchAll(PDO::FETCH_KEY_PAIR);

            if ($sel_up !== '' && !isset($opsi_up[$sel_up])) $sel_up = '';   // pilihan tidak valid -> semua UP
            $nama_up = $sel_up !== '' ? $opsi_up[$sel_up] : '';
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . " Gagal memuat data unit: " . $e->getMessage());
    }
}

$scope_label = ($nama_upi ?: '-') . ' › ' . ($nama_ap ?: '-') . (($nama_up ?? '') !== '' ? ' › ' . $nama_up : ' › Semua UP');

// Kondisi SQL wilayah untuk kolom UnitUpi/UnitAp/UnitUp pada alias/tabel tertentu
$build_scope = function (string $a) use ($wilayah_valid, $unitUpi, $unitAp, $sel_up) {
    if (!$wilayah_valid) return ['1 = 0', []];
    $c = ["$a.UnitAp = :s_ap"];
    $p = [':s_ap' => $unitAp];
    if (!empty($unitUpi)) { $c[] = "$a.UnitUpi = :s_upi"; $p[':s_upi'] = $unitUpi; }
    if ($sel_up !== '')   { $c[] = "$a.UnitUp = :s_up";   $p[':s_up']  = $sel_up; }
    return [implode(' AND ', $c), $p];
};

// ------------------------------------------------------------------
// 2. Konfigurasi metode & bobot
// ------------------------------------------------------------------
$metode_tersedia = [
    'gabungan' => 'Skor Gabungan (bobot dapat diatur)',
    'nominal'  => 'Nominal Tagihan Terbesar',
    'telat'    => 'Frekuensi Keterlambatan Terbanyak',
    'tunggak'  => 'Tunggakan / Lewat Bulan Terbanyak',
];
$preset_bobot = [            // [nominal, telat, tunggak]
    'nominal' => [100, 0, 0],
    'telat'   => [0, 100, 0],
    'tunggak' => [0, 0, 100],
];

$metode_dipilih = 'gabungan';
$bobot_dipilih  = [50, 30, 20];
$skala_dipilih  = isset($_GET['skala']) ? (int) $_GET['skala'] : 0;

// ------------------------------------------------------------------
// 3. PROSES PEMERINGKATAN
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_prioritas'])) {

    $skala_dipilih  = (int) ($_POST['skala_prioritas'] ?? 0);
    $metode_post    = $_POST['metode'] ?? 'gabungan';
    $metode_dipilih = isset($metode_tersedia[$metode_post]) ? $metode_post : 'gabungan';

    if ($metode_dipilih === 'gabungan') {
        $bobot_dipilih = [
            (int) ($_POST['bobot_nominal'] ?? 0),
            (int) ($_POST['bobot_telat']   ?? 0),
            (int) ($_POST['bobot_tunggak'] ?? 0),
        ];
    } else {
        $bobot_dipilih = $preset_bobot[$metode_dipilih];
    }

    if (!$wilayah_valid) {
        $pesan_error = "Akun Anda belum memiliki Unit AP / Unit UP, proses dibatalkan.";
    } elseif ($skala_dipilih < 1 || $skala_dipilih > 9) {
        $pesan_error = "Pilih salah satu Skala Prioritas (1-9) terlebih dahulu!";
    } elseif (min($bobot_dipilih) < 0 || array_sum($bobot_dipilih) !== 100) {
        $pesan_error = "Total bobot harus tepat 100% (saat ini " . array_sum($bobot_dipilih) . "%).";
    } else {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $conn->beginTransaction();

            // Ambil pelanggan pada skala terpilih HANYA di wilayah pengguna (unit pada
            // kategorisasi_risiko) + 3 indikator dari tagihan periode terkait.
            // Definisi indikator sama dengan modul proses_risiko.
            [$scope_k_sql, $scope_k_par] = $build_scope('k');
            $sql_grup = "
                SELECT 
                    k.IdPel,
                    MAX(k.UnitUp)  AS unit_up,
                    MAX(k.UnitAp)  AS unit_ap,
                    MAX(k.UnitUpi) AS unit_upi,
                    COALESCE(SUM(COALESCE(p.RpTag, 0) + COALESCE(p.RpBK, 0)), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN p.IdPel IS NOT NULL AND p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END), 0) AS late_count,
                    COALESCE(SUM(CASE WHEN p.IdPel IS NOT NULL AND (p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek) THEN 1 ELSE 0 END), 0) AS lewat_bulan_count
                FROM kategorisasi_risiko k
                INNER JOIN dil d ON d.Idpel = k.IdPel
                LEFT JOIN pelunasan_ap2t p ON p.IdPel = k.IdPel AND LEFT(p.ThBlRek, 4) = :periode_p
                WHERE k.Periode = :periode_k AND k.SkalaPrioritas = :skala
                  AND $scope_k_sql
                GROUP BY k.IdPel
            ";
            $stmt_grup = $conn->prepare($sql_grup);
            $stmt_grup->execute(array_merge([
                ':periode_p' => $periode_filter,
                ':periode_k' => $periode_filter,
                ':skala'     => $skala_dipilih,
            ], $scope_k_par));
            $rows = $stmt_grup->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                throw new Exception("Tidak ada pelanggan pada Skala Prioritas $skala_dipilih untuk Periode $periode_filter di wilayah " . htmlspecialchars($scope_label) . ". Jalankan 'Proses Risiko' terlebih dahulu.");
            }

            $ranked = hitungPeringkatPrioritas($rows, $bobot_dipilih[0], $bobot_dipilih[1], $bobot_dipilih[2]);

            // Bersihkan hasil lama untuk skala ini HANYA di wilayah pengguna (jangan menyentuh wilayah lain)
            [$scope_h_sql, $scope_h_par] = $build_scope('hasil_prioritas');
            $stmt_del = $conn->prepare("DELETE FROM hasil_prioritas WHERE Periode = :periode AND SkalaPrioritas = :skala AND $scope_h_sql");
            $stmt_del->execute(array_merge([':periode' => $periode_filter, ':skala' => $skala_dipilih], $scope_h_par));

            $placeholder_row = '(' . implode(',', array_fill(0, 16, '?')) . ',NOW())';

            foreach (array_chunk($ranked, 500) as $chunk) {
                $ph   = [];
                $vals = [];
                foreach ($chunk as $r) {
                    $ph[] = $placeholder_row;
                    array_push(
                        $vals,
                        $periode_filter, $r['IdPel'], $skala_dipilih, $r['peringkat'],
                        $r['total_amount'], $r['late_count'], $r['lewat_bulan_count'], $r['skor'],
                        $metode_dipilih, $bobot_dipilih[0], $bobot_dipilih[1], $bobot_dipilih[2],
                        // unit = unit pelanggan pada kategorisasi_risiko (fallback ke unit pengguna)
                        $r['unit_up']  ?: $unitUp,
                        $r['unit_ap']  ?: $unitAp,
                        $r['unit_upi'] ?: $unitUpi,
                        $namaAkun
                    );
                }

                $sql_insert = "
                    INSERT INTO hasil_prioritas (
                        Periode, IdPel, SkalaPrioritas, Peringkat,
                        TotalTagihan, JumlahTelat, JumlahLewatBulan, SkorPrioritas,
                        MetodePeringkat, BobotNominal, BobotTelat, BobotTunggak,
                        UnitUp, UnitAp, UnitUpi, DiprosesOleh, DiprosesPada
                    ) VALUES " . implode(',', $ph) . "
                    ON DUPLICATE KEY UPDATE
                        SkalaPrioritas   = VALUES(SkalaPrioritas),
                        Peringkat        = VALUES(Peringkat),
                        TotalTagihan     = VALUES(TotalTagihan),
                        JumlahTelat      = VALUES(JumlahTelat),
                        JumlahLewatBulan = VALUES(JumlahLewatBulan),
                        SkorPrioritas    = VALUES(SkorPrioritas),
                        MetodePeringkat  = VALUES(MetodePeringkat),
                        BobotNominal     = VALUES(BobotNominal),
                        BobotTelat       = VALUES(BobotTelat),
                        BobotTunggak     = VALUES(BobotTunggak),
                        UnitUp           = VALUES(UnitUp),
                        UnitAp           = VALUES(UnitAp),
                        UnitUpi          = VALUES(UnitUpi),
                        DiprosesOleh     = VALUES(DiprosesOleh),
                        DiprosesPada     = VALUES(DiprosesPada)
                ";
                $conn->prepare($sql_insert)->execute($vals);
            }

            $conn->commit();

            $jumlah = count($ranked);
            $pesan_sukses = "Pemeringkatan <strong>Skala Prioritas $skala_dipilih</strong> (Periode $periode_filter, wilayah <strong>"
                          . htmlspecialchars($scope_label) . "</strong>, metode "
                          . htmlspecialchars(labelMetodePeringkat($metode_dipilih)) . ") berhasil: <strong>"
                          . number_format($jumlah) . " pelanggan</strong> diperingkat. "
                          . "<a href=\"" . pr_e(pr_url('hasil_prioritas', ['skala' => $skala_dipilih])) . "\" class=\"alert-link\">Lihat hasilnya &rarr;</a>";
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            if ($e->getCode() === '42S02') {
                $tabel_belum_ada = true;
                $pesan_error = "Tabel <code>hasil_prioritas</code> belum dibuat. Jalankan file <code>hasil_prioritas.sql</code> terlebih dahulu.";
            } else {
                $pesan_error = "Gagal memproses pemeringkatan: " . $e->getMessage();
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = $e->getMessage();
        }
    }
}

// ------------------------------------------------------------------
// 4. DATA UNTUK TAMPILAN (kartu 1-9, status, pratinjau) -- seluruhnya per wilayah
// ------------------------------------------------------------------
$kartu_skala = [];
for ($n = 1; $n <= 9; $n++) {
    $kartu_skala[$n] = ['jumlah' => 0, 'ranked' => 0, 'terakhir' => null, 'metode' => null, 'status' => 'kosong'];
}
$preview_top = [];

try {
    [$scope_k_sql, $scope_k_par] = $build_scope('k');
    $stmt_k = $conn->prepare("
        SELECT k.SkalaPrioritas, COUNT(*) AS jml 
        FROM kategorisasi_risiko k
        WHERE k.Periode = :periode AND k.SkalaPrioritas BETWEEN 1 AND 9 
          AND $scope_k_sql
        GROUP BY k.SkalaPrioritas
    ");
    $stmt_k->execute(array_merge([':periode' => $periode_filter], $scope_k_par));
    foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $kartu_skala[(int) $r['SkalaPrioritas']]['jumlah'] = (int) $r['jml'];
    }
} catch (PDOException $e) {
    if (empty($pesan_error)) $pesan_error = "Gagal memuat ringkasan skala: " . $e->getMessage();
}

try {
    [$scope_h_sql, $scope_h_par] = $build_scope('h');
    $stmt_h = $conn->prepare("
        SELECT h.SkalaPrioritas, COUNT(*) AS jml, MAX(h.DiprosesPada) AS terakhir, MAX(h.MetodePeringkat) AS metode
        FROM hasil_prioritas h
        WHERE h.Periode = :periode AND $scope_h_sql
        GROUP BY h.SkalaPrioritas
    ");
    $stmt_h->execute(array_merge([':periode' => $periode_filter], $scope_h_par));
    foreach ($stmt_h->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $n = (int) $r['SkalaPrioritas'];
        if ($n >= 1 && $n <= 9) {
            $kartu_skala[$n]['ranked']   = (int) $r['jml'];
            $kartu_skala[$n]['terakhir'] = $r['terakhir'];
            $kartu_skala[$n]['metode']   = $r['metode'];
        }
    }

    if ($skala_dipilih >= 1 && $skala_dipilih <= 9) {
        $stmt_pv = $conn->prepare("
            SELECT h.Peringkat, h.IdPel, d.NamaPelanggan, h.TotalTagihan, h.JumlahTelat, h.JumlahLewatBulan, h.SkorPrioritas
            FROM hasil_prioritas h
            INNER JOIN dil d ON d.Idpel = h.IdPel
            WHERE h.Periode = :periode AND h.SkalaPrioritas = :skala AND $scope_h_sql
            ORDER BY h.Peringkat ASC, h.IdPel ASC
            LIMIT 10
        ");
        $stmt_pv->execute(array_merge([':periode' => $periode_filter, ':skala' => $skala_dipilih], $scope_h_par));
        $preview_top = $stmt_pv->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '42S02') {
        $tabel_belum_ada = true;
    } elseif (empty($pesan_error)) {
        $pesan_error = "Gagal memuat hasil pemeringkatan: " . $e->getMessage();
    }
}

// Tentukan status tiap kartu
foreach ($kartu_skala as $n => &$k) {
    if ($k['jumlah'] === 0)                 $k['status'] = 'kosong';   // tidak ada pelanggan
    elseif ($k['ranked'] === 0)             $k['status'] = 'belum';    // belum pernah diperingkat
    elseif ($k['ranked'] !== $k['jumlah'])  $k['status'] = 'usang';    // jumlah beda -> perlu proses ulang
    else                                    $k['status'] = 'sudah';
}
unset($k);

include __DIR__ . '/../../templates/perencanaan/proses_prioritas.php';
?>