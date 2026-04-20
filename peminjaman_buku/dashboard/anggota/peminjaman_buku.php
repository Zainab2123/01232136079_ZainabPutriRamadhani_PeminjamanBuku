<?php
session_start();
require '../../koneksi.php';

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

/*
========================
SEARCH
========================
*/
// SEARCH HISTORY
if (!isset($_SESSION['search_history_pinjam'])) {
    $_SESSION['search_history_pinjam'] = [];
}

$keyword = '';
$is_search = false;

// kalau search
if (isset($_POST['search'])) {
    $keyword = trim($_POST['keyword']);

    if ($keyword != '') {
        $is_search = true;

        $_SESSION['keyword_pinjam'] = $keyword;
        $_SESSION['search_history_pinjam'][] = $keyword;
    }
}

// kalau masih ada keyword di session
if (isset($_SESSION['keyword_pinjam']) && !isset($_GET['reset'])) {
    $keyword = $_SESSION['keyword_pinjam'];
    $is_search = true;
}

// kalau reset
if (isset($_GET['reset'])) {
    $keyword = '';
    $is_search = false;

    unset($_SESSION['keyword_pinjam']);
}

// history untuk datalist
$history_for_input = array_unique($_SESSION['search_history_pinjam']);
// =======================
// AMBIL DATA KATEGORI
// =======================
$kategori = mysqli_query($conn, "SELECT * FROM kategori");

// =======================
// FILTER / SORT
// =======================
$sort = isset($_POST['sort']) ? $_POST['sort'] : '';
$kategori_id = isset($_POST['kategori']) ? $_POST['kategori'] : '';

$order = "";
$where_kategori = "";

switch ($sort) {
    case 'terbaru':
        $order = "ORDER BY p.tanggal_peminjaman DESC";
        break;
    case 'terlama':
        $order = "ORDER BY p.tanggal_peminjaman ASC";
        break;
    case 'az':
        $order = "ORDER BY b.judul ASC";
        break;
    case 'za':
        $order = "ORDER BY b.judul DESC";
        break;
}

// filter kategori
if (!empty($kategori_id)) {
    $where_kategori = "AND b.id_kategori = '$kategori_id'";
}

// =======================
// QUERY (menampilkan dipinjam & menunggu_pinjam)
// =======================
if ($is_search && $keyword != '') {

    $query = mysqli_query($conn, "
    SELECT
        p.id_peminjaman,
        p.tanggal_peminjaman,
        p.status,
        b.judul,
        b.penulis,
        b.penerbit,
        b.gambar_buku,
        d.jumlah,
        k.nama_kategori
    FROM peminjaman p
    JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
    JOIN buku b ON d.id_buku = b.id_buku
    JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.id_anggota = '$id_anggota'
    AND p.status IN ('dipinjam', 'menunggu_pinjam')
    AND (
        b.judul LIKE '%$keyword%'
        OR b.penulis LIKE '%$keyword%'
        OR b.penerbit LIKE '%$keyword%'
        OR k.nama_kategori LIKE '%$keyword%'
    )
    $where_kategori
    $order
    ");

} else {

    $query = mysqli_query($conn, "
    SELECT
        p.id_peminjaman,
        p.tanggal_peminjaman,
        p.status,
        b.judul,
        b.penulis,
        b.penerbit,
        b.gambar_buku,
        d.jumlah,
        k.nama_kategori
    FROM peminjaman p
    JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
    JOIN buku b ON d.id_buku = b.id_buku
    JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.id_anggota = '$id_anggota'
    AND p.status IN ('dipinjam', 'menunggu_pinjam')
    $where_kategori
    $order
    ");
}

// Fungsi helper status badge
function getStatusBadgeAnggota($status)
{
    switch ($status) {
        case 'menunggu_pinjam':
            return '<span class="status-badge status-menunggu-pinjam"><i class="fas fa-clock"></i> Menunggu Persetujuan</span>';
        case 'dipinjam':
            return '<span class="status-badge status-dipinjam"><i class="fas fa-book-open"></i> Dipinjam</span>';
        default:
            return '<span class="status-badge">' . htmlspecialchars($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Buku - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/anggota.css">
    <link rel="stylesheet" href="../../assets/css/notifikasi.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="anggota-page">

        <a href="../dashboard_anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>

        <h2><i class="fas fa-hand-holding"></i> Buku yang Dipinjam</h2>

        <form method="POST" class="top-bar">

            <div class="search-box">
                <input type="text" name="keyword" placeholder="Cari judul, penulis, atau penerbit..." list="history"
                    value="<?php echo htmlspecialchars($keyword); ?>">

                <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                <button type="button" id="resetBtn" class="btn-secondary">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const resetBtn = document.getElementById('resetBtn');

                    if (resetBtn) {
                        resetBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            window.location.href = window.location.pathname + '?reset=1';
                        });
                    }
                });
            </script>

            <div class="filter-box">
                <select name="sort" onchange="this.form.submit()">
                    <option value="" <?= $sort == '' ? 'selected' : '' ?>>--Urutkan--</option>
                    <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Terlama</option>
                    <option value="az" <?= $sort == 'az' ? 'selected' : '' ?>>A-Z</option>
                    <option value="za" <?= $sort == 'za' ? 'selected' : '' ?>>Z-A</option>
                </select>

                <?php
                $kategori_list = [];
                while ($k = mysqli_fetch_assoc($kategori)) {
                    $kategori_list[] = $k;
                }
                ?>
                <select name="kategori" onchange="this.form.submit()">
                    <option value="" <?= $kategori_id == '' ? 'selected' : '' ?>>--Kategori--</option>

                    <?php foreach ($kategori_list as $k) { ?>
                        <option value="<?php echo $k['id_kategori']; ?>" <?= $kategori_id == $k['id_kategori'] ? 'selected' : '' ?>>
                            <?php echo $k['nama_kategori']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

        </form>

        <?php
        if (mysqli_num_rows($query) > 0) {
            while ($row = mysqli_fetch_assoc($query)) {
                ?>

                <div class="book-item">

                    <div class="book-item-header">
                        <div class="book-item-image">
                            <?php
                            $img_path = "../../assets/gambar/" . $row['gambar_buku'];
                            $has_img = !empty($row['gambar_buku']) && file_exists("../../assets/gambar/" . $row['gambar_buku']);
                            ?>
                            <?php if ($has_img): ?>
                                <img src="<?php echo $img_path; ?>" alt="<?php echo $row['judul']; ?>">
                            <?php else: ?>
                                <div class="book-item-no-image">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="book-item-details">
                            <p class="book-item-title"><?php echo $row['judul']; ?></p>
                            <p><b>Penulis:</b> <?php echo $row['penulis']; ?></p>
                            <p><b>Penerbit:</b> <?php echo $row['penerbit']; ?></p>
                            <p><b>Kategori:</b> <?php echo $row['nama_kategori']; ?></p>
                            <p><b>Jumlah:</b> <?php echo $row['jumlah']; ?></p>
                            <p><b>Tanggal Pinjam:</b> <?php echo $row['tanggal_peminjaman']; ?></p>
                            <p><?= getStatusBadgeAnggota($row['status']) ?></p>

                            <?php if ($row['status'] === 'dipinjam'): ?>
                                <div>
                                    <!-- 🔥 UBAH INI SAJA (HAPUS MODAL, LANGSUNG KE PAGE) -->
                                    <button type="button" class="btn-warning btn-sm"
                                        onclick="window.location.href='../../transaksi/pengembalian.php?id_peminjaman=<?php echo $row['id_peminjaman']; ?>'">
                                        <i class="fas fa-rotate-left"></i> Kembalikan
                                    </button>
                                </div>
                            <?php elseif ($row['status'] === 'menunggu_pinjam'): ?>
                                <div>
                                    <button class="btn-secondary btn-sm" disabled style="opacity:0.7;cursor:not-allowed;">
                                        <i class="fas fa-clock"></i> Menunggu Persetujuan Admin
                                    </button>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                </div>

                <?php
            }
        } else {
            echo "<div class='empty-state'><i class='fas fa-book-open'></i><p>Tidak ada buku yang sedang dipinjam.</p></div>";
        }
        ?>

    </div>
    
    <?php include '../../footer.php'; ?>

</body>

</html>