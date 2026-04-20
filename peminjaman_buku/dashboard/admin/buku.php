<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../../koneksi.php';

// =======================
// HAPUS BUKU
// Dipanggil via ?hapus=id. Hapus file gambar dari server,
// lalu hapus baris dari tabel buku.
// =======================
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];

    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar_buku FROM buku WHERE id_buku='$id'"));

    if (file_exists("../../assets/gambar/" . $old['gambar_buku'])) {
        unlink("../../assets/gambar/" . $old['gambar_buku']);
    }

    mysqli_query($conn, "DELETE FROM buku WHERE id_buku='$id'");

    header("Location: buku.php");
    exit;
}

// =======================
// KONTROL FORM (TOGGLE)
// Tentukan apakah form tambah/edit ditampilkan.
// ?aksi=tambah → form kosong | ?aksi=edit&id=X → form terisi data buku X
// =======================
$edit = null;
$showForm = false;

if (isset($_GET['aksi'])) {

    // kalau tambah
    if ($_GET['aksi'] == 'tambah') {
        $showForm = true;
    }

    // kalau edit
    if ($_GET['aksi'] == 'edit' && isset($_GET['id'])) {
        $id_edit = $_GET['id'];
        $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM buku WHERE id_buku='$id_edit'"));
        $showForm = true;
    }
}

// =======================
// TAMBAH BUKU
// Proses form POST tambah: upload gambar ke assets/gambar,
// lalu INSERT data buku baru ke database.
// =======================
if (isset($_POST['tambah'])) {

    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun'];
    $stok = $_POST['stok'];
    $kategori = $_POST['kategori'];
    $status = $_POST['status'] ?? 'rilis';
    $tanggal_rilis = (!empty($_POST['tanggal_rilis'])) ? $_POST['tanggal_rilis'] : null;
    $auto_release = isset($_POST['auto_release']) ? 1 : 0;

    $tgl_sql = $tanggal_rilis ? "'$tanggal_rilis'" : "NULL";

    $nama_file = $_FILES['gambar']['name'];
    $tmp = $_FILES['gambar']['tmp_name'];

    move_uploaded_file($tmp, "../../assets/gambar/" . $nama_file);

    mysqli_query($conn, "INSERT INTO buku
    (judul, penulis, penerbit, tahun_terbit, stok, id_kategori, gambar_buku, status, tanggal_rilis, auto_release)
    VALUES
    ('$judul','$penulis','$penerbit','$tahun','$stok','$kategori','$nama_file','$status',$tgl_sql,$auto_release)");

    header("Location: buku.php");
    exit;
}

// =======================
// UPDATE BUKU
// Proses form POST edit: jika ada gambar baru, hapus yang lama
// dulu lalu upload baru, kemudian UPDATE semua kolom buku.
// =======================
if (isset($_POST['update'])) {

    $id = $_POST['id_buku'];
    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun'];
    $stok = $_POST['stok'];
    $kategori = $_POST['kategori'];

    if ($_FILES['gambar']['name'] != '') {

        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar_buku FROM buku WHERE id_buku='$id'"));

        if (file_exists("../../assets/gambar/" . $old['gambar_buku'])) {
            unlink("../../assets/gambar/" . $old['gambar_buku']);
        }

        $nama_file = $_FILES['gambar']['name'];
        $tmp = $_FILES['gambar']['tmp_name'];

        move_uploaded_file($tmp, "../../assets/gambar/" . $nama_file);

        $status = $_POST['status'] ?? 'rilis';
        $tanggal_rilis = (!empty($_POST['tanggal_rilis'])) ? $_POST['tanggal_rilis'] : null;
        $auto_release = isset($_POST['auto_release']) ? 1 : 0;
        $tgl_sql = $tanggal_rilis ? "'$tanggal_rilis'" : "NULL";

        mysqli_query($conn, "UPDATE buku SET
            judul='$judul',
            penulis='$penulis',
            penerbit='$penerbit',
            tahun_terbit='$tahun',
            stok='$stok',
            id_kategori='$kategori',
            gambar_buku='$nama_file',
            status='$status',
            tanggal_rilis=$tgl_sql,
            auto_release=$auto_release
            WHERE id_buku='$id'
        ");

    } else {

        $status = $_POST['status'] ?? 'rilis';
        $tanggal_rilis = (!empty($_POST['tanggal_rilis'])) ? $_POST['tanggal_rilis'] : null;
        $auto_release = isset($_POST['auto_release']) ? 1 : 0;
        $tgl_sql = $tanggal_rilis ? "'$tanggal_rilis'" : "NULL";

        mysqli_query($conn, "UPDATE buku SET
            judul='$judul',
            penulis='$penulis',
            penerbit='$penerbit',
            tahun_terbit='$tahun',
            stok='$stok',
            id_kategori='$kategori',
            status='$status',
            tanggal_rilis=$tgl_sql,
            auto_release=$auto_release
            WHERE id_buku='$id'
        ");
    }

    header("Location: buku.php");
    exit;
}

// =======================
// AUTO RELEASE
// Cek buku berstatus 'akan_datang' yang auto_release=1 dan tanggal_rilis-nya
// sudah lewat. Kalau iya, otomatis ubah status jadi 'rilis'.
// =======================
$today = date('Y-m-d');
mysqli_query($conn, "
    UPDATE buku
    SET status = 'rilis'
    WHERE status = 'akan_datang'
    AND auto_release = 1
    AND tanggal_rilis IS NOT NULL
    AND tanggal_rilis <= '$today'
");

// =======================
// SEARCH & FILTER (semua via POST)
// Baca keyword dan pilihan filter dari form POST.
// Jika ada tombol 'reset', kosongkan semua kondisi filter.
// =======================
$keyword = '';
$is_search = false;
$filter_kategori = '';
$filter_stok = '';

if (isset($_POST['reset'])) {
    // Reset semua
    $keyword = '';
    $is_search = false;
    $filter_kategori = '';
    $filter_stok = '';
} else {
    // Baca keyword
    if (isset($_POST['keyword'])) {
        $keyword = trim($_POST['keyword']);
        if ($keyword != '') {
            $is_search = true;
        }
    }
    // Baca filter kategori
    if (isset($_POST['kategori'])) {
        $filter_kategori = $_POST['kategori'];
    }
    // Baca filter stok
    if (isset($_POST['stok'])) {
        $filter_stok = $_POST['stok'];
    }
}

$kategori = mysqli_query($conn, "SELECT * FROM kategori");

$where_kategori = "";
if (!empty($filter_kategori)) {
    $where_kategori = "AND b.id_kategori = '$filter_kategori'";
}

$where_stok = "";
switch ($filter_stok) {
    case 'terbanyak':
        $where_stok = ""; // just order
        break;
    case 'tersedikit':
        $where_stok = ""; // just order
        break;
    case 'habis':
        $where_stok = "AND b.stok = 0";
        break;
}

$order_stok = "";
switch ($filter_stok) {
    case 'terbanyak':
        $order_stok = "ORDER BY b.stok DESC";
        break;
    case 'tersedikit':
        $order_stok = "ORDER BY b.stok ASC";
        break;
}

// =======================
// QUERY DATA BUKU
// Ambil semua buku dengan JOIN ke tabel kategori.
// Kalau ada keyword → pakai WHERE LIKE. Filter & urutan stok
// ditambahkan sesuai pilihan filter yang aktif.
// =======================
if ($is_search && $keyword != '') {

    $data = mysqli_query($conn, "
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE (b.judul LIKE '%$keyword%'
    OR b.penulis LIKE '%$keyword%'
    OR b.penerbit LIKE '%$keyword%')
    $where_kategori
    $where_stok
    $order_stok
    ");

} else {

    $data = mysqli_query($conn, "
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE 1=1
    $where_kategori
    $where_stok
    $order_stok
    ");
}

// =======================
// STATS
// Hitung angka untuk 3 kartu statistik di atas tabel:
// total stok, jumlah buku dipinjam aktif, total judul buku.
// =======================
$stats_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(stok) AS total FROM buku"))['total'];
$stats_dipinjam = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(d.jumlah), 0) AS dipinjam
    FROM detail_peminjaman d
    JOIN peminjaman p ON d.id_peminjaman = p.id_peminjaman
    WHERE p.status = 'dipinjam'
"))['dipinjam'];
$stats_sisa = $stats_total;
$stats_judul = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jumlah FROM buku"))['jumlah'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Buku - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="admin-page">

        <a href="../dashboard_admin.php" class="back-link"><i class="fas fa-arrow-left"></i>Dashboard</a>

        <h2><i class="fas fa-book-open"></i> Kelola Data Buku</h2>

        <!-- STATS INFO -->
        <div class="stats-row">
            <div class="stat-card stat-primary">
                <span class="stat-icon"><i class="fas fa-book"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_judul ?></div>
                    <div class="stat-label">Judul Buku</div>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <span class="stat-icon"><i class="fas fa-hand-holding"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_dipinjam ?></div>
                    <div class="stat-label">Sedang Dipinjam</div>
                </div>
            </div>
            <div class="stat-card stat-success">
                <span class="stat-icon"><i class="fas fa-boxes-stacked"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_sisa ?></div>
                    <div class="stat-label">Sisa di Perpustakaan</div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="admin-search-bar">
            <form method="POST">
                <div class="search-input-with-btns">
                    <div class="form-group">
                        <label><i class="fas fa-magnifying-glass"></i> Cari Buku:</label>
                        <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Cari judul, penulis, penerbit...">
                    </div>
                    <div class="search-btn-group">
                        <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                        <button type="submit" name="reset" class="btn-secondary"><i class="fas fa-rotate-left"></i> Reset</button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Filter Kategori:</label>
                    <select name="kategori" onchange="this.form.submit()">
                        <option value="">-- Semua Kategori --</option>
                        <?php
                        mysqli_data_seek($kategori, 0);
                        while ($k = mysqli_fetch_assoc($kategori)) { ?>
                            <option value="<?php echo $k['id_kategori']; ?>"
                                <?php if ($filter_kategori == $k['id_kategori']) echo 'selected'; ?>>
                                <?php echo $k['nama_kategori']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Filter Stok:</label>
                    <select name="stok" onchange="this.form.submit()">
                        <option value="">-- Semua Stok --</option>
                        <option value="terbanyak" <?= $filter_stok == 'terbanyak' ? 'selected' : '' ?>>Stok Terbanyak</option>
                        <option value="tersedikit" <?= $filter_stok == 'tersedikit' ? 'selected' : '' ?>>Stok Tersedikit</option>
                        <option value="habis" <?= $filter_stok == 'habis' ? 'selected' : '' ?>>Stok Habis (0)</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <?php if (isset($_GET['aksi']) && $_GET['aksi'] == 'tambah') { ?>
                <a href="buku.php">
                    <button class="btn-secondary"><i class="fas fa-xmark"></i> Tutup Form</button>
                </a>
            <?php } else { ?>
                <a href="?aksi=tambah">
                    <button><i class="fas fa-plus"></i> Tambah Buku</button>
                </a>
            <?php } ?>
        </div>

        <!-- FORM TAMBAH / EDIT -->
        <?php if ($showForm) { ?>

        <div class="form-panel" id="formPanel">
            <h3>
                <i class="fas fa-pen-to-square"></i>
                <?php echo $edit ? "Edit Buku" : "Tambah Buku"; ?>
                <?php if ($edit) { ?>
                    <span style="font-size:0.82rem;color:var(--primary-light);font-weight:500;margin-left:8px;">
                        (Buku No. <?php echo $edit['id_buku']; ?>)
                    </span>
                <?php } ?>
            </h3>

            <form method="POST" enctype="multipart/form-data">

                <?php if ($edit) { ?>
                    <input type="hidden" name="id_buku" value="<?php echo $edit['id_buku']; ?>">
                <?php } ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Judul:</label>
                        <input type="text" name="judul" required
                            value="<?php echo $edit ? $edit['judul'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Penulis:</label>
                        <input type="text" name="penulis" required
                            value="<?php echo $edit ? $edit['penulis'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Penerbit:</label>
                        <input type="text" name="penerbit" required
                            value="<?php echo $edit ? $edit['penerbit'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Tahun Terbit:</label>
                        <input type="text" name="tahun" required
                            value="<?php echo $edit ? $edit['tahun_terbit'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Stok:</label>
                        <input type="number" name="stok" required
                            value="<?php echo $edit ? $edit['stok'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Kategori:</label>
                        <select name="kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            mysqli_data_seek($kategori, 0);
                            while($k = mysqli_fetch_assoc($kategori)) { ?>
                                <option value="<?php echo $k['id_kategori']; ?>"
                                    <?php if($edit && $edit['id_kategori'] == $k['id_kategori']) echo 'selected'; ?>>
                                    <?php echo $k['nama_kategori']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Gambar Buku:</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="gambar" id="fileInput<?php echo $edit ? 'Edit' : 'Add'; ?>"
                                onchange="document.getElementById('fileName<?php echo $edit ? 'Edit' : 'Add'; ?>').textContent = this.files[0] ? this.files[0].name : 'Belum dipilih'">
                            <label for="fileInput<?php echo $edit ? 'Edit' : 'Add'; ?>" class="file-upload-label">
                                <i class="fas fa-cloud-arrow-up"></i> Pilih File
                            </label>
                            <span class="file-upload-name" id="fileName<?php echo $edit ? 'Edit' : 'Add'; ?>">
                                <?php echo $edit ? $edit['gambar_buku'] : 'Belum dipilih'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" id="statusSelect" onchange="toggleTanggalRilis()" required>
                            <option value="rilis" <?php if (!$edit || $edit['status'] == 'rilis') echo 'selected'; ?>>Rilis</option>
                            <option value="akan_datang" <?php if ($edit && $edit['status'] == 'akan_datang') echo 'selected'; ?>>Akan Datang</option>
                        </select>
                    </div>

                    <div class="form-group" id="tanggalRilisGroup" style="<?php echo (!$edit || $edit['status'] == 'rilis') ? 'display:none;' : ''; ?>">
                        <label>Tanggal Rilis: <small style="color:var(--gray-400);font-weight:400;">(opsional jika status Rilis)</small></label>
                        <input type="date" name="tanggal_rilis"
                            value="<?php echo $edit ? $edit['tanggal_rilis'] : ''; ?>">
                    </div>

                    <div class="form-group" id="autoReleaseGroup" style="<?php echo (!$edit || $edit['status'] == 'rilis') ? 'display:none;' : ''; ?>">
                        <label>Auto Release:</label>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
                            <input type="checkbox" name="auto_release" id="autoRelease" value="1"
                                <?php if ($edit && $edit['auto_release'] == 1) echo 'checked'; ?>
                                style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
                            <label for="autoRelease" style="font-weight:500;font-size:0.88rem;color:var(--gray-700);margin:0;cursor:pointer;">
                                Otomatis jadi Rilis saat tanggal sudah lewat
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="<?php echo $edit ? 'update' : 'tambah'; ?>">
                        <i class="fas fa-floppy-disk"></i> <?php echo $edit ? 'Update Buku' : 'Tambah Buku'; ?>
                    </button>
                    <?php if ($edit) { ?>
                        <button type="button" class="btn-secondary" onclick="window.location.href='buku.php';">
                            <i class="fas fa-xmark"></i> Tutup
                        </button>
                    <?php } ?>
                </div>

            </form>
        </div>

        <?php } ?>

        <!-- TABLE DATA -->
        <table>
            <tr>
                <th>No</th>
                <th>Judul</th>
                <th>Penulis</th>
                <th>Penerbit</th>
                <th>Tahun</th>
                <th>Stok</th>
                <th>Kategori</th>
                <th>Status</th>
                <th>Tanggal Rilis</th>
                <th>Gambar</th>
                <th>Aksi</th>
            </tr>

            <?php
            // Cek jumlah hasil query untuk menentukan apakah ada data atau tidak
            $jumlah_buku = mysqli_num_rows($data);
            if ($jumlah_buku == 0): ?>
            <tr>
                <td colspan="11">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>Tidak ada data buku.</p>
                    </div>
                </td>
            </tr>
            <?php else:
            // Loop tiap baris buku dari hasil query
            $no = 1;
            while ($row = mysqli_fetch_assoc($data)) {
                // Cek apakah file gambar benar-benar ada di server
                $img_path = "../../assets/gambar/" . $row['gambar_buku'];
                $has_img = !empty($row['gambar_buku']) && file_exists("../../assets/gambar/" . $row['gambar_buku']);
            ?>
            <tr>
                <td data-label="No"><?php echo $no++; ?></td>
                <td data-label="Judul"><?php echo $row['judul']; ?></td>
                <td data-label="Penulis"><?php echo $row['penulis']; ?></td>
                <td data-label="Penerbit"><?php echo $row['penerbit']; ?></td>
                <td data-label="Tahun"><?php echo $row['tahun_terbit']; ?></td>
                <td data-label="Stok"><?php echo $row['stok']; ?></td>
                <td data-label="Kategori"><?php echo $row['nama_kategori']; ?></td>
                <td data-label="Status">
                    <?php if ($row['status'] == 'akan_datang'): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#EDE9FE;color:#7C3AED;padding:3px 10px;border-radius:var(--radius-full);font-size:0.75rem;font-weight:700;">
                            <i class="fas fa-clock"></i> Akan Datang
                        </span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#DCFCE7;color:#15803D;padding:3px 10px;border-radius:var(--radius-full);font-size:0.75rem;font-weight:700;">
                            <i class="fas fa-check-circle"></i> Rilis
                        </span>
                    <?php endif; ?>
                </td>
                <td data-label="Tanggal Rilis">
                    <?php if (!empty($row['tanggal_rilis'])): ?>
                        <span style="font-size:0.82rem;color:var(--gray-700);"><?= date('d M Y', strtotime($row['tanggal_rilis'])) ?></span>
                        <?php if ($row['auto_release'] == 1): ?>
                            <br><span style="font-size:0.68rem;color:#7C3AED;font-weight:600;"><i class="fas fa-bolt"></i> Auto</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--gray-400);">-</span>
                    <?php endif; ?>
                </td>
                <td data-label="Gambar">
                    <?php if ($has_img): ?>
                        <img src="<?= $img_path ?>" width="60" alt="Gambar Buku">
                    <?php else: ?>
                        <div style="width:60px;height:60px;background:var(--gray-100);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--gray-300);">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                </td>
                <td data-label="Aksi">
                    <div class="table-actions">
                        <a href="?aksi=edit&id=<?php echo $row['id_buku']; ?>">
                            <button class="btn-edit"><i class="fas fa-pen"></i> Edit</button>
                        </a>
                        <a href="#" onclick="showConfirm('Hapus Buku', 'Yakin ingin menghapus buku &quot;<?php echo addslashes($row['judul']); ?>&quot;?', function(){ window.location.href='?hapus=<?php echo $row['id_buku']; ?>'; })">
                            <button class="btn-delete"><i class="fas fa-trash"></i> Hapus</button>
                        </a>
                    </div>
                </td>
            </tr>
            <?php } // end while loop buku
            endif; // end pengecekan data kosong ?>
        </table>

    </div>

    <!-- CUSTOM CONFIRM DIALOG -->
    <div id="confirmOverlay" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon icon-danger">
                <i class="fas fa-trash"></i>
            </div>
            <div class="confirm-title" id="confirmTitle">Konfirmasi</div>
            <div class="confirm-message" id="confirmMessage">Apakah Anda yakin?</div>
            <div class="confirm-actions">
                <button class="btn-confirm-yes" id="confirmYes">Ya, Hapus</button>
                <button class="btn-confirm-no" onclick="hideConfirm()">Batal</button>
            </div>
        </div>
    </div>

    <script>
        function toggleTanggalRilis() {
            var status = document.getElementById('statusSelect').value;
            var tglGroup = document.getElementById('tanggalRilisGroup');
            var autoGroup = document.getElementById('autoReleaseGroup');
            if (status === 'akan_datang') {
                tglGroup.style.display = '';
                autoGroup.style.display = '';
            } else {
                tglGroup.style.display = 'none';
                autoGroup.style.display = 'none';
            }
        }

        var confirmCallback = null;

        function showConfirm(title, message, callback) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            confirmCallback = callback;
            document.getElementById('confirmOverlay').classList.add('active');
        }

        function hideConfirm() {
            document.getElementById('confirmOverlay').classList.remove('active');
            confirmCallback = null;
        }

        document.getElementById('confirmYes').addEventListener('click', function() {
            if (confirmCallback) confirmCallback();
            hideConfirm();
        });

        document.getElementById('confirmOverlay').addEventListener('click', function(e) {
            if (e.target === this) hideConfirm();
        });
    </script>

     <?php include '../../footer.php'; ?>

</body>

</html>
