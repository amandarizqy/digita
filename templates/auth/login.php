<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Digita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Memanggil file CSS eksternal dari folder assets -->
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-login">
<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-md-4">
            <div class="card card-login p-4 shadow">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-dark mb-1">DIGITA</h3>
                    <span class="text-muted small">Manajemen Mutu & Siklus Aset PLN</span>
                </div>

                <!-- Menampilkan pesan error dari file logic login.php -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger py-2 small text-center" role="alert">
                        <?= $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Form diarahkan ke file logic PHP itu sendiri -->
                <form action="../../modules/auth/login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username / NIP</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="******" required>
                    </div>
                    <button type="submit" class="btn btn-pln w-100 py-2">Masuk ke Sistem</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>