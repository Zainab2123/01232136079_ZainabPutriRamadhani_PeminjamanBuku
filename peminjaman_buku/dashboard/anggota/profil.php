<?php
session_start();
include '../../koneksi.php';

// =======================
// CEK LOGIN
// =======================
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    echo "Kamu belum login!";
    exit;
}

$id_user = $_SESSION['id_user'];

// =======================
// CEK STATUS AKTIF / NONAKTIF (FIX SECURITY)
// =======================
$cek_status = $conn->prepare("SELECT status FROM anggota WHERE id_user = ?");
$cek_status->bind_param("i", $id_user);
$cek_status->execute();
$res_status = $cek_status->get_result();
$status_data = $res_status->fetch_assoc();

if (!$status_data || $status_data['status'] !== 'aktif') {

    session_unset();
    session_destroy();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Akun Dinonaktifkan - Perpustakaan</title>
        <link rel="stylesheet" href="../../assets/css/style.css">
        <link rel="stylesheet" href="../../assets/css/auth.css">
        <link rel="stylesheet" href="../../assets/css/notifikasi.css">
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
            <a href='../../login/login.php' class="notif-standalone-btn btn-primary">
                <i class="fas fa-right-to-bracket"></i> Kembali ke Login
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// =======================
// AMBIL DATA ANGGOTA
// =======================
$query = "SELECT * FROM anggota WHERE id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// =======================
// CEK DATA ADA ATAU TIDAK
// =======================
if (!$data) {
    echo "Data anggota tidak ditemukan!";
    exit;
}

// =======================
// UPDATE PROFIL
// =======================
if (isset($_POST['update'])) {

    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $no_hp = $_POST['no_hp'];

    $update = $conn->prepare("
        UPDATE anggota
        SET nama = ?, kelas = ?, no_hp = ?
        WHERE id_user = ?
    ");
    $update->bind_param("sssi", $nama, $kelas, $no_hp, $id_user);
    $update->execute();

    header("Location: profil.php");
    exit;
}

// =======================
// KONTROL FORM (TOGGLE)
// =======================
$showForm = false;
if (isset($_GET['aksi']) && $_GET['aksi'] == 'edit') {
    $showForm = true;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/anggota.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="anggota-page">

        <a href="../dashboard_anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>

        <div class="profile-card">
            <h1><i class="fas fa-user-circle"></i> Profil Saya</h1>

            <div class="profile-info">
                <div class="profile-info-item">
                    <i class="fas fa-user"></i>
                    <span><b>Nama:</b> <?= $data['nama']; ?></span>
                </div>
                <div class="profile-info-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span><b>Kelas:</b> <?= $data['kelas']; ?></span>
                </div>
                <div class="profile-info-item">
                    <i class="fas fa-phone"></i>
                    <span><b>No HP:</b> <?= $data['no_hp']; ?></span>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <?php if ($showForm): ?>
                    <a href="profil.php">
                        <button class="btn-secondary"><i class="fas fa-xmark"></i> Tutup Form</button>
                    </a>
                <?php else: ?>
                    <a href="?aksi=edit">
                        <button><i class="fas fa-pen-to-square"></i> Edit Profil</button>
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <div class="profile-form" id="editForm" style="<?= $showForm ? 'display:block;' : 'display:none;' ?>">
            <h2><i class="fas fa-pen-to-square"></i> Edit Profil</h2>

            <form method="POST">

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nama</label>
                        <input type="text" name="nama" value="<?= $data['nama']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-graduation-cap"></i> Kelas</label>
                        <input type="text" name="kelas" value="<?= $data['kelas']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> No HP</label>
                        <input type="text" name="no_hp" value="<?= $data['no_hp']; ?>" required>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 1.25rem;">
                    <button type="submit" name="update"><i class="fas fa-floppy-disk"></i> Simpan</button>
                </div>

            </form>
        </div>

    </div>

    <?php include '../../footer.php'; ?>

</body>

</html>