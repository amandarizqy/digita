<?php
// includes/pg_lepas_sesi.php  -  Lepas tiket milik sesi halaman Pemutusan & Penyalaan (AMI/AMR/SDU) saat user logout.
//
// Cara pakai: panggil SEBELUM session_destroy()/session_unset() di proses logout (session masih berisi SessionID):
//     require_once __DIR__ . '/pg_lepas_sesi.php';      // sesuaikan path
//     pg_lepas_sesi_tiket($conn);
//     ... session_destroy();
//
// Efeknya sama dengan tombol "Hapus Sesi" di tiap halaman: tiket sesi yang belum selesai (AKTIF, belum ditindak)
// dilepas kembali ke antrean (Sesi dikosongkan). Tiket yang sudah ditindak tetap menyimpan Sesi-nya sebagai riwayat.

/**
 * @return int jumlah tiket yang dilepas ke antrean (dari semua halaman).
 */
function pg_lepas_sesi_tiket(PDO $conn): int
{
    // halaman => [tabel tiket, kunci tiket, tabel tindakan]
    static $peta = [
        'pemutusan_ami' => ['trx_order_putus_ami', 'TiketPutusAmi', 'trx_tindakan_putus_ami'],
        'pemutusan_amr' => ['trx_order_putus_amr', 'TiketPutusAmr', 'trx_tindakan_putus_amr'],
        'pemutusan_sdu' => ['trx_order_putus_sdu', 'TiketPutusSdu', 'trx_tindakan_putus_sdu'],
        'penyalaan_ami' => ['trx_order_nyala_ami', 'TiketNyalaAmi', 'trx_tindakan_nyala_ami'],
        'penyalaan_amr' => ['trx_order_nyala_amr', 'TiketNyalaAmr', 'trx_tindakan_nyala_amr'],
        'penyalaan_sdu' => ['trx_order_nyala_sdu', 'TiketNyalaSdu', 'trx_tindakan_nyala_sdu'],
    ];

    $dilepas = 0;
    foreach ($peta as $halaman => [$tabel, $pk, $tindakan]) {
        $kunci = 'pg_sesi_' . $halaman;                     // sama dengan $kunci_sesi di tiap modul
        $sesi  = (string) ($_SESSION[$kunci] ?? '');
        if ($sesi === '') continue;
        try {
            $st = $conn->prepare("UPDATE `$tabel` t SET t.Sesi = '' WHERE t.Sesi = :sesi AND t.StatusData = 'AKTIF'
                                  AND NOT EXISTS (SELECT 1 FROM `$tindakan` x WHERE x.`$pk` = t.`$pk`)");
            $st->execute([':sesi' => $sesi]);
            $dilepas += $st->rowCount();
        } catch (Throwable $e) {
            error_log('pg_lepas_sesi_tiket ' . $halaman . ': ' . $e->getMessage());   // jangan gagalkan logout
        }
        unset($_SESSION[$kunci]);
    }
    return $dilepas;
}