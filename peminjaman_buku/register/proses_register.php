<?php
include '../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = 'anggota'; // role default

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Cek apakah username sudah ada
    $check = $conn->prepare("SELECT username FROM user WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Register Gagal - Perpustakaan</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <link rel="stylesheet" href="../assets/css/auth.css">
            <link rel="stylesheet" href="../assets/css/notifikasi.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body class="auth-body">
            <div class="notif-standalone">
                <div class="notif-standalone-icon notif-warning">
                    <i class="fas fa-user-check"></i>
                </div>
                <h3>Username sudah digunakan silahkan pilih username lain</h3>
                <p>Username yang kamu pilih sudah terdaftar. Silakan pilih username lain dan coba lagi.</p>
                <hr class="notif-standalone-divider">
                <a href='register.php' class="notif-standalone-btn btn-warning">
                    <i class="fas fa-arrow-left"></i> Kembali ke Register
                </a>
            </div>
        </body>
        </html>
        <?php
    } else {
        // Insert ke tabel user
        $stmt = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        if ($stmt->execute()) {

            // 🔥 ambil id_user terakhir
            $id_user = $conn->insert_id;

            // 🔥 nama random sementara
            $nama_random = "user" . rand(10000, 99999);

            // 🔥 insert ke tabel anggota
            $stmt2 = $conn->prepare("INSERT INTO anggota (id_user, nama, kelas, no_hp) VALUES (?, ?, '', '')");
            $stmt2->bind_param("is", $id_user, $nama_random);
            $stmt2->execute();
            $stmt2->close();

            echo "Registrasi berhasil! <a href='../login/login.php'>Login di sini</a>";

        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $check->close();
}

$conn->close();
?>