<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login/login.php");
    exit;
}

require '../../koneksi.php';

// ======================
// SEARCH & FILTER (semua via POST)
// ======================
$keyword = '';
$is_search = false;
$filter_status = '';

if (isset($_POST['reset'])) {
    $keyword = '';
    $is_search = false;
    $filter_status = '';
} else {
    if (isset($_POST['keyword'])) {
        $keyword = trim($_POST['keyword']);
        if ($keyword != '') $is_search = true;
    }
    if (isset($_POST['status'])) {
        $filter_status = $_POST['status'];
    }
}

// ======================
// HAPUS
// Hapus transaksi dari tabel peminjaman via ?hapus=id_peminjaman.
// ======================
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM peminjaman WHERE id_peminjaman='$id'");
    header("Location: transaksi.php");
    exit;
}

// ======================
// TAMBAH PEMINJAMAN (INLINE)
// Proses form tambah transaksi: validasi anggota aktif, cek stok,
// INSERT ke peminjaman & detail_peminjaman, lalu kurangi stok buku.
// ======================
$error_tambah = '';

if (isset($_POST['tambah_pinjam'])) {
    $id_anggota  = intval($_POST['id_anggota']);
    $id_buku     = intval($_POST['id_buku']);
    $jumlah      = intval($_POST['jumlah']);
    $tgl_pinjam  = $_POST['tanggal_pinjam'];
    $tgl_kembali = !empty($_POST['tanggal_kembali']) ? $_POST['tanggal_kembali'] : null;

    if (!$id_anggota || !$id_buku || !$tgl_pinjam || $jumlah < 1) {
        $error_tambah = 'Anggota, buku, jumlah, dan tanggal pinjam wajib diisi!';
    } else {
        $cek_anggota = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT status FROM anggota WHERE id_anggota = '$id_anggota'")
        );
        if (!$cek_anggota || $cek_anggota['status'] !== 'aktif') {
            $error_tambah = 'Anggota tidak aktif atau tidak ditemukan.';
        } else {
            $cek_buku = mysqli_fetch_assoc(
                mysqli_query($conn, "SELECT judul, stok FROM buku WHERE id_buku = '$id_buku'")
            );
            if (!$cek_buku) {
                $error_tambah = 'Buku tidak ditemukan!';
            } elseif ($cek_buku['stok'] < $jumlah) {
                $error_tambah = 'Stok buku <strong>' . htmlspecialchars($cek_buku['judul']) . '</strong> tidak mencukupi. Tersedia: <strong>' . $cek_buku['stok'] . '</strong>.';
            } else {
                $tgl_kembali_sql = $tgl_kembali ? "'$tgl_kembali'" : "NULL";
                $insert = mysqli_query($conn, "
                    INSERT INTO peminjaman (id_anggota, tanggal_peminjaman, tanggal_kembali, status)
                    VALUES ('$id_anggota', '$tgl_pinjam', $tgl_kembali_sql, 'dipinjam')
                ");
                if ($insert) {
                    $id_peminjaman = mysqli_insert_id($conn);
                    mysqli_query($conn, "
                        INSERT INTO detail_peminjaman (id_peminjaman, id_buku, jumlah)
                        VALUES ('$id_peminjaman', '$id_buku', '$jumlah')
                    ");
                    mysqli_query($conn, "UPDATE buku SET stok = stok - $jumlah WHERE id_buku = '$id_buku'");
                    $_SESSION['flash_success'] = 'Transaksi peminjaman berhasil ditambahkan!';
                    header("Location: transaksi.php");
                    exit;
                } else {
                    $error_tambah = 'Gagal menyimpan data transaksi.';
                }
            }
        }
    }
}

// ======================
// UPDATE
// Perbarui data transaksi (tanggal, status) berdasarkan id_peminjaman.
// ======================
if (isset($_POST['update'])) {
    $id          = $_POST['id_peminjaman'];
    $id_anggota  = $_POST['id_anggota'];
    $tgl_pinjam  = $_POST['tanggal_peminjaman'];
    $tgl_kembali = $_POST['tanggal_kembali'];
    $status      = $_POST['status'];

    mysqli_query($conn, "UPDATE peminjaman SET
        id_anggota='$id_anggota',
        tanggal_peminjaman='$tgl_pinjam',
        tanggal_kembali='$tgl_kembali',
        status='$status'
        WHERE id_peminjaman='$id'
    ");

    header("Location: transaksi.php");
    exit;
}

// ======================
// KONTROL FORM TAMBAH (TOGGLE)
// Tentukan apakah form tambah transaksi ditampilkan
// (?aksi=tambah atau ada error validasi).
// ======================
$showFormTambah = false;
if (isset($_GET['aksi']) && $_GET['aksi'] == 'tambah') {
    $showFormTambah = true;
}
if ($error_tambah != '') {
    $showFormTambah = true;
}

// Ambil semua anggota aktif & buku rilis untuk search JS
$all_anggota = mysqli_query($conn, "
    SELECT a.id_anggota, a.nama, u.username
    FROM anggota a JOIN user u ON a.id_user = u.id_user
    WHERE a.status = 'aktif'
    ORDER BY a.nama ASC
");
$anggota_data = [];
while ($r = mysqli_fetch_assoc($all_anggota)) {
    $anggota_data[] = $r;
}

$all_buku = mysqli_query($conn, "
    SELECT b.id_buku, b.judul, b.penulis, b.stok
    FROM buku b
    WHERE b.status = 'rilis' AND b.stok > 0
    ORDER BY b.judul ASC
");
$buku_data = [];
while ($r = mysqli_fetch_assoc($all_buku)) {
    $buku_data[] = $r;
}

// ======================
// FLASH MESSAGE
// Baca pesan sukses dari session (di-set sebelum redirect),
// lalu langsung hapus agar hanya tampil sekali.
// ======================
$flash_success = '';
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ======================
// DATA EDIT
// Ambil data transaksi yang akan diedit via ?edit=id_peminjaman.
// ======================
$edit = null;
if (isset($_GET['edit'])) {
    $id   = $_GET['edit'];
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM peminjaman WHERE id_peminjaman='$id'"));
}

// ======================
// DATA LIST + SEARCH + FILTER
// Query semua transaksi dengan JOIN ke anggota, buku, kategori.
// Bisa difilter berdasarkan status dan keyword pencarian.
// ======================
$query = "
    SELECT
        p.*,
        a.nama,
        b.judul AS judul_buku,
        b.penulis AS penulis_buku,
        b.penerbit AS penerbit_buku,
        k.nama_kategori,
        d.jumlah
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.id_anggota
    LEFT JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
    LEFT JOIN buku b ON d.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE 1=1
";

if ($filter_status != '') {
    $query .= " AND p.status = '$filter_status'";
}
if ($is_search && $keyword != '') {
    $query .= " AND (b.judul LIKE '%$keyword%' OR b.penulis LIKE '%$keyword%' OR b.penerbit LIKE '%$keyword%' OR a.nama LIKE '%$keyword%')";
}
$query .= " ORDER BY p.id_peminjaman DESC";
$data = mysqli_query($conn, $query);

// ======================
// DATA GRAFIK TRANSAKSI
// Siapkan label sumbu X dan data jumlah transaksi untuk line chart.
// Filter: hari_ini (per jam), minggu_ini (per hari), bulan_ini (per tanggal).
// ======================
$filter_grafik = isset($_GET['grafik']) ? $_GET['grafik'] : 'hari_ini';

$today       = date('Y-m-d');
$start_week  = date('Y-m-d', strtotime('monday this week'));
$start_month = date('Y-m-01');

if ($filter_grafik == 'minggu_ini') {
    $grafik_labels  = [];
    $grafik_pinjam  = [];
    $grafik_kembali = [];
    for ($i = 0; $i < 7; $i++) {
        $tgl      = date('Y-m-d', strtotime($start_week . " +$i days"));
        $label    = date('D', strtotime($tgl));
        $label_id = ['Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab','Sun'=>'Min'];
        $grafik_labels[]  = $label_id[$label] ?? $label;
        $q_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE DATE(tanggal_peminjaman) = '$tgl'"));
        $q_k = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE DATE(tanggal_kembali) = '$tgl' AND status = 'dikembalikan'"));
        $grafik_pinjam[]  = (int)$q_p['total'];
        $grafik_kembali[] = (int)$q_k['total'];
    }
} elseif ($filter_grafik == 'bulan_ini') {
    $days_in_month  = (int)date('t');
    $grafik_labels  = [];
    $grafik_pinjam  = [];
    $grafik_kembali = [];
    for ($i = 1; $i <= $days_in_month; $i++) {
        $tgl = date('Y-m-') . str_pad($i, 2, '0', STR_PAD_LEFT);
        $grafik_labels[] = (string)$i;
        $q_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE DATE(tanggal_peminjaman) = '$tgl'"));
        $q_k = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE DATE(tanggal_kembali) = '$tgl' AND status = 'dikembalikan'"));
        $grafik_pinjam[]  = (int)$q_p['total'];
        $grafik_kembali[] = (int)$q_k['total'];
    }
} else {
    // Hari ini: per jam (0-23)
    $grafik_labels  = [];
    $grafik_pinjam  = [];
    $grafik_kembali = [];
    for ($h = 0; $h < 24; $h++) {
        $jam        = str_pad($h, 2, '0', STR_PAD_LEFT);
        $grafik_labels[] = $jam . ':00';
        $jam_start  = $today . ' ' . $jam . ':00:00';
        $jam_end    = $today . ' ' . $jam . ':59:59';
        $q_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE tanggal_peminjaman BETWEEN '$jam_start' AND '$jam_end'"));
        $q_k = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE tanggal_kembali BETWEEN '$jam_start' AND '$jam_end' AND status = 'dikembalikan'"));
        $grafik_pinjam[]  = (int)$q_p['total'];
        $grafik_kembali[] = (int)$q_k['total'];
    }
}

// Total stats
$total_pinjam   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status IN ('dipinjam','dikembalikan','menunggu_kembali')"))['total'];
$total_kembali  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status = 'dikembalikan'"))['total'];
$total_aktif    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status = 'dipinjam'"))['total'];
$total_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status IN ('menunggu_pinjam','menunggu_kembali')"))['total'];

// Fungsi helper status badge
function getStatusBadge($status) {
    switch ($status) {
        case 'menunggu_pinjam':
            return '<span class="status-badge status-menunggu-pinjam"><i class="fas fa-clock"></i> Menunggu Pinjam</span>';
        case 'dipinjam':
            return '<span class="status-badge status-dipinjam"><i class="fas fa-book-open"></i> Dipinjam</span>';
        case 'menunggu_kembali':
            return '<span class="status-badge status-menunggu-kembali"><i class="fas fa-rotate-left"></i> Menunggu Kembali</span>';
        case 'dikembalikan':
            return '<span class="status-badge status-dikembalikan"><i class="fas fa-check-circle"></i> Dikembalikan</span>';
        case 'ditolak_pinjam':
            return '<span class="status-badge status-ditolak"><i class="fas fa-times-circle"></i> Ditolak Pinjam</span>';
        case 'ditolak_kembali':
            return '<span class="status-badge status-ditolak"><i class="fas fa-times-circle"></i> Ditolak Kembali</span>';
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
    <title>Kelola Transaksi - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/notifikasi.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>

    <div class="admin-page">

        <a href="../dashboard_admin.php" class="back-link"><i class="fas fa-arrow-left"></i>Dashboard</a>

        <h2><i class="fas fa-arrow-right-arrow-left"></i> Kelola Transaksi</h2>

        <!-- FLASH SUCCESS -->
        <?php if ($flash_success): ?>
        <div class="alert-success">
            <i class="fas fa-circle-check" style="font-size:1.1rem;flex-shrink:0;"></i>
            <span><?= $flash_success ?></span>
        </div>
        <?php endif; ?>

        <!-- ============================================
             GRAFIK + STATS TRANSAKSI
             ============================================ -->
        <div class="grafik-transaksi-wrap">

            <!-- SIDE: Stats Cards -->
            <div class="grafik-stats-side">
                <div class="stat-card stat-primary">
                    <span class="stat-icon"><i class="fas fa-book-open"></i></span>
                    <div>
                        <div class="stat-value"><?= $total_pinjam ?></div>
                        <div class="stat-label">Total Dipinjam</div>
                    </div>
                </div>
                <div class="stat-card stat-success">
                    <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                    <div>
                        <div class="stat-value"><?= $total_kembali ?></div>
                        <div class="stat-label">Dikembalikan</div>
                    </div>
                </div>
                <div class="stat-card stat-warning">
                    <span class="stat-icon"><i class="fas fa-spinner"></i></span>
                    <div>
                        <div class="stat-value"><?= $total_aktif ?></div>
                        <div class="stat-label">Sedang Dipinjam</div>
                    </div>
                </div>
                <div class="stat-card stat-danger">
                    <span class="stat-icon"><i class="fas fa-clock"></i></span>
                    <div>
                        <div class="stat-value"><?= $total_menunggu ?></div>
                        <div class="stat-label">Menunggu Validasi</div>
                    </div>
                </div>
            </div>

            <!-- MAIN: Chart -->
            <div class="grafik-chart-side">
                <div class="grafik-chart-header">
                    <span class="grafik-chart-title"><i class="fas fa-chart-line"></i> Grafik Harian Transaksi</span>
                    <div class="grafik-filter-btns">
                        <a href="?grafik=hari_ini"   class="grafik-btn <?= $filter_grafik == 'hari_ini'   ? 'active' : '' ?>">Hari Ini</a>
                        <a href="?grafik=minggu_ini"  class="grafik-btn <?= $filter_grafik == 'minggu_ini'  ? 'active' : '' ?>">Minggu Ini</a>
                        <a href="?grafik=bulan_ini"   class="grafik-btn <?= $filter_grafik == 'bulan_ini'   ? 'active' : '' ?>">Bulan Ini</a>
                    </div>
                </div>
                <div class="grafik-chart-canvas-wrap">
                    <canvas id="grafikTransaksi"></canvas>
                </div>
            </div>

        </div>

        <!-- SEARCH BAR -->
        <div class="admin-search-bar">
            <form method="POST">
                <div class="search-input-with-btns">
                    <div class="form-group">
                        <label><i class="fas fa-magnifying-glass"></i> Cari Transaksi:</label>
                        <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Cari judul, penulis, penerbit, anggota...">
                    </div>
                    <div class="search-btn-group">
                        <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                        <button type="submit" name="reset" class="btn-secondary"><i class="fas fa-rotate-left"></i> Reset</button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Filter Status:</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <option value="menunggu_pinjam"  <?= $filter_status == 'menunggu_pinjam'  ? 'selected' : '' ?>>Menunggu Pinjam</option>
                        <option value="dipinjam"         <?= $filter_status == 'dipinjam'         ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="menunggu_kembali" <?= $filter_status == 'menunggu_kembali' ? 'selected' : '' ?>>Menunggu Kembali</option>
                        <option value="dikembalikan"     <?= $filter_status == 'dikembalikan'     ? 'selected' : '' ?>>Dikembalikan</option>
                        <option value="ditolak_pinjam"   <?= $filter_status == 'ditolak_pinjam'   ? 'selected' : '' ?>>Ditolak Pinjam</option>
                        <option value="ditolak_kembali"  <?= $filter_status == 'ditolak_kembali'  ? 'selected' : '' ?>>Ditolak Kembali</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <?php if ($showFormTambah): ?>
                <a href="transaksi.php">
                    <button class="btn-secondary"><i class="fas fa-xmark"></i> Tutup Form</button>
                </a>
            <?php else: ?>
                <a href="?aksi=tambah">
                    <button><i class="fas fa-plus"></i> Tambah Transaksi</button>
                </a>
            <?php endif; ?>
        </div>

        <!-- FORM TAMBAH INLINE -->
        <?php if ($showFormTambah): ?>
        <div class="form-panel" id="formTambahPinjam">
            <h3><i class="fas fa-book-medical"></i> Tambah Transaksi Peminjaman</h3>

            <?php if ($error_tambah): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation" style="flex-shrink:0;"></i>
                <span><?= $error_tambah ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="formPinjamSubmit">
                <input type="hidden" name="id_anggota" id="hiddenIdAnggota">
                <input type="hidden" name="id_buku" id="hiddenIdBuku">

                <div class="form-grid">

                    <!-- SEARCH ANGGOTA -->
                    <div class="form-group" style="position:relative;">
                        <label>Anggota:</label>
                        <input type="text" id="searchAnggota" placeholder="Ketik nama atau username anggota..."
                            autocomplete="off"
                            oninput="filterAnggota(this.value)"
                            onfocus="showDropdown('dropAnggota')"
                            onblur="setTimeout(function(){ hideDropdown('dropAnggota') }, 200)">
                        <div id="dropAnggota" class="search-dropdown" style="display:none;"></div>
                        <div id="selectedAnggota" class="search-selected" style="display:none;"></div>
                    </div>

                    <!-- SEARCH BUKU -->
                    <div class="form-group" style="position:relative;">
                        <label>Buku:</label>
                        <input type="text" id="searchBuku" placeholder="Ketik judul atau penulis buku..."
                            autocomplete="off"
                            oninput="filterBuku(this.value)"
                            onfocus="showDropdown('dropBuku')"
                            onblur="setTimeout(function(){ hideDropdown('dropBuku') }, 200)">
                        <div id="dropBuku" class="search-dropdown" style="display:none;"></div>
                        <div id="selectedBuku" class="search-selected" style="display:none;"></div>
                    </div>

                    <!-- JUMLAH -->
                    <div class="form-group">
                        <label>Jumlah:</label>
                        <input type="number" name="jumlah" id="inputJumlah" min="1" value="1" required>
                    </div>

                    <!-- TANGGAL PINJAM -->
                    <div class="form-group">
                        <label>Tanggal Pinjam:</label>
                        <input type="date" name="tanggal_pinjam" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- TANGGAL KEMBALI (OPSIONAL) -->
                    <div class="form-group">
                        <label>Tanggal Kembali: <small style="color:var(--gray-400);font-weight:400;">(opsional)</small></label>
                        <input type="date" name="tanggal_kembali" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" name="tambah_pinjam" onclick="return validatePinjamForm()">
                        <i class="fas fa-floppy-disk"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- FORM EDIT -->
        <div id="formEdit" class="edit-form-panel" style="<?= $edit ? '' : 'display:none;' ?>">
            <?php if ($edit): ?>
                <h3><i class="fas fa-pen-to-square"></i> Edit Data Ke - <?= $edit['id_peminjaman'] ?></h3>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id_peminjaman" value="<?= $edit['id_peminjaman'] ?? '' ?>">
                <input type="hidden" name="id_anggota"    value="<?= $edit['id_anggota']    ?? '' ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Tanggal Pinjam</label>
                        <input type="date" name="tanggal_peminjaman" value="<?= $edit['tanggal_peminjaman'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Kembali</label>
                        <input type="date" name="tanggal_kembali" value="<?= $edit['tanggal_kembali'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="menunggu_pinjam"  <?= ($edit && $edit['status'] == 'menunggu_pinjam')  ? 'selected' : '' ?>>Menunggu Pinjam</option>
                            <option value="dipinjam"         <?= ($edit && $edit['status'] == 'dipinjam')         ? 'selected' : '' ?>>Dipinjam</option>
                            <option value="menunggu_kembali" <?= ($edit && $edit['status'] == 'menunggu_kembali') ? 'selected' : '' ?>>Menunggu Kembali</option>
                            <option value="dikembalikan"     <?= ($edit && $edit['status'] == 'dikembalikan')     ? 'selected' : '' ?>>Dikembalikan</option>
                            <option value="ditolak_pinjam"   <?= ($edit && $edit['status'] == 'ditolak_pinjam')   ? 'selected' : '' ?>>Ditolak Pinjam</option>
                            <option value="ditolak_kembali"  <?= ($edit && $edit['status'] == 'ditolak_kembali')  ? 'selected' : '' ?>>Ditolak Kembali</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update"><i class="fas fa-floppy-disk"></i> Update</button>
                    <button type="button" class="btn-secondary" onclick="closeEdit()"><i class="fas fa-xmark"></i> Tutup</button>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <div class="table-wrapper">
        <table>
            <tr>
                <th>No</th>
                <th>Nama Anggota</th>
                <th>Judul Buku</th>
                <th>Kategori</th>
                <th>Jumlah</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>

            <?php
            // Hitung total baris dari query untuk cek apakah ada data
            $jumlah_trx = mysqli_num_rows($data);
            if ($jumlah_trx == 0): ?>
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada data transaksi pada periode ini.</p>
                    </div>
                </td>
            </tr>
            <?php else:
            // Loop tiap baris transaksi dari hasil query
            $no = 1;
            while ($row = mysqli_fetch_assoc($data)) { ?>
                <tr>
                    <td data-label="No"><?= $no++ ?></td>
                    <td data-label="Anggota"><?= $row['nama'] ?></td>
                    <td data-label="Buku"><?= $row['judul_buku'] ?></td>
                    <td data-label="Kategori"><?= $row['nama_kategori'] ?></td>
                    <td data-label="Jumlah"><?= $row['jumlah'] ?></td>
                    <td data-label="Pinjam"><?= $row['tanggal_peminjaman'] ?></td>
                    <td data-label="Kembali"><?= $row['tanggal_kembali'] ?: '-' ?></td>
                    <td data-label="Status"><?= getStatusBadge($row['status']) ?></td>
                    <td data-label="Aksi">
                        <div class="table-actions">
                            <?php if ($row['status'] === 'menunggu_pinjam'): ?>
                                <button class="btn-success btn-sm" onclick="showValidasiPinjam(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars(addslashes($row['judul_buku'])) ?>')">
                                    <i class="fas fa-check"></i> Setujui
                                </button>
                                <button class="btn-danger btn-sm" onclick="showTolakPinjam(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars(addslashes($row['judul_buku'])) ?>')">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            <?php elseif ($row['status'] === 'menunggu_kembali'): ?>
                                <button class="btn-success btn-sm" onclick="showValidasiKembali(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars(addslashes($row['judul_buku'])) ?>')">
                                    <i class="fas fa-check"></i> Setujui
                                </button>
                                <button class="btn-danger btn-sm" onclick="showTolakKembali(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars(addslashes($row['judul_buku'])) ?>')">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            <?php else: ?>
                                <a href="?edit=<?= $row['id_peminjaman'] ?>">
                                    <button class="btn-edit btn-sm"><i class="fas fa-pen"></i> Edit</button>
                                </a>
                                <a href="#" onclick="showConfirm('Hapus Transaksi', 'Yakin ingin menghapus data transaksi ini?', function(){ window.location.href='?hapus=<?= $row['id_peminjaman'] ?>'; })">
                                    <button class="btn-delete btn-sm"><i class="fas fa-trash"></i> Hapus</button>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php } // end while transaksi
            endif; // end cek data kosong ?>
        </table>
        </div>

    </div>

    <?php include '../../footer.php'; ?>

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

    <!-- MODAL SETUJUI PINJAM -->
    <div id="modalSetujuiPinjam" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon" style="background:#D1FAE5;color:#059669;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="confirm-title" id="setujuiPinjamTitle">Setujui Peminjaman?</div>
            <div class="confirm-message" id="setujuiPinjamMsg">Yakin ingin menyetujui permohonan peminjaman ini?</div>
            <div class="confirm-actions">
                <button class="btn-confirm-yes btn-confirm-warning" id="btnSetujuiPinjam" style="background:linear-gradient(135deg,#10B981 0%,#34D399 100%);color:#fff;">Ya, Setujui</button>
                <button class="btn-confirm-no" onclick="hideModal('modalSetujuiPinjam')">Batal</button>
            </div>
        </div>
    </div>

    <!-- MODAL TOLAK PINJAM -->
    <div id="modalTolakPinjam" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon icon-danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="confirm-title" id="tolakPinjamTitle">Tolak Peminjaman?</div>
            <div class="confirm-message" id="tolakPinjamMsg">Yakin ingin menolak permohonan peminjaman ini?</div>
            <form method="POST" action="../../transaksi/proses_validasi.php" id="formTolakPinjam">
                <input type="hidden" name="id_peminjaman" id="tolakPinjamId">
                <div style="margin-bottom:12px;">
                    <input type="text" name="alasan" placeholder="Alasan penolakan (opsional)" style="width:100%;max-width:100%;padding:8px 12px;border:2px solid var(--gray-200);border-radius:8px;font-size:0.85rem;">
                </div>
                <div class="confirm-actions">
                    <button type="submit" name="tolak_pinjam" class="btn-confirm-yes">Ya, Tolak</button>
                    <button type="button" class="btn-confirm-no" onclick="hideModal('modalTolakPinjam')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SETUJUI KEMBALI -->
    <div id="modalSetujuiKembali" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon" style="background:#D1FAE5;color:#059669;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="confirm-title" id="setujuiKembaliTitle">Setujui Pengembalian?</div>
            <div class="confirm-message" id="setujuiKembaliMsg">Yakin ingin menyetujui pengembalian buku ini?</div>
            <div class="confirm-actions">
                <button class="btn-confirm-yes btn-confirm-warning" id="btnSetujuiKembali" style="background:linear-gradient(135deg,#10B981 0%,#34D399 100%);color:#fff;">Ya, Setujui</button>
                <button class="btn-confirm-no" onclick="hideModal('modalSetujuiKembali')">Batal</button>
            </div>
        </div>
    </div>

    <!-- MODAL TOLAK KEMBALI -->
    <div id="modalTolakKembali" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon icon-danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="confirm-title" id="tolakKembaliTitle">Tolak Pengembalian?</div>
            <div class="confirm-message" id="tolakKembaliMsg">Yakin ingin menolak pengembalian buku ini?</div>
            <form method="POST" action="../../transaksi/proses_validasi.php" id="formTolakKembali">
                <input type="hidden" name="id_peminjaman" id="tolakKembaliId">
                <div style="margin-bottom:12px;">
                    <input type="text" name="alasan" placeholder="Alasan penolakan (opsional)" style="width:100%;max-width:100%;padding:8px 12px;border:2px solid var(--gray-200);border-radius:8px;font-size:0.85rem;">
                </div>
                <div class="confirm-actions">
                    <button type="submit" name="tolak_kembali" class="btn-confirm-yes">Ya, Tolak</button>
                    <button type="button" class="btn-confirm-no" onclick="hideModal('modalTolakKembali')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // CHART.JS - Grafik Line Chart Transaksi
        // ============================================
        (function() {
            var labels     = <?= json_encode($grafik_labels) ?>;
            var dataPinjam  = <?= json_encode($grafik_pinjam) ?>;
            var dataKembali = <?= json_encode($grafik_kembali) ?>;

            var ctx = document.getElementById('grafikTransaksi').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Peminjaman',
                            data: dataPinjam,
                            borderColor: '#4F46E5',
                            backgroundColor: 'rgba(79,70,229,0.08)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#4F46E5',
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Pengembalian',
                            data: dataKembali,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16,185,129,0.07)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#10B981',
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 11, weight: '600' },
                                usePointStyle: true,
                                pointStyleWidth: 10,
                                boxHeight: 8
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(15,23,42,0.85)',
                            titleFont: { size: 11, weight: '700' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: { size: 10 },
                                color: '#94A3B8',
                                maxRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 15
                            },
                            grid: { color: 'rgba(226,232,240,0.6)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { size: 10 },
                                color: '#94A3B8',
                                stepSize: 1,
                                precision: 0
                            },
                            grid: { color: 'rgba(226,232,240,0.6)' }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        })();

        // ============================================
        // SEARCH ANGGOTA & BUKU (INLINE FORM)
        // ============================================
        var anggotaData = <?php echo json_encode($anggota_data); ?>;
        var bukuData    = <?php echo json_encode($buku_data); ?>;

        var selectedAnggotaId = null;
        var selectedBukuId    = null;
        var selectedBukuStok  = 0;

        function showDropdown(id) {
            document.getElementById(id).style.display = 'block';
        }
        function hideDropdown(id) {
            document.getElementById(id).style.display = 'none';
        }

        function filterAnggota(q) {
            var drop = document.getElementById('dropAnggota');
            drop.innerHTML = '';
            drop.style.display = 'block';
            if (!q) { drop.style.display = 'none'; return; }
            var results = anggotaData.filter(function(a) {
                return a.nama.toLowerCase().indexOf(q.toLowerCase()) !== -1 ||
                       a.username.toLowerCase().indexOf(q.toLowerCase()) !== -1;
            });
            if (results.length === 0) {
                drop.innerHTML = '<div class="search-dropdown-empty">Tidak ada anggota aktif ditemukan</div>';
                return;
            }
            results.slice(0, 10).forEach(function(a) {
                var item = document.createElement('div');
                item.className = 'search-dropdown-item';
                item.innerHTML = '<strong>' + a.nama + '</strong> <span style="color:var(--gray-400);font-size:0.8rem;">(' + a.username + ')</span>';
                item.addEventListener('mousedown', function() {
                    selectedAnggotaId = a.id_anggota;
                    document.getElementById('hiddenIdAnggota').value = a.id_anggota;
                    document.getElementById('searchAnggota').value = a.nama + ' (' + a.username + ')';
                    drop.style.display = 'none';
                    showSelectedBadge('selectedAnggota', a.nama + ' (' + a.username + ')', 'anggota');
                });
                drop.appendChild(item);
            });
        }

        function filterBuku(q) {
            var drop = document.getElementById('dropBuku');
            drop.innerHTML = '';
            drop.style.display = 'block';
            if (!q) { drop.style.display = 'none'; return; }
            var results = bukuData.filter(function(b) {
                return b.judul.toLowerCase().indexOf(q.toLowerCase()) !== -1 ||
                       b.penulis.toLowerCase().indexOf(q.toLowerCase()) !== -1;
            });
            if (results.length === 0) {
                drop.innerHTML = '<div class="search-dropdown-empty">Tidak ada buku tersedia ditemukan</div>';
                return;
            }
            results.slice(0, 10).forEach(function(b) {
                var item = document.createElement('div');
                item.className = 'search-dropdown-item';
                item.innerHTML = '<strong>' + b.judul + '</strong> <span style="color:var(--gray-400);font-size:0.8rem;">— ' + b.penulis + ' | Stok: ' + b.stok + '</span>';
                item.addEventListener('mousedown', function() {
                    selectedBukuId   = b.id_buku;
                    selectedBukuStok = parseInt(b.stok);
                    document.getElementById('hiddenIdBuku').value = b.id_buku;
                    document.getElementById('searchBuku').value   = b.judul;
                    document.getElementById('inputJumlah').max    = b.stok;
                    drop.style.display = 'none';
                    showSelectedBadge('selectedBuku', b.judul + ' (Stok: ' + b.stok + ')', 'buku');
                });
                drop.appendChild(item);
            });
        }

        function showSelectedBadge(containerId, text, type) {
            var el       = document.getElementById(containerId);
            var color    = type === 'anggota' ? 'var(--primary)'       : 'var(--success)';
            var colorBg  = type === 'anggota' ? 'var(--primary-50)'    : 'var(--success-light)';
            el.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;background:' + colorBg + ';color:' + color + ';border-radius:var(--radius-sm);padding:5px 10px;font-size:0.82rem;font-weight:600;">'
                + '<i class="fas fa-check-circle"></i> ' + text
                + ' <span onclick="clearSelected(\'' + containerId + '\', \'' + type + '\');" style="cursor:pointer;margin-left:4px;color:var(--gray-400);" title="Hapus pilihan"><i class="fas fa-xmark"></i></span>'
                + '</span>';
            el.style.display = 'block';
            if (type === 'anggota') {
                document.getElementById('searchAnggota').style.display = 'none';
            } else {
                document.getElementById('searchBuku').style.display = 'none';
            }
        }

        function clearSelected(containerId, type) {
            document.getElementById(containerId).style.display  = 'none';
            document.getElementById(containerId).innerHTML = '';
            if (type === 'anggota') {
                selectedAnggotaId = null;
                document.getElementById('hiddenIdAnggota').value = '';
                var inp = document.getElementById('searchAnggota');
                inp.value = ''; inp.style.display = ''; inp.focus();
            } else {
                selectedBukuId   = null;
                selectedBukuStok = 0;
                document.getElementById('hiddenIdBuku').value = '';
                document.getElementById('inputJumlah').removeAttribute('max');
                var inp = document.getElementById('searchBuku');
                inp.value = ''; inp.style.display = ''; inp.focus();
            }
        }

        function validatePinjamForm() {
            if (!selectedAnggotaId || !document.getElementById('hiddenIdAnggota').value) {
                alert('Pilih anggota terlebih dahulu!');
                return false;
            }
            if (!selectedBukuId || !document.getElementById('hiddenIdBuku').value) {
                alert('Pilih buku terlebih dahulu!');
                return false;
            }
            return true;
        }

        // ============================================
        // EDIT & CLOSE
        // ============================================
        function closeEdit() {
            document.getElementById("formEdit").style.display = "none";
            var url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.pathname + url.search);
        }

        <?php if ($edit): ?>
        window.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById("formEdit");
            if (form && form.style.display !== 'none') {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        <?php endif; ?>

        // ============================================
        // CONFIRM DIALOG
        // ============================================
        var confirmCallback = null;

        function showConfirm(title, message, callback) {
            document.getElementById('confirmTitle').textContent   = title;
            document.getElementById('confirmMessage').textContent = message;
            confirmCallback = callback;
            document.getElementById('confirmOverlay').classList.add('active');
        }

        function hideConfirm() {
            document.getElementById('confirmOverlay').classList.remove('active');
            confirmCallback = null;
        }

        function hideModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        document.getElementById('confirmYes').addEventListener('click', function() {
            if (confirmCallback) confirmCallback();
            hideConfirm();
        });

        document.getElementById('confirmOverlay').addEventListener('click', function(e) {
            if (e.target === this) hideConfirm();
        });

        // ============================================
        // VALIDASI PINJAM / KEMBALI
        // ============================================
        function showValidasiPinjam(id, nama, buku) {
            document.getElementById('setujuiPinjamTitle').textContent = 'Setujui Peminjaman?';
            document.getElementById('setujuiPinjamMsg').textContent   = nama + ' meminjam buku "' + buku + '". Yakin setujui?';
            document.getElementById('btnSetujuiPinjam').onclick = function() {
                var form = document.createElement('form');
                form.method  = 'POST';
                form.action  = '../../transaksi/proses_validasi.php';
                form.innerHTML = '<input type="hidden" name="setujui_pinjam" value="1"><input type="hidden" name="id_peminjaman" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };
            document.getElementById('modalSetujuiPinjam').classList.add('active');
        }

        function showTolakPinjam(id, nama, buku) {
            document.getElementById('tolakPinjamTitle').textContent = 'Tolak Peminjaman?';
            document.getElementById('tolakPinjamMsg').textContent   = nama + ' meminjam buku "' + buku + '". Yakin tolak?';
            document.getElementById('tolakPinjamId').value = id;
            document.getElementById('modalTolakPinjam').classList.add('active');
        }

        function showValidasiKembali(id, nama, buku) {
            document.getElementById('setujuiKembaliTitle').textContent = 'Setujui Pengembalian?';
            document.getElementById('setujuiKembaliMsg').textContent   = nama + ' mengembalikan buku "' + buku + '". Yakin setujui?';
            document.getElementById('btnSetujuiKembali').onclick = function() {
                var form = document.createElement('form');
                form.method  = 'POST';
                form.action  = '../../transaksi/proses_validasi.php';
                form.innerHTML = '<input type="hidden" name="setujui_kembali" value="1"><input type="hidden" name="id_peminjaman" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };
            document.getElementById('modalSetujuiKembali').classList.add('active');
        }

        function showTolakKembali(id, nama, buku) {
            document.getElementById('tolakKembaliTitle').textContent = 'Tolak Pengembalian?';
            document.getElementById('tolakKembaliMsg').textContent   = nama + ' mengembalikan buku "' + buku + '". Yakin tolak?';
            document.getElementById('tolakKembaliId').value = id;
            document.getElementById('modalTolakKembali').classList.add('active');
        }

        // Close modals on backdrop click
        document.querySelectorAll('.confirm-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>

</body>
</html>
