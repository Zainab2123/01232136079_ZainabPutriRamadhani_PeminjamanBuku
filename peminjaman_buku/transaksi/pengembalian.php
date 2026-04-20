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

/*
========================================
CEK STATUS AKTIF / NONAKTIF
========================================
*/
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
// VALIDASI ID PINJAMAN
// =======================
if (!isset($_GET['id_peminjaman'])) {
    echo "ID peminjaman tidak ditemukan!";
    exit;
}

$id = $_GET['id_peminjaman'];

// =======================
// AMBIL DATA
// =======================
$query = mysqli_query($conn, "
SELECT
    p.id_peminjaman,
    p.tanggal_peminjaman,
    b.judul,
    b.penulis,
    b.gambar_buku,
    d.jumlah
FROM peminjaman p
JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
JOIN buku b ON d.id_buku = b.id_buku
WHERE p.id_peminjaman='$id'
");

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "Data tidak ditemukan!";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Buku - Perpustakaan</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transaksi.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="transaksi-page">

        <a href="../dashboard/dashboard_anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>

        <div class="transaksi-card">
            <h3><i class="fas fa-rotate-left"></i> Form Pengembalian Buku</h3>

            <!-- BOOK PREVIEW -->
            <div class="book-preview" style="display:flex; gap:15px; align-items:center;">

                <!-- GAMBAR -->
                <?php
                $gambar_path = "../assets/gambar/" . $data['gambar_buku'];
                $has_image = !empty($data['gambar_buku']) && file_exists("../assets/gambar/" . $data['gambar_buku']);
                ?>
                <?php if ($has_image): ?>
                    <img src="<?= $gambar_path ?>" alt="<?php echo $data['judul']; ?>">
                <?php else: ?>
                    <div class="book-preview-placeholder">
                        <i class="fas fa-book"></i>
                    </div>
                <?php endif; ?>

                <!-- DETAIL (VERTICAL CENTER) -->
                <div style="text-align:left; line-height:1.6;">

                    <p style="font-size:16px; font-weight:bold; margin-bottom:18px;" class="book-title">
                        <?php echo $data['judul']; ?>
                    </p>

                    <p style="margin-bottom:2px;"><b>Penulis:</b> <?php echo $data['penulis']; ?></p>
                    <p style="margin-bottom:2px;"><b>Jumlah:</b> <?php echo $data['jumlah']; ?></p>
                    <p><b>Tanggal Pinjam:</b> <?php echo $data['tanggal_peminjaman']; ?></p>

                </div>

            </div>

            <hr style="margin:15px 0;">

            <form method="POST" action="proses_pengembalian.php" class="transaksi-form" id="formKembali">

                <input type="hidden" name="id_peminjaman" value="<?php echo $data['id_peminjaman']; ?>">

                <div class="form-group">
                    <label><i class="fas fa-calendar-check"></i> Tanggal Pengembalian:</label>
                    <input type="date" name="tanggal_pengembalian" required>
                </div>

                <!-- ✅ FIX: TAMBAH TYPE BUTTON (BIAR GA RELOAD) -->
                <button type="button" name="kembalikan" class="submit-btn" onclick="confirmKembali()">
                    <i class="fas fa-check"></i> Kembalikan
                </button>

            </form>
        </div>

    </div>

    <!-- MODAL KONFIRMASI KEMBALIKAN -->
    <div id="modalKonfirmasi" class="modal-overlay" onclick="if(event.target===this) tutupModal()">
        <div class="modal-box">
            <div style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;background:#FEF3C7;color:#F59E0B;">
                <i class="fas fa-rotate-left"></i>
            </div>
            <p style="font-size:1rem;color:var(--gray-700);margin-bottom:1.5rem;font-weight:500;">
                Kamu yakin mau mengembalikan buku <strong><?php echo addslashes($data['judul']); ?></strong>?
            </p>
            <div class="modal-actions">
                <!-- ✅ FIX: TAMBAH TYPE BUTTON -->
                <button type="button" onclick="document.getElementById('formKembali').submit()">
                    Ya, Kembalikan
                </button>

                <button type="button" class="btn-secondary" onclick="tutupModal()">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <script>
    function confirmKembali() {
        document.getElementById('modalKonfirmasi').style.display = 'flex';
    }
    function tutupModal() {
        document.getElementById('modalKonfirmasi').style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') tutupModal();
    });
    </script>

</body>

</html>