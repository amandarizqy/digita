<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_formulir = $_POST['no_formulir'] ?? '';
    $nama_akun = $_SESSION['NamaAkun'];
    $tgl_terima = date('Y-m-d');

    if (empty($no_formulir)) {
        die("Nomor Formulir tidak valid.");
    }

    try {
        // Update status pengiriman menjadi DITERIMA
        $query = "UPDATE formulir_pengiriman 
                  SET StatusPengiriman = 'DITERIMA', 
                      TglTerima = :tgl, 
                      AkunPenerima = :akun 
                  WHERE NoFormulir = :no_form AND StatusPengiriman = 'DIKIRIM'";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':tgl' => $tgl_terima,
            ':akun' => $nama_akun,
            ':no_form' => $no_formulir
        ]);

        header("Location: penerimaan.php?status=sukses");
        exit;

    } catch (Exception $e) {
        die("Gagal mengkonfirmasi penerimaan: " . $e->getMessage());
    }
}
?>