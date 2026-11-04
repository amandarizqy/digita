<!-- templates/penghapusan.php -->
<div class="p-6 space-y-5 bg-gray-50/50 min-h-screen font-sans">
    
    <!-- Title Section & Action Buttons -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">FPK & Order Penghapusan</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Langkah 1: Buat FPK Penghapusan &rarr; Langkah 2: Gabungkan FPK ke dalam Surat Perintah Kerja (Order).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="?page=penghapusan&action=create_fpk" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-2 rounded-md flex items-center gap-1.5 shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Buat FPK
            </a>
            <a href="?page=penghapusan&action=create_order" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-2 rounded-md flex items-center gap-1.5 shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Buat Order
            </a>
        </div>
    </div>

    <!-- Main Navigation Tabs (Eksekusi / Monitoring) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-2 flex gap-2">
        <a href="?page=penghapusan&tab=eksekusi" class="bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-md flex items-center gap-1.5 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            Eksekusi
        </a>
        <a href="?page=penghapusan&tab=monitoring" class="text-gray-600 hover:bg-gray-100 text-xs font-semibold px-4 py-2 rounded-md flex items-center gap-1.5 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            Monitoring
        </a>
    </div>

    <!-- Sub Entitas Tags -->
    <div class="flex items-center gap-2 text-xs">
        <span class="text-gray-500 font-bold uppercase tracking-wider text-[11px]">SUB ENTITAS:</span>
        <button class="border border-blue-500 bg-blue-50 text-blue-600 font-semibold px-3 py-1 rounded-full flex items-center gap-1">
            <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            Order Penghapusan
        </button>
        <button class="border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 font-medium px-3 py-1 rounded-full transition">
            Mutasi Penghapusan
        </button>
    </div>

    <!-- Data Table Card Container -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200/80 p-5 space-y-4">
        
        <!-- Table Search Bar Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-blue-600">Daftar FPK & Order Penghapusan</h2>
            <form method="GET" action="" class="relative">
                <input type="hidden" name="page" value="penghapusan">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Cari no form, idpel, no ref..." class="text-xs border border-gray-200 rounded-md pl-8 pr-3 py-1.5 w-64 focus:outline-none focus:ring-1 focus:ring-blue-500 bg-gray-50/50">
            </form>
        </div>

        <!-- Table Data -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-700 font-bold uppercase tracking-wider text-[11px] bg-gray-50/50">
                        <th class="py-3 px-3 w-8">#</th>
                        <th class="py-3 px-3">NO FPK</th>
                        <th class="py-3 px-3">TGL FPK</th>
                        <th class="py-3 px-3">IDPEL / NO REF</th>
                        <th class="py-3 px-3">SIM ID</th>
                        <th class="py-3 px-3">MODEM</th>
                        <th class="py-3 px-3">NO ORDER / PETUGAS</th>
                        <th class="py-3 px-3 text-right">TAHAP / STATUS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                    <?php 
                    $no = 1;
                    if (isset($list_penghapusan) && $list_penghapusan->num_rows > 0): 
                        while ($row = $list_penghapusan->fetch_assoc()): 
                    ?>
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-3 text-gray-400 font-normal"><?= $no++ ?></td>
                            <td class="py-3 px-3 font-bold text-gray-900"><?= htmlspecialchars($row['no_fpk']) ?></td>
                            <td class="py-3 px-3 text-gray-600"><?= htmlspecialchars($row['tgl_fpk']) ?></td>
                            <td class="py-3 px-3 leading-tight">
                                <div class="font-bold text-gray-900">IDPEL: <?= htmlspecialchars($row['idpel']) ?></div>
                                <div class="text-gray-500 text-[11px] mt-0.5">BONGKAR: <?= htmlspecialchars($row['bongkar'] ?: '-') ?></div>
                                <div class="text-gray-500 text-[11px]">PASANG: <?= htmlspecialchars($row['pasang'] ?: '-') ?></div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="bg-cyan-100 text-cyan-700 font-mono text-[11px] px-2 py-0.5 rounded font-semibold">
                                    <?= htmlspecialchars($row['sim_id']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-600"><?= htmlspecialchars($row['modem']) ?></td>
                            <td class="py-3 px-3">
                                <?php if (!empty($row['no_order'])): ?>
                                    <div class="flex items-center gap-1 font-semibold text-gray-800">
                                        <span><?= htmlspecialchars($row['no_order']) ?></span>
                                        <a href="modules/penghapusan/cetak_order.php?id=<?= $row['id_pk'] ?>" class="text-blue-500 hover:text-blue-700 ml-1">
                                            <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        </a>
                                    </div>
                                    <div class="text-gray-500 text-[11px]"><?= htmlspecialchars($row['petugas']) ?></div>
                                <?php else: ?>
                                    <span class="italic text-gray-400 font-normal">Belum diorderkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?= render_badge_status_penghapusan($row['status']) ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <!-- Fallback / Row Contoh Statis Jika Belum Ada Data DB -->
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-3 text-gray-400 font-normal">1</td>
                            <td class="py-3 px-3 font-bold text-gray-900">5661000120261104-F.F</td>
                            <td class="py-3 px-3 text-gray-600">2026-11-04</td>
                            <td class="py-3 px-3 leading-tight">
                                <div class="font-bold text-gray-900">IDPEL: 54610000119</div>
                                <div class="text-gray-500 text-[11px] mt-0.5">BONGKAR: 86678204662433</div>
                                <div class="text-gray-500 text-[11px]">PASANG: -</div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="bg-cyan-100 text-cyan-700 font-mono text-[11px] px-2 py-0.5 rounded font-semibold">
                                    621007864233621700
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-600">867749010776270</td>
                            <td class="py-3 px-3">
                                <span class="italic text-gray-400 font-normal">Belum diorderkan</span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?= render_badge_status_penghapusan('BA SELESAI') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination Info -->
        <div class="text-xs text-gray-500 pt-2 border-t">
            Menampilkan <span class="font-bold text-gray-700">1 - <?= isset($list_penghapusan) && $list_penghapusan ? $list_penghapusan->num_rows : 1 ?></span> dari <span class="font-bold text-gray-700"><?= isset($list_penghapusan) && $list_penghapusan ? $list_penghapusan->num_rows : 1 ?></span> data
        </div>
    </div>
</div>