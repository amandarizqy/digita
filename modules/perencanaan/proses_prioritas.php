<?php
// modules/perencanaan/proses_prioritas.php
// Halaman Skala Prioritas Khusus: pengguna memilih 1 Skala Prioritas (1-9), lalu seluruh
// pelanggan pada skala itu diperingkat. Hasil disimpan ke tabel hasil_prioritas.

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

    if ($skala_dipilih < 1 || $skala_dipilih > 9) {
        $pesan_error = "Pilih salah satu Skala Prioritas (1-9) terlebih dahulu!";
    } elseif (min($bobot_dipilih) < 0 || array_sum($bobot_dipilih) !== 100) {
        $pesan_error = "Total bobot harus tepat 100% (saat ini " . array_sum($bobot_dipilih) . "%).";
    } else {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $conn->beginTransaction();

            // Ambil seluruh pelanggan pada skala terpilih + 3 indikator dari tagihan periode terkait.
            // Definisi indikator sama dengan modul proses_risiko.
            $sql_grup = "
                SELECT 
                    k.IdPel,
                    COALESCE(SUM(COALESCE(p.RpTag, 0) + COALESCE(p.RpBK, 0)), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN p.IdPel IS NOT NULL AND p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END), 0) AS late_count,
                    COALESCE(SUM(CASE WHEN p.IdPel IS NOT NULL AND (p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek) THEN 1 ELSE 0 END), 0) AS lewat_bulan_count
                FROM kategorisasi_risiko k
                INNER JOIN dil d ON d.Idpel = k.IdPel
                LEFT JOIN pelunasan_ap2t p ON p.IdPel = k.IdPel AND LEFT(p.ThBlRek, 4) = :periode_p
                WHERE k.Periode = :periode_k AND k.SkalaPrioritas = :skala
                GROUP BY k.IdPel
            ";
            $stmt_grup = $conn->prepare($sql_grup);
            $stmt_grup->execute([
                ':periode_p' => $periode_filter,
                ':periode_k' => $periode_filter,
                ':skala'     => $skala_dipilih,
            ]);
            $rows = $stmt_grup->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                throw new Exception("Tidak ada pelanggan pada Skala Prioritas $skala_dipilih untuk Periode $periode_filter. Jalankan 'Proses Risiko' terlebih dahulu.");
            }

            $ranked = hitungPeringkatPrioritas($rows, $bobot_dipilih[0], $bobot_dipilih[1], $bobot_dipilih[2]);

            // Bersihkan hasil lama untuk skala ini, lalu simpan hasil baru
            $stmt_del = $conn->prepare("DELETE FROM hasil_prioritas WHERE Periode = ? AND SkalaPrioritas = ?");
            $stmt_del->execute([$periode_filter, $skala_dipilih]);

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
                        $unitUp, $unitAp, $unitUpi, $namaAkun
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
            $pesan_sukses = "Pemeringkatan <strong>Skala Prioritas $skala_dipilih</strong> (Periode $periode_filter, metode "
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
// 4. DATA UNTUK TAMPILAN (kartu 1-9, status, pratinjau)
// ------------------------------------------------------------------
$kartu_skala = [];
for ($n = 1; $n <= 9; $n++) {
    $kartu_skala[$n] = ['jumlah' => 0, 'ranked' => 0, 'terakhir' => null, 'metode' => null, 'status' => 'kosong'];
}
$preview_top = [];

try {
    $stmt_k = $conn->prepare("
        SELECT SkalaPrioritas, COUNT(*) AS jml 
        FROM kategorisasi_risiko 
        WHERE Periode = :periode AND SkalaPrioritas BETWEEN 1 AND 9 
        GROUP BY SkalaPrioritas
    ");
    $stmt_k->execute([':periode' => $periode_filter]);
    foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $kartu_skala[(int) $r['SkalaPrioritas']]['jumlah'] = (int) $r['jml'];
    }
} catch (PDOException $e) {
    if (empty($pesan_error)) $pesan_error = "Gagal memuat ringkasan skala: " . $e->getMessage();
}

try {
    $stmt_h = $conn->prepare("
        SELECT SkalaPrioritas, COUNT(*) AS jml, MAX(DiprosesPada) AS terakhir, MAX(MetodePeringkat) AS metode
        FROM hasil_prioritas 
        WHERE Periode = :periode 
        GROUP BY SkalaPrioritas
    ");
    $stmt_h->execute([':periode' => $periode_filter]);
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
            WHERE h.Periode = :periode AND h.SkalaPrioritas = :skala
            ORDER BY h.Peringkat ASC, h.IdPel ASC
            LIMIT 10
        ");
        $stmt_pv->execute([':periode' => $periode_filter, ':skala' => $skala_dipilih]);
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