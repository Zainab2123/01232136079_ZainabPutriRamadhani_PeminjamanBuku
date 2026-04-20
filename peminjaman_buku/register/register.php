<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Perpustakaan</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="auth-body">

    <div class="auth-container">
        <div class="auth-icon">
            <i class="fas fa-user-plus"></i>
        </div>

        <h2>Daftar Akun</h2>
        <p class="auth-subtitle">Buat akun baru perpustakaan</p>

        <form method="POST" action="proses_register.php">
            <label><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" placeholder="Pilih username" required>

            <label><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" placeholder="Buat password" required>

            <button type="submit"><i class="fas fa-user-check"></i> Daftar</button>
        </form>

        <p class="auth-footer">Sudah punya akun? <a href="../login/login.php">Login di sini</a></p>
    </div>

</body>

</html>
