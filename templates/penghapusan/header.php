<!-- templates/layouts/header.php -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Digitalisasi Tata Kelola Aset Shuntrip'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- TOP NAV BAR -->
    <header class="bg-white border-b border-slate-200 px-6 py-3 sticky top-0 z-40 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold shadow-xs">
                🛡️
            </div>
            <span class="font-bold text-slate-800 text-sm tracking-tight">Digitalisasi Tata Kelola Aset Shuntrip</span>
        </div>

        <div class="flex items-center gap-2">
            <span class="bg-blue-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow-xs flex items-center gap-1.5">
                🏢 UID BANTEN
            </span>
            <span class="bg-slate-100 border border-slate-300 text-slate-700 font-bold px-3 py-1.5 rounded-lg text-xs">
                UPI: 56
            </span>
            <span class="bg-cyan-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow-xs">
                Role: SA.KP
            </span>
            <div class="text-xs font-bold text-slate-800 pl-2 cursor-pointer hover:text-blue-600 flex items-center gap-1">
                Super Admin Pusat ▾
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="flex-1 max-w-[1400px] w-full mx-auto px-6 py-6 space-y-5">