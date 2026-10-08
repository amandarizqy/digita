<?php
// tests/ProsesRisikoTest.php
// White box test untuk modules/perencanaan/proses_risiko.php
// ID test (U1.., D1.., K1.., B..) mengikuti tabel test case di laporan.

use PHPUnit\Framework\TestCase;

final class ProsesRisikoTest extends TestCase
{
    private PDO $conn;
    private const PERIODE = '2025';

    // ------------------------------------------------------------------
    // Infrastruktur
    // ------------------------------------------------------------------

    protected function setUp(): void
    {
        $this->conn = require __DIR__ . '/koneksi_test.php';
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['pelunasan_ap2t', 'kategorisasi_risiko', 'dil', 'master_pengguna'] as $t) {
            $this->conn->exec("TRUNCATE TABLE $t");
        }
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Menjalankan proses_risiko.php seolah-olah ada request masuk.
     * Variabel hasil ($pesan_sukses, dst.) ditangkap lewat compact().
     * Parameter periode selalu dipaksa 2025 karena pr_periode() menyimpan nilainya
     * di variabel static (tidak berubah selama satu proses PHP).
     */
    private function jalankan(array $get = [], array $post = [], string $method = 'GET'): array
    {
        $conn = $this->conn;                       // dipakai oleh file yang di-include
        $_GET  = ['periode' => self::PERIODE] + $get;
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SESSION = ['NamaAkun' => 'tester', 'KodeHak' => 'AM.UI', 'pr_periode' => self::PERIODE];

        ob_start();                                // buang HTML template
        try {
            include __DIR__ . '/../modules/perencanaan/proses_risiko.php';
        } finally {
            ob_end_clean();
        }

        return compact('pesan_sukses', 'pesan_error', 'list_preview', 'hal', 'total_rows');
    }

    private function pelanggan(string $id): void
    {
        $this->conn->prepare('INSERT INTO dil (Idpel, NamaPelanggan) VALUES (?, ?)')->execute([$id, "Pelanggan $id"]);
    }

    private function kategori(string $id, ?string $sr, ?string $kep, string $periode = self::PERIODE): void
    {
        $this->conn->prepare('INSERT INTO kategorisasi_risiko (IdPel, Periode, PosisiSR, LevelKepentingan) VALUES (?,?,?,?)')
            ->execute([$id, $periode, $sr, $kep]);
    }

    private function tagihan(string $id, string $thbl, ?string $tgl, float $rp): void
    {
        $this->conn->prepare('INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, RpTag, RpBK, TglBayar) VALUES (?,?,?,0,?)')
            ->execute([$id, $thbl, $rp, $tgl]);
    }

    private function scalar(string $sql, array $p = [])
    {
        $s = $this->conn->prepare($sql);
        $s->execute($p);
        return $s->fetchColumn();
    }

    /**
     * 12 pelanggan (P001..P012) yang menutup semua kombinasi
     * 3 LevelKepentingan x 4 jalur LevelKeterlambatan. Total tagihan dibuat bervariasi
     * (100..1200) agar LevelDampak ikut bervariasi.
     * Return: [IdPel => ['kep' => ..., 'tagihan' => [[thbl, tgl, rp], ...]]]
     */
    private function seedPopulasi(): array
    {
        $kep   = ['RENDAH', 'MODERAT', 'TINGGI'];
        $jalur = [
            [['202501', '2025-01-10'], ['202502', '2025-02-15']],   // keterlambatan RENDAH
            [['202501', '2025-02-05'], ['202502', '2025-02-10']],   // MODERAT (lewat bulan, tidak telat)
            [['202501', '2025-01-25'], ['202502', '2025-03-28']],   // TINGGI (telat>=2, lewat>0)
            [['202501', '2025-01-25'], ['202502', '2025-02-26']],   // jalur sisa: telat>=2, lewat=0 -> MODERAT
        ];
        $data = [];
        for ($i = 0; $i < 12; $i++) {
            $id    = sprintf('P%03d', $i + 1);
            $total = (($i * 5) % 12 + 1) * 100;
            $tags  = [];
            foreach ($jalur[intdiv($i, 3)] as [$thbl, $tgl]) {
                $tags[] = [$thbl, $tgl, $total / 2];
            }
            $data[$id] = ['kep' => $kep[$i % 3], 'tagihan' => $tags];

            $this->pelanggan($id);
            $this->kategori($id, '0', $kep[$i % 3]);
            foreach ($tags as [$thbl, $tgl, $rp]) {
                $this->tagihan($id, $thbl, $tgl, $rp);
            }
        }
        return $data;
    }

    private function total(array $tagihan): float
    {
        return array_sum(array_column($tagihan, 2));
    }

    /** Hasil yang seharusnya, dihitung memakai fungsi di risiko_helper.php. */
    private function harapan(array $tagihan, string $kep, float $p10, float $p70): array
    {
        $late = 0;
        $lewat = 0;
        foreach ($tagihan as [$thbl, $tgl]) {
            if ($tgl !== null && (int) substr($tgl, 8, 2) > 20) $late++;
            if ($tgl === null || substr($tgl, 0, 4) . substr($tgl, 5, 2) > $thbl) $lewat++;
        }
        $ket = hitungKeterlambatan($late, $lewat);
        $k   = hitungKemungkinan($kep, $ket);
        $dmp = hitungDampak($this->total($tagihan), $p10, $p70);
        return [
            'LevelKeterlambatan' => $ket,
            'LevelKemungkinan'   => $k['LevelKemungkinan'],
            'LevelDampak'        => $dmp,
            'Kuadran'            => $k['kuadran'],
            'SkalaPrioritas'     => hitungSkalaPrioritas($k['LevelKemungkinan'], $dmp),
        ];
    }

    // ------------------------------------------------------------------
    // Blok 2: update_sr
    // ------------------------------------------------------------------

    public function test_U1_request_get_tidak_memproses_apa_apa(): void
    {
        $r = $this->jalankan();
        $this->assertSame('', $r['pesan_sukses']);
        $this->assertSame('', $r['pesan_error']);
    }

    public function test_U2_post_dengan_action_type_lain_dilewati(): void
    {
        $this->pelanggan('A001');
        $r = $this->jalankan([], ['action_type' => 'lain', 'idpel_target' => 'A001', 'posisi_sr' => '1'], 'POST');
        $this->assertSame('', $r['pesan_sukses']);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko'));
    }

    public function test_U3_update_sr_idpel_kosong_tidak_menjalankan_query(): void
    {
        $r = $this->jalankan([], ['action_type' => 'update_sr', 'idpel_target' => '', 'posisi_sr' => '1'], 'POST');
        $this->assertSame('', $r['pesan_sukses']);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko'));
    }

    public function test_U4_update_sr_tergantung(): void
    {
        $this->pelanggan('A001');
        $r = $this->jalankan([], ['action_type' => 'update_sr', 'idpel_target' => 'A001', 'posisi_sr' => '1'], 'POST');
        $this->assertStringContainsString('Tergantung dengan Pelanggan Lain', $r['pesan_sukses']);
        $this->assertSame('1', $this->scalar("SELECT PosisiSR FROM kategorisasi_risiko WHERE IdPel='A001' AND Periode=2025"));
    }

    public function test_U5_update_sr_default_mandiri_dan_upsert_tidak_menggandakan_baris(): void
    {
        $this->pelanggan('A001');
        $this->kategori('A001', '1', 'TINGGI');                       // sudah ada -> jalur ON DUPLICATE KEY
        $r = $this->jalankan([], ['action_type' => 'update_sr', 'idpel_target' => 'A001'], 'POST');   // posisi_sr tidak dikirim
        $this->assertStringContainsString('Tidak Tergantung (Mandiri)', $r['pesan_sukses']);
        $this->assertSame('0', $this->scalar("SELECT PosisiSR FROM kategorisasi_risiko WHERE IdPel='A001' AND Periode=2025"));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko'));
        $this->assertSame('TINGGI', $this->scalar("SELECT LevelKepentingan FROM kategorisasi_risiko WHERE IdPel='A001'"), 'update_sr tidak boleh menyentuh LevelKepentingan');
    }

    public function test_U6_update_sr_idpel_tidak_ada_di_dil_menghasilkan_pesan_error(): void
    {
        // Butuh FOREIGN KEY kategorisasi_risiko.IdPel -> dil.Idpel (sesuai komentar "FK error 1452" di kode).
        $r = $this->jalankan([], ['action_type' => 'update_sr', 'idpel_target' => 'TIDAKADA', 'posisi_sr' => '1'], 'POST');
        $this->assertStringContainsString('Gagal merubah Posisi SR', $r['pesan_error']);
    }

    // ------------------------------------------------------------------
    // Blok 3: hapus / reset
    // ------------------------------------------------------------------

    public function test_D1_tanpa_action_crud_dilewati(): void
    {
        $r = $this->jalankan(['idpel' => 'A001']);
        $this->assertSame('', $r['pesan_sukses']);
    }

    public function test_D2_hapus_idpel_kosong_dilewati(): void
    {
        $r = $this->jalankan(['action_crud' => 'hapus', 'idpel' => '']);
        $this->assertSame('', $r['pesan_sukses']);
    }

    public function test_D3_hapus_hanya_periode_aktif_dan_reset_index_dil(): void
    {
        $this->pelanggan('A001');
        $this->kategori('A001', '1', 'TINGGI', '2025');
        $this->kategori('A001', '1', 'TINGGI', '2024');
        $this->conn->exec("UPDATE dil SET IndexPrioritas = 5 WHERE Idpel = 'A001'");

        $r = $this->jalankan(['action_crud' => 'hapus', 'idpel' => 'A001']);

        $this->assertStringContainsString('berhasil di-reset', $r['pesan_sukses']);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = 2025'));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko WHERE Periode = 2024'), 'periode lain harus tetap');
        $this->assertNull($this->scalar("SELECT IndexPrioritas FROM dil WHERE Idpel = 'A001'"));
    }

    // ------------------------------------------------------------------
    // Blok 4: kalkulasi risiko
    // ------------------------------------------------------------------

    public function test_K1_post_tanpa_tombol_proses_dilewati(): void
    {
        $this->seedPopulasi();
        $r = $this->jalankan([], ['sesuatu' => '1'], 'POST');
        $this->assertSame('', $r['pesan_sukses']);
        $this->assertSame('', $r['pesan_error']);
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM kategorisasi_risiko WHERE SkalaPrioritas IS NOT NULL'));
    }

    public function test_K2_proses_risiko_tanpa_pilihan_idpel(): void
    {
        $r = $this->jalankan([], ['proses_risiko' => '1'], 'POST');
        $this->assertStringContainsString('Pilih minimal satu ID Pelanggan', $r['pesan_error']);
    }

    public function test_K3_proses_semua_tanpa_data_pelunasan_rollback(): void
    {
        $r = $this->jalankan([], ['proses_semua' => '1'], 'POST');
        $this->assertStringContainsString('Tidak ada data pelunasan_ap2t', $r['pesan_error']);
        $this->assertSame('', $r['pesan_sukses']);
    }

    /** K4 + K6: hasil SQL harus sama dengan hasil fungsi di risiko_helper.php; pelanggan tanpa LevelKepentingan dilewati. */
    public function test_K4_K6_hasil_sql_sama_dengan_helper(): void
    {
        $data = $this->seedPopulasi();

        // K6: ada di DIL, PosisiSR terisi, tetapi LevelKepentingan kosong -> harus dilewati
        $this->pelanggan('X999');
        $this->kategori('X999', '0', null);
        $this->tagihan('X999', '202501', '2025-01-05', 50);

        $r = $this->jalankan([], ['proses_semua' => '1'], 'POST');
        $this->assertSame('', $r['pesan_error']);
        $this->assertStringContainsString('berhasil diproses', $r['pesan_sukses']);

        // Populasi persentil = SEMUA IdPel di pelunasan_ap2t periode 2025 (termasuk X999)
        $totals = array_map(fn($d) => $this->total($d['tagihan']), $data);
        $totals[] = 50.0;
        $p10 = hitungNilaiPercentile($totals, 0.10);
        $p70 = hitungNilaiPercentile($totals, 0.70);

        foreach ($data as $id => $d) {
            $row = $this->conn->query("SELECT * FROM kategorisasi_risiko WHERE IdPel='$id' AND Periode=2025")->fetch();
            $exp = $this->harapan($d['tagihan'], $d['kep'], $p10, $p70);

            $this->assertSame($exp['LevelKeterlambatan'], $row['LevelKeterlambatan'], "$id LevelKeterlambatan");
            $this->assertSame($exp['LevelKemungkinan'],   $row['LevelKemungkinan'],   "$id LevelKemungkinan");
            $this->assertSame($exp['LevelDampak'],        $row['LevelDampak'],        "$id LevelDampak");
            $this->assertSame($exp['Kuadran'],            (int) $row['Kuadran'],      "$id Kuadran");
            $this->assertSame($exp['SkalaPrioritas'],     (int) $row['SkalaPrioritas'], "$id SkalaPrioritas");
            $this->assertEquals($exp['SkalaPrioritas'], $this->scalar('SELECT IndexPrioritas FROM dil WHERE Idpel = ?', [$id]), "$id dil.IndexPrioritas");
        }

        $this->assertNull($this->scalar("SELECT SkalaPrioritas FROM kategorisasi_risiko WHERE IdPel='X999'"), 'X999 harus dilewati');
    }

    public function test_K5_hanya_idpel_terpilih_yang_dihitung(): void
    {
        $this->seedPopulasi();
        $this->jalankan([], ['proses_risiko' => '1', 'idpel_list' => ['P001', 'P002']], 'POST');
        $terhitung = $this->conn
            ->query('SELECT IdPel FROM kategorisasi_risiko WHERE SkalaPrioritas IS NOT NULL ORDER BY IdPel')
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['P001', 'P002'], $terhitung);
    }

    public function test_K8_populasi_satu_pelanggan(): void
    {
        $this->pelanggan('S001');
        $this->kategori('S001', '1', 'TINGGI');
        $this->tagihan('S001', '202501', '2025-01-10', 500);
        $r = $this->jalankan([], ['proses_semua' => '1'], 'POST');
        $this->assertSame('', $r['pesan_error']);
        // P10 = P70 = 500 -> total <= P10 -> SANGAT RENDAH; kep TINGGI x ket RENDAH -> kuadran 7 -> skala 7
        $this->assertSame('SANGAT RENDAH', $this->scalar("SELECT LevelDampak FROM kategorisasi_risiko WHERE IdPel='S001'"));
        $this->assertSame(7, (int) $this->scalar("SELECT SkalaPrioritas FROM kategorisasi_risiko WHERE IdPel='S001'"));
    }

    // ------------------------------------------------------------------
    // Blok 5: read / filter / paginasi
    // ------------------------------------------------------------------

    public function test_B1_filter_status_sudah_dan_belum(): void
    {
        foreach (['A001', 'A002', 'A003'] as $id) $this->pelanggan($id);
        $this->kategori('A001', '0', 'TINGGI');
        $this->conn->exec("UPDATE kategorisasi_risiko SET SkalaPrioritas = 7 WHERE IdPel = 'A001'");

        $this->assertSame(1, $this->jalankan(['status_filter' => 'sudah'])['total_rows']);
        $this->assertSame(2, $this->jalankan(['status_filter' => 'belum'])['total_rows']);
        $this->assertSame(3, $this->jalankan(['status_filter' => 'tidak_dikenal'])['total_rows'], 'nilai filter asing = tampil semua');
    }

    public function test_B2_halaman_melebihi_maksimum_dijepit_ke_halaman_terakhir(): void
    {
        $this->pelanggan('A001');
        $this->assertSame(1, $this->jalankan(['hal' => '999'])['hal']);
        $this->assertSame(1, $this->jalankan(['hal' => '-5'])['hal']);
    }

    // ------------------------------------------------------------------
    // TEMUAN: test di bawah ini menggambarkan perilaku YANG SEHARUSNYA.
    // Test akan GAGAL selama bug-nya belum diperbaiki; itu memang bukti bug-nya.
    // ------------------------------------------------------------------

    public function test_TEMUAN_pesan_hapus_tidak_di_escape_xss(): void
    {
        $r = $this->jalankan(['action_crud' => 'hapus', 'idpel' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $r['pesan_sukses'], 'IDPel masuk pesan tanpa htmlspecialchars (pr_alert mencetak mentah)');
    }

    public function test_TEMUAN_keyword_nol_diabaikan_oleh_empty(): void
    {
        $this->pelanggan('P001');
        $this->pelanggan('ABC');
        $this->assertSame(1, $this->jalankan(['keyword' => '0'])['total_rows'], 'empty("0") bernilai true sehingga filter tidak diterapkan');
    }
}
