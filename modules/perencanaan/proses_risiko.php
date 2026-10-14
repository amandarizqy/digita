<?php
// modules/perencanaan/proses_risiko.php

// Mencegah error path helper
$helper_path = __DIR__ . '/../../functions/risiko_helper.php';
if (file_exists($helper_path)) {
    require_once $helper_path;
}

$pesan_sukses = "";
$pesan_error  = "";

// ==========================================
// PERIODE YANG DIPROSES
// ==========================================
// Kalkulasi risiko HANYA mengecek/memproses data tagihan periode 2025 (kolom
// `Periode` di kategorisasi_risiko bertipe YEAR dan merupakan salah satu
// Primary Key bersama IdPel). Nilai Periode yang disimpan tetap diturunkan
// dari 4 karakter awal ThBlRek, bukan dari tanggal server.
$periode_filter = pr_periode();

$pesan_info = "";   // (periode default kini ditentukan helper pr_periode(): tahun terbaru yang punya data)

// ==========================================
// 1. UNIT PENGGUNA (SESSION) + HIERARKI WILAYAH
// Sumber: UPI = master_upi (dari session), AP = master_ap, UP = master_up.
// ==========================================
// Hierarki: UPI (tertinggi) -> AP -> UP (terendah).
// - UnitUpi pengguna WAJIB ada; semua data dibatasi ke UPI ini.
// - Pengguna memilih UnitAp dahulu, lalu UnitUp yang datanya ingin diproses.
// - Jika akun pengguna sudah terikat ke AP / UP tertentu, pilihan dikunci ke nilai itu.
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

if (empty($unitUpi)) {
    $pesan_error = trim($pesan_error . " Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat ditampilkan/diproses.");
}

// Pilihan wilayah dari form (POST didahulukan, lalu GET)
$sel_ap = trim((string) ($_POST['unit_ap'] ?? $_GET['unit_ap'] ?? ''));
$sel_up = trim((string) ($_POST['unit_up'] ?? $_GET['unit_up'] ?? ''));

$ap_terkunci = !empty($unitAp);
$up_terkunci = !empty($unitUp);
if ($ap_terkunci) $sel_ap = $unitAp;
if ($up_terkunci) $sel_up = $unitUp;

// Daftar pilihan AP & UP (dari master_ap & master_up, dibatasi UPI pengguna).
// Bentuk: [kode => SingkatanNama]. Kode tetap dipakai untuk filter/nilai form,
// sedangkan SingkatanNama hanya untuk tampilan.
$opsi_ap  = [];
$opsi_up  = [];
$nama_upi = $unitUpi;
if (!empty($unitUpi)) {
    try {
        // Nama singkat UPI (master_upi)
        $stmt_nu = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), UnitUpi) FROM master_upi WHERE UnitUpi = ?");
        $stmt_nu->execute([$unitUpi]);
        $nama_upi = $stmt_nu->fetchColumn() ?: $unitUpi;

        // AP: dari master_ap (AKTIF), hanya yang berada di bawah UPI pengguna
        $sql_ap = "SELECT UnitAp, COALESCE(NULLIF(SingkatanNama, ''), UnitAp) AS Nama
                   FROM master_ap WHERE UnitUpi = :upi AND StatusData = 'AKTIF'";
        $prm_ap = [':upi' => $unitUpi];
        if ($ap_terkunci) { $sql_ap .= " AND UnitAp = :ap"; $prm_ap[':ap'] = $unitAp; }
        $stmt_ap = $conn->prepare($sql_ap . " ORDER BY UnitAp");
        $stmt_ap->execute($prm_ap);
        $opsi_ap = $stmt_ap->fetchAll(PDO::FETCH_KEY_PAIR);

        // AP yang dipilih harus valid di bawah UPI ini
        if ($sel_ap !== '' && !isset($opsi_ap[$sel_ap])) {
            $sel_ap = '';
            $sel_up = '';
        }

        if ($sel_ap !== '') {
            // UP: dari master_up (AKTIF), hanya yang berada di bawah AP terpilih
            $sql_up = "SELECT UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) AS Nama
                       FROM master_up WHERE UnitAp = :ap AND StatusData = 'AKTIF'";
            $prm_up = [':ap' => $sel_ap];
            if ($up_terkunci) { $sql_up .= " AND UnitUp = :up"; $prm_up[':up'] = $unitUp; }
            $stmt_up = $conn->prepare($sql_up . " ORDER BY UnitUp");
            $stmt_up->execute($prm_up);
            $opsi_up = $stmt_up->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        // UP yang dipilih harus valid di bawah AP ini
        if ($sel_up !== '' && !isset($opsi_up[$sel_up])) {
            $sel_up = '';
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . " Gagal memuat daftar unit: " . $e->getMessage());
    }
}

// Label wilayah untuk pesan/konfirmasi (memakai SingkatanNama)
$nama_ap = $sel_ap !== '' ? ($opsi_ap[$sel_ap] ?? $sel_ap) : '';
$nama_up = $sel_up !== '' ? ($opsi_up[$sel_up] ?? $sel_up) : '';
$scope_label = ($nama_upi ?: '-')
             . ($nama_ap !== '' ? ' › ' . $nama_ap : '')
             . ($nama_up !== '' ? ' › ' . $nama_up : '');

// Sumber unit per pelanggan: tabel `dil` TIDAK memiliki kolom unit, maka unit tiap
// pelanggan diambil dari pelunasan_ap2t. SENGAJA tanpa filter periode, supaya daftar
// pelanggan tetap muncul di wilayahnya walau periode yang dipilih belum punya tagihan
// (pemrosesan tetap hanya memakai tagihan periode terpilih).
$unit_map = "(SELECT IdPel, MAX(UnitUp) AS UnitUp, MAX(UnitAp) AS UnitAp, MAX(UnitUpi) AS UnitUpi
              FROM pelunasan_ap2t GROUP BY IdPel)";

// Pembangun kondisi SQL wilayah (alias tabel yang memuat kolom UnitUpi/UnitAp/UnitUp
// disesuaikan pemanggil)
$build_scope = function (string $a) use ($unitUpi, $sel_ap, $sel_up) {
    if (empty($unitUpi)) return ['1 = 0', []];
    $c = ["$a.UnitUpi = :s_upi"];
    $p = [':s_upi' => $unitUpi];
    if ($sel_ap !== '') { $c[] = "$a.UnitAp = :s_ap"; $p[':s_ap'] = $sel_ap; }
    if ($sel_up !== '') { $c[] = "$a.UnitUp = :s_up"; $p[':s_up'] = $sel_up; }
    return [implode(' AND ', $c), $p];
};

// Ambil unit pelanggan jika ia berada di dalam UPI pengguna (guard aksi per-baris).
// Pelanggan harus ada di DIL dan punya tagihan (periode mana pun).
$ambil_dil_dalam_upi = function (string $idpel) use ($conn, $unitUpi) {
    if (empty($unitUpi) || $idpel === '') return false;
    $st = $conn->prepare("
        SELECT p.IdPel AS Idpel, p.UnitUp, p.UnitAp, p.UnitUpi
        FROM pelunasan_ap2t p
        INNER JOIN dil d ON d.Idpel = p.IdPel
        WHERE p.IdPel = ? AND p.UnitUpi = ?
        LIMIT 1
    ");
    $st->execute([$idpel, $unitUpi]);
    return $st->fetch(PDO::FETCH_ASSOC);
};

// ==========================================
// LOGIKA CRUD: UPDATE POSISI SR (UPDATE)
// ==========================================
// CATATAN PENTING: PosisiSR HANYA berarti "apakah lokasi pelanggan ini saling
// tergantung/paralel dengan pelanggan lain" (0 = mandiri, 1 = tergantung).
// Ia TIDAK ADA hubungannya dengan LevelKepentingan (yang berasal dari modul
// input_kepentingan.php terpisah), sehingga aksi ini murni hanya mengubah
// PosisiSR dan tidak pernah menyentuh kolom LevelKepentingan.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'update_sr') {
    $idpel_target = trim($_POST['idpel_target'] ?? '');
    $posisi_sr    = trim($_POST['posisi_sr'] ?? '0');

    if (!empty($idpel_target)) {
        try {
            $dil_row = $ambil_dil_dalam_upi($idpel_target);
            if (!$dil_row) {
                throw new Exception("ID Pelanggan $idpel_target tidak ditemukan di wilayah UPI Anda.");
            }

            // Unit yang disimpan = unit pelanggan di DIL (bukan unit akun login)
            $sql_update_sr = "
                INSERT INTO kategorisasi_risiko (IdPel, Periode, PosisiSR, UnitUp, UnitAp, UnitUpi)
                VALUES (:idpel, :periode, :sr, :up, :ap, :upi)
                ON DUPLICATE KEY UPDATE PosisiSR = VALUES(PosisiSR)
            ";
            $stmt_sr = $conn->prepare($sql_update_sr);
            $stmt_sr->execute([
                ':idpel'   => $idpel_target,
                ':periode' => $periode_filter,
                ':sr'      => $posisi_sr,
                ':up'      => $dil_row['UnitUp']  ?: NULL,
                ':ap'      => $dil_row['UnitAp']  ?: NULL,
                ':upi'     => $dil_row['UnitUpi'] ?: NULL
            ]);

            $status_txt   = ($posisi_sr === '1') ? 'Tergantung dengan Pelanggan Lain' : 'Tidak Tergantung (Mandiri)';
            $pesan_sukses = "Posisi SR untuk ID Pelanggan <strong>" . htmlspecialchars($idpel_target) . "</strong> (Periode $periode_filter) berhasil diperbaharui menjadi: <strong>$status_txt</strong>.";
        } catch (Exception $e) {
            $pesan_error = "Gagal merubah Posisi SR: " . $e->getMessage();
        }
    }
}

// ==========================================
// LOGIKA CRUD: HAPUS / RESET RISIKO (DELETE)
// ==========================================
if (isset($_GET['action_crud']) && $_GET['action_crud'] === 'hapus') {
    $idpel_hapus = trim($_GET['idpel'] ?? '');
    if (!empty($idpel_hapus)) {
        try {
            if (!$ambil_dil_dalam_upi($idpel_hapus)) {
                throw new Exception("ID Pelanggan $idpel_hapus tidak ditemukan di wilayah UPI Anda.");
            }

            $conn->beginTransaction();

            // Hapus dari kategorisasi_risiko HANYA untuk periode yang sedang aktif diproses
            // (IdPel + Periode adalah composite primary key)
            $stmt_del = $conn->prepare("DELETE FROM kategorisasi_risiko WHERE IdPel = ? AND Periode = ?");
            $stmt_del->execute([$idpel_hapus, $periode_filter]);

            // Reset IndexPrioritas di DIL
            $stmt_dil_reset = $conn->prepare("UPDATE dil SET IndexPrioritas = NULL WHERE Idpel = ?");
            $stmt_dil_reset->execute([$idpel_hapus]);

            $conn->commit();
            $pesan_sukses = "Data kategorisasi risiko IDPel <strong>" . htmlspecialchars($idpel_hapus) . "</strong> (Periode $periode_filter) berhasil di-reset!";
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = "Gagal menghapus data: " . $e->getMessage();
        }
    }
}

// ==========================================
// LOGIKA CRUD: KALKULASI PROSES RISIKO (CREATE / UPSERT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['proses_risiko']) || isset($_POST['proses_semua']))) {

    $is_proses_semua = isset($_POST['proses_semua']);
    $selected_idpel  = $_POST['idpel_list'] ?? [];

    if (empty($unitUpi)) {
        $pesan_error = "Akun Anda belum memiliki Unit UPI, proses dibatalkan.";
    } elseif ($sel_ap === '') {
        $pesan_error = "Pilih <strong>Unit AP</strong> terlebih dahulu (UP boleh dikosongkan untuk memproses semua UP di AP tersebut).";
    } elseif ($is_proses_semua || !empty($selected_idpel)) {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $conn->beginTransaction();

            // 1. Hitung threshold P10 & P70 dari populasi -- HANYA dari tagihan Periode 2025
            //    (populasi acuan = seluruh periode, agar ambang konsisten antar unit)
            $stmt_total = $conn->prepare("SELECT COUNT(DISTINCT IdPel) FROM pelunasan_ap2t WHERE LEFT(ThBlRek, 4) = :periode");
            $stmt_total->execute([':periode' => $periode_filter]);
            $total_populasi = (int) $stmt_total->fetchColumn();

            if ($total_populasi === 0) {
                throw new Exception("Tidak ada data pelunasan_ap2t untuk periode $periode_filter!");
            }

            $off_p10 = max(0, (int)floor($total_populasi * 0.10) - 1);
            $off_p70 = max(0, (int)floor($total_populasi * 0.70) - 1);

            $sql_p10 = "
                SELECT SUM(COALESCE(RpTag, 0) + COALESCE(RpBK, 0)) AS total 
                FROM pelunasan_ap2t 
                WHERE LEFT(ThBlRek, 4) = :periode
                GROUP BY IdPel ORDER BY total ASC LIMIT 1 OFFSET $off_p10
            ";
            $stmt_p10 = $conn->prepare($sql_p10);
            $stmt_p10->execute([':periode' => $periode_filter]);
            $p10 = (float) $stmt_p10->fetchColumn();

            $sql_p70 = "
                SELECT SUM(COALESCE(RpTag, 0) + COALESCE(RpBK, 0)) AS total 
                FROM pelunasan_ap2t 
                WHERE LEFT(ThBlRek, 4) = :periode
                GROUP BY IdPel ORDER BY total ASC LIMIT 1 OFFSET $off_p70
            ";
            $stmt_p70 = $conn->prepare($sql_p70);
            $stmt_p70->execute([':periode' => $periode_filter]);
            $p70 = (float) $stmt_p70->fetchColumn();

            // 2. Tentukan Scope WHERE
            //    - Wajib periode 2025 (LEFT(ThBlRek,4))
            //    - Wajib IdPel sudah ada di DIL (mencegah FK error 1452)
            //    - Wajib berada di wilayah terpilih: UPI (session) -> AP -> UP (opsional)
            //    - Wajib PosisiSR sudah diisi (atribut interdependensi lokasi, dari input_survey.php)
            //    - Wajib LevelKepentingan sudah diisi dengan nilai valid (dari input_kepentingan.php,
            //      TIDAK diturunkan dari PosisiSR)
            [$scope_sql, $scope_params] = $build_scope('p');

            $params = array_merge([
                ':periode' => $periode_filter,
                ':p10'     => $p10,
                ':p70'     => $p70,
            ], $scope_params);

            $where_clause = "
                WHERE LEFT(p.ThBlRek, 4) = :periode
                  AND dil_chk.Idpel IS NOT NULL
                  AND $scope_sql
                  AND k_ext.PosisiSR IS NOT NULL AND k_ext.PosisiSR <> ''
                  AND k_ext.LevelKepentingan IS NOT NULL AND k_ext.LevelKepentingan <> ''
                  AND k_ext.LevelKepentingan IN ('RENDAH', 'MODERAT', 'TINGGI')
            ";

            if (!$is_proses_semua) {
                $in_placeholders = [];
                foreach ($selected_idpel as $idx => $id) {
                    $key = ":idpel_" . $idx;
                    $in_placeholders[] = $key;
                    $params[$key] = $id;
                }
                $where_clause .= " AND p.IdPel IN (" . implode(',', $in_placeholders) . ")";
            }

            // 3. Exec Set-Based Bulk UPSERT
            //    Struktur berlapis (raw -> ket -> kd -> final) supaya setiap tahap
            //    hanya mereferensikan kolom hasil subquery sebelumnya (bukan alias
            //    di level SELECT yang sama), dan supaya matriks Kepentingan x
            //    Keterlambatan bisa dihitung penuh 3x3 sesuai helper::hitungKemungkinan().
            //    Unit (Up/Ap/Upi) yang disimpan = unit pada tagihan pelunasan_ap2t pelanggan.
            $sql_bulk_upsert = "
                INSERT INTO kategorisasi_risiko (
                    Periode, IdPel, LevelKeterlambatan, LevelKepentingan, 
                    LevelKemungkinan, LevelDampak, Kuadran, PosisiSR, 
                    SkalaPrioritas, UnitUp, UnitAp, UnitUpi
                )
                SELECT 
                    kd.periode_val,
                    kd.IdPel,
                    kd.lvKeterlambatan,
                    kd.lvKepentingan,
                    CASE 
                        WHEN kd.kuadran IN (1, 2) THEN 'SANGAT JARANG TERJADI'
                        WHEN kd.kuadran IN (3, 4, 5) THEN 'BISA TERJADI'
                        ELSE 'SANGAT MUNGKIN TERJADI'
                    END AS lvKemungkinan,
                    CASE 
                        WHEN kd.total_amount <= :p10 THEN 'SANGAT RENDAH'
                        WHEN kd.total_amount > :p70 THEN 'SANGAT TINGGI'
                        ELSE 'MODERAT'
                    END AS lvDampak,
                    kd.kuadran,
                    kd.posisi_sr,
                    CASE 
                        WHEN kd.kuadran IN (1,2) AND kd.total_amount <= :p10 THEN 1
                        WHEN kd.kuadran IN (1,2) AND (kd.total_amount > :p10 AND kd.total_amount <= :p70) THEN 2
                        WHEN kd.kuadran IN (1,2) AND kd.total_amount > :p70 THEN 3
                        WHEN kd.kuadran IN (3,4,5) AND kd.total_amount <= :p10 THEN 4
                        WHEN kd.kuadran IN (3,4,5) AND (kd.total_amount > :p10 AND kd.total_amount <= :p70) THEN 5
                        WHEN kd.kuadran IN (3,4,5) AND kd.total_amount > :p70 THEN 6
                        WHEN kd.kuadran IN (6,7,8,9) AND kd.total_amount <= :p10 THEN 7
                        WHEN kd.kuadran IN (6,7,8,9) AND (kd.total_amount > :p10 AND kd.total_amount <= :p70) THEN 8
                        WHEN kd.kuadran IN (6,7,8,9) AND kd.total_amount > :p70 THEN 9
                        ELSE 0
                    END AS skalaPrioritas,
                    kd.unit_up, kd.unit_ap, kd.unit_upi
                FROM (
                    -- LEVEL C: Kuadran = matriks penuh 3x3 (LevelKepentingan x LevelKeterlambatan)
                    SELECT
                        ket.IdPel,
                        ket.periode_val,
                        ket.posisi_sr,
                        ket.lvKepentingan,
                        ket.lvKeterlambatan,
                        ket.total_amount,
                        ket.unit_up,
                        ket.unit_ap,
                        ket.unit_upi,
                        CASE 
                            WHEN ket.lvKepentingan = 'RENDAH'  AND ket.lvKeterlambatan = 'RENDAH'  THEN 1
                            WHEN ket.lvKepentingan = 'RENDAH'  AND ket.lvKeterlambatan = 'MODERAT' THEN 2
                            WHEN ket.lvKepentingan = 'RENDAH'  AND ket.lvKeterlambatan = 'TINGGI'  THEN 3
                            WHEN ket.lvKepentingan = 'MODERAT' AND ket.lvKeterlambatan = 'RENDAH'  THEN 4
                            WHEN ket.lvKepentingan = 'MODERAT' AND ket.lvKeterlambatan = 'MODERAT' THEN 5
                            WHEN ket.lvKepentingan = 'MODERAT' AND ket.lvKeterlambatan = 'TINGGI'  THEN 6
                            WHEN ket.lvKepentingan = 'TINGGI'  AND ket.lvKeterlambatan = 'RENDAH'  THEN 7
                            WHEN ket.lvKepentingan = 'TINGGI'  AND ket.lvKeterlambatan = 'MODERAT' THEN 8
                            WHEN ket.lvKepentingan = 'TINGGI'  AND ket.lvKeterlambatan = 'TINGGI'  THEN 9
                            ELSE 0
                        END AS kuadran
                    FROM (
                        -- LEVEL B: LevelKeterlambatan dari counter mentah (sama seperti helper::hitungKeterlambatan)
                        SELECT
                            raw.IdPel,
                            raw.periode_val,
                            raw.posisi_sr,
                            raw.LevelKepentingan AS lvKepentingan,
                            raw.total_amount,
                            raw.unit_up,
                            raw.unit_ap,
                            raw.unit_upi,
                            CASE 
                                WHEN raw.late_count <= 1 AND raw.lewat_bulan_count = 0 THEN 'RENDAH'
                                WHEN raw.late_count <= 1 AND raw.lewat_bulan_count > 0 THEN 'MODERAT'
                                WHEN raw.late_count >= 2 AND raw.lewat_bulan_count > 0 THEN 'TINGGI'
                                ELSE 'MODERAT'
                            END AS lvKeterlambatan
                        FROM (
                            -- LEVEL A: agregasi mentah per pelanggan.
                            -- LevelKepentingan diambil APA ADANYA dari kategorisasi_risiko
                            -- (hasil input_kepentingan.php) -- TIDAK diturunkan dari PosisiSR.
                            SELECT
                                p.IdPel,
                                LEFT(MIN(p.ThBlRek), 4) AS periode_val,
                                k_ext.PosisiSR AS posisi_sr,
                                k_ext.LevelKepentingan AS LevelKepentingan,
                                MAX(p.UnitUp)  AS unit_up,
                                MAX(p.UnitAp)  AS unit_ap,
                                MAX(p.UnitUpi) AS unit_upi,
                                SUM(COALESCE(p.RpTag, 0) + COALESCE(p.RpBK, 0)) AS total_amount,
                                SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) AS late_count,
                                SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) AS lewat_bulan_count
                            FROM pelunasan_ap2t p
                            INNER JOIN dil dil_chk ON dil_chk.Idpel = p.IdPel
                            INNER JOIN kategorisasi_risiko k_ext ON p.IdPel = k_ext.IdPel AND k_ext.Periode = :periode
                            $where_clause
                            GROUP BY p.IdPel, k_ext.PosisiSR, k_ext.LevelKepentingan
                        ) raw
                    ) ket
                ) kd
                ON DUPLICATE KEY UPDATE
                    Periode            = VALUES(Periode),
                    LevelKeterlambatan = VALUES(LevelKeterlambatan),
                    LevelKepentingan   = VALUES(LevelKepentingan),
                    LevelKemungkinan   = VALUES(LevelKemungkinan),
                    LevelDampak        = VALUES(LevelDampak),
                    Kuadran            = VALUES(Kuadran),
                    PosisiSR           = VALUES(PosisiSR),
                    SkalaPrioritas     = VALUES(SkalaPrioritas),
                    UnitUp             = VALUES(UnitUp),
                    UnitAp             = VALUES(UnitAp),
                    UnitUpi            = VALUES(UnitUpi)
            ";

            $stmt_bulk = $conn->prepare($sql_bulk_upsert);
            $stmt_bulk->execute($params);
            $jumlah_diproses = $stmt_bulk->rowCount();

            // Sync Ke DIL -- hanya untuk periode yang sedang diproses DAN wilayah terpilih
            [$scope_dil_sql, $scope_dil_params] = $build_scope('k');
            $sql_sync_dil = "
                UPDATE dil d
                INNER JOIN kategorisasi_risiko k ON k.IdPel = d.Idpel AND k.Periode = :periode
                SET d.IndexPrioritas = k.SkalaPrioritas
                WHERE $scope_dil_sql
            ";
            $stmt_sync = $conn->prepare($sql_sync_dil);
            $stmt_sync->execute(array_merge([':periode' => $periode_filter], $scope_dil_params));

            $conn->commit();
            $target_label = $is_proses_semua ? "seluruh pelanggan" : "data terpilih";
            $pesan_sukses = "Kalkulasi risiko Periode <strong>$periode_filter</strong> untuk <strong>$target_label</strong> di wilayah <strong>" . htmlspecialchars($scope_label) . "</strong> berhasil diproses ($jumlah_diproses baris). "
                          . "Data yang belum memiliki <strong>Posisi SR</strong> dan/atau <strong>Level Kepentingan</strong> (dari modul Input Kepentingan) otomatis dilewati.";
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = "Gagal memproses data: " . $e->getMessage();
        }
    } else {
        $pesan_error = "Pilih minimal satu ID Pelanggan atau klik tombol 'Proses Semua Data'!";
    }
}

// ==========================================
// LOGIKA READ & FILTER
// ==========================================
$keyword      = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$status_filter= isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'semua';

try {
    // Kondisi wilayah untuk seluruh query baca (alias dil = d)
    [$scope_view_sql, $scope_view_params] = $build_scope('u');

    // Ringkasan Statistik (seluruhnya mengikuti wilayah terpilih)
    $stmt_dil = $conn->prepare("SELECT COUNT(*) FROM dil d INNER JOIN $unit_map u ON u.IdPel = d.Idpel WHERE $scope_view_sql");
    $stmt_dil->execute($scope_view_params);
    $total_dil = (int) $stmt_dil->fetchColumn();

    $prm_stat = array_merge([':periode' => $periode_filter], $scope_view_params);

    $stmt_sudah = $conn->prepare("
        SELECT COUNT(*) FROM kategorisasi_risiko k
        INNER JOIN $unit_map u ON u.IdPel = k.IdPel
        WHERE k.Periode = :periode AND k.SkalaPrioritas IS NOT NULL AND k.SkalaPrioritas != 0
          AND $scope_view_sql
    ");
    $stmt_sudah->execute($prm_stat);
    $total_sudah = (int) $stmt_sudah->fetchColumn();
    $total_belum = max(0, $total_dil - $total_sudah);

    // Jumlah data yang SUDAH punya Posisi SR (atribut interdependensi lokasi)
    $stmt_siap_sr = $conn->prepare("
        SELECT COUNT(*) FROM kategorisasi_risiko k
        INNER JOIN $unit_map u ON u.IdPel = k.IdPel
        WHERE k.Periode = :periode AND k.PosisiSR IS NOT NULL AND k.PosisiSR <> ''
          AND $scope_view_sql
    ");
    $stmt_siap_sr->execute($prm_stat);
    $total_siap_sr  = (int) $stmt_siap_sr->fetchColumn();
    $total_belum_sr = max(0, $total_dil - $total_siap_sr);

    // Jumlah data yang SUDAH punya LevelKepentingan (hasil modul input_kepentingan.php)
    $stmt_siap_kepentingan = $conn->prepare("
        SELECT COUNT(*) FROM kategorisasi_risiko k
        INNER JOIN $unit_map u ON u.IdPel = k.IdPel
        WHERE k.Periode = :periode 
          AND k.LevelKepentingan IS NOT NULL AND k.LevelKepentingan <> ''
          AND k.LevelKepentingan IN ('RENDAH','MODERAT','TINGGI')
          AND $scope_view_sql
    ");
    $stmt_siap_kepentingan->execute($prm_stat);
    $total_siap_kepentingan  = (int) $stmt_siap_kepentingan->fetchColumn();
    $total_belum_kepentingan = max(0, $total_dil - $total_siap_kepentingan);

    // Query Data List
    $where_conditions = [$scope_view_sql];
    $params_view      = array_merge([':periode' => $periode_filter], $scope_view_params);

    if (!empty($keyword)) {
        $where_conditions[] = "(d.Idpel LIKE :kw OR d.NamaPelanggan LIKE :kw)";
        $params_view[':kw'] = '%' . $keyword . '%';
    }

    if ($status_filter === 'belum') {
        $where_conditions[] = "(k.SkalaPrioritas IS NULL OR k.SkalaPrioritas = 0)";
    } elseif ($status_filter === 'sudah') {
        $where_conditions[] = "(k.SkalaPrioritas IS NOT NULL AND k.SkalaPrioritas != 0)";
    } elseif ($status_filter === 'belum_sr') {
        $where_conditions[] = "(k.PosisiSR IS NULL OR k.PosisiSR = '')";
    } elseif ($status_filter === 'belum_kepentingan') {
        $where_conditions[] = "(k.LevelKepentingan IS NULL OR k.LevelKepentingan = '' OR k.LevelKepentingan NOT IN ('RENDAH','MODERAT','TINGGI'))";
    }

    $where_sql = " WHERE " . implode(' AND ', $where_conditions);

    // Paginasi (parameter `hal`; `page` dipakai router utama untuk nama modul)
    $limit = 25;
    $hal   = max(1, (int) ($_GET['hal'] ?? 1));

    $stmt_cnt = $conn->prepare("SELECT COUNT(*) FROM dil d INNER JOIN $unit_map u ON u.IdPel = d.Idpel LEFT JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel AND k.Periode = :periode $where_sql");
    $stmt_cnt->execute($params_view);
    $total_rows = (int) $stmt_cnt->fetchColumn();
    $hal        = min($hal, max(1, (int) ceil($total_rows / $limit)));
    $offset     = ($hal - 1) * $limit;

    // LEFT JOIN dibatasi ke Periode aktif via kondisi ON
    $sql_list = "
        SELECT 
            d.Idpel, 
            d.NamaPelanggan, 
            k.PosisiSR, 
            k.LevelKeterlambatan, 
            k.LevelKepentingan,
            k.LevelKemungkinan, 
            k.LevelDampak, 
            k.Kuadran, 
            k.SkalaPrioritas 
        FROM dil d
        INNER JOIN $unit_map u ON u.IdPel = d.Idpel
        LEFT JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel AND k.Periode = :periode
        $where_sql
        ORDER BY 
            CASE WHEN k.SkalaPrioritas IS NULL OR k.SkalaPrioritas = 0 THEN 0 ELSE 1 END ASC,
            k.SkalaPrioritas DESC, 
            d.Idpel ASC 
        LIMIT :lim OFFSET :off
    ";

    $stmt_list = $conn->prepare($sql_list);
    foreach ($params_view as $key => $val) {
        $stmt_list->bindValue($key, $val);
    }
    $stmt_list->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt_list->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $list_preview = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pesan_error = "Gagal mengambil data preview: " . $e->getMessage();
    $list_preview = [];
    $total_rows   = 0;
    $total_dil = $total_sudah = $total_belum = 0;
    $total_belum_sr = $total_belum_kepentingan = 0;
    $limit        = 25;
    $hal          = 1;
    $offset       = 0;
}

include __DIR__ . '/../../templates/perencanaan/proses_risiko.php';
?>