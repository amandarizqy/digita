<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?? 'DIGITA' ?></title>
  <!-- Untuk CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <!-- Untuk Gambar (seperti logo di sidebar/navbar) -->
  <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo"> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- TAMBAHAN: SweetAlert2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

  <style>
    .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px;
               background: #0b253a; color: #fff; z-index: 1000; }
    .main-wrapper { margin-left: 250px; min-height: 100vh; display: flex; flex-direction: column; }
    .content-body { padding: 30px; flex: 1; }
  </style>
</head>
<body class="bg-light">
  <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="content-body"><?= $content ?? '' ?></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- SweetAlert2 CSS & JS CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>