// assets/js/loader.js - tampilkan overlay saat klik link / submit form / filter
(function () {
    var overlay = document.getElementById('page-loader');
    if (!overlay) return;

    var DELAY_LINK = 100;  // ms sebelum overlay tampil saat klik link (0 = langsung)
    var DELAY_FORM = 0;    // ms sebelum overlay tampil saat submit/filter (0 = langsung)
    var timer = null, failsafe = null;

    function show(delay) {
        if (timer) return;
        timer = setTimeout(function () {
            overlay.classList.add('show');
        }, delay || 0);
        // Pengaman: sembunyikan otomatis jika tidak pernah pindah halaman (mis. file download)
        failsafe = setTimeout(hide, 120000);
    }

    function hide() {
        clearTimeout(timer);
        clearTimeout(failsafe);
        timer = failsafe = null;
        overlay.classList.remove('show');
    }

    // Bisa dipanggil manual, mis. sebelum window.location.href = '...'
    window.showPageLoader = function () { show(0); };
    window.hidePageLoader = hide;

    // 1. Klik link
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!a || e.defaultPrevented || e.button !== 0) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-loader') || a.hasAttribute('data-bs-toggle')) return;
        if (a.origin !== location.origin) return;
        if (a.pathname === location.pathname && a.search === location.search && a.hash) return;

        show(DELAY_LINK);
    });

    // 2. Submit form lewat tombol / Enter (tidak aktif jika validasi HTML5 gagal atau submit dibatalkan)
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (e.defaultPrevented) return;
        if (f.hasAttribute('data-no-loader') || (f.target && f.target !== '_self')) return;
        var s = e.submitter;
        if (s && (s.hasAttribute('data-no-loader') || (s.formTarget && s.formTarget !== '_self'))) return;
        show(DELAY_FORM);
    });

    // 3. Filter otomatis: onchange="this.form.submit()" memanggil form.submit() langsung,
    //    yang TIDAK memicu event 'submit'. Bungkus fungsinya agar overlay ikut tampil.
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (!this.hasAttribute('data-no-loader') && (!this.target || this.target === '_self')) {
            show(DELAY_FORM);
        }
        return nativeSubmit.apply(this, arguments);
    };

    // 4. Tombol Back/Forward: halaman dipulihkan dari cache dengan overlay masih tampil
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) hide();
    });
})();