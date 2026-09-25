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
// Sesuai kebutuhan bisnis saat ini: kalkulasi risiko HANYA mengecek/memproses
// data tagihan periode 2025 (kolom `Periode` di kategorisasi_risiko bertipe YEAR
// dan merupakan salah satu Primary Key bersama IdPel).
// Nilai Periode yang disimpan tetap diturunkan dari 4 karakter awal ThBlRek
// (lihat helper::getPeriodeFromThBlRek), bukan dari tanggal server.
$periode_filter = '2025';

// 1. Ambil Unit Pengguna dari Session
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

// ==========================================
// LOGIKA CRUD: UPDATE POSISI SR (UPDATE)
// ==========================================
// Catatan: kategorisasi_risiko sekarang punya composite primary key (IdPel, Periode),
// jadi setiap upsert wajib menyertakan Periode agar tidak bentrok/duplikat.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'update_sr') {
    $idpel_target = trim($_POST['idpel_target'] ?? '');
    $posisi_sr    = trim($_POST['posisi_sr'] ?? '0');

    if (!empty($idpel_target)) {
        try {
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
                ':up'      => $unitUp,
                ':ap'      => $unitAp,
                ':upi'     => $unitUpi
            ]);

            $pesan_sukses = "Status Posisi SR untuk ID Pelanggan <strong>$idpel_target</strong> (Periode $periode_filter) berhasil diperbaharui.";
        } catch (PDOException $e) {
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
            $conn->beginTransaction();

            // Hapus dari kategorisasi_risiko HANYA untuk periode yang sedang aktif diproses.
            // (IdPel + Periode adalah composite primary key, jadi tidak menghapus histori periode lain)
            $stmt_del = $conn->prepare("DELETE FROM kategorisasi_risiko WHERE IdPel = ? AND Periode = ?");
            $stmt_del->execute([$idpel_hapus, $periode_filter]);

            // Reset IndexPrioritas di DIL
            $stmt_dil_reset = $conn->prepare("UPDATE dil SET IndexPrioritas = NULL WHERE Idpel = ?");
            $stmt_dil_reset->execute([$idpel_hapus]);

            $conn->commit();
            $pesan_sukses = "Data kategorisasi risiko IDPel <strong>$idpel_hapus</strong> (Periode $periode_filter) berhasil di-reset!";
        } catch (PDOException $e) {
            $conn->rollBack();
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

    if ($is_proses_semua || !empty($selected_idpel)) {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $conn->beginTransaction();

            // 1. Hitung threshold P10 & P70 dari populasi -- HANYA dari tagihan Periode 2025
            //    agar konsisten dengan populasi yang benar-benar akan dikalkulasi di bawah.
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
            //    - Wajib PosisiSR sudah pernah diisi (prasyarat, LevelKepentingan diturunkan darinya)
            $params = [
                ':periode' => $periode_filter,
                ':p10'     => $p10,
                ':p70'     => $p70,
                ':unitUp'  => $unitUp,
                ':unitAp'  => $unitAp,
                ':unitUpi' => $unitUpi
            ];

            $where_clause = "
                WHERE LEFT(p.ThBlRek, 4) = :periode
                  AND dil_chk.Idpel IS NOT NULL
                  AND k_ext.PosisiSR IS NOT NULL
                  AND k_ext.PosisiSR <> ''
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
            //    - INNER JOIN ke dil untuk memastikan tidak melanggar FK-kategorisasi_risiko-IdPel
            //    - INNER JOIN ke kategorisasi_risiko (k_ext) dibatasi ke Periode aktif, dan hanya
            //      baris yang PosisiSR-nya sudah diisi (lihat $where_clause) yang boleh dihitung
            //    - Periode diisi dari LEFT(ThBlRek,4), bukan dari tanggal server
            $sql_bulk_upsert = "
                INSERT INTO kategorisasi_risiko (
                    Periode, IdPel, LevelKeterlambatan, LevelKepentingan, 
                    LevelKemungkinan, LevelDampak, Kuadran, PosisiSR, 
                    SkalaPrioritas, UnitUp, UnitAp, UnitUpi
                )
                SELECT 
                    t.periode, t.IdPel, t.lvKeterlambatan, t.lvKepentingan,
                    t.lvKemungkinan, t.lvDampak, t.kuadran, t.posisi_sr,
                    t.skalaPrioritas, :unitUp, :unitAp, :unitUpi
                FROM (
                    SELECT 
                        sub.periode_val AS periode,
                        sub.IdPel,
                        sub.posisi_sr,
                        sub.lvKeterlambatan,
                        sub.lvKepentingan,
                        sub.kuadran,
                        CASE 
                            WHEN sub.kuadran IN (1, 2) THEN 'SANGAT JARANG TERJADI'
                            WHEN sub.kuadran IN (3, 4, 5) THEN 'BISA TERJADI'
                            ELSE 'SANGAT MUNGKIN TERJADI'
                        END AS lvKemungkinan,
                        CASE 
                            WHEN sub.total_amount <= :p10 THEN 'SANGAT RENDAH'
                            WHEN sub.total_amount > :p70 THEN 'SANGAT TINGGI'
                            ELSE 'MODERAT'
                        END AS lvDampak,
                        CASE 
                            WHEN sub.kuadran IN (1,2) AND sub.total_amount <= :p10 THEN 1
                            WHEN sub.kuadran IN (1,2) AND (sub.total_amount > :p10 AND sub.total_amount <= :p70) THEN 2
                            WHEN sub.kuadran IN (1,2) AND sub.total_amount > :p70 THEN 3
                            WHEN sub.kuadran IN (3,4,5) AND sub.total_amount <= :p10 THEN 4
                            WHEN sub.kuadran IN (3,4,5) AND (sub.total_amount > :p10 AND sub.total_amount <= :p70) THEN 5
                            WHEN sub.kuadran IN (3,4,5) AND sub.total_amount > :p70 THEN 6
                            WHEN sub.kuadran IN (6,7,8,9) AND sub.total_amount <= :p10 THEN 7
                            WHEN sub.kuadran IN (6,7,8,9) AND (sub.total_amount > :p10 AND sub.total_amount <= :p70) THEN 8
                            WHEN sub.kuadran IN (6,7,8,9) AND sub.total_amount > :p70 THEN 9
                            ELSE 0
                        END AS skalaPrioritas
                    FROM (
                        SELECT 
                            p.IdPel,
                            LEFT(MIN(p.ThBlRek), 4) AS periode_val,
                            k_ext.PosisiSR AS posisi_sr,
                            SUM(COALESCE(p.RpTag, 0) + COALESCE(p.RpBK, 0)) AS total_amount,
                            CASE WHEN k_ext.PosisiSR = '1' THEN 'TINGGI' ELSE 'RENDAH' END AS lvKepentingan,
                            CASE 
                                WHEN SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) <= 1 
                                     AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) = 0 
                                     THEN 'RENDAH'
                                WHEN SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) <= 1 
                                     AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) > 0 
                                     THEN 'MODERAT'
                                WHEN SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) >= 2 
                                     AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) > 0 
                                     THEN 'TINGGI'
                                ELSE 'MODERAT'
                            END AS lvKeterlambatan,
                            CASE 
                                WHEN k_ext.PosisiSR != '1' THEN
                                    CASE 
                                        WHEN (SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) <= 1 AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) = 0) THEN 1
                                        WHEN (SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) >= 2 AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) > 0) THEN 3
                                        ELSE 2
                                    END
                                ELSE
                                    CASE 
                                        WHEN (SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) <= 1 AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) = 0) THEN 7
                                        WHEN (SUM(CASE WHEN p.TglBayar IS NOT NULL AND DAY(p.TglBayar) > 20 THEN 1 ELSE 0 END) >= 2 AND SUM(CASE WHEN p.TglBayar IS NULL OR DATE_FORMAT(p.TglBayar, '%Y%m') > p.ThBlRek THEN 1 ELSE 0 END) > 0) THEN 9
                                        ELSE 8
                                    END
                            END AS kuadran
                        FROM pelunasan_ap2t p
                        INNER JOIN dil dil_chk ON dil_chk.Idpel = p.IdPel
                        INNER JOIN kategorisasi_risiko k_ext ON p.IdPel = k_ext.IdPel AND k_ext.Periode = :periode
                        $where_clause
                        GROUP BY p.IdPel, k_ext.PosisiSR
                    ) sub
                ) t
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

            // Sync Ke DIL -- hanya untuk periode yang sedang diproses
            $sql_sync_dil = "
                INSERT INTO dil (Idpel, IndexPrioritas)
                SELECT IdPel, SkalaPrioritas FROM kategorisasi_risiko WHERE Periode = :periode
                ON DUPLICATE KEY UPDATE IndexPrioritas = VALUES(IndexPrioritas)
            ";
            $stmt_sync = $conn->prepare($sql_sync_dil);
            $stmt_sync->execute([':periode' => $periode_filter]);

            $conn->commit();
            $target_label = $is_proses_semua ? "seluruh populasi" : "data terpilih";
            $pesan_sukses = "Kalkulasi risiko Periode <strong>$periode_filter</strong> untuk <strong>$target_label</strong> berhasil diproses ($jumlah_diproses baris). "
                          . "Data yang belum memiliki <strong>Posisi SR</strong> otomatis dilewati dan tidak ikut dihitung.";
        } catch (Exception $e) {
            $conn->rollBack();
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
    // Ringkasan Statistik (semua dibatasi ke Periode aktif untuk k.*, kecuali total_dil)
    $total_dil = (int) $conn->query("SELECT COUNT(*) FROM dil")->fetchColumn();

    $stmt_sudah = $conn->prepare("SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = :periode AND SkalaPrioritas IS NOT NULL AND SkalaPrioritas != 0");
    $stmt_sudah->execute([':periode' => $periode_filter]);
    $total_sudah = (int) $stmt_sudah->fetchColumn();
    $total_belum = max(0, $total_dil - $total_sudah);

    // Jumlah data yang SUDAH punya Posisi SR (syarat wajib sebelum bisa dikalkulasi)
    $stmt_siap_sr = $conn->prepare("SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = :periode AND PosisiSR IS NOT NULL AND PosisiSR <> ''");
    $stmt_siap_sr->execute([':periode' => $periode_filter]);
    $total_siap_sr = (int) $stmt_siap_sr->fetchColumn();
    // Jumlah data yang BELUM punya Posisi SR -> tidak bisa/tidak akan diproses
    $total_belum_sr = max(0, $total_dil - $total_siap_sr);

    // Query Data List
    $where_conditions = [];
    $params_view      = [':periode' => $periode_filter];

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
    }

    $where_sql = count($where_conditions) > 0 ? " WHERE " . implode(' AND ', $where_conditions) : "";

    // LEFT JOIN dibatasi ke Periode aktif via kondisi ON, supaya:
    // - Pelanggan yang belum pernah punya baris di kategorisasi_risiko tetap tampil (k.* = NULL)
    // - Pelanggan tidak duplikat baris akibat ada data periode lain
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
        LEFT JOIN kategorisasi_risiko k ON d.Idpel = k.Idpel AND k.Periode = :periode
        $where_sql
        ORDER BY 
            CASE WHEN k.SkalaPrioritas IS NULL OR k.SkalaPrioritas = 0 THEN 0 ELSE 1 END ASC,
            k.SkalaPrioritas DESC, 
            d.Idpel ASC 
        LIMIT 50
    ";

    $stmt_list = $conn->prepare($sql_list);
    $stmt_list->execute($params_view);
    $list_preview = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pesan_error = "Gagal mengambil data preview: " . $e->getMessage();
}

include __DIR__ . '/../../templates/perencanaan/proses_risiko.php';
?>