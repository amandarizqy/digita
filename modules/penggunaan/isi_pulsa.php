<?php
// modules/penggunaan/isi_pulsa.php  -  Isi Pulsa (tabel `transaksi_pulsa`)
// Kolom: NoTrx CHAR(12) PK | TglTrx DATE | SimId VARCHAR(25) (-> master_nomor) | IsiKredit INT | JumlahKredit INT | TglTempo DATE
//        | NamaAkun VARCHAR(20) | KodeUp CHAR(5) | KodeAp CHAR(5) | KodeUpi CHAR(2) | StatusData ENUM | WaktuData TIMESTAMP
// Alur   : (1) cari & pilih SIM (master_nomor, PRABAYAR, AKTIF, dalam wilayah akun)  ->  (2) isi tanggal, nominal isi kredit,
//          jumlah kredit sesudah isi, dan tanggal tempo  ->  simpan. NoTrx otomatis: 'TP' + yymmdd (TglTrx) + 4 digit urut.
// Kode unit transaksi = kode unit SIM itu sendiri (sudah pasti berada dalam wilayah akun). NamaAkun = akun yang login.
// Halaman ini menulis transaksi_pulsa DAN memperbarui master_nomor (JumlahKredit, TglTempo, TglPulsaTerakhir)
// dalam satu transaksi database. Master hanya diperbarui bila TglTrx >= TglPulsaTerakhir (input tanggal mundur
// tetap tercatat di transaksi_pulsa, tetapi tidak menimpa data master yang lebih baru).

$pesan_sukses = '';
$pesan_error  = '';
$hanya_prabayar = true;      // isi pulsa hanya untuk SIM PRABAYAR; false = semua jenis produk
$awalan_notrx   = 'TP';      // NoTrx = awalan + yymmdd + 4 digit urut (12 karakter)

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$akun      = (string) ($_SESSION['NamaAkun'] ?? '');

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

/** Kondisi dasar SIM yang boleh diisi pulsa (alias m): aktif, (prabayar), dalam wilayah akun. */
$kondisi_sim = function (array &$p) use ($u, $hanya_prabayar): string {
    $w = ["m.StatusData = 'AKTIF'", pg_scope($u, 'm', 'Kode', $p)];
    if ($hanya_prabayar) $w[] = "m.JenisProduk = 'PRABAYAR'";
    return implode(' AND ', $w);
};

// ---------------------------------------------------------
// SIMPAN (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sim   = trim((string) ($_POST['sim'] ?? ''));
    $tgl   = trim((string) ($_POST['tgltrx'] ?? ''));
    $tempo = trim((string) ($_POST['tgltempo'] ?? ''));
    $isi_s = trim((string) ($_POST['isikredit'] ?? ''));
    $jml_s = trim((string) ($_POST['jumlahkredit'] ?? ''));
    $dikunci = false;

    $tgl_ok   = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m1) && checkdate((int) $m1[2], (int) $m1[3], (int) $m1[1]);
    $tempo_ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tempo, $m2) && checkdate((int) $m2[2], (int) $m2[3], (int) $m2[1]);

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap || $akun === '') {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI / sesi tidak valid, sehingga transaksi tidak dapat disimpan.';
    } elseif ($sim === '') {
        $pesan_error = 'Pilih SIM terlebih dahulu.';
    } elseif (!$tgl_ok)               { $pesan_error = 'Tanggal transaksi tidak valid.';
    } elseif ($tgl > date('Y-m-d'))   { $pesan_error = 'Tanggal transaksi tidak boleh melewati hari ini.';
    } elseif ($isi_s === '' || !ctype_digit($isi_s) || (int) $isi_s < 1 || (float) $isi_s > 4294967295) {
        $pesan_error = 'Isi kredit harus angka bulat lebih dari 0.';
    } elseif ($jml_s === '' || !ctype_digit($jml_s) || (float) $jml_s > 4294967295) {
        $pesan_error = 'Jumlah kredit harus angka bulat tidak negatif.';
    } elseif (!$tempo_ok)             { $pesan_error = 'Tanggal tempo tidak valid.';
    } elseif ($tempo < $tgl)          { $pesan_error = 'Tanggal tempo tidak boleh lebih awal dari tanggal transaksi.';
    } else {
        try {
            // SIM dibaca ulang dari database: harus dalam wilayah akun, aktif, dan (bila diatur) prabayar
            $p = [':sim' => $sim];
            $kond = $kondisi_sim($p);
            $st = $conn->prepare("SELECT m.SimId, m.NomorAkun, m.KodeUp, m.KodeAp, m.KodeUpi FROM master_nomor m WHERE m.SimId = :sim AND $kond LIMIT 1");
            $st->execute($p);
            $nm = $st->fetch(PDO::FETCH_ASSOC);
            if (!$nm) throw new RuntimeException('SIM tidak ditemukan di wilayah Anda, tidak aktif, atau bukan prabayar.');

            // NoTrx berikutnya: awalan + yymmdd(TglTrx) + 4 digit urut; dikunci agar tidak kembar bila ada input bersamaan
            $dikunci = (int) $conn->query("SELECT GET_LOCK('digita_notrx_pulsa', 10)")->fetchColumn() === 1;
            if (!$dikunci) throw new RuntimeException('Sistem sedang menyimpan transaksi lain. Coba lagi sebentar.');
            $awal = $awalan_notrx . date('ymd', strtotime($tgl));
            $st = $conn->prepare("SELECT MAX(CAST(RIGHT(NoTrx, 4) AS UNSIGNED)) FROM transaksi_pulsa WHERE NoTrx LIKE :a");
            $st->execute([':a' => $awal . '%']);
            $urut = (int) $st->fetchColumn() + 1;
            if ($urut > 9999) throw new RuntimeException("Urutan transaksi $awal sudah mencapai 9999.");
            $notrx = $awal . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);

            // INSERT transaksi + UPDATE master dalam satu transaksi database (keduanya berhasil atau keduanya batal)
            $conn->beginTransaction();

            $conn->prepare("INSERT INTO transaksi_pulsa (NoTrx, TglTrx, SimId, IsiKredit, JumlahKredit, TglTempo, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData, WaktuData)
                            VALUES (?,?,?,?,?,?,?,?,?,?, 'AKTIF', NOW())")
                 ->execute([$notrx, $tgl, $nm['SimId'], (int) $isi_s, (int) $jml_s, $tempo, $akun, $nm['KodeUp'], $nm['KodeAp'], $nm['KodeUpi']]);

            // Perbarui master_nomor hanya jika transaksi ini yang terbaru (tanggal mundur tidak menimpa data baru)
            $conn->prepare("UPDATE master_nomor
                               SET JumlahKredit = ?, TglTempo = ?, TglPulsaTerakhir = ?
                             WHERE SimId = ?
                               AND (TglPulsaTerakhir IS NULL OR TglPulsaTerakhir <= ?)")
                 ->execute([(int) $jml_s, $tempo, $tgl, $nm['SimId'], $tgl]);

            $conn->commit();

            $pesan_sukses = 'Transaksi <strong class="font-monospace">' . pg_e($notrx) . '</strong> disimpan: SIM <strong>' . pg_e($nm['NomorAkun'] ?? $nm['SimId'])
                          . '</strong>, isi kredit <strong>' . pg_n($isi_s) . '</strong>, jumlah kredit <strong>' . pg_n($jml_s) . '</strong>, tempo ' . pg_e($tempo) . '.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal menyimpan: ' . pg_e($e->getMessage());
        } finally {
            if ($dikunci) $conn->query("SELECT RELEASE_LOCK('digita_notrx_pulsa')");
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('isi_pulsa', $pesan_sukses !== '' ? [] : ['sim' => $sim]));   // gagal: SIM tetap terpilih
        exit;
    }
}

// ---------------------------------------------------------
// LANGKAH 1: cari & pilih SIM
// ---------------------------------------------------------
$keyword = trim($_GET['keyword'] ?? '');
$limit   = 5;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));
$cari = []; $total_cari = 0; $sim_pilih = null;
if ($unit_siap) {
    try {
        $p = []; $kond = $kondisi_sim($p);
        $w = [$kond];
        if ($keyword !== '') { $w[] = '(m.SimId LIKE :k1 OR m.NomorAkun LIKE :k2)'; $p[':k1'] = $p[':k2'] = '%' . $keyword . '%'; }
        $from = "FROM master_nomor m WHERE " . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(*) $from");
        $st->execute($p);
        $total_cari = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_cari / $limit)));

        $st = $conn->prepare("SELECT m.SimId, m.NomorAkun, m.JenisProduk, m.TglPulsaTerakhir, m.TglTempo, m.JumlahKredit $from ORDER BY m.NomorAkun, m.SimId LIMIT :lim OFFSET :off");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $cari = $st->fetchAll(PDO::FETCH_ASSOC);

        // SIM terpilih (?sim=) harus valid di wilayah akun
        $pil = trim((string) ($_GET['sim'] ?? ''));
        if ($pil !== '') {
            $pp = [':sim' => $pil]; $kp = $kondisi_sim($pp);
            $st = $conn->prepare("SELECT m.SimId, m.NomorAkun, m.JenisProduk, m.TglPulsaTerakhir, m.TglTempo, m.JumlahKredit FROM master_nomor m WHERE m.SimId = :sim AND $kp LIMIT 1");
            $st->execute($pp);
            $sim_pilih = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat data SIM: ' . pg_e($e->getMessage()));
    }
}

$boleh_monitor = pga_boleh('monitor_isi_pulsa');

include __DIR__ . '/../../templates/penggunaan/isi_pulsa.php';