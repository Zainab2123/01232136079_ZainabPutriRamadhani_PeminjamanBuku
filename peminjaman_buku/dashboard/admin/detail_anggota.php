<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login/login.php");
    exit;
}

require '../../koneksi.php';

$id = $_GET['id'];

/*
========================================
FILTER PERINGATAN (via POST)
========================================
*/
$filter_peringatan = '';
if (isset($_POST['reset_filter'])) {
    $filter_peringatan = '';
} elseif (isset($_POST['peringatan'])) {
    $filter_peringatan = $_POST['peringatan'];
}

/*
========================================
DATA ANGGOTA
========================================
*/
$anggota = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM anggota WHERE id_anggota='$id'
"));

/*
========================================
BUKU YANG SEDANG DIPINJAM + PERINGATAN
========================================
*/
$buku_result = mysqli_query($conn, "
    SELECT
        buku.judul,
        buku.id_buku,
        peminjaman.tanggal_peminjaman,
        peminjaman.id_peminjaman,
        detail_peminjaman.jumlah,
        DATEDIFF(NOW(), peminjaman.tanggal_peminjaman) AS hari_pinjam
    FROM peminjaman
    JOIN detail_peminjaman
        ON detail_peminjaman.id_peminjaman = peminjaman.id_peminjaman
    JOIN buku
        ON buku.id_buku = detail_peminjaman.id_buku
    WHERE peminjaman.id_anggota='$id'
    AND peminjaman.status='dipinjam'
");

// Fetch all buku into array for filtering
$buku_data = [];
while ($row = mysqli_fetch_assoc($buku_result)) {
    $hari = $row['hari_pinjam'] ?? 0;

    if ($hari <= 7) {
        $peringatan_val = "aman";
    } elseif ($hari <= 14) {
        $peringatan_val = "perhatian";
    } elseif ($hari <= 30) {
        $peringatan_val = "telat";
    } else {
        $peringatan_val = "kritis";
    }

    $row['peringatan_val'] = $peringatan_val;
    $buku_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Anggota - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="admin-page">

        <a href="anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Daftar Anggota</a>

        <h2><i class="fas fa-id-card"></i> Detail Anggota</h2>

        <div class="detail-section">
            <h3><i class="fas fa-circle-info"></i> Informasi Anggota</h3>
            <div class="detail-info">
                <div class="detail-info-item">
                    <p><b><i class="fas fa-user"></i> Nama:</b> <?= $anggota['nama']; ?></p>
                </div>
                <div class="detail-info-item">
                    <p><b><i class="fas fa-graduation-cap"></i> Kelas:</b> <?= $anggota['kelas']; ?></p>
                </div>
                <div class="detail-info-item">
                    <p><b><i class="fas fa-phone"></i> No HP:</b> <?= $anggota['no_hp']; ?></p>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-header">
                <h3><i class="fas fa-book"></i> Buku yang sedang dipinjam</h3>
                <form method="POST" class="detail-filter-form">
                    <div class="search-input-with-btns">
                        <div class="form-group" style="min-width:180px;">
                            <label><i class="fas fa-filter"></i> Filter Peringatan:</label>
                            <select name="peringatan" onchange="this.form.submit()">
                                <option value="">-- Semua --</option>
                                <option value="aman" <?= $filter_peringatan == 'aman' ? 'selected' : '' ?>>Aman</option>
                                <option value="perhatian" <?= $filter_peringatan == 'perhatian' ? 'selected' : '' ?>>Perhatian</option>
                                <option value="telat" <?= $filter_peringatan == 'telat' ? 'selected' : '' ?>>Telat</option>
                                <option value="kritis" <?= $filter_peringatan == 'kritis' ? 'selected' : '' ?>>Kritis</option>
                            </select>
                        </div>
                        <div class="search-btn-group">
                            <button type="submit" name="reset_filter" class="btn-secondary"><i class="fas fa-rotate-left"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php
            $has_books = false;
            foreach ($buku_data as $row) {

                // Skip if filter active and doesn't match
                if (!empty($filter_peringatan) && $row['peringatan_val'] != $filter_peringatan) {
                    continue;
                }

                $has_books = true;

                $hari = $row['hari_pinjam'] ?? 0;
                $peringatan_val = $row['peringatan_val'];

                if ($peringatan_val == 'aman') {
                    $badge_class = "badge-aman";
                    $label = "AMAN";
                    $icon = "fa-circle-check";
                } elseif ($peringatan_val == 'perhatian') {
                    $badge_class = "badge-perhatian";
                    $label = "PERHATIAN";
                    $icon = "fa-triangle-exclamation";
                } elseif ($peringatan_val == 'telat') {
                    $badge_class = "badge-telat";
                    $label = "TELAT";
                    $icon = "fa-clock";
                } else {
                    $badge_class = "badge-kritis";
                    $label = "KRITIS";
                    $icon = "fa-circle-exclamation";
                }
            ?>
            <div class="detail-book-item <?= $badge_class != 'badge-aman' ? 'row-' . str_replace('badge-', '', $badge_class) : 'row-aman'; ?>" style="background:var(--card);border-radius:var(--radius-md);padding:16px 18px;margin-bottom:10px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div>
                        <p style="font-weight:700;color:var(--gray-900);font-size:0.92rem;margin-bottom:2px;">
                            <i class="fas fa-book" style="color:var(--primary);margin-right:4px;"></i>
                            <?= $row['judul']; ?>
                        </p>
                        <p style="font-size:0.8rem;color:var(--gray-500);">
                            <i class="fas fa-copy"></i> Jumlah: <?= $row['jumlah']; ?>
                            &nbsp;&middot;&nbsp;
                            <i class="fas fa-calendar"></i> <?= $row['tanggal_peminjaman']; ?>
                            &nbsp;&middot;&nbsp;
                            <i class="fas fa-hourglass-half"></i> <?= $hari; ?> hari
                        </p>
                    </div>
                    <span class="warning-badge <?= $badge_class; ?>">
                        <i class="fas <?= $icon; ?>"></i> <?= $label; ?>
                    </span>
                </div>
            </div>
            <?php } ?>

            <?php if (!$has_books): ?>
                <div style="text-align:center;padding:30px;color:var(--gray-400);">
                    <i class="fas fa-book-open" style="font-size:2rem;margin-bottom:8px;display:block;opacity:0.4;"></i>
                    <p style="font-size:0.9rem;color:var(--gray-500);">Tidak ada buku yang sesuai filter.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>
