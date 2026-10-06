<?php
// functions/perencanaan_input.php
// Logika bersama Input Tingkat Kepentingan & Input Survey SR:
// dua cara (upload XLSX / input satu-satu) -> kolom tunggal di kategorisasi_risiko untuk (IdPel, Periode).
require_once __DIR__ . '/xlsx_reader.php';

if (!function_exists('pi_proses')) {

    function pi_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

    function pi_idpel($v): ?string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d+(\.0+)?$/', $v))                    $v = preg_replace('/\.0+$/', '', $v);
        elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))     $v = sprintf('%.0f', (float) $v);
        else return null;
        if (strlen($v) === 11) $v = '0' . $v;
        return preg_match('/^\d{12}$/', $v) ? $v : null;
    }

    function pi_periode($v): ?int
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}$/', $v) && $v >= 1901 && $v <= 2155 ? (int) $v : null;
    }

    function pi_norm_kepentingan($v): ?string
    {
        $v = strtoupper(trim((string) $v));
        return in_array($v, ['RENDAH', 'MODERAT', 'TINGGI'], true) ? $v : null;
    }

    function pi_norm_sr($v): ?string
    {
        $v = preg_replace('/\.0+$/', '', trim((string) $v));
        return $v === '0' || $v === '1' ? $v : null;
    }

    /** [UnitUp, UnitAp, UnitUpi] dari akun login (session, cadangan master_pengguna). */
    function pi_unit_login(PDO $conn): array
    {
        $u = [$_SESSION['UnitUp'] ?? '', $_SESSION['UnitAp'] ?? '', $_SESSION['UnitUpi'] ?? ''];
        if (in_array('', $u, true) && !empty($_SESSION['NamaAkun'])) {
            try {
                $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
                $st->execute([$_SESSION['NamaAkun']]);
                if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $u = [$u[0] ?: (string) $r['UnitUp'], $u[1] ?: (string) $r['UnitAp'], $u[2] ?: (string) $r['UnitUpi']];
                }
            } catch (PDOException $e) { /* ditangani pemanggil */ }
        }
        return $u;
    }

    /**
     * Simpan pasangan [idpel, nilai] ke kolom $kolom (nama dari konfigurasi, bukan input user).
     * Baris baru: unit dari login. Baris lama: hanya $kolom yang diperbarui. Batch 500 / 1 transaksi.
     * @return array [baru, diperbarui]
     */
    function pi_simpan(PDO $conn, string $kolom, int $periode, array $pasangan, array $unit): array
    {
        $baru = $upd = 0;
        $conn->beginTransaction();
        try {
            foreach (array_chunk($pasangan, 500) as $paket) {
                $n  = count($paket);
                $st = $conn->prepare("SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = ? AND IdPel IN (" . implode(',', array_fill(0, $n, '?')) . ")");
                $st->execute(array_merge([$periode], array_column($paket, 0)));
                $ada   = (int) $st->fetchColumn();
                $baru += $n - $ada;
                $upd  += $ada;

                $stmt = $conn->prepare("INSERT INTO kategorisasi_risiko (IdPel, Periode, $kolom, UnitUp, UnitAp, UnitUpi) VALUES "
                      . implode(',', array_fill(0, $n, '(?,?,?,?,?,?)')) . " ON DUPLICATE KEY UPDATE $kolom = VALUES($kolom)");
                $param = [];
                foreach ($paket as [$id, $nilai]) array_push($param, $id, $periode, $nilai, $unit[0], $unit[1], $unit[2]);
                $stmt->execute($param);
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
        return [$baru, $upd];
    }

    /** Proses request halaman input. Mengembalikan state untuk template. */
    function pi_proses(PDO $conn, array $cfg): array
    {
        $unit = pi_unit_login($conn);
        $st = ['sukses' => '', 'error' => '', 'peringatan' => '', 'hasil' => null, 'ditolak' => [],
               'mode' => ($_POST['mode'] ?? '') === 'manual' ? 'manual' : 'upload',
               'periode' => (string) ($_POST['periode'] ?? pr_periode()),
               'idpel' => (string) ($_POST['idpel'] ?? ''),
               'nilai' => (string) ($_POST['nilai'] ?? ''),
               'unit' => $unit, 'unit_siap' => !in_array('', $unit, true)];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $st;

        $norm = $cfg['normalisasi'];
        $periode = pi_periode($_POST['periode'] ?? '');
        if (!$st['unit_siap'])      { $st['error'] = 'Akun Anda belum memiliki UnitUp / UnitAp / UnitUpi di master pengguna.'; return $st; }
        if ($periode === null)      { $st['error'] = 'Periode harus berupa tahun 4 digit, contoh 2025.'; return $st; }

        try {
            // ---------- Input satu-satu ----------
            if ($st['mode'] === 'manual') {
                $id    = pi_idpel($_POST['idpel'] ?? '');
                $nilai = $norm($_POST['nilai'] ?? '');
                if ($id === null)         { $st['error'] = 'IdPel harus 12 digit angka.'; return $st; }
                if ($nilai === null)      { $st['error'] = $cfg['label_nilai'] . ' tidak valid.'; return $st; }

                [$baru] = pi_simpan($conn, $cfg['kolom'], $periode, [[$id, $nilai]], $unit);
                $st['sukses'] = "IdPel $id periode $periode: {$cfg['label_nilai']} = " . ($cfg['opsi'][$nilai] ?? $nilai) . ' berhasil disimpan (' . ($baru ? 'data baru' : 'data diperbarui') . ').';
                try {
                    $q = $conn->prepare("SELECT 1 FROM dil WHERE Idpel = ?"); $q->execute([$id]);
                    if (!$q->fetchColumn()) $st['peringatan'] = "IdPel $id belum ada di DIL, sehingga belum tampil di halaman hasil.";
                } catch (PDOException $e) { /* abaikan */ }
                $st['idpel'] = $st['nilai'] = '';
                return $st;
            }

            // ---------- Upload XLSX ----------
            $f = $_FILES['file_xlsx'] ?? null;
            if ($f === null || in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $st['error'] = 'File melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ').'; return $st;
            }
            if ($f['error'] !== UPLOAD_ERR_OK) { $st['error'] = 'Pilih file .xlsx terlebih dahulu.'; return $st; }
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($f['tmp_name'], false, null, 0, 2) !== 'PK') {
                $st['error'] = 'Format file harus .xlsx (Excel).'; return $st;
            }

            set_time_limit(300);
            $t0 = microtime(true);
            $kolId = $kolNilai = null;
            $baris = [];
            $dibaca = $tolak = $dup = 0;

            foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                if ($kolId === null) {
                    if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                    $kol = [];
                    foreach ($sel as $huruf => $v) $kol[pi_header($v)] = $huruf;
                    if (!isset($kol['idpel'])) continue;
                    foreach ($cfg['alias_excel'] as $a) if (isset($kol[$a])) { $kolNilai = $kol[$a]; break; }
                    if ($kolNilai === null) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . $cfg['nama_excel'] . '.');
                    $kolId = $kol['idpel'];
                    continue;
                }
                if (trim(implode('', $sel)) === '') continue;
                $dibaca++;
                $id    = pi_idpel($sel[$kolId] ?? '');
                $nilai = $norm($sel[$kolNilai] ?? '');
                $alasan = $id === null ? 'IdPel harus 12 digit angka' : ($nilai === null ? $cfg['nama_excel'] . ' harus ' . $cfg['format_excel'] : null);
                if ($alasan !== null) {
                    $tolak++;
                    if (count($st['ditolak']) < 50) $st['ditolak'][] = [$no, (string) ($sel[$kolId] ?? ''), $alasan];
                    continue;
                }
                if (isset($baris[$id . '#'])) $dup++;
                $baris[$id . '#'] = [$id, $nilai];
            }
            if ($kolId === null) throw new RuntimeException('Baris judul kolom (Idpel, ' . $cfg['nama_excel'] . ') tidak ditemukan.');

            $baru = $upd = 0;
            if ($baris) [$baru, $upd] = pi_simpan($conn, $cfg['kolom'], $periode, array_values($baris), $unit);

            $st['hasil'] = ['dibaca' => $dibaca, 'baru' => $baru, 'diperbarui' => $upd, 'duplikat' => $dup, 'ditolak' => $tolak,
                            'durasi' => round(microtime(true) - $t0, 2), 'nama' => $f['name'], 'periode' => $periode];
            if ($baris) $st['sukses'] = 'Berhasil menyimpan ' . number_format(count($baris), 0, ',', '.') . " data {$cfg['label_nilai']} untuk periode $periode.";
            else        $st['error']  = 'Tidak ada baris valid yang bisa disimpan.';
        } catch (Throwable $e) {
            $st['error'] = 'Gagal memproses: ' . $e->getMessage();
        }
        return $st;
    }
}
