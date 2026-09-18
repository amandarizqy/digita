/* ==========================================================
   Riwayat Pelunasan — Perencanaan / Input
   Endpoint API: modules/perencanaan/riwayat_pelunasan_Controller.php
   ========================================================== */

const API_URL = '../../../modules/perencanaan/riwayat_pelunasan_Controller.php';

document.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('tabelRiwayat')) initListPage();
  if (document.getElementById('formPelunasan')) initFormPage();
});

/* ---------------------------------------------------------
   HALAMAN LIST (index.html)
--------------------------------------------------------- */
function initListPage() {
  loadRiwayat();

  const searchInput = document.getElementById('searchInput');
  let debounce;
  searchInput.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(() => loadRiwayat(this.value), 350);
  });
}

async function loadRiwayat(keyword = '') {
  const tbody = document.getElementById('tabelRiwayat');
  try {
    const res = await fetch(`${API_URL}?action=list&q=${encodeURIComponent(keyword)}`);
    const data = await res.json();

    if (!data.success || !data.data.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Belum ada data riwayat pelunasan.</td></tr>`;
      return;
    }

    tbody.innerHTML = data.data.map((row, i) => `
      <tr>
        <td>${i + 1}</td>
        <td>${row.kode_aset}</td>
        <td>${row.nama_aset}</td>
        <td>${formatTanggal(row.tanggal_pelunasan)}</td>
        <td>${formatRupiah(row.nominal)}</td>
        <td>${row.metode_pembayaran}</td>
        <td><span class="badge-status ${row.jenis_pelunasan === 'lunas' ? 'badge-lunas' : 'badge-cicilan'}">
              ${row.jenis_pelunasan === 'lunas' ? 'Lunas' : 'Cicilan'}
            </span></td>
        <td>${row.file_bukti
              ? `<a href="../../../uploads/pelunasan/${row.file_bukti}" target="_blank" class="file-link"><i class="fa-solid fa-file-pdf"></i>${row.file_bukti}</a>`
              : `<span class="empty-file">belum diunggah</span>`}</td>
        <td>
          <button class="icon-btn" title="Lihat" onclick="lihatDetail(${row.id})"><i class="fa-solid fa-eye"></i></button>
          <button class="icon-btn" title="Ubah" onclick="ubahData(${row.id})"><i class="fa-solid fa-pen"></i></button>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Gagal memuat data. Periksa koneksi ke server.</td></tr>`;
    console.error(err);
  }
}

function lihatDetail(id) {
  window.location.href = `detail.html?id=${id}`;
}
function ubahData(id) {
  window.location.href = `form.html?id=${id}`;
}

/* ---------------------------------------------------------
   HALAMAN FORM (form.html)
--------------------------------------------------------- */
function initFormPage() {
  loadDaftarAset();

  document.getElementById('asetSelect').addEventListener('change', tampilkanPreviewAset);
  document.getElementById('fileUpload').addEventListener('change', tampilkanNamaFile);
  document.getElementById('formPelunasan').addEventListener('submit', simpanPelunasan);
}

async function loadDaftarAset() {
  const select = document.getElementById('asetSelect');
  try {
    const res = await fetch(`${API_URL}?action=daftar_aset`);
    const data = await res.json();
    if (data.success) {
      data.data.forEach(aset => {
        const opt = document.createElement('option');
        opt.value = aset.id;
        opt.textContent = `${aset.kode_aset} — ${aset.nama_aset}`;
        opt.dataset.jenis = aset.nama_aset;
        opt.dataset.sisa = aset.sisa_tagihan || 0;
        select.appendChild(opt);
      });
    }
  } catch (err) {
    console.error('Gagal memuat daftar aset', err);
  }
}

function tampilkanPreviewAset() {
  const opt = this.options ? this.options[this.selectedIndex] : document.getElementById('asetSelect').selectedOptions[0];
  const preview = document.getElementById('asetPreview');
  if (!opt || !opt.value) { preview.classList.remove('show'); return; }

  document.getElementById('prevJenis').textContent = opt.dataset.jenis;
  const sisa = parseInt(opt.dataset.sisa || 0);
  document.getElementById('prevSisa').textContent = sisa > 0 ? formatRupiah(sisa) : 'Tidak ada (lunas)';
  preview.classList.add('show');
}

function tampilkanNamaFile() {
  const box = document.querySelector('.upload-box');
  if (this.files.length) {
    box.innerHTML = `<i class="fa-solid fa-file-circle-check"></i>${this.files[0].name}`;
  }
}

async function simpanPelunasan(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('.btn-save');
  const alertBox = document.getElementById('formAlert');

  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

  try {
    const formData = new FormData(form);
    formData.append('action', 'simpan');

    const res = await fetch(API_URL, { method: 'POST', body: formData });
    const result = await res.json();

    alertBox.classList.remove('success', 'error');
    if (result.success) {
      alertBox.textContent = 'Data riwayat pelunasan berhasil disimpan.';
      alertBox.classList.add('success', 'show');
      setTimeout(() => window.location.href = 'index.html', 1200);
    } else {
      alertBox.textContent = result.message || 'Gagal menyimpan data.';
      alertBox.classList.add('error', 'show');
    }
  } catch (err) {
    alertBox.textContent = 'Terjadi kesalahan koneksi ke server.';
    alertBox.classList.add('error', 'show');
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Data';
  }
}

/* ---------------------------------------------------------
   HELPER
--------------------------------------------------------- */
function formatRupiah(angka) {
  return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
}
function formatTanggal(tgl) {
  if (!tgl) return '-';
  return new Date(tgl).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}