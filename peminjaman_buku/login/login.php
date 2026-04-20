<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Perpustakaan</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="auth-body">

    <div class="auth-container">
        <div class="auth-icon">
            <i class="fas fa-book-open-reader"></i>
        </div>

        <h2>Login</h2>
        <p class="auth-subtitle">Masuk ke akun perpustakaan kamu</p>

        <form method="POST" action="proses_login.php">
            <label><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" placeholder="Masukkan username" required>

            <label><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required>

            <button type="submit"><i class="fas fa-right-to-bracket"></i> Login</button>
        </form>

        <p class="auth-footer">Belum punya akun? <a href="../register/register.php">Daftar di sini</a></p>
    </div>

</body>

</html>
