<?php
// Konfigurasi Database (Sesuaikan jika diperlukan)
$host = '10.96.100.123'; 
$user = 'digita'; 
$pass = 'Niaga@123';
$db   = 'smsp';

// Koneksi ke Database MariaDB
$conn = new mysqli($host,$user, $pass,$db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$pesan_status = "";

// Logika untuk Menjalankan Script Python
if (isset($_POST['start_python'])) {
    // Perintah untuk Windows: START /B menjalankan di background tanpa memblokir PHP
    // Jika menggunakan Linux nanti, ganti dengan: exec("python3 gateway_sms.py > /dev/null 2>&1 &");
    pclose(popen("START /B python gateway_sms.py", "r"));
    $pesan_status = "<div style='color: green;'>Service Python berhasil di-trigger di latar belakang!</div>";
}

// Logika untuk Menyimpan Pesan ke Antrean (Outbox)
if (isset($_POST['kirim_sms'])) {
    $nomor =$conn->real_escape_string($_POST['nomor_tujuan']);$teks = $conn->real_escape_string($_POST['isi_pesan']);
    
    // Status 'Reserved' memicu Python untuk membacanya
    $query = "INSERT INTO outbox (DestinationNumber, TextDecoded, CreatorID, Status) 
              VALUES ('$nomor', '$teks', 'WebPHP', 'Reserved')";
              
    if ($conn->query($query) === TRUE) {$pesan_status = "<div style='color: blue;'>Pesan ke $nomor berhasil dimasukkan ke antrean! Menunggu Python mengirimkannya...</div>";
    } else {
        $pesan_status = "<div style='color: red;'>Gagal menyimpan pesan: " . $conn->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Kontrol SMS & Middleware</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; max-width: 500px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; cursor: pointer; color: white; border: none; border-radius: 4px; }
        .btn-green { background-color: #28a745; }
        .btn-blue { background-color: #007bff; }
    </style>
</head>
<body>

    <h2>Panel Tes SMS & Python Service</h2>
    
    <!-- Area Notifikasi -->
    <?php if($pesan_status != "") echo "<b>$pesan_status</b><br><br>"; ?>

    <!-- Bagian 1: Kontrol Service Python -->
    <div class="card">
        <h3>1. Kontrol Middleware Python</h3>
        <p style="font-size: 14px; color: #555;">
            Klik tombol di bawah untuk menjalankan `gateway_sms.py`. Cukup klik 1 kali saja agar tidak terjadi bentrok di port COM17.
        </p>
        <form method="POST" action="">
            <button type="submit" name="start_python" class="btn-green">Jalankan Service Python</button>
        </form>
    </div>

    <!-- Bagian 2: Form Tes Kirim SMS -->
    <div class="card">
        <h3>2. Tes Kirim SMS (Insert ke Outbox)</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nomor Tujuan:</label>
                <input type="text" name="nomor_tujuan" placeholder="Contoh: 08123456789" required>
            </div>
            <div class="form-group">
                <label>Isi Pesan:</label>
                <textarea name="isi_pesan" rows="4" placeholder="Tulis instruksi / pesan di sini..." required></textarea>
            </div>
            <button type="submit" name="kirim_sms" class="btn-blue">Masukkan ke Antrean SMS</button>
        </form>
    </div>

</body>
</html>