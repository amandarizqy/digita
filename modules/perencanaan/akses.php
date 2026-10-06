<?php
// modules/perencanaan/akses.php
// Matriks otoritas submenu Perencanaan (sesuai tabel hak akses). Ubah HANYA di file ini.
if (!function_exists('pa_matriks')) {

    /** KodeHak yang melewati pengecekan submenu (administrator sistem). Kosongkan array jika tidak diinginkan. */
    function pa_penuh(): array
    {
        return ['ADM', 'SA.KP'];
    }

    /** action => daftar KodeHak yang berhak. Action yang tidak tercantum = tidak ada yang berhak. */
    function pa_matriks(): array
    {
        $semua    = ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];
        $tanpa_vd = array_values(array_diff($semua, ['SF.VD']));

        return [
            // INPUT
            'upload_riwayat'    => ['SF.UI'],                       // Riwayat Pelunasan
            'input_kepentingan' => ['SF.AP', 'SF.UP', 'SF.VD'],     // Tingkat Kepentingan
            'input_survey'      => ['SF.AP', 'SF.UP', 'SF.VD'],     // Survey Sambungan Rumah
            // PROSES
            'proses_risiko'     => ['AM.UI'],                       // Klasifikasi Level Risiko
            'proses_prioritas'  => ['TL.AP', 'TL.UP'],              // Skala Prioritas
            // MONITORING
            'hasil_risiko'      => $semua,                          // Hasil klasifikasi level risiko
            'hasil_kepentingan' => $semua,                          // Hasil tingkat kepentingan
            'hasil_survey'      => $tanpa_vd,                       // Hasil survei SR
            'hasil_prioritas'   => $tanpa_vd,                       // Hasil skala prioritas
            // 'data_riwayat' tidak ada di tabel -> hanya untuk pa_penuh()
        ];
    }

    function pa_boleh(string $action, ?string $kode = null): bool
    {
        $kode = $kode ?? (string) ($_SESSION['KodeHak'] ?? '');
        if (in_array($kode, pa_penuh(), true)) return true;
        return in_array($kode, pa_matriks()[$action] ?? [], true);
    }

    /** Submenu pertama (urutan menu) yang boleh dibuka; '' jika tidak ada. */
    function pa_pertama(array $urutan): string
    {
        foreach ($urutan as $a) if (pa_boleh($a)) return $a;
        return '';
    }

    /** Skrip SweetAlert2: cegat klik menu terlarang, arahkan tab ke submenu yang boleh, beri ikon gembok. */
    function pa_skrip_cegat(array $grup, array $ditolak): string
    {
        $j = fn($v) => json_encode($v, JSON_UNESCAPED_SLASHES);
        return '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  var ditolak = ' . $j($ditolak) . ', grup = ' . $j($grup) . ';
  function aksi(a) {
    try { var u = new URL(a.href, location.href); return u.searchParams.get("page") === "perencanaan" ? u.searchParams.get("action") : null; }
    catch (e) { return null; }
  }
  function tolak() {
    var pesan = "Anda tidak memiliki wewenang untuk membuka menu ini.";
    if (window.Swal) Swal.fire({icon: "error", title: "Akses ditolak", text: pesan, confirmButtonText: "OK", confirmButtonColor: "#0d6efd"});
    else alert("Akses ditolak\n" + pesan);
  }
  document.querySelectorAll("a[href]").forEach(function (a) {
    var act = aksi(a); if (!act) return;
    // tab level-2: arahkan ke submenu pertama di grupnya yang boleh
    if (a.classList.contains("nav-link") && ditolak.indexOf(act) > -1) {
      for (var g in grup) {
        if (grup[g].indexOf(act) < 0) continue;
        var ok = grup[g].filter(function (x) { return ditolak.indexOf(x) < 0; })[0];
        if (ok) { var u = new URL(a.href, location.href); u.searchParams.set("action", ok); a.href = u.pathname + u.search; }
      }
    }
    // tandai yang masih terlarang
    if (ditolak.indexOf(aksi(a)) > -1) {
      a.setAttribute("aria-disabled", "true");
      a.style.opacity = ".6";
      if (!a.querySelector(".bi-lock-fill")) a.insertAdjacentHTML("beforeend", \' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>\');
    }
  });
  document.addEventListener("click", function (e) {
    var a = e.target.closest("a[href]"); if (!a) return;
    var act = aksi(a);
    if (act && ditolak.indexOf(act) > -1) { e.preventDefault(); e.stopPropagation(); tolak(); }
  }, true);
})();
</script>';
    }
}
