<?php
// modules/perencanaan/input_survey.php

$pesan_sukses = "";
$pesan_error = "";

// ---------------------------------------------------------
// 1. AMBIL UNIT PENGGUNA DARI TABLE master_pengguna BERDASARKAN SESSION LOGIN
// ---------------------------------------------------------
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
            // Konversi string kosong menjadi NULL agar tidak memicu Foreign Key Constraint Error
            $unitUp  = empty($user_data['UnitUp'])  ? NULL : $user_data['UnitUp'];
            $unitAp  = empty($user_data['UnitAp'])  ? NULL : $user_data['UnitAp'];
            $unitUpi = empty($user_data['UnitUpi']) ? NULL : $user_data['UnitUpi'];
        } else {
            $pesan_error = "Data unit pengguna tidak ditemukan di master_pengguna.";
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal mengambil data unit pengguna: " . $e->getMessage();
    }
} else {
    $pesan_error = "Sesi login tidak valid. Silakan login kembali.";
}

// ---------------------------------------------------------
// 2. PROSES SIMPAN / UPDATE DATA SURVEY SR (Versi PDO)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_data'])) {
    $Idpel    = trim($_POST['Idpel'] ?? $_POST['idpel'] ?? '');
    $PosisiSR = $_POST['PosisiSR'] ?? $_POST['posisiSR'] ?? '0';

    if (!empty($Idpel)) {
        try {
            // Cek terlebih dahulu apakah Idpel terdaftar di tabel DIL
            $stmt_check = $conn->prepare("SELECT Idpel FROM dil WHERE Idpel = ?");
            $stmt_check->execute([$Idpel]);

            if ($stmt_check->rowCount() > 0) {

                // -----------------------------------------------------------
                // Tentukan Periode dari 4 karakter awal ThBlRek (pelunasan_ap2t)
                // -----------------------------------------------------------
                // kategorisasi_risiko punya composite primary key (Idpel, Periode),
                // jadi Periode WAJIB diisi. Nilainya diturunkan dari data tagihan
                // pelanggan itu sendiri di pelunasan_ap2t, bukan tanggal server.
                $stmt_periode = $conn->prepare(
                    "SELECT LEFT(MIN(ThBlRek), 4) AS Periode FROM pelunasan_ap2t WHERE IdPel = ?"
                );
                $stmt_periode->execute([$Idpel]);
                $periode_row = $stmt_periode->fetch(PDO::FETCH_ASSOC);
                $Periode = $periode_row['Periode'] ?? null;

                if (empty($Periode)) {
                    $pesan_error = "Tidak ditemukan data tagihan (ThBlRek) untuk IdPel <strong>" 
                                 . htmlspecialchars($Idpel) . "</strong> di tabel pelunasan_ap2t, "
                                 . "sehingga Periode tidak dapat ditentukan dan data survey belum bisa disimpan.";
                } else {
                    // Urutan dan nama kolom diselaraskan dengan struktur tabel database (PascalCase)
                    $query = "INSERT INTO kategorisasi_risiko (Idpel, Periode, PosisiSR, UnitUp, UnitAp, UnitUpi) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                                  PosisiSR = VALUES(PosisiSR),
                                  UnitUp   = VALUES(UnitUp),
                                  UnitAp   = VALUES(UnitAp),
                                  UnitUpi  = VALUES(UnitUpi)";

                    $stmt = $conn->prepare($query);
                    // Eksekusi parameter array persis seperti di upload_riwayat.php
                    $simpan = $stmt->execute([$Idpel, $Periode, $PosisiSR, $unitUp, $unitAp, $unitUpi]);

                    if ($simpan) {
                        $status_txt = ($PosisiSR === '1') ? 'Terhubung (1)' : 'Tidak Terhubung (0)';
                        $pesan_sukses = "Data Survey SR untuk IdPel <strong>" . htmlspecialchars($Idpel) . "</strong> "
                                      . "($status_txt) Periode <strong>" . htmlspecialchars($Periode) . "</strong> berhasil disimpan!";
                    }
                }
            } else {
                $pesan_error = "IdPel <strong>" . htmlspecialchars($Idpel) . "</strong> tidak ditemukan di tabel DIL!";
            }
        } catch (PDOException $e) {
            $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
        }
    } else {
        $pesan_error = "IdPel wajib diisi!";
    }
}

// ---------------------------------------------------------
// 3. HITUNG RINGKASAN TOTAL & AMBIL 10 DATA UNTUK POP-UP
// ---------------------------------------------------------
$total_sudah = 0;
$total_belum = 0;
$list_sudah  = [];
$list_belum  = [];

try {
    // NB: kategorisasi_risiko punya composite primary key (Idpel, Periode), artinya
    // satu Idpel bisa punya lebih dari satu baris (beda Periode). Semua hitungan &
    // daftar di bawah ini memakai DISTINCT Idpel / "pernah ada baris PosisiSR terisi"
    // supaya satu pelanggan tidak terhitung dobel walau punya beberapa baris Periode.

    // A. HITUNG TOTAL ANGKA CARD (Menggunakan COUNT DISTINCT Idpel agar tidak dobel)
    $sql_count_sudah = "SELECT COUNT(DISTINCT Idpel) FROM kategorisasi_risiko WHERE PosisiSR IS NOT NULL AND PosisiSR != ''";
    $total_sudah = (int) $conn->query($sql_count_sudah)->fetchColumn();

    $sql_total_dil = "SELECT COUNT(*) FROM dil";
    $total_dil = (int) $conn->query($sql_total_dil)->fetchColumn();

    $total_belum = max(0, $total_dil - $total_sudah);

    // B. AMBIL 10 DATA SUDAH SURVEY (ambil satu baris representatif per Idpel: Periode terbaru)
    $sql_sudah = "
        SELECT d.Idpel, d.NamaPelanggan, k.PosisiSR
        FROM dil d
        INNER JOIN (
            SELECT k1.Idpel, k1.PosisiSR
            FROM kategorisasi_risiko k1
            INNER JOIN (
                SELECT Idpel, MAX(Periode) AS MaxPeriode
                FROM kategorisasi_risiko
                WHERE PosisiSR IS NOT NULL AND PosisiSR != ''
                GROUP BY Idpel
            ) k2 ON k1.Idpel = k2.Idpel AND k1.Periode = k2.MaxPeriode
        ) k ON d.Idpel = k.Idpel
        ORDER BY d.Idpel ASC
        LIMIT 10
    ";
    $list_sudah = $conn->query($sql_sudah)->fetchAll(PDO::FETCH_ASSOC);

    // C. AMBIL 10 DATA BELUM SURVEY (Idpel yang tidak punya baris manapun dengan PosisiSR terisi)
    $sql_belum = "
        SELECT d.Idpel, d.NamaPelanggan
        FROM dil d
        WHERE NOT EXISTS (
            SELECT 1 FROM kategorisasi_risiko k
            WHERE k.Idpel = d.Idpel AND k.PosisiSR IS NOT NULL AND k.PosisiSR != ''
        )
        ORDER BY d.Idpel ASC
        LIMIT 10
    ";
    $list_belum = $conn->query($sql_belum)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pesan_error = "Gagal memuat data: " . $e->getMessage();
}

// ---------------------------------------------------------
// 4. PANGGIL FRONTEND
// ---------------------------------------------------------
include '../../templates/perencanaan/input_survey.php';
?>