<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login/login.php");
    exit;
}

// ======================
// FILTER TANGGAL
// Baca jenis laporan, periode, dan range tanggal dari URL.
// Default: laporan transaksi untuk bulan ini.
// ======================
$jenis   = isset($_GET['jenis'])   ? $_GET['jenis']   : 'transaksi';
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'bulanan';

$today      = date('Y-m-d');
$tgl_dari   = isset($_GET['tgl_dari'])  && $_GET['tgl_dari']  != '' ? $_GET['tgl_dari']  : date('Y-m-01');
$tgl_sampai = isset($_GET['tgl_sampai'])&& $_GET['tgl_sampai']!= '' ? $_GET['tgl_sampai']: $today;

// Shortcut periode
if (isset($_GET['periode'])) {
    if ($periode == 'harian') {
        $tgl_dari   = $today;
        $tgl_sampai = $today;
    } elseif ($periode == 'mingguan') {
        $tgl_dari   = date('Y-m-d', strtotime('monday this week'));
        $tgl_sampai = date('Y-m-d', strtotime('sunday this week'));
    } elseif ($periode == 'bulanan') {
        $tgl_dari   = date('Y-m-01');
        $tgl_sampai = date('Y-m-t');
    }
    // 'kustom' = pakai tgl_dari & tgl_sampai dari input
}

// ======================
// DATA LAPORAN TRANSAKSI
// Query JOIN peminjaman+anggota+buku+kategori sesuai rentang tanggal.
// Hitung juga durasi pinjam (DATEDIFF) dan stats ringkasan.
// ======================
$lap_transaksi = [];
$stats_transaksi = [];
if ($jenis == 'transaksi') {
    $sql = "
        SELECT
            p.id_peminjaman,
            a.nama       AS nama_anggota,
            a.kelas,
            b.judul      AS judul_buku,
            b.penulis,
            k.nama_kategori,
            d.jumlah,
            p.tanggal_peminjaman,
            p.tanggal_kembali,
            p.status,
            DATEDIFF(
                CASE WHEN p.tanggal_kembali IS NOT NULL THEN p.tanggal_kembali ELSE NOW() END,
                p.tanggal_peminjaman
            ) AS durasi_hari
        FROM peminjaman p
        JOIN anggota a ON p.id_anggota = a.id_anggota
        LEFT JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
        LEFT JOIN buku b ON d.id_buku = b.id_buku
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        WHERE DATE(p.tanggal_peminjaman) BETWEEN '$tgl_dari' AND '$tgl_sampai'
        ORDER BY p.tanggal_peminjaman DESC
    ";
    $res = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($res)) $lap_transaksi[] = $r;

    // Stats
    $stats_transaksi['total']      = count($lap_transaksi);
    $stats_transaksi['dipinjam']   = count(array_filter($lap_transaksi, fn($r) => $r['status'] == 'dipinjam'));
    $stats_transaksi['kembali']    = count(array_filter($lap_transaksi, fn($r) => $r['status'] == 'dikembalikan'));
    $stats_transaksi['menunggu']   = count(array_filter($lap_transaksi, fn($r) => in_array($r['status'], ['menunggu_pinjam','menunggu_kembali'])));
    $stats_transaksi['ditolak']    = count(array_filter($lap_transaksi, fn($r) => in_array($r['status'], ['ditolak_pinjam','ditolak_kembali'])));
}

// ======================
// DATA LAPORAN ANGGOTA
// Query semua anggota + ringkasan aktivitas pinjam di periode dipilih.
// Diurutkan dari yang paling banyak meminjam.
// ======================
$lap_anggota = [];
$stats_anggota = [];
if ($jenis == 'anggota') {
    $sql = "
        SELECT
            a.id_anggota,
            a.nama,
            a.kelas,
            a.no_hp,
            a.status,
            COUNT(DISTINCT p.id_peminjaman) AS total_pinjam,
            SUM(CASE WHEN p.status = 'dikembalikan' THEN 1 ELSE 0 END) AS total_kembali,
            SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END) AS sedang_pinjam,
            MAX(DATEDIFF(NOW(), p.tanggal_peminjaman)) AS max_hari
        FROM anggota a
        LEFT JOIN peminjaman p ON a.id_anggota = p.id_anggota
            AND DATE(p.tanggal_peminjaman) BETWEEN '$tgl_dari' AND '$tgl_sampai'
        GROUP BY a.id_anggota
        ORDER BY total_pinjam DESC, a.nama ASC
    ";
    $res = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($res)) $lap_anggota[] = $r;

    $stats_anggota['total']    = count($lap_anggota);
    $stats_anggota['aktif']    = count(array_filter($lap_anggota, fn($r) => $r['status'] == 'aktif'));
    $stats_anggota['nonaktif'] = count(array_filter($lap_anggota, fn($r) => $r['status'] == 'nonaktif'));
    $stats_anggota['pernah_pinjam'] = count(array_filter($lap_anggota, fn($r) => $r['total_pinjam'] > 0));
}

// ======================
// DATA LAPORAN BUKU
// Query semua buku + jumlah peminjaman di periode dipilih.
// Diurutkan berdasarkan yang paling banyak dipinjam.
// ======================
$lap_buku = [];
$stats_buku = [];
if ($jenis == 'buku') {
    $sql = "
        SELECT
            b.id_buku,
            b.judul,
            b.penulis,
            b.penerbit,
            k.nama_kategori,
            b.stok,
            b.status AS status_buku,
            COUNT(DISTINCT d.id_peminjaman) AS total_dipinjam,
            SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END) AS sedang_dipinjam,
            SUM(CASE WHEN p.status = 'dikembalikan' THEN 1 ELSE 0 END) AS sudah_kembali
        FROM buku b
        LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
        LEFT JOIN detail_peminjaman d ON b.id_buku = d.id_buku
        LEFT JOIN peminjaman p ON d.id_peminjaman = p.id_peminjaman
            AND DATE(p.tanggal_peminjaman) BETWEEN '$tgl_dari' AND '$tgl_sampai'
        GROUP BY b.id_buku
        ORDER BY total_dipinjam DESC, b.judul ASC
    ";
    $res = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($res)) $lap_buku[] = $r;

    $stats_buku['total']        = count($lap_buku);
    $stats_buku['rilis']        = count(array_filter($lap_buku, fn($r) => $r['status_buku'] == 'rilis'));
    $stats_buku['pernah_pinjam']= count(array_filter($lap_buku, fn($r) => $r['total_dipinjam'] > 0));
    $stats_buku['total_stok']   = array_sum(array_column($lap_buku, 'stok'));
}

// Label periode untuk display
$label_periode = [
    'harian'  => 'Harian',
    'mingguan'=> 'Mingguan',
    'bulanan' => 'Bulanan',
    'kustom'  => 'Kustom',
];
$label_jenis = [
    'transaksi' => 'Laporan Transaksi',
    'anggota'   => 'Laporan Anggota',
    'buku'      => 'Laporan Buku',
];

$nama_perpustakaan = 'Perpustakaan';
$tgl_cetak = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div class="admin-page laporan-wrap">

    <a href="../dashboard_admin.php" class="back-link no-print"><i class="fas fa-arrow-left"></i>Dashboard</a>

    <h2 class="no-print"><i class="fas fa-chart-bar"></i> Laporan</h2>

    <!-- PRINT HEADER (hanya tampil saat print) -->
    <div class="print-header">
        <h2><?= $nama_perpustakaan ?></h2>
        <p><?= $label_jenis[$jenis] ?? 'Laporan' ?></p>
        <p>Periode: <?= date('d/m/Y', strtotime($tgl_dari)) ?> &mdash; <?= date('d/m/Y', strtotime($tgl_sampai)) ?></p>
        <p>Dicetak: <?= $tgl_cetak ?></p>
    </div>

    <!-- ============================================
         JENIS LAPORAN TABS
         ============================================ -->
    <div class="jenis-tabs no-print">
        <?php
        $tabs = [
            'transaksi' => ['icon'=>'fa-arrow-right-arrow-left', 'label'=>'Transaksi'],
            'anggota'   => ['icon'=>'fa-users',                  'label'=>'Anggota'],
            'buku'      => ['icon'=>'fa-book-open',              'label'=>'Buku'],
        ];
        foreach ($tabs as $key => $tab):
            $active = $jenis == $key ? 'active' : '';
            $url = "?jenis=$key&periode=$periode&tgl_dari=" . urlencode($tgl_dari) . "&tgl_sampai=" . urlencode($tgl_sampai);
        ?>
        <a href="<?= $url ?>" class="jenis-tab <?= $active ?>">
            <span class="tab-icon"><i class="fas <?= $tab['icon'] ?>"></i></span>
            <?= $tab['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ============================================
         FILTER PANEL
         ============================================ -->
    <div class="filter-panel no-print">
        <div class="filter-panel-title">
            <i class="fas fa-sliders"></i> Filter Laporan
        </div>
        <form method="GET" id="filterForm">
            <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis) ?>">
            <input type="hidden" name="periode" id="hiddenPeriode" value="<?= htmlspecialchars($periode) ?>">

            <div class="filter-row">
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_dari" id="inputTglDari" value="<?= htmlspecialchars($tgl_dari) ?>" max="<?= $today ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" id="inputTglSampai" value="<?= htmlspecialchars($tgl_sampai) ?>" max="<?= $today ?>">
                </div>
                <div class="filter-group">
                    <label>Periode Cepat</label>
                    <div class="periode-btns">
                        <?php
                        $periodes = [
                            'harian'   => 'Hari Ini',
                            'mingguan' => 'Minggu Ini',
                            'bulanan'  => 'Bulan Ini',
                            'kustom'   => 'Kustom',
                        ];
                        foreach ($periodes as $p => $lbl):
                            $isActive = ($periode == $p || ($p=='kustom' && !in_array($periode, ['harian','mingguan','bulanan'])));
                        ?>
                        <button type="button" class="periode-btn <?= $isActive ? 'active' : '' ?>"
                            onclick="setPeriode('<?= $p ?>')">
                            <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button type="submit" style="
                        padding: 8px 20px;
                        background: var(--primary);
                        color: #fff;
                        border: none;
                        border-radius: var(--radius-md);
                        font-size: 0.84rem;
                        font-weight: 700;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                    "><i class="fas fa-magnifying-glass"></i> Tampilkan</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================
         EXPORT BAR
         ============================================ -->
    <div class="export-bar no-print">
        <div class="export-bar-left">
            <div class="lap-title"><?= $label_jenis[$jenis] ?></div>
            <div class="lap-subtitle">
                <i class="fas fa-calendar-days"></i>
                <?= date('d M Y', strtotime($tgl_dari)) ?> &mdash; <?= date('d M Y', strtotime($tgl_sampai)) ?>
                &nbsp;&bull;&nbsp; <?= $label_periode[$periode] ?? 'Kustom' ?>
            </div>
        </div>
        <div class="export-btns">
            <button class="btn-export btn-export-pdf"   onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button class="btn-export btn-export-excel" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button class="btn-export btn-export-csv"   onclick="exportCSV()">
                <i class="fas fa-file-csv"></i> CSV
            </button>
            <button class="btn-export btn-export-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <?php /* ==================== TRANSAKSI ==================== */ ?>
    <?php if ($jenis == 'transaksi'): ?>

    <!-- STATS -->
    <div class="lap-stats-row">
        <div class="lap-stat-card">
            <div class="lap-stat-icon blue"><i class="fas fa-list-check"></i></div>
            <div><div class="lap-stat-val"><?= $stats_transaksi['total'] ?></div><div class="lap-stat-lbl">Total Transaksi</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon blue"><i class="fas fa-book-open"></i></div>
            <div><div class="lap-stat-val"><?= $stats_transaksi['dipinjam'] ?></div><div class="lap-stat-lbl">Masih Dipinjam</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div><div class="lap-stat-val"><?= $stats_transaksi['kembali'] ?></div><div class="lap-stat-lbl">Dikembalikan</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon amber"><i class="fas fa-clock"></i></div>
            <div><div class="lap-stat-val"><?= $stats_transaksi['menunggu'] ?></div><div class="lap-stat-lbl">Menunggu Validasi</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div><div class="lap-stat-val"><?= $stats_transaksi['ditolak'] ?></div><div class="lap-stat-lbl">Ditolak</div></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="lap-table-wrap">
    <?php if (empty($lap_transaksi)): ?>
        <div class="lap-empty">
            <i class="fas fa-inbox"></i>
            <p>Tidak ada data transaksi pada periode ini.</p>
        </div>
    <?php else: ?>
    <table class="lap-table" id="tableData">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Nama Anggota</th>
                <th>Kelas</th>
                <th>Judul Buku</th>
                <th>Penulis</th>
                <th>Kategori</th>
                <th>Jml</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>Durasi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; foreach ($lap_transaksi as $r): ?>
            <?php
            $badge = '';
            switch ($r['status']) {
                case 'dipinjam':        $badge = '<span class="sbadge sbadge-dipinjam"><i class="fas fa-book-open"></i> Dipinjam</span>'; break;
                case 'dikembalikan':    $badge = '<span class="sbadge sbadge-kembali"><i class="fas fa-check-circle"></i> Kembali</span>'; break;
                case 'menunggu_pinjam': $badge = '<span class="sbadge sbadge-menunggu"><i class="fas fa-clock"></i> Menunggu Pinjam</span>'; break;
                case 'menunggu_kembali':$badge = '<span class="sbadge sbadge-menunggu"><i class="fas fa-rotate-left"></i> Menunggu Kembali</span>'; break;
                case 'ditolak_pinjam':  $badge = '<span class="sbadge sbadge-ditolak"><i class="fas fa-times-circle"></i> Ditolak Pinjam</span>'; break;
                case 'ditolak_kembali': $badge = '<span class="sbadge sbadge-ditolak"><i class="fas fa-times-circle"></i> Ditolak Kembali</span>'; break;
                default: $badge = '<span class="sbadge">' . htmlspecialchars($r['status']) . '</span>';
            }
            ?>
            <tr>
                <td class="no-col"><?= $no++ ?></td>
                <td><?= htmlspecialchars($r['nama_anggota']) ?></td>
                <td><?= htmlspecialchars($r['kelas'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['judul_buku'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['penulis'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['nama_kategori'] ?? '-') ?></td>
                <td style="text-align:center;"><?= $r['jumlah'] ?? 1 ?></td>
                <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($r['tanggal_peminjaman'])) ?></td>
                <td style="white-space:nowrap;"><?= $r['tanggal_kembali'] ? date('d/m/Y', strtotime($r['tanggal_kembali'])) : '-' ?></td>
                <td style="text-align:center;"><?= (int)$r['durasi_hari'] ?> hr</td>
                <td><?= $badge ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>

    <?php /* ==================== ANGGOTA ==================== */ ?>
    <?php elseif ($jenis == 'anggota'): ?>

    <!-- STATS -->
    <div class="lap-stats-row">
        <div class="lap-stat-card">
            <div class="lap-stat-icon blue"><i class="fas fa-users"></i></div>
            <div><div class="lap-stat-val"><?= $stats_anggota['total'] ?></div><div class="lap-stat-lbl">Total Anggota</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon green"><i class="fas fa-user-check"></i></div>
            <div><div class="lap-stat-val"><?= $stats_anggota['aktif'] ?></div><div class="lap-stat-lbl">Aktif</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon gray"><i class="fas fa-user-xmark"></i></div>
            <div><div class="lap-stat-val"><?= $stats_anggota['nonaktif'] ?></div><div class="lap-stat-lbl">Nonaktif</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon indigo"><i class="fas fa-book-open"></i></div>
            <div><div class="lap-stat-val"><?= $stats_anggota['pernah_pinjam'] ?></div><div class="lap-stat-lbl">Pernah Pinjam</div></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="lap-table-wrap">
    <?php if (empty($lap_anggota)): ?>
        <div class="lap-empty">
            <i class="fas fa-users-slash"></i>
            <p>Tidak ada data anggota.</p>
        </div>
    <?php else: ?>
    <table class="lap-table" id="tableData">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Nama Anggota</th>
                <th>Kelas</th>
                <th>No HP</th>
                <th>Status</th>
                <th>Total Pinjam</th>
                <th>Dikembalikan</th>
                <th>Masih Pinjam</th>
                <th>Lama Pinjam</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; foreach ($lap_anggota as $r): ?>
            <tr>
                <td class="no-col"><?= $no++ ?></td>
                <td><?= htmlspecialchars($r['nama']) ?></td>
                <td><?= htmlspecialchars($r['kelas'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['no_hp'] ?? '-') ?></td>
                <td>
                    <?php if ($r['status'] == 'aktif'): ?>
                        <span class="sbadge sbadge-aktif"><i class="fas fa-circle-check"></i> Aktif</span>
                    <?php else: ?>
                        <span class="sbadge sbadge-nonaktif"><i class="fas fa-circle-xmark"></i> Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:700;"><?= (int)$r['total_pinjam'] ?></td>
                <td style="text-align:center;"><?= (int)$r['total_kembali'] ?></td>
                <td style="text-align:center;"><?= (int)$r['sedang_pinjam'] ?></td>
                <td style="text-align:center;"><?= $r['max_hari'] !== null ? (int)$r['max_hari'] . ' hr' : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>

    <?php /* ==================== BUKU ==================== */ ?>
    <?php elseif ($jenis == 'buku'): ?>

    <!-- STATS -->
    <div class="lap-stats-row">
        <div class="lap-stat-card">
            <div class="lap-stat-icon blue"><i class="fas fa-books"></i></div>
            <div><div class="lap-stat-val"><?= $stats_buku['total'] ?></div><div class="lap-stat-lbl">Total Judul</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon green"><i class="fas fa-circle-check"></i></div>
            <div><div class="lap-stat-val"><?= $stats_buku['rilis'] ?></div><div class="lap-stat-lbl">Status Rilis</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon indigo"><i class="fas fa-book-open"></i></div>
            <div><div class="lap-stat-val"><?= $stats_buku['pernah_pinjam'] ?></div><div class="lap-stat-lbl">Pernah Dipinjam</div></div>
        </div>
        <div class="lap-stat-card">
            <div class="lap-stat-icon amber"><i class="fas fa-layer-group"></i></div>
            <div><div class="lap-stat-val"><?= $stats_buku['total_stok'] ?></div><div class="lap-stat-lbl">Total Stok</div></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="lap-table-wrap">
    <?php if (empty($lap_buku)): ?>
        <div class="lap-empty">
            <i class="fas fa-book-open"></i>
            <p>Tidak ada data buku.</p>
        </div>
    <?php else: ?>
    <table class="lap-table" id="tableData">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Judul Buku</th>
                <th>Penulis</th>
                <th>Penerbit</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Status</th>
                <th>Total Dipinjam</th>
                <th>Sedang Dipinjam</th>
                <th>Sudah Kembali</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; foreach ($lap_buku as $r): ?>
            <tr>
                <td class="no-col"><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($r['judul']) ?></strong></td>
                <td><?= htmlspecialchars($r['penulis'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['penerbit'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['nama_kategori'] ?? '-') ?></td>
                <td style="text-align:center;font-weight:700;"><?= (int)$r['stok'] ?></td>
                <td>
                    <?php if ($r['status_buku'] == 'rilis'): ?>
                        <span class="sbadge sbadge-rilis"><i class="fas fa-check-circle"></i> Rilis</span>
                    <?php else: ?>
                        <span class="sbadge sbadge-akan-datang"><i class="fas fa-clock"></i> <?= htmlspecialchars($r['status_buku']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:700;color:var(--primary);"><?= (int)$r['total_dipinjam'] ?></td>
                <td style="text-align:center;"><?= (int)$r['sedang_dipinjam'] ?></td>
                <td style="text-align:center;"><?= (int)$r['sudah_kembali'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<?php include '../../footer.php'; ?>

<script>
// ======================
// PERIODE SHORTCUT
// ======================
var today = new Date();
function fmt(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth()+1).padStart(2,'0');
    var dd = String(d.getDate()).padStart(2,'0');
    return y + '-' + m + '-' + dd;
}

function setPeriode(p) {
    document.getElementById('hiddenPeriode').value = p;
    document.querySelectorAll('.periode-btn').forEach(function(b){ b.classList.remove('active'); });
    event.target.classList.add('active');

    var d1, d2;
    if (p === 'harian') {
        d1 = fmt(today); d2 = fmt(today);
    } else if (p === 'mingguan') {
        var day = today.getDay(); // 0=Sun
        var mon = new Date(today); mon.setDate(today.getDate() - (day === 0 ? 6 : day - 1));
        var sun = new Date(mon);   sun.setDate(mon.getDate() + 6);
        d1 = fmt(mon); d2 = fmt(sun);
    } else if (p === 'bulanan') {
        d1 = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
        d2 = fmt(new Date(today.getFullYear(), today.getMonth()+1, 0));
    } else {
        // kustom - biarkan input aktif
        document.getElementById('inputTglDari').focus();
        return;
    }
    document.getElementById('inputTglDari').value   = d1;
    document.getElementById('inputTglSampai').value = d2;
    document.getElementById('filterForm').submit();
}

// ======================
// EXPORT PDF
// ======================
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    // Header
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('<?= addslashes($nama_perpustakaan) ?>', 14, 14);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('<?= addslashes($label_jenis[$jenis]) ?>', 14, 20);
    doc.text('Periode: <?= date('d/m/Y', strtotime($tgl_dari)) ?> - <?= date('d/m/Y', strtotime($tgl_sampai)) ?>', 14, 26);
    doc.text('Dicetak: <?= $tgl_cetak ?>', 14, 32);

    // Line
    doc.setDrawColor(200);
    doc.line(14, 35, doc.internal.pageSize.getWidth() - 14, 35);

    // Table
    const table = document.getElementById('tableData');
    if (!table) { alert('Tidak ada data untuk diekspor.'); return; }

    const headers = [];
    const rows    = [];

    table.querySelectorAll('thead tr th').forEach(function(th) {
        headers.push(th.innerText.trim());
    });

    table.querySelectorAll('tbody tr').forEach(function(tr) {
        var row = [];
        tr.querySelectorAll('td').forEach(function(td) { row.push(td.innerText.trim()); });
        rows.push(row);
    });

    doc.autoTable({
        head: [headers],
        body: rows,
        startY: 38,
        styles: { fontSize: 7.5, cellPadding: 2.5, overflow: 'linebreak' },
        headStyles: { fillColor: [79, 70, 229], textColor: 255, fontStyle: 'bold', fontSize: 7.5 },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        margin: { left: 14, right: 14 },
        didDrawPage: function(data) {
            // Footer halaman
            doc.setFontSize(7);
            doc.setTextColor(150);
            doc.text('Halaman ' + doc.internal.getCurrentPageInfo().pageNumber, doc.internal.pageSize.getWidth() - 14, doc.internal.pageSize.getHeight() - 8, { align: 'right' });
        }
    });

    doc.save('laporan_<?= $jenis ?>_<?= $tgl_dari ?>_<?= $tgl_sampai ?>.pdf');
}

// ======================
// EXPORT EXCEL
// ======================
function exportExcel() {
    const table = document.getElementById('tableData');
    if (!table) { alert('Tidak ada data untuk diekspor.'); return; }

    // Clone untuk bersihkan badge HTML
    var clone = table.cloneNode(true);
    clone.querySelectorAll('.sbadge').forEach(function(el) {
        el.parentNode.textContent = el.innerText.trim();
    });

    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.table_to_sheet(clone);

    // Lebar kolom otomatis
    var range = XLSX.utils.decode_range(ws['!ref']);
    ws['!cols'] = [];
    for (var C = range.s.c; C <= range.e.c; C++) {
        var maxW = 10;
        for (var R = range.s.r; R <= range.e.r; R++) {
            var cell = ws[XLSX.utils.encode_cell({r:R,c:C})];
            if (cell && cell.v) maxW = Math.max(maxW, String(cell.v).length + 2);
        }
        ws['!cols'].push({ wch: Math.min(maxW, 40) });
    }

    XLSX.utils.book_append_sheet(wb, ws, '<?= ucfirst($jenis) ?>');
    XLSX.writeFile(wb, 'laporan_<?= $jenis ?>_<?= $tgl_dari ?>_<?= $tgl_sampai ?>.xlsx');
}

// ======================
// EXPORT CSV
// ======================
function exportCSV() {
    const table = document.getElementById('tableData');
    if (!table) { alert('Tidak ada data untuk diekspor.'); return; }

    var rows = [];
    table.querySelectorAll('tr').forEach(function(tr) {
        var row = [];
        tr.querySelectorAll('th, td').forEach(function(cell) { row.push('"' + cell.innerText.trim().replace(/"/g,'""') + '"'); });
        rows.push(row.join(','));
    });

    var csv    = '\uFEFF' + rows.join('\n'); // BOM untuk Excel UTF-8
    var blob   = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url    = URL.createObjectURL(blob);
    var a      = document.createElement('a');
    a.href     = url;
    a.download = 'laporan_<?= $jenis ?>_<?= $tgl_dari ?>_<?= $tgl_sampai ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>
