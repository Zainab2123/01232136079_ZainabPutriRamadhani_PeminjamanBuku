<?php
session_start();
require '../koneksi.php';

// =======================
// CEK LOGIN
// =======================
if (!isset($_SESSION['id_anggota'])) {
    echo "Silakan login dulu!";
    exit;
}

$id_anggota = $_SESSION['id_anggota'];

// =======================
// CEK STATUS AKTIF / NONAKTIF
// =======================
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

// =======================
// AMBIL ID BUKU
// =======================
if (!isset($_GET['id_buku'])) {
    echo "ID buku tidak ditemukan!";
    exit;
}

$id_buku = $_GET['id_buku'];

// =======================
// AMBIL DATA BUKU
// =======================
$query = mysqli_query($conn, "SELECT * FROM buku WHERE id_buku='$id_buku'");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "Data buku tidak ditemukan!";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Buku - Perpustakaan</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transaksi.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

<div class="transaksi-page">

    <a href="../dashboard/dashboard_anggota.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>

    <div class="transaksi-card">

        <h3><i class="fas fa-hand-holding"></i> Form Peminjaman Buku</h3>

        <!-- BOOK PREVIEW -->
        <div class="book-preview" style="display:flex; gap:15px; align-items:center;">

            <?php
            $gambar_path = "../assets/gambar/" . $data['gambar_buku'];
            $has_image = !empty($data['gambar_buku']) && file_exists("../assets/gambar/" . $data['gambar_buku']);
            ?>

            <?php if ($has_image): ?>
                <img src="<?= $gambar_path ?>" alt="<?= $data['judul']; ?>">
            <?php else: ?>
                <div class="book-preview-placeholder">
                    <i class="fas fa-book"></i>
                </div>
            <?php endif; ?>

            <div style="text-align:left; line-height:1.6;">

                <p style="font-size:16px; font-weight:bold;">
                    <?= $data['judul']; ?>
                </p>

                <p><b>Penulis:</b> <?= $data['penulis']; ?></p>
                <p><b>Stok:</b> <?= $data['stok']; ?></p>

            </div>

        </div>

        <hr style="margin:15px 0;">

        <!-- FORM (TIDAK DIUBAH) -->
        <form method="POST" action="proses_peminjaman.php" class="transaksi-form" id="formPinjam">

            <input type="hidden" name="id_buku" value="<?= $data['id_buku']; ?>">
            <input type="hidden" name="id_anggota" value="<?= $_SESSION['id_anggota']; ?>">

            <input type="hidden" name="pinjam" value="1">

            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Jumlah Pinjam:</label>
                <input type="number" name="jumlah" min="1" placeholder="Jumlah buku" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Tanggal Pinjam:</label>
                <input type="date" name="tanggal_peminjaman" required>
            </div>

            <button type="button" name="pinjam" class="submit-btn" onclick="confirmPinjam()">
                <i class="fas fa-check"></i> Pinjam
            </button>

        </form>

    </div>
</div>

<!-- MODAL (TIDAK DIUBAH) -->
<div id="modalKonfirmasi" class="modal-overlay"
     onclick="if(event.target===this) tutupModal()">

    <div class="modal-box">

        <div style="width:56px;height:56px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            margin:0 auto 16px;font-size:1.5rem;
            background:var(--primary-50);color:var(--primary);">
            <i class="fas fa-hand-holding"></i>
        </div>

        <p style="font-size:1rem;color:var(--gray-700);
            margin-bottom:1.5rem;font-weight:500;">
            Kamu yakin mau meminjam buku <strong><?= addslashes($data['judul']); ?></strong>?
        </p>

        <div class="modal-actions">

            <button type="button" onclick="submitPinjam()">
                Ya, Pinjam
            </button>

            <button type="button" class="btn-secondary" onclick="tutupModal()">
                Batal
            </button>

        </div>

    </div>
</div>

<!-- 🔥 FIX FUNCTION ONLY -->
<script>
function confirmPinjam() {
    document.getElementById('modalKonfirmasi').style.display = 'flex';
}

function tutupModal() {
    document.getElementById('modalKonfirmasi').style.display = 'none';
}

/*
FIX UTAMA:
biar form PHP benar-benar terkirim (POST + pinjam=1 tetap kebaca)
*/
function submitPinjam() {
    document.getElementById('formPinjam').requestSubmit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') tutupModal();
});
</script>

</body>
</html>