<?php
// templates/penggunaan/input_dil.php  -  Input DIL
// Variabel dari modules/penggunaan/input_dil.php: $data, $pesan_sukses, $pesan_error

echo pg_header([
    'title'    => 'Input DIL',
    'subtitle' => 'Input Daftar Induk Langganan.',
    'action'   => 'input_dil',
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <p class="text-muted mb-0">Bangun tabel / form <strong>Input DIL</strong> di file <code>templates/penggunaan/input_dil.php</code>.</p>
        <!-- Tempatkan <table> atau <form> di sini -->
    </div>
</div>