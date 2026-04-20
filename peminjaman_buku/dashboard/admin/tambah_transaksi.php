<?php
session_start();

// ============================================
// KEAMANAN: Hanya admin yang bisa akses
// ============================================
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login/login.php");
    exit;
}

require '../../koneksi.php';

// ============================================
// VARIABEL PESAN & ERROR
// ============================================
$error   = '';
$success = '';

// ============================================
// PROSES TAMBAH TRANSAKSI (PEMINJAMAN)
// ============================================
if (isset($_POST['tambah'])) {

    $id_anggota      = intval($_POST['id_anggota']);
    $id_buku         = intval($_POST['id_buku']);
    $jumlah          = intval($_POST['jumlah']);
    $tanggal_pinjam  = $_POST['tanggal_pinjam'];
    $tanggal_kembali = $_POST['tanggal_kembali'];

    // --- Validasi: Semua field wajib ---
    if (!$id_anggota || !$id_buku || !$tanggal_pinjam || !$tanggal_kembali || $jumlah < 1) {
        $error = 'Semua field wajib diisi dengan benar!';

    } else {

        // --- Validasi: Cek status anggota harus aktif ---
        $cek_anggota = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT status FROM anggota WHERE id_anggota = '$id_anggota'")
        );

        if (!$cek_anggota || $cek_anggota['status'] !== 'aktif') {
            $error = 'Anggota yang dipilih tidak aktif atau tidak ditemukan. Peminjaman tidak dapat diproses.';

        } else {

            // --- Validasi: Cek stok buku cukup ---
            $cek_buku = mysqli_fetch_assoc(
                mysqli_query($conn, "SELECT judul, stok FROM buku WHERE id_buku = '$id_buku'")
            );

            if (!$cek_buku) {
                $error = 'Buku tidak ditemukan!';

            } elseif ($cek_buku['stok'] < $jumlah) {
                $error = 'Stok buku <strong>' . htmlspecialchars($cek_buku['judul']) . '</strong> tidak mencukupi. Stok tersedia: <strong>' . $cek_buku['stok'] . '</strong>.';

            } else {

                // --- Insert ke tabel peminjaman ---
                // Status = 'dipinjam' karena admin sendiri yang menambahkan (tidak perlu validasi)
                $stmt_pinjam = $conn->prepare("
                    INSERT INTO peminjaman (id_anggota, tanggal_peminjaman, tanggal_kembali, status)
                    VALUES (?, ?, ?, 'dipinjam')
                ");
                $stmt_pinjam->bind_param("iss", $id_anggota, $tanggal_pinjam, $tanggal_kembali);

                if ($stmt_pinjam->execute()) {

                    $id_peminjaman = $conn->insert_id;

                    // --- Insert ke tabel detail_peminjaman ---
                    $stmt_detail = $conn->prepare("
                        INSERT INTO detail_peminjaman (id_peminjaman, id_buku, jumlah)
                        VALUES (?, ?, ?)
                    ");
                    $stmt_detail->bind_param("iii", $id_peminjaman, $id_buku, $jumlah);

                    if ($stmt_detail->execute()) {

                        // --- Kurangi stok buku ---
                        $stmt_stok = $conn->prepare("
                            UPDATE buku SET stok = stok - ? WHERE id_buku = ?
                        ");
                        $stmt_stok->bind_param("ii", $jumlah, $id_buku);
                        $stmt_stok->execute();
                        $stmt_stok->close();

                        // --- Sukses: redirect ke halaman transaksi ---
                        $_SESSION['flash_success'] = 'Transaksi peminjaman berhasil ditambahkan!';
                        header("Location: transaksi.php");
                        exit;

                    } else {
                        $error = 'Gagal menyimpan detail peminjaman: ' . $stmt_detail->error;
                    }

                    $stmt_detail->close();

                } else {
                    $error = 'Gagal menyimpan data peminjaman: ' . $stmt_pinjam->error;
                }

                $stmt_pinjam->close();
            }
        }
    }
}

// ============================================
// AMBIL DATA ANGGOTA AKTIF UNTUK DROPDOWN
// ============================================
$anggota_list = mysqli_query($conn, "
    SELECT a.id_anggota, a.nama, u.username
    FROM anggota a
    JOIN user u ON a.id_user = u.id_user
    WHERE a.status = 'aktif'
    ORDER BY a.nama ASC
");

// ============================================
// AMBIL DATA BUKU YANG TERSEDIA (status=rilis & stok > 0) UNTUK DROPDOWN
// ============================================
$buku_list = mysqli_query($conn, "
    SELECT b.id_buku, b.judul, b.penulis, b.stok, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE b.status = 'rilis' AND b.stok > 0
    ORDER BY b.judul ASC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Transaksi - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="admin-page">

        <a href="transaksi.php" class="back-link"><i class="fas fa-arrow-left"></i> Kelola Transaksi</a>

        <h2><i class="fas fa-book-medical"></i> Tambah Transaksi Peminjaman</h2>

        <!-- PESAN ERROR -->
        <?php if ($error): ?>
        <div style="
            background: var(--danger-light);
            border: 1px solid #FCA5A5;
            border-left: 4px solid var(--danger);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #B91C1C;
            font-size: 0.92rem;
            font-weight: 500;
        ">
            <i class="fas fa-circle-exclamation" style="font-size:1.1rem;flex-shrink:0;margin-top:1px;"></i>
            <span><?= $error ?></span>
        </div>
        <?php endif; ?>

        <!-- FORM TAMBAH TRANSAKSI -->
        <div class="form-panel">
            <h3><i class="fas fa-plus-circle"></i> Form Peminjaman Buku</h3>

            <form method="POST" autocomplete="off">

                <div class="form-grid">

                    <!-- PILIH ANGGOTA -->
                    <div class="form-group">
                        <label><i class="fas fa-user" style="color:var(--primary);margin-right:4px;"></i> Anggota <span style="color:var(--danger);">*</span></label>
                        <select name="id_anggota" required>
                            <option value="">-- Pilih Anggota (Aktif) --</option>
                            <?php while ($a = mysqli_fetch_assoc($anggota_list)): ?>
                                <option value="<?= $a['id_anggota'] ?>"
                                    <?= (isset($_POST['id_anggota']) && $_POST['id_anggota'] == $a['id_anggota']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['username']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color:var(--gray-400);font-size:0.78rem;">Hanya anggota aktif yang ditampilkan.</small>
                    </div>

                    <!-- PILIH BUKU -->
                    <div class="form-group">
                        <label><i class="fas fa-book" style="color:var(--primary);margin-right:4px;"></i> Buku <span style="color:var(--danger);">*</span></label>
                        <select name="id_buku" id="selectBuku" required onchange="updateStok(this)">
                            <option value="">-- Pilih Buku --</option>
                            <?php while ($b = mysqli_fetch_assoc($buku_list)): ?>
                                <option value="<?= $b['id_buku'] ?>"
                                    data-stok="<?= $b['stok'] ?>"
                                    <?= (isset($_POST['id_buku']) && $_POST['id_buku'] == $b['id_buku']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['judul']) ?> — <?= htmlspecialchars($b['penulis']) ?>
                                    (Stok: <?= $b['stok'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small id="infoStok" style="color:var(--gray-400);font-size:0.78rem;">Hanya buku berstatus rilis & stok > 0 yang ditampilkan.</small>
                    </div>

                    <!-- JUMLAH PINJAM -->
                    <div class="form-group">
                        <label><i class="fas fa-hashtag" style="color:var(--primary);margin-right:4px;"></i> Jumlah Pinjam <span style="color:var(--danger);">*</span></label>
                        <input
                            type="number"
                            name="jumlah"
                            id="inputJumlah"
                            min="1"
                            value="<?= isset($_POST['jumlah']) ? intval($_POST['jumlah']) : 1 ?>"
                            required
                        >
                    </div>

                    <!-- TANGGAL PINJAM -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day" style="color:var(--primary);margin-right:4px;"></i> Tanggal Pinjam <span style="color:var(--danger);">*</span></label>
                        <input
                            type="date"
                            name="tanggal_pinjam"
                            value="<?= isset($_POST['tanggal_pinjam']) ? $_POST['tanggal_pinjam'] : date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <!-- TANGGAL KEMBALI -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-check" style="color:var(--primary);margin-right:4px;"></i> Tanggal Kembali <span style="color:var(--danger);">*</span></label>
                        <input
                            type="date"
                            name="tanggal_kembali"
                            value="<?= isset($_POST['tanggal_kembali']) ? $_POST['tanggal_kembali'] : date('Y-m-d', strtotime('+7 days')) ?>"
                            required
                        >
                    </div>

                </div>

                <!-- INFO: Status otomatis -->
                <div style="
                    background: var(--success-light);
                    border: 1px solid #6EE7B7;
                    border-radius: var(--radius-md);
                    padding: 12px 16px;
                    margin-bottom: 1.25rem;
                    font-size: 0.84rem;
                    color: #065F46;
                    display: flex;
                    gap: 8px;
                    align-items: center;
                ">
                    <i class="fas fa-circle-check"></i>
                    <span>Status transaksi akan otomatis <strong>Dipinjam</strong> karena ditambahkan langsung oleh admin (tidak perlu validasi).</span>
                </div>

                <!-- AKSI -->
                <div class="form-actions">
                    <button type="submit" name="tambah">
                        <i class="fas fa-floppy-disk"></i> Simpan Transaksi
                    </button>
                    <a href="transaksi.php">
                        <button type="button" class="btn-secondary">
                            <i class="fas fa-xmark"></i> Batal
                        </button>
                    </a>
                </div>

            </form>
        </div>

    </div>

    <script>
        // Update info stok saat buku dipilih & batasi max jumlah
        function updateStok(select) {
            var opt   = select.options[select.selectedIndex];
            var stok  = opt ? parseInt(opt.getAttribute('data-stok')) : 0;
            var info  = document.getElementById('infoStok');
            var input = document.getElementById('inputJumlah');

            if (stok > 0) {
                info.style.color  = 'var(--success)';
                info.textContent  = 'Stok tersedia: ' + stok + ' buku.';
                input.max = stok;
            } else {
                info.style.color = 'var(--gray-400)';
                info.textContent = 'Hanya buku berstatus rilis & stok > 0 yang ditampilkan.';
                input.removeAttribute('max');
            }
        }
    </script>

</body>
</html>
