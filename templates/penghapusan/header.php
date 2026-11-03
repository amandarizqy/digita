<?php
// templates/header.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIGITAS - Modul Penghapusan Aset</title>
    
    <!-- CSS Tailwind & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Leaflet CSS & JS untuk Peta Interaktif -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .form-grid { display: grid; grid-template-columns: 200px 1fr 150px 1fr; border-top: 1px solid #d1d5db; border-left: 1px solid #d1d5db; }
        .form-cell { padding: 12px; border-right: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; display: flex; align-items: center; }
        .form-label { font-weight: 600; color: #374151; background-color: #f9fafb; }
        #map { height: 300px; width: 100%; border-left: 1px solid #d1d5db; border-right: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; z-index: 1; }
    </style>
</head>
<body class="bg-gray-100 p-6 font-sans">