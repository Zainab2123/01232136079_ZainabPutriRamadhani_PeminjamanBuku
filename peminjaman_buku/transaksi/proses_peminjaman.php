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
// PROSES PEMINJAMAN
// ============================================
if (isset($_POST['pinjam'])) {

    $id_buku = $_POST['id_buku'];
    $jumlah = $_POST['jumlah'];
    $tgl_pinjam = $_POST['tanggal_peminjaman'];

    // CEK STOK
    $cek = mysqli_query($conn, "
        SELECT stok, judul 
        FROM buku 
        WHERE id_buku='$id_buku'
    ");
    $data = mysqli_fetch_assoc($cek);

    if (!$data) {
        echo "Buku tidak ditemukan!";
        exit;
    }

    if ($data['stok'] < $jumlah) {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Stok Tidak Cukup - Perpustakaan</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <link rel="stylesheet" href="../assets/css/auth.css">
            <link rel="stylesheet" href="../assets/css/notifikasi.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body class="auth-body">
            <div class="notif-standalone">
                <div class="notif-standalone-icon notif-warning">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h3>Stok tidak cukup!</h3>
                <p>Maaf, jumlah buku yang ingin dipinjam melebihi stok yang tersedia. Silakan kurangi jumlah atau pilih buku lain.</p>
                <hr class="notif-standalone-divider">
                <a href='javascript:history.back()' class="notif-standalone-btn btn-warning">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // INSERT PEMINJAMAN
    mysqli_query($conn, "
        INSERT INTO peminjaman 
        (id_anggota, tanggal_peminjaman, status) 
        VALUES 
        ('$id_anggota', '$tgl_pinjam', 'menunggu_pinjam')
    ");

    $id_peminjaman = mysqli_insert_id($conn);

    // INSERT DETAIL PEMINJAMAN
    mysqli_query($conn, "
        INSERT INTO detail_peminjaman 
        (id_peminjaman, id_buku, jumlah) 
        VALUES 
        ('$id_peminjaman', '$id_buku', '$jumlah')
    ");

    // AMBIL DATA USER
    $get_user = mysqli_query($conn, "
        SELECT id_user, nama 
        FROM anggota 
        WHERE id_anggota='$id_anggota'
    ");

    $user_data = mysqli_fetch_assoc($get_user);

    $id_user = $user_data['id_user'];
    $nama_anggota = $user_data['nama'];

    // ============================================
    // NOTIFIKASI KE ADMIN
    // ============================================
    $admin = mysqli_query($conn, "
        SELECT id_user 
        FROM user 
        WHERE role='admin' 
        LIMIT 1
    ");

    $admin_data = mysqli_fetch_assoc($admin);

    if ($admin_data) {

        $id_admin = $admin_data['id_user'];

        $pesan_admin = $nama_anggota .
            " memohon untuk meminjam buku \"" .
            $data['judul'] .
            "\" sebanyak " . $jumlah . " eksemplar.";

        mysqli_query($conn, "
            INSERT INTO notifikasi 
            (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca) 
            VALUES 
            ('$id_admin', '$id_admin', 'permohonan_pinjam', '$pesan_admin', '$id_peminjaman', 0)
        ");
    }

    // ============================================
    // NOTIFIKASI KE ANGGOTA
    // ============================================
    $pesan_anggota = "Permohonan peminjaman buku \"" .
        $data['judul'] .
        "\" sebanyak $jumlah eksemplar berhasil dikirim! Menunggu persetujuan admin.";

    mysqli_query($conn, "
        INSERT INTO notifikasi 
        (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca) 
        VALUES 
        ('$id_user', NULL, 'info_pinjam', '$pesan_anggota', '$id_peminjaman', 0)
    ");

    // ============================================
    // FLASH MESSAGE
    // ============================================
    $_SESSION['flash_success'] = 'Permohonan peminjaman berhasil dikirim! Menunggu persetujuan admin.';

    // ============================================
    // REDIRECT KE DASHBOARD
    // ============================================
    header("Location: ../dashboard/dashboard_anggota.php");
    exit();
}
?>