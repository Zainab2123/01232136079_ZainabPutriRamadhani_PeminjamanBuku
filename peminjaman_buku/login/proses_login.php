<?php
session_start();
include '../koneksi.php';

$username = $_POST['username'];
$password = $_POST['password'];

// Ambil user dari DB berdasarkan username
$stmt = $conn->prepare("SELECT id_user, username, password, role FROM user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();

    $db_pass = $data['password'];

    // cek password
    if (password_verify($password, $db_pass) || $password === $db_pass) {

        // 🔥 WAJIB ADA INI
        $_SESSION['id_user'] = $data['id_user'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['role'] = $data['role'];

        // 🔥 AMBIL ID ANGGOTA + CEK STATUS
        if ($data['role'] === 'anggota') {

            $id_user = $data['id_user'];

            $query = $conn->prepare("
                SELECT id_anggota, status 
                FROM anggota 
                WHERE id_user = ?
            ");
            $query->bind_param("i", $id_user);
            $query->execute();
            $result2 = $query->get_result();
            $anggota = $result2->fetch_assoc();

            // 🚨 FIX: CEK DATA KOSONG
            if (!$anggota) {
                echo "Data anggota tidak ditemukan!";
                exit();
            }

            // CEK STATUS AKTIF / NONAKTIF
            if ($anggota['status'] !== 'aktif') {

                session_unset();
                session_destroy();

                ?>
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Akun Dinonaktifkan - Perpustakaan</title>
                    <link rel="stylesheet" href="../assets/css/style.css">
                    <link rel="stylesheet" href="../assets/css/auth.css">
                    <link rel="stylesheet" href="../assets/css/notifikasi.css">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
                </head>
                <body class="auth-body">
                    <div class="notif-standalone">
                        <div class="notif-standalone-icon notif-danger">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h3>Akun kamu sudah dinonaktifkan oleh admin!</h3>
                        <p>Silakan hubungi admin untuk informasi lebih lanjut:</p>
                        <p class="notif-detail"><i class="fas fa-phone"></i> 0812-3456-7890</p>
                        <p class="notif-detail"><i class="fas fa-envelope"></i> adminperpustakaan@gmail.com</p>
                        <hr class="notif-standalone-divider">
                        <a href='../login/login.php' class="notif-standalone-btn btn-primary">
                            <i class="fas fa-right-to-bracket"></i> Kembali ke Login
                        </a>
                    </div>
                </body>
                </html>
                <?php
                exit();
            }

            $_SESSION['id_anggota'] = $anggota['id_anggota'];
        }

        // redirect
        if ($data['role'] === 'admin') {
            header("Location: ../dashboard/dashboard_admin.php");
        } else {
            header("Location: ../dashboard/dashboard_anggota.php");
        }
        exit();

    } else {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Gagal - Perpustakaan</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <link rel="stylesheet" href="../assets/css/auth.css">
            <link rel="stylesheet" href="../assets/css/notifikasi.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body class="auth-body">
            <div class="notif-standalone">
                <div class="notif-standalone-icon notif-danger">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Password salah! Coba lagi</h3>
                <p>Password yang kamu masukkan tidak sesuai. Silakan periksa kembali dan coba lagi.</p>
                <hr class="notif-standalone-divider">
                <a href='login.php' class="notif-standalone-btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Kembali ke Login
                </a>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Gagal - Perpustakaan</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/auth.css">
        <link rel="stylesheet" href="../assets/css/notifikasi.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>
    <body class="auth-body">
        <div class="notif-standalone">
            <div class="notif-standalone-icon notif-danger">
                <i class="fas fa-user-xmark"></i>
            </div>
            <h3>Username tidak ditemukan! Coba lagi</h3>
            <p>Username yang kamu masukkan tidak terdaftar. Silakan periksa kembali atau daftar akun baru.</p>
            <hr class="notif-standalone-divider">
            <a href='login.php' class="notif-standalone-btn btn-danger">
                <i class="fas fa-arrow-left"></i> Kembali ke Login
            </a>
        </div>
    </body>
    </html>
    <?php
}

$stmt->close();
$conn->close();
?>