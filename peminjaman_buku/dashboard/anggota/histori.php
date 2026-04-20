<?php
include '../../koneksi.php';
session_start();

/*
========================================
CEK LOGIN DASAR (optional aman)
========================================
*/
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
SESSION HIDE
========================
*/
if (!isset($_SESSION['hide_histori'])) {
    $_SESSION['hide_histori'] = [];
}

if (isset($_POST['hapus_id'])) {
    $_SESSION['hide_histori'][] = $_POST['hapus_id'];
}

/*
========================
CLEAR SEMUA (FIX)
========================
*/
if (isset($_POST['clear_all'])) {
    $_SESSION['hide_histori'] = [];

    $ambil_semua = mysqli_query($conn, "
        SELECT id_peminjaman 
        FROM peminjaman 
        WHERE status = 'dikembalikan'
    ");

    while ($data = mysqli_fetch_assoc($ambil_semua)) {
        $_SESSION['hide_histori'][] = $data['id_peminjaman'];
    }
}

/*
========================
SEARCH
========================
*/
$keyword = '';
$is_search = false;

if (isset($_POST['search'])) {
    $keyword = trim($_POST['keyword']);
    if ($keyword != '') {
        $is_search = true;
    }
}

if (isset($_POST['reset'])) {
    $_POST = [];
    $keyword = '';
    $is_search = false;
}

/*
========================
FILTER
========================
*/
$kategori = mysqli_query($conn, "SELECT * FROM kategori");

$sort = isset($_POST['sort']) ? $_POST['sort'] : '';
$kategori_id = isset($_POST['kategori']) ? $_POST['kategori'] : '';

$order = "";
$where_kategori = "";

switch ($sort) {
    case 'terbaru':
        $order = "ORDER BY p.tanggal_kembali DESC";
        break;
    case 'terlama':
        $order = "ORDER BY p.tanggal_kembali ASC";
        break;
    case 'az':
        $order = "ORDER BY b.judul ASC";
        break;
    case 'za':
        $order = "ORDER BY b.judul DESC";
        break;
}

if (!empty($kategori_id)) {
    $where_kategori = "AND b.id_kategori = '$kategori_id'";
}

/*
========================
QUERY
========================
*/
if ($is_search && $keyword != '') {

    $query = mysqli_query($conn, "
    SELECT
        p.id_peminjaman,
        p.tanggal_peminjaman,
        p.tanggal_kembali,
        b.id_buku,
        b.judul,
        b.penulis,
        b.penerbit,
        b.gambar_buku,
        d.jumlah,
        k.nama_kategori
    FROM peminjaman p
    JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
    JOIN buku b ON d.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.status = 'dikembalikan'
    AND (
        b.judul LIKE '%$keyword%'
        OR b.penulis LIKE '%$keyword%'
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
        p.tanggal_kembali,
        b.id_buku,
        b.judul,
        b.penulis,
        b.penerbit,
        b.gambar_buku,
        d.jumlah,
        k.nama_kategori
    FROM peminjaman p
    JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
    JOIN buku b ON d.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.status = 'dikembalikan'
    $where_kategori
    $order
    ");
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Peminjaman</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/anggota.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="anggota-page">

        <a href="../dashboard_anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>

        <h1><i class="fas fa-clock-rotate-left"></i> Histori Peminjaman (Dikembalikan)</h1>

        <form method="POST" class="top-bar">

            <div class="search-box">
                <input type="text" name="keyword" placeholder="Cari judul, penulis, atau penerbit...">

                <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                <button type="submit" name="reset" class="btn-secondary"><i class="fas fa-rotate-left"></i>
                    Reset</button>
            </div>

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

        <div class="clear-btn-wrapper">
            <form method="POST">
                <button type="submit" name="clear_all" class="btn-secondary btn-sm">
                    <i class="fas fa-broom"></i> Clear Semua Histori
                </button>
            </form>
        </div>

        <?php
        $has_data = false;

        if (mysqli_num_rows($query) > 0) {
            while ($row = mysqli_fetch_assoc($query)) {

                if (in_array($row['id_peminjaman'], $_SESSION['hide_histori'])) {
                    continue;
                }

                $has_data = true;
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
                            <p><b>Tanggal Kembali:</b> <?php echo $row['tanggal_kembali']; ?></p>

                            <div>
                                <button type="button" class="btn-secondary btn-sm"
                                    onclick="confirmHapusHistori('<?php echo $row['id_peminjaman']; ?>', '<?php echo addslashes($row['judul']); ?>')"><i
                                        class="fas fa-trash"></i> Hapus</button>
                            </div>

                        </div>

                    </div>

                </div>

                <?php
            }
        }

        if (!$has_data) {
            echo "<div class='empty-state'><i class='fas fa-clock-rotate-left'></i><p>Tidak ada histori peminjaman.</p></div>";
        }
        ?>

    </div>

    <!-- MODAL KONFIRMASI HAPUS HISTORI -->
    <div id="modalKonfirmasi" class="modal-overlay" onclick="if(event.target===this) tutupModal()">
        <div class="modal-box">
            <div
                style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;background:#FEE2E2;color:#DC2626;">
                <i class="fas fa-trash"></i>
            </div>
            <p style="font-size:1rem;color:var(--gray-700);margin-bottom:1.5rem;font-weight:500;" id="modalPesan">Kamu
                yakin?</p>
            <div class="modal-actions">
                <button id="modalBtnYa" onclick="">Ya</button>
                <button class="btn-secondary" onclick="tutupModal()">Batal</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for hapus -->
    <form id="formHapus" method="POST" style="display:none;"><input type="hidden" name="hapus_id" id="hapusIdInput">
    </form>

    <script>
        function confirmHapusHistori(id, judul) {
            document.getElementById('modalPesan').innerHTML = 'Hapus histori peminjaman buku <strong>\'' + judul + '\'?</strong>';
            document.getElementById('modalBtnYa').onclick = function () {
                document.getElementById('hapusIdInput').value = id;
                document.getElementById('formHapus').submit();
            };
            document.getElementById('modalKonfirmasi').style.display = 'flex';
        }
        function tutupModal() {
            document.getElementById('modalKonfirmasi').style.display = 'none';
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') tutupModal();
        });
    </script>

    <?php include '../../footer.php'; ?>

</body>

</html>