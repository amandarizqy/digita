<?php
// templates/_konfirmasi.php
// Dialog konfirmasi & peringatan SweetAlert2 yang SERAGAM untuk semua template (Perencanaan, Penggunaan, dst.).
// Dipakai dengan menambah atribut pada tombol / tautan / formulir, lalu include file ini di akhir template:
//     <?php include __DIR__ . '/../_konfirmasi.php';
//
//   data-konfirmasi="Pesan"      (wajib)  isi pertanyaan
//   data-judul="Hapus data?"     judul kartu (default: "Apakah Anda yakin?")
//   data-ya="Ya, Hapus"          teks tombol konfirmasi (default: "Ya, Lanjutkan")
//   data-bahaya="1"              tombol konfirmasi merah (aksi menghapus/menonaktifkan), fokus awal ke Batal
//   data-ikon="question"         ikon lain (default: warning)
//   data-jumlah=".cek:checked"   "{jumlah}" di pesan diganti jumlah elemen yang cocok
// Dapat dipasang pada <a>, <button type="submit"> (juga yang memakai atribut form=""), atau <form>.
// JavaScript: window.notifPeringatan(judul, teks) untuk peringatan sederhana (pengganti alert()).
if (defined('KONFIRMASI_SWAL')) return;     // cukup dimuat sekali per halaman
define('KONFIRMASI_SWAL', true);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    if (window.__konfirmasiSiap) return;
    window.__konfirmasiSiap = true;

    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function pesanDari(el) {
        var p = el.getAttribute('data-konfirmasi') || '', sel = el.getAttribute('data-jumlah');
        if (sel) p = p.replace('{jumlah}', document.querySelectorAll(sel).length);
        return p;
    }

    function tanya(el, lanjut) {
        var pesan = pesanDari(el), bahaya = el.getAttribute('data-bahaya') === '1';
        if (!window.Swal) { if (confirm(pesan)) lanjut(); return; }          // cadangan bila CDN tidak terjangkau
        Swal.fire({
            icon: el.getAttribute('data-ikon') || 'warning',
            title: el.getAttribute('data-judul') || 'Apakah Anda yakin?',
            html: esc(pesan).replace(/\n/g, '<br>'),
            showCancelButton: true,
            confirmButtonText: el.getAttribute('data-ya') || 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: bahaya ? '#dc3545' : '#0d6efd',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            focusCancel: bahaya
        }).then(function (r) { if (r.isConfirmed) lanjut(); });
    }

    // Kirim formulir bersama nama/nilai tombolnya (form.submit() biasa tidak membawa nilai tombol)
    function kirim(form, pengirim) {
        form.setAttribute('data-sudah', '1');                                  // pengiriman ulang tidak ditanya lagi
        if (form.requestSubmit) { try { form.requestSubmit(pengirim || undefined); return; } catch (e) { /* lanjut ke cadangan */ } }
        if (pengirim && pengirim.name) {
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = pengirim.name; h.value = pengirim.value;
            form.appendChild(h);
        }
        form.submit();
    }

    // Tombol & tautan
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-konfirmasi]');
        if (!el || el.tagName === 'FORM' || el.disabled || el.getAttribute('aria-disabled') === 'true') return;
        e.preventDefault(); e.stopPropagation();
        if (el.tagName === 'A') { tanya(el, function () { location.href = el.href; }); return; }
        var f = el.form;
        if (!f) return;
        if (!f.checkValidity()) { f.reportValidity(); return; }                 // isian wajib diperiksa dulu
        tanya(el, function () { kirim(f, el); });
    }, true);

    // Formulir yang bertanda data-konfirmasi
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.hasAttribute || !f.hasAttribute('data-konfirmasi') || f.getAttribute('data-sudah') === '1') return;
        e.preventDefault(); e.stopPropagation();
        var s = e.submitter;
        tanya(f, function () { kirim(f, s); });
    }, true);

    window.notifPeringatan = function (judul, teks) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: judul, text: teks, confirmButtonText: 'OK', confirmButtonColor: '#0d6efd' });
        else alert(teks);
    };
})();
</script>