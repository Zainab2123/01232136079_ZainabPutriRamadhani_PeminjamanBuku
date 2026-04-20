<?php
session_start();
require '../koneksi.php';

// ============================================
// CEK LOGIN
// ============================================
if (!isset($_SESSION['id_anggota'])) {
    echo "Silakan login dulu!";
    exit;
}

$id_anggota = $_SESSION['id_anggota'];

// ============================================
// CEK STATUS AKTIF
// ============================================
$cek_status = mysqli_query($conn, "
    SELECT status FROM anggota WHERE id_anggota='$id_anggota'
");

$data_status = mysqli_fetch_assoc($cek_status);

if (!$data_status || $data_status['status'] !== 'aktif') {

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

// ============================================
// PROSES PENGEMBALIAN (FIX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id_peminjaman'];
    $tgl_kembali = $_POST['tanggal_pengembalian'];

    // AMBIL DATA BUKU
    $q = mysqli_query($conn, "
    SELECT dp.id_buku, dp.jumlah, b.judul
    FROM detail_peminjaman dp
    JOIN buku b ON dp.id_buku = b.id_buku
    WHERE dp.id_peminjaman='$id'
    ");

    $data = mysqli_fetch_assoc($q);
    $judul_buku = $data['judul'];

    // UPDATE STATUS
    mysqli_query($conn, "
    UPDATE peminjaman 
    SET status='menunggu_kembali' 
    WHERE id_peminjaman='$id'
    ");

    // AMBIL DATA USER
    $get_user = mysqli_query($conn, "SELECT id_user, nama FROM anggota WHERE id_anggota='$id_anggota'");
    $user_data = mysqli_fetch_assoc($get_user);

    $id_user = $user_data['id_user'];
    $nama_anggota = $user_data['nama'];

    // ============================================
    // NOTIFIKASI KE ADMIN
    // ============================================
    $admin = mysqli_query($conn, "SELECT id_user FROM user WHERE role='admin' LIMIT 1");
    $admin_data = mysqli_fetch_assoc($admin);

    if ($admin_data) {
        $id_admin = $admin_data['id_user'];

        $pesan_admin = "$nama_anggota memohon untuk mengembalikan buku \"{$judul_buku}\".";

        mysqli_query($conn, "INSERT INTO notifikasi 
        (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca) 
        VALUES 
        ('$id_admin', '$id_admin', 'permohonan_kembali', '$pesan_admin', '$id', 0)");
    }

    // ============================================
    // NOTIFIKASI KE ANGGOTA
    // ============================================
    $pesan_anggota = "Permohonan pengembalian buku \"{$judul_buku}\" berhasil dikirim! Menunggu persetujuan admin.";

    mysqli_query($conn, "INSERT INTO notifikasi 
    (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca) 
    VALUES 
    ('$id_user', NULL, 'info_kembali', '$pesan_anggota', '$id', 0)");

    // ============================================
    // FLASH MESSAGE
    // ============================================
    $_SESSION['flash_success'] = 'Permohonan pengembalian berhasil dikirim! Menunggu persetujuan admin.';

    // REDIRECT
    header("Location: ../dashboard/dashboard_anggota.php");
    exit;
}
?>