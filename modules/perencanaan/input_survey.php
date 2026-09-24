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
                // Urutan dan nama kolom diselaraskan dengan struktur tabel database (PascalCase)
                $query = "INSERT INTO kategorisasi_risiko (Idpel, PosisiSR, UnitUp, UnitAp, UnitUpi) 
                          VALUES (?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                              PosisiSR = VALUES(PosisiSR),
                              UnitUp   = VALUES(UnitUp),
                              UnitAp   = VALUES(UnitAp),
                              UnitUpi  = VALUES(UnitUpi)";
                
                $stmt = $conn->prepare($query);
                // Eksekusi parameter array persis seperti di upload_riwayat.php
                $simpan = $stmt->execute([$Idpel, $PosisiSR, $unitUp, $unitAp, $unitUpi]);

                if ($simpan) {
                    $status_txt = ($PosisiSR === '1') ? 'Terhubung (1)' : 'Tidak Terhubung (0)';
                    $pesan_sukses = "Data Survey SR untuk IdPel <strong>" . htmlspecialchars($Idpel) . "</strong> ($status_txt) berhasil disimpan!";
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
    // A. HITUNG TOTAL ANGKA CARD (Menggunakan COUNT agar cepat)
    $sql_count_sudah = "SELECT COUNT(*) FROM kategorisasi_risiko WHERE PosisiSR IS NOT NULL AND PosisiSR != ''";
    $total_sudah = (int) $conn->query($sql_count_sudah)->fetchColumn();

    $sql_total_dil = "SELECT COUNT(*) FROM dil";
    $total_dil = (int) $conn->query($sql_total_dil)->fetchColumn();

    $total_belum = max(0, $total_dil - $total_sudah);

    // B. AMBIL 10 DATA SUDAH SURVEY
    $sql_sudah = "SELECT d.Idpel, d.NamaPelanggan, k.PosisiSR 
                  FROM dil d 
                  INNER JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel 
                  WHERE k.PosisiSR IS NOT NULL AND k.PosisiSR != ''
                  ORDER BY d.Idpel ASC 
                  LIMIT 10";
    $list_sudah = $conn->query($sql_sudah)->fetchAll(PDO::FETCH_ASSOC);

    // C. AMBIL 10 DATA BELUM SURVEY
    $sql_belum = "SELECT d.Idpel, d.NamaPelanggan 
                  FROM dil d 
                  LEFT JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel 
                  WHERE (k.Idpel IS NULL OR k.PosisiSR IS NULL OR k.PosisiSR = '')
                  ORDER BY d.Idpel ASC 
                  LIMIT 10";
    $list_belum = $conn->query($sql_belum)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pesan_error = "Gagal memuat data: " . $e->getMessage();
}

// ---------------------------------------------------------
// 4. PANGGIL FRONTEND
// ---------------------------------------------------------
include '../../templates/perencanaan/input_survey.php';
?>