<?php
session_start();
require '../koneksi.php';

// ============================================
// KEAMANAN: Cek apakah user sudah login
// Jika session id_anggota tidak ada, redirect ke login
// ============================================
if (!isset($_SESSION['id_anggota'])) {
    header("Location: ../login/login.php");
    exit;
}

// ============================================
// CEK STATUS AKTIF ANGGOTA
// Jika status bukan 'aktif' (misalnya 'nonaktif' atau 'diblokir'),
// hancurkan session dan tampilkan pesan akun dinonaktifkan
// Ini untuk mencegah anggota yang sudah dinonaktifkan tetap bisa akses
// ============================================
$id = $_SESSION['id_anggota'];

$cek_status = mysqli_query($conn, "
    SELECT status FROM anggota WHERE id_anggota='$id'
");

$data_status = mysqli_fetch_assoc($cek_status);

if ($data_status['status'] !== 'aktif') {

    session_destroy();

    echo "
        <h3>Akun kamu sudah dinonaktifkan oleh admin!</h3>
        <p>Silakan hubungi admin untuk informasi lebih lanjut:</p>
        <p>Telepon: 0812-3456-7890</p>
        <p>Email: adminperpustakaan@gmail.com</p>
        <br>
        <a href='../login/login.php'>Kembali ke Login</a>
    ";

    exit();
}

// ============================================
// CEK ROLE: Pastikan yang akses halaman ini benar-benar anggota
// ============================================
if ($_SESSION['role'] != 'anggota') {
    header("Location: ../login/login.php");
    exit;
}

// ============================================
// HITUNG NOTIFIKASI BELUM DIBACA UNTUK BADGE
// Query mengambil jumlah notifikasi unread anggota
// ditampilkan sebagai angka merah di ikon bel navbar
// ============================================
$id_user = $_SESSION['id_user'];
$notif_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifikasi WHERE id_user = '$id_user' AND dibaca = 0");
$notif_unread = mysqli_fetch_assoc($notif_q)['total'];

// ============================================
// SEARCH HISTORY - Riwayat pencarian buku
// Disimpan di session agar bisa ditampilkan sebagai saran di input pencarian
// ============================================
if (!isset($_SESSION['search_history'])) {
    $_SESSION['search_history'] = [];
}

// HANDLE RESET DARI GET
if (isset($_GET['reset'])) {
    $keyword = '';
    $is_search = false;
    unset($_SESSION['keyword']);
}

// ============================================
// MODE SEARCH / RESET
// Jika user submit form pencarian, ambil keyword dan simpan ke history
// Jika user klik Reset, kosongkan keyword dan tampilkan semua buku
// ============================================
$keyword = '';
$is_search = false;

// kalau search ditekan
if (isset($_POST['search'])) {
    $keyword = trim($_POST['keyword']);

    if ($keyword != '') {
        $is_search = true;

        // simpan ke session (INI KUNCI FIX NYA)
        $_SESSION['keyword'] = $keyword;

        $_SESSION['search_history'][] = $keyword;
    }
}

// kalau filter / reload tapi masih ada keyword di session
if (isset($_SESSION['keyword']) && !isset($_GET['reset'])) {
    $keyword = $_SESSION['keyword'];
    $is_search = true;
}

// DATA HISTORY INPUT
// Ambil unique history untuk ditampilkan di datalist pencarian
// ============================================
$history_for_input = array_unique($_SESSION['search_history']);

// ============================================
// FLASH MESSAGE - Pesan sukses satu kali (setelah peminjaman/pengembalian)
// Flash message disimpan di session, ditampilkan sekali, lalu dihapus
// Digunakan untuk menampilkan toast "Permohonan berhasil dikirim!" setelah redirect
// ============================================
$flash_success = '';
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ============================================
// SORT & FILTER - Pengaturan urutan dan filter kategori
// Mendukung sorting: A-Z, Z-A, Stok terbanyak/tersedikit, Terbaru/Terlama
// Filter berdasarkan kategori buku
// ============================================
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$kategori = mysqli_query($conn, "SELECT * FROM kategori");
$kategori_id = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$where_kategori = "";

if (!empty($kategori_id)) {
    $where_kategori = "AND b.id_kategori = '$kategori_id'";
}

$order = "";

switch ($sort) {
    case 'az':
        $order = "ORDER BY b.judul ASC";
        break;
    case 'za':
        $order = "ORDER BY b.judul DESC";
        break;
    case 'stok-terbanyak':
        $order = "ORDER BY b.stok DESC";
        break;
    case 'stok-tersedikit':
        $order = "ORDER BY b.stok ASC";
        break;
    case 'terbaru':
        $order = "ORDER BY b.tahun_terbit DESC";
        break;
    case 'terlama':
        $order = "ORDER BY b.tahun_terbit ASC";
        break;
}

// ============================================
// QUERY BUKU POPULER - Top 3 buku paling banyak dipinjam
// Digunakan untuk menampilkan section "Buku Populer" di dashboard
// ============================================
$populer_query = mysqli_query($conn, "
    SELECT b.*, k.nama_kategori, COUNT(dp.id_peminjaman) AS total_pinjam
    FROM buku b
    JOIN detail_peminjaman dp ON b.id_buku = dp.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    GROUP BY b.id_buku
    ORDER BY total_pinjam DESC
    LIMIT 3
");
$populer_count = mysqli_num_rows($populer_query);

// ============================================
// QUERY BUKU AKAN DATANG
// Ambil semua buku dengan status 'akan_datang', urutkan berdasarkan tanggal_rilis
// ============================================
$akan_datang_query = mysqli_query($conn, "
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE b.status = 'akan_datang'
    ORDER BY b.tanggal_rilis ASC
");
$akan_datang_count = mysqli_num_rows($akan_datang_query);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anggota - Perpustakaan</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/notifikasi.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ============================================
           INFO ICON BUTTON - Tombol info di navbar
           ============================================ */
        .info-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.15rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            position: relative;
            flex-shrink: 0;
        }

        .info-icon-btn:hover {
            background: var(--primary-50);
            color: var(--primary);
        }

        /* ============================================
           INFO POPUP MODAL - Popup aturan & denda
           Style mengikuti pattern confirm-overlay/confirm-box
           ============================================ */
        .info-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 999;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }

        .info-popup-overlay.active {
            display: flex;
        }

        .info-popup-box {
            background: var(--card);
            border-radius: var(--radius-xl);
            padding: 32px;
            max-width: 480px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--shadow-2xl);
            animation: scaleIn 0.25s ease;
        }

        .info-popup-box .info-popup-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.5rem;
            background: var(--primary-50);
            color: var(--primary);
        }

        .info-popup-box h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .info-popup-section {
            margin-bottom: 1.25rem;
        }

        .info-popup-section:last-of-type {
            margin-bottom: 0;
        }

        .info-popup-section h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-popup-section h4 .section-icon {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .info-popup-section .sub-section {
            margin-bottom: 0.75rem;
        }

        .info-popup-section .sub-section:last-child {
            margin-bottom: 0;
        }

        .info-popup-section .sub-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 0.4rem;
        }

        .info-popup-section .sub-title .sub-icon {
            margin-right: 4px;
        }

        .info-popup-section ul {
            margin: 0 0 0.5rem 0;
            padding-left: 20px;
            list-style: disc;
        }

        .info-popup-section ul li {
            font-size: 0.82rem;
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 0.15rem;
        }

        .info-popup-section .fine-note {
            font-size: 0.78rem;
            color: var(--gray-400);
            font-style: italic;
            margin-top: 0.25rem;
        }

        .info-popup-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 1rem 0;
        }

        .info-popup-close-btn {
            display: block;
            width: 100%;
            padding: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            border: none;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            transition: all var(--transition-fast);
            margin-top: 1.25rem;
        }

        .info-popup-close-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* ============================================
           POPULAR BOOKS SECTION - Buku Populer
           ============================================ */
        .popular-books-section {
            margin: 1.25rem 0;
        }

        .popular-books-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .popular-books-section h3 i {
            color: #F59E0B;
            font-size: 1.05rem;
        }

        .popular-books-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .popular-book-card {
            background: var(--card);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-slow);
            box-shadow: var(--shadow-xs);
        }

        .popular-book-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-200);
        }

        .popular-book-card .popular-book-image {
            width: 100%;
            height: 160px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .popular-book-card .popular-book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-slow);
            position: absolute;
            top: 0;
            left: 0;
        }

        .popular-book-card:hover .popular-book-image img {
            transform: scale(1.05);
        }

        .popular-book-card .popular-book-image .no-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gray-300);
            gap: 8px;
            z-index: 1;
        }

        .popular-book-card .popular-book-image .no-image i {
            font-size: 2rem;
            opacity: 0.6;
        }

        .popular-book-card .popular-book-image .no-image span {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
        }

        .popular-book-card .popular-book-image .popular-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(245, 158, 11, 0.9);
            color: #fff;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 0.68rem;
            font-weight: 700;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }

        .popular-book-card .popular-book-info {
            padding: 12px 14px;
        }

        .popular-book-card .popular-book-info h4 {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .popular-book-card .popular-book-info .popular-count {
            font-size: 0.78rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .popular-book-card .popular-book-info .popular-count i {
            color: #F59E0B;
            font-size: 0.72rem;
        }

        /* Empty placeholder card for popular books */
        .popular-book-card.popular-empty {
            opacity: 0.5;
            cursor: default;
        }

        .popular-book-card.popular-empty:hover {
            transform: none;
            box-shadow: var(--shadow-xs);
            border-color: var(--gray-200);
        }

        /* ============================================
           RESPONSIVE - Popular Books
           ============================================ */
        @media (max-width: 575px) {
            .popular-books-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .popular-book-card .popular-book-image {
                height: 120px;
            }

            .popular-book-card .popular-book-info {
                padding: 10px;
            }

            .popular-book-card .popular-book-info h4 {
                font-size: 0.78rem;
                -webkit-line-clamp: 2;
            }

            .popular-book-card .popular-book-info .popular-count {
                font-size: 0.7rem;
            }

            .popular-book-card .popular-book-image .popular-badge {
                font-size: 0.6rem;
                padding: 2px 8px;
            }

            .info-popup-box {
                padding: 24px 18px;
                max-height: 80vh;
            }

            .info-popup-box h3 {
                font-size: 1.05rem;
            }

            .info-popup-section h4 {
                font-size: 0.88rem;
            }

            .info-popup-section ul li {
                font-size: 0.78rem;
            }
        }

        @media (max-width: 379px) {
            .popular-books-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .popular-book-card:last-child {
                display: none;
            }
        }

        /* ============================================
           BUKU AKAN DATANG SECTION - Horizontal Scroll
           ============================================ */
        .coming-soon-section {
            margin: 1.25rem 0;
        }

        .coming-soon-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .coming-soon-section h3 i {
            color: #8B5CF6;
            font-size: 1.05rem;
        }

        .coming-soon-row {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding-bottom: 8px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--gray-300) transparent;
        }

        .coming-soon-row::-webkit-scrollbar {
            height: 5px;
        }

        .coming-soon-row::-webkit-scrollbar-track {
            background: transparent;
        }

        .coming-soon-row::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 10px;
        }

        .coming-soon-card {
            background: var(--card);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-slow);
            box-shadow: var(--shadow-xs);
            min-width: 150px;
            max-width: 150px;
            flex-shrink: 0;
        }

        .coming-soon-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: #C4B5FD;
        }

        .coming-soon-card .coming-soon-image {
            width: 100%;
            height: 160px;
            overflow: hidden;
            background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .coming-soon-card .coming-soon-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-slow);
            position: absolute;
            top: 0;
            left: 0;
        }

        .coming-soon-card:hover .coming-soon-image img {
            transform: scale(1.05);
        }

        .coming-soon-card .coming-soon-image .no-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #A78BFA;
            gap: 8px;
            z-index: 1;
        }

        .coming-soon-card .coming-soon-image .no-image i {
            font-size: 2rem;
            opacity: 0.6;
        }

        .coming-soon-card .coming-soon-image .no-image span {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .coming-soon-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(139, 92, 246, 0.9);
            color: #fff;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 0.62rem;
            font-weight: 700;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            white-space: nowrap;
        }

        .coming-soon-card .coming-soon-info {
            padding: 10px 12px;
        }

        .coming-soon-card .coming-soon-info h4 {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .coming-soon-card .coming-soon-date {
            font-size: 0.73rem;
            color: #7C3AED;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .coming-soon-card .coming-soon-date i {
            font-size: 0.68rem;
        }

        .coming-soon-card .coming-soon-date.no-date {
            color: var(--gray-400);
            font-weight: 500;
        }

        @media (max-width: 575px) {
            .coming-soon-card {
                min-width: 130px;
                max-width: 130px;
            }

            .coming-soon-card .coming-soon-image {
                height: 140px;
            }

            .coming-soon-card .coming-soon-info h4 {
                font-size: 0.75rem;
            }

            .coming-soon-card .coming-soon-date {
                font-size: 0.68rem;
            }
        }
    </style>
</head>

<body>

    <div class="dashboard-wrapper">

        <!-- ============================================
         NAVBAR - Bar navigasi atas dashboard anggota
         Urutan: Peminjaman, Pengembalian, Bell (icon only), Info icon, Lainnya (dropdown)
         ============================================ -->
        <div class="dashboard-navbar">
            <h2><i class="fas fa-book-bookmark"></i> Dashboard</h2>

            <!-- Tombol hamburger untuk menu mobile -->
            <button class="hamburger-btn" onclick="toggleMobileMenu()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="nav-links" id="navLinks">
                <!-- Link Peminjaman -->
                <a href="anggota/peminjaman_buku.php">Peminjaman</a>
                <!-- Link Pengembalian -->
                <a href="anggota/pengembalian_buku.php">Pengembalian</a>

                <!-- ============================================
                 IKON BELL NOTIFIKASI + BADGE + DROPDOWN
                 Hanya ikon bell, tanpa teks "Notifikasi"
                   - notif-bell: Tombol ikon bel
                   - notif-badge: Angka merah jumlah unread
                   - notif-dropdown: Panel dropdown isinya notifikasi (diisi via AJAX)
                 ============================================ -->
                <div style="position:relative;" id="notifWrapper">
                    <span class="notif-bell" onclick="toggleNotifDropdown(event)">
                        <i class="fas fa-bell"></i>
                        <!-- Badge unread: tampil jika ada notifikasi belum dibaca -->
                        <span class="notif-badge <?= $notif_unread > 0 ? '' : 'hidden' ?>"
                            id="notifBadge"><?= $notif_unread ?></span>
                    </span>

                    <!-- DROPDOWN NOTIFIKASI -->
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown-header">
                            <h4><i class="fas fa-bell"></i> Notifikasi</h4>
                            <div class="notif-header-actions">
                                <!-- Tombol: tandai semua notifikasi sebagai sudah dibaca -->
                                <button class="btn-secondary btn-sm" onclick="tandaiSemuaDibaca()"><i
                                        class="fas fa-check-double"></i> Baca Semua</button>
                                <!-- Tombol: hapus semua notifikasi dari database -->
                                <button class="btn-danger btn-sm" onclick="clearSemuaNotif()"><i
                                        class="fas fa-trash"></i> Hapus Semua</button>
                            </div>
                        </div>
                        <!-- Container list notifikasi - isinya di-render oleh JavaScript via AJAX -->
                        <div class="notif-dropdown-body" id="notifList">
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>Tidak ada notifikasi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol info: popup aturan & denda -->
                <button class="info-icon-btn" onclick="showInfoPopup(event)" title="Aturan & Denda">
                    <i class="fas fa-circle-info"></i>
                </button>

                <!-- Dropdown "Lainnya" untuk link tambahan -->
                <span onclick="toggleMenu()" class="menu-trigger">
                    Lainnya <i class="fas fa-chevron-down"></i>
                </span>

                <div id="menuLainnya" class="dropdown-menu">
                    <a href="anggota/profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="anggota/histori.php"><i class="fas fa-clock-rotate-left"></i> Histori</a>
                    <a href="#" onclick="showLogout()" class="danger"><i class="fas fa-right-from-bracket"></i>
                        Logout</a>
                </div>
            </div>
        </div>

        <!-- ============================================
         MODAL LOGOUT - Konfirmasi sebelum logout
         ============================================ -->
        <div id="modalLogout" class="modal-overlay">
            <div class="modal-box">
                <div class="confirm-icon icon-info"
                    style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;background:var(--primary-50);color:var(--primary);">
                    <i class="fas fa-right-from-bracket"></i>
                </div>
                <p style="font-size:1rem;color:var(--gray-700);margin-bottom:1.5rem;font-weight:500;">Kamu yakin mau
                    logout?</p>
                <div class="modal-actions">
                    <button onclick="logout()">Ya</button>
                    <button class="btn-secondary" onclick="tutup()">Tidak</button>
                </div>
            </div>
        </div>

        <!-- ============================================
         INFO POPUP MODAL - Aturan, Denda, dan Pengumuman
         Style mengikuti pattern modal-overlay/modal-box yang sudah ada
         ============================================ -->
        <div id="infoPopup" class="info-popup-overlay" onclick="if(event.target===this) hideInfoPopup()">
            <div class="info-popup-box">
                <div class="info-popup-icon">
                    <i class="fas fa-circle-info"></i>
                </div>
                <h3>Aturan, Denda & Pengumuman</h3>

                <!-- Section 1: Aturan & Denda -->
                <div class="info-popup-section">
                    <h4><i class="fas fa-clipboard-list section-icon"></i> 1. Aturan & Denda</h4>

                    <div class="sub-section">
                        <div class="sub-title"><i class="fas fa-book sub-icon"></i> Buku Rusak</div>
                        <p style="font-size:0.8rem;color:var(--gray-500);margin-bottom:0.25rem;">
                            Denda dikenakan berdasarkan tingkat kerusakan buku:
                        </p>
                        <ul>
                            <li><strong>Ringan</strong> (lipatan atau sobekan kecil) → <strong>Rp5.000</strong></li>
                            <li><strong>Sedang</strong> (beberapa halaman sobek atau terdapat coretan) →
                                <strong>Rp15.000</strong>
                            </li>
                            <li><strong>Berat</strong> (halaman hilang atau kerusakan parah) → <strong>Rp30.000</strong>
                                atau wajib mengganti buku</li>
                        </ul>
                    </div>

                    <div class="sub-section">
                        <div class="sub-title"><i class="fas fa-book-open sub-icon" style="color:#DC2626;"></i> Buku
                            Hilang</div>
                        <ul>
                            <li>Wajib mengganti buku dengan judul yang sama</li>
                            <li>Jika buku tidak tersedia, maka wajib mengganti dengan uang sesuai harga buku</li>
                        </ul>
                    </div>

                    <div class="sub-section">
                        <div class="sub-title"><i class="fas fa-clock sub-icon" style="color:#F59E0B;"></i>
                            Keterlambatan</div>
                        <ul>
                            <li>Keterlambatan lebih dari 30 hari dikenakan denda sebesar <strong>Rp50.000</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="info-popup-divider"></div>

                <!-- Section 2: Pengumuman -->
                <div class="info-popup-section">
                    <h4><i class="fas fa-bullhorn section-icon"></i> 2. Pengumuman</h4>

                    <div class="sub-section">
                        <div class="sub-title"><i class="fas fa-clock sub-icon"></i> Jam Operasional</div>
                        <ul>
                            <li>Perpustakaan beroperasi pada pukul <strong>08.00 – 15.00</strong></li>
                        </ul>
                    </div>

                    <div class="sub-section">
                        <div class="sub-title"><i class="fas fa-calendar-xmark sub-icon" style="color:#DC2626;"></i>
                            Informasi Penutupan</div>
                        <ul>
                            <li>Pada tanggal berwarna <strong style="color:#DC2626;">merah</strong> (hari libur
                                nasional), perpustakaan tidak beroperasi</li>
                            <li>Pada masa libur siswa, perpustakaan juga tidak beroperasi</li>
                        </ul>
                    </div>
                </div>

                <button class="info-popup-close-btn" onclick="hideInfoPopup()">
                    <i class="fas fa-check"></i> Mengerti
                </button>
            </div>
        </div>

        <!-- ============================================
         TOAST CONTAINER - Popup notifikasi di kanan atas
         Toast muncul otomatis jika ada notifikasi baru (polling 5 detik)
         ============================================ -->
        <div class="notif-toast-container" id="toastContainer"></div>

        <!-- ============================================
 FORM PENCARIAN BUKU
 Dengan datalist untuk riwayat pencarian
 ============================================ -->
        <div class="search-form">
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-magnifying-glass"></i> Cari Buku:</label>
                    <input type="text" name="keyword" placeholder="Cari judul, penulis, penerbit..." list="history"
                        value="<?php echo htmlspecialchars($keyword); ?>">

                    <!-- Datalist: saran pencarian dari history -->
                    <datalist id="history">
                        <?php foreach ($history_for_input as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="search-buttons">
                    <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                    <button type="button" id="resetBtn" class="btn-secondary">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                </div>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        const resetBtn = document.getElementById('resetBtn');

                        if (resetBtn) {
                            resetBtn.addEventListener('click', function () {
                                window.location.href = window.location.pathname + '?reset=1';
                            });
                        }
                    });
                </script>
            </form>
        </div> <?php
        // ============================================
        // QUERY PENCARIAN / DEFAULT
        // Jika mode search aktif, gunakan query pencarian (judul, penulis, penerbit)
        // Jika tidak, tampilkan semua buku dengan sort & filter yang aktif
        // ============================================
        if ($is_search && $keyword != '') {

            $keyword_clean = str_replace('-', ' ', $keyword);

            $query = "SELECT b.*, k.nama_kategori
            FROM buku b
            LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
            WHERE b.status = 'rilis'
            AND (REPLACE(b.judul, '-', ' ') LIKE '%$keyword_clean%'
            OR b.penulis LIKE '%$keyword%'
            OR b.penerbit LIKE '%$keyword%')
            $where_kategori
            ORDER BY b.judul LIKE '$keyword_clean%' DESC, b.judul ASC";

        } else {

            $query = "SELECT b.*, k.nama_kategori
            FROM buku b
            LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
            WHERE b.status = 'rilis'
            $where_kategori
            $order";
        }

        $result = mysqli_query($conn, $query);
        ?>

        <!-- ============================================
         BUKU POPULER - Top 3 buku paling banyak dipinjam
         Ditampilkan di atas filter kategori & urut
         ============================================ -->
        <?php if ($populer_count > 0): ?>
            <div class="popular-books-section">
                <h3><i class="fas fa-fire"></i> Buku Populer</h3>
                <div class="popular-books-row">
                    <?php
                    $rank = 1;
                    while ($pop = mysqli_fetch_assoc($populer_query)) {
                        $gambar_path = "../assets/gambar/" . $pop['gambar_buku'];
                        $has_image = !empty($pop['gambar_buku']) && file_exists("../assets/gambar/" . $pop['gambar_buku']);
                        ?>
                        <div class="popular-book-card">
                            <div class="popular-book-image">
                                <?php if ($has_image): ?>
                                    <img src="<?= $gambar_path ?>" alt="<?php echo htmlspecialchars($pop['judul']); ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-book"></i>
                                        <span>Tidak ada gambar</span>
                                    </div>
                                <?php endif; ?>
                                <span class="popular-badge"><i class="fas fa-trophy"></i> #<?= $rank ?></span>
                            </div>
                            <div class="popular-book-info">
                                <h4><?php echo htmlspecialchars($pop['judul']); ?></h4>
                                <div class="popular-count">
                                    <i class="fas fa-hand-holding-heart"></i>
                                    Dipinjam <?= $pop['total_pinjam'] ?> kali
                                </div>
                            </div>
                        </div>
                        <?php
                        $rank++;
                    }

                    // Jika kurang dari 3, tambahkan placeholder card kosong
                    for ($i = $populer_count; $i < 3; $i++) {
                        ?>
                        <div class="popular-book-card popular-empty">
                            <div class="popular-book-image">
                                <div class="no-image">
                                    <i class="fas fa-book"></i>
                                    <span>Belum ada</span>
                                </div>
                            </div>
                            <div class="popular-book-info">
                                <h4 style="color:var(--gray-400);">-</h4>
                                <div class="popular-count" style="color:var(--gray-400);">
                                    <i class="fas fa-minus-circle"></i> Belum ada data
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================
         BUKU AKAN DATANG - Horizontal scroll carousel
         ============================================ -->
        <?php if ($akan_datang_count > 0): ?>
            <div class="coming-soon-section">
                <h3><i class="fas fa-rocket"></i> Buku Akan Datang</h3>
                <div class="coming-soon-row">
                    <?php while ($cs = mysqli_fetch_assoc($akan_datang_query)):
                        $cs_img = "../assets/gambar/" . $cs['gambar_buku'];
                        $cs_has_img = !empty($cs['gambar_buku']) && file_exists("../assets/gambar/" . $cs['gambar_buku']);
                        $tgl_rilis = !empty($cs['tanggal_rilis']) ? date('d M Y', strtotime($cs['tanggal_rilis'])) : null;
                    ?>
                    <div class="coming-soon-card">
                        <div class="coming-soon-image">
                            <?php if ($cs_has_img): ?>
                                <img src="<?= $cs_img ?>" alt="<?= htmlspecialchars($cs['judul']) ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-book-open"></i>
                                    <span>Coming Soon</span>
                                </div>
                            <?php endif; ?>
                            <span class="coming-soon-badge"><i class="fas fa-clock"></i> Segera</span>
                        </div>
                        <div class="coming-soon-info">
                            <h4><?= htmlspecialchars($cs['judul']) ?></h4>
                            <?php if ($tgl_rilis): ?>
                                <div class="coming-soon-date">
                                    <i class="fas fa-calendar-day"></i>
                                    <?= $tgl_rilis ?>
                                </div>
                            <?php else: ?>
                                <div class="coming-soon-date no-date">
                                    <i class="fas fa-calendar"></i>
                                    Belum ditentukan
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <h3><i class="fas fa-book"></i> Daftar Buku</h3>

        <!-- ============================================
         FILTER KATEGORI & SORTING
         ============================================ -->
        <form method="GET">
            <div class="filter-row">
                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Filter Kategori:</label>
                    <select name="kategori" onchange="this.form.submit()">
                        <option value="">-- Semua Kategori --</option>

                        <?php
                        mysqli_data_seek($kategori, 0);
                        while ($k = mysqli_fetch_assoc($kategori)) { ?>
                            <option value="<?php echo $k['id_kategori']; ?>" <?php if ($kategori_id == $k['id_kategori'])
                                   echo 'selected'; ?>>
                                <?php echo $k['nama_kategori']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-arrow-down-wide-short"></i> Urutkan:</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="">-- Default --</option>
                        <option value="az" <?= $sort == 'az' ? 'selected' : '' ?>>A - Z</option>
                        <option value="za" <?= $sort == 'za' ? 'selected' : '' ?>>Z - A</option>
                        <option value="stok-terbanyak" <?= $sort == 'stok-terbanyak' ? 'selected' : '' ?>>Stok Terbanyak
                        </option>
                        <option value="stok-tersedikit" <?= $sort == 'stok-tersedikit' ? 'selected' : '' ?>>Stok Tersedikit
                        </option>
                        <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Terlama</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- ============================================
         GRID BUKU - Menampilkan daftar buku dalam card
         Setiap card berisi: gambar, judul, penulis, penerbit, tahun, stok, tombol pinjam
         ============================================ -->
        <div class="book-grid">

            <?php
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="book-card">
                        <div class="book-image">
                            <?php
                            $gambar_path = "../assets/gambar/" . $row['gambar_buku'];
                            $has_image = !empty($row['gambar_buku']) && file_exists("../assets/gambar/" . $row['gambar_buku']);
                            ?>
                            <?php if ($has_image): ?>
                                <img src="<?= $gambar_path ?>" alt="<?php echo $row['judul']; ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-book"></i>
                                    <span>Tidak ada gambar</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($row['stok'] > 0): ?>
                                <span class="book-stock-badge"><i class="fas fa-box"></i> <?php echo $row['stok']; ?></span>
                            <?php else: ?>
                                <span class="book-stock-badge out-of-stock"><i class="fas fa-xmark"></i> Habis</span>
                            <?php endif; ?>
                        </div>

                        <div class="book-info">
                            <span class="book-category-tag"><?php echo $row['nama_kategori']; ?></span>
                            <h4><?php echo $row['judul']; ?></h4>
                            <p class="book-author"><i class="fas fa-pen-nib"></i> <?php echo $row['penulis']; ?></p>
                            <p><i class="fas fa-building"></i> <?php echo $row['penerbit']; ?></p>
                            <p><i class="fas fa-calendar"></i> <?php echo $row['tahun_terbit']; ?></p>
                        </div>

                        <div class="book-action">
                            <?php if ($row['stok'] > 0): ?>
                                <!-- Tombol Pinjam: redirect ke halaman peminjaman dengan id_buku -->
                                <form method="GET" action="../transaksi/peminjaman.php">
                                    <input type="hidden" name="id_buku" value="<?php echo $row['id_buku']; ?>">
                                    <button type="submit"><i class="fas fa-hand-holding"></i> Pinjam</button>
                                </form>
                            <?php else: ?>
                                <!-- Jika stok habis, tombol pinjam disabled -->
                                <button class="btn-secondary" disabled style="opacity:0.6;cursor:not-allowed;"><i
                                        class="fas fa-ban"></i> Stok Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='empty-state'><i class='fas fa-book-open'></i><p>Data buku tidak ada.</p></div>";
            }
            ?>

        </div>

    </div>

    <script>
        // ============================================
        // FUNGSI NAVIGASI & MODAL
        // ============================================

        /**
         * showLogout() - Menampilkan modal konfirmasi logout
         */
        function showLogout() {
            document.getElementById("modalLogout").style.display = "flex";
        }

        /**
         * tutup() - Menyembunyikan modal logout
         */
        function tutup() {
            document.getElementById("modalLogout").style.display = "none";
        }

        /**
         * logout() - Redirect ke halaman login
         */
        function logout() {
            window.location.href = "../login/login.php";
        }

        /**
         * toggleMenu() - Buka/tutup dropdown menu "Lainnya"
         * Dropdown berisi: Profil, Histori, Logout
         */
        function toggleMenu() {
            var menu = document.getElementById("menuLainnya");
            if (!menu) return;
            menu.style.display = (menu.style.display === "block") ? "none" : "block";
        }

        /**
         * toggleMobileMenu() - Buka/tutup menu navigasi di tampilan mobile
         */
        function toggleMobileMenu() {
            var nav = document.getElementById("navLinks");
            if (nav) nav.classList.toggle("active");
        }

        /**
         * showInfoPopup(e) - Menampilkan popup info aturan & denda
         * @param {Event} e - Event click (untuk stopPropagation)
         */
        function showInfoPopup(e) {
            e.stopPropagation();
            document.getElementById("infoPopup").classList.add("active");
        }

        /**
         * hideInfoPopup() - Menyembunyikan popup info aturan & denda
         */
        function hideInfoPopup() {
            document.getElementById("infoPopup").classList.remove("active");
        }

        /**
         * Inisialisasi: sembunyikan dropdown "Lainnya" saat halaman dimuat
         */
        window.onload = function () {
            var menu = document.getElementById("menuLainnya");
            if (menu) menu.style.display = "none";
        };

        /**
         * Event listener global: Tutup dropdown "Lainnya", mobile menu, dan info popup saat klik di luar
         */
        document.addEventListener('click', function (event) {
            // Tutup dropdown "Lainnya"
            var menu = document.getElementById("menuLainnya");
            var trigger = document.querySelector('.menu-trigger');
            if (!menu || !trigger) return;
            if (menu.contains(event.target) || trigger.contains(event.target)) return;
            menu.style.display = "none";

            // Tutup mobile menu
            var nav = document.getElementById("navLinks");
            var btn = document.querySelector('.hamburger-btn');
            if (!nav || !btn) return;
            if (nav.contains(event.target) || btn.contains(event.target)) return;
            nav.classList.remove("active");
        });

        /**
         * Tutup info popup dengan tombol Escape
         */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideInfoPopup();
                tutup();
            }
        });

        // ============================================
        // SISTEM NOTIFIKASI DROPDOWN
        // Bagian ini mengatur dropdown notifikasi di navbar anggota
        // Fungsi-fungsi serupa dengan dashboard_admin.php, tetapi TANPA tombol validasi
        // (karena anggota tidak bisa menyetujui/menolak peminjaman)
        // ============================================

        /**
         * shownToastIds - Array untuk tracking toast yang sudah ditampilkan
         * Format: ["1", "5", "12", ...]
         */
        var shownToastIds = [];

        /**
         * toggleNotifDropdown(e) - Buka/tutup dropdown notifikasi saat ikon bell diklik
         * @param {Event} e - Event click
         * 
         * Alur:
         * 1. e.stopPropagation() - Cegah event bubbling
         * 2. Toggle class "active" pada dropdown
         * 3. Jika dropdown dibuka, fetch data notifikasi terbaru
         */
        function toggleNotifDropdown(e) {
            e.stopPropagation();
            var dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) {
                loadNotifikasi();
            }
        }

        /**
         * Event listener: Tutup dropdown saat klik di luar area notifWrapper
         */
        document.addEventListener('click', function (e) {
            var wrapper = document.getElementById('notifWrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('active');
            }
        });

        /**
         * loadNotifikasi() - Fetch data notifikasi dari server via AJAX
         * Endpoint: GET ../api/notifikasi.php?limit=15
         * 
         * Alur:
         * 1. Fetch ke API notifikasi
         * 2. Jika sukses: update badge + render daftar notifikasi ke dropdown
         * 3. Jika gagal: log error ke console
         * 
         * Dipanggil oleh:
         * - toggleNotifDropdown() saat dropdown dibuka
         * - Pada saat halaman pertama kali dimuat
         */
        function loadNotifikasi() {
            fetch('../api/notifikasi.php?limit=15')
                .then(res => {
                    if (!res.ok) throw new Error('HTTP error ' + res.status);
                    return res.json();
                })
                .then(data => {
                    console.log('loadNotifikasi response:', data);
                    if (data.success) {
                        updateBadge(data.unread);
                        renderNotifList(data.data);
                    } else {
                        console.error('API error:', data.error);
                    }
                })
                .catch(err => console.error('Error loading notifikasi:', err));
        }

        /**
         * updateBadge(count) - Update angka pada badge notifikasi (titik merah di ikon bell)
         * @param {number} count - Jumlah notifikasi belum dibaca
         * 
         * - Jika count > 0: tampilkan badge (max "99+")
         * - Jika count = 0: sembunyikan badge
         */
        function updateBadge(count) {
            var badge = document.getElementById('notifBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        /**
         * renderNotifList(items) - Render daftar notifikasi ke dalam dropdown #notifList
         * @param {Array} items - Array objek notifikasi dari API
         * 
         * Mirip dengan versi admin, TETAPI TANPA tombol aksi validasi (Setujui/Tolak)
         * karena anggota hanya bisa melihat notifikasi, bukan memvalidasi.
         * 
         * Setiap notifikasi ditampilkan dengan:
         *   - Ikon warna sesuai tipe (permohonan, disetujui, ditolak, info)
         *   - Teks pesan
         *   - Waktu relatif (Baru saja, 5 menit lalu, dll)
         *   - Style berbeda jika belum dibaca (background unread)
         *   - Tombol hapus individual (X)
         */
        function renderNotifList(items) {
            var container = document.getElementById('notifList');

            // Jika tidak ada notifikasi, tampilkan pesan kosong
            if (!items || items.length === 0) {
                container.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><p>Tidak ada notifikasi</p></div>';
                return;
            }

            var html = '';
            items.forEach(function (n) {
                // Tentukan ikon dan warna berdasarkan tipe notifikasi
                var iconClass = 'tipe-info';
                var icon = 'fas fa-bell';
                if (n.tipe === 'permohonan_pinjam') { iconClass = 'tipe-permohonan'; icon = 'fas fa-hand-holding'; }
                else if (n.tipe === 'permohonan_kembali') { iconClass = 'tipe-permohonan'; icon = 'fas fa-rotate-left'; }
                else if (n.tipe === 'disetujui_pinjam') { iconClass = 'tipe-disetujui'; icon = 'fas fa-check-circle'; }
                else if (n.tipe === 'disetujui_kembali') { iconClass = 'tipe-disetujui'; icon = 'fas fa-check-circle'; }
                else if (n.tipe === 'ditolak_pinjam') { iconClass = 'tipe-ditolak'; icon = 'fas fa-times-circle'; }
                else if (n.tipe === 'ditolak_kembali') { iconClass = 'tipe-ditolak'; icon = 'fas fa-times-circle'; }
                else if (n.tipe === 'info_pinjam' || n.tipe === 'info_kembali') { iconClass = 'tipe-info'; icon = 'fas fa-paper-plane'; }
                else if (n.tipe === 'info_admin') { iconClass = 'tipe-info-admin'; icon = 'fas fa-shield-halved'; }

                // Style unread (background berbeda) jika belum dibaca
                var unreadClass = n.dibaca == 0 ? ' unread' : '';
                var time = formatTime(n.tanggal);

                // Bangun HTML item notifikasi (TANPA tombol validasi, beda dengan admin)
                html += '<div class="notif-item' + unreadClass + '" data-id="' + n.id_notifikasi + '">';
                html += '  <div class="notif-item-icon ' + iconClass + '"><i class="' + icon + '"></i></div>';
                html += '  <div class="notif-item-content">';
                html += '    <p>' + escapeHtml(n.pesan) + '</p>';
                html += '    <span class="notif-time">' + time + '</span>';
                html += '  </div>';
                // Tombol hapus individual per notifikasi
                html += '  <button class="notif-item-delete" onclick="event.stopPropagation(); hapusNotif(' + n.id_notifikasi + ')" title="Hapus"><i class="fas fa-trash-alt"></i></button>';
                html += '</div>';
            });

            container.innerHTML = html;
        }

        /**
         * formatTime(dateStr) - Format waktu relatif untuk notifikasi
         * @param {string} dateStr - Tanggal dari database
         * @returns {string} Waktu relatif (Baru saja, 5 menit lalu, 2 jam lalu, dll)
         */
        function formatTime(dateStr) {
            var d = new Date(dateStr);
            var now = new Date();
            var diff = Math.floor((now - d) / 1000);
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        /**
         * escapeHtml(text) - Sanitasi teks dari XSS
         * @param {string} text - Teks mentah
         * @returns {string} Teks yang sudah di-escape
         */
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * hapusNotif(id) - Hapus satu notifikasi dari database
         * @param {number} id - ID notifikasi yang akan dihapus
         * 
         * POST ke API dengan action 'hapus', lalu refresh dropdown
         */
        function hapusNotif(id) {
            var formData = new FormData();
            formData.append('action', 'hapus');
            formData.append('id_notifikasi', id);

            fetch('../api/notifikasi.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadNotifikasi(); // Refresh dropdown
                    }
                });
        }

        /**
         * tandaiSemuaDibaca() - Tandai semua notifikasi anggota sebagai sudah dibaca
         * 
         * POST ke API dengan action 'baca_semua'
         * Setelah itu badge hilang dan style unread dihapus
         */
        function tandaiSemuaDibaca() {
            var formData = new FormData();
            formData.append('action', 'baca_semua');

            fetch('../api/notifikasi.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadNotifikasi();
                    }
                });
        }

        /**
         * clearSemuaNotif() - Hapus semua notifikasi anggota dari database
         * 
         * POST ke API dengan action 'clear_all'
         * Dropdown akan menampilkan "Tidak ada notifikasi"
         */
        function clearSemuaNotif() {
            var formData = new FormData();
            formData.append('action', 'clear_all');

            fetch('../api/notifikasi.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadNotifikasi();
                    }
                });
        }

        // ============================================
        // AUTO POLLING NOTIFIKASI (setiap 5 detik)
        // Mengecek notifikasi baru secara berkala:
        //   1. Update badge
        //   2. Render ulang isi dropdown
        //   3. Toast popup untuk notifikasi baru
        // ============================================

        /**
         * checkNewNotifikasi() - Polling otomatis untuk cek notifikasi baru
         * Dipanggil setiap 5 detik via setInterval
         * 
         * Alur:
         * 1. Fetch ke API notifikasi
         * 2. Update badge angka unread
         * 3. Render ulang dropdown agar selalu sinkron
         * 4. Tampilkan toast untuk notifikasi yang belum pernah muncul di toast
         */
        function checkNewNotifikasi() {
            fetch('../api/notifikasi.php?limit=15')
                .then(res => {
                    if (!res.ok) throw new Error('HTTP error ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        updateBadge(data.unread);
                        // SELALU render ulang isi dropdown agar selalu sinkron
                        renderNotifList(data.data);
                        // Toast untuk notifikasi baru saja
                        data.data.forEach(function (n) {
                            if (n.dibaca == 0 && !shownToastIds.includes(String(n.id_notifikasi))) {
                                shownToastIds.push(String(n.id_notifikasi));
                                showNotifToast(n);
                            }
                        });
                    }
                })
                .catch(err => console.error('Error polling:', err));
        }

        /**
         * showNotifToast(n) - Tampilkan toast popup notifikasi di pojok kanan atas
         * @param {Object} n - Objek notifikasi {pesan, tipe, id_notifikasi}
         * 
         * Toast popup muncul otomatis saat ada notifikasi baru (via polling).
         * Warna toast sesuai tipe: kuning (permohonan), hijau (disetujui), merah (ditolak)
         * Auto-dismiss setelah 5 detik.
         */
        function showNotifToast(n) {
            var container = document.getElementById('toastContainer');
            if (!container) return;

            // Tentukan ikon dan warna toast
            var iconClass = 'tipe-info';
            var icon = 'fas fa-bell';
            var toastClass = '';
            if (n.tipe === 'permohonan_pinjam') { iconClass = 'tipe-permohonan'; icon = 'fas fa-hand-holding'; toastClass = 'toast-warning'; }
            else if (n.tipe === 'permohonan_kembali') { iconClass = 'tipe-permohonan'; icon = 'fas fa-rotate-left'; toastClass = 'toast-warning'; }
            else if (n.tipe === 'disetujui_pinjam') { iconClass = 'tipe-disetujui'; icon = 'fas fa-check-circle'; toastClass = 'toast-success'; }
            else if (n.tipe === 'disetujui_kembali') { iconClass = 'tipe-disetujui'; icon = 'fas fa-check-circle'; toastClass = 'toast-success'; }
            else if (n.tipe === 'ditolak_pinjam') { iconClass = 'tipe-ditolak'; icon = 'fas fa-times-circle'; toastClass = 'toast-danger'; }
            else if (n.tipe === 'ditolak_kembali') { iconClass = 'tipe-ditolak'; icon = 'fas fa-times-circle'; toastClass = 'toast-danger'; }
            else if (n.tipe === 'info_pinjam' || n.tipe === 'info_kembali') { iconClass = 'tipe-info'; icon = 'fas fa-paper-plane'; toastClass = 'toast-success'; }
            else if (n.tipe === 'info_admin') { iconClass = 'tipe-info-admin'; icon = 'fas fa-shield-halved'; toastClass = ''; }

            // Buat elemen toast
            var toast = document.createElement('div');
            toast.className = 'notif-toast ' + toastClass;
            toast.dataset.id = n.id_notifikasi;
            toast.innerHTML = '<div class="notif-toast-icon ' + iconClass + '"><i class="' + icon + '"></i></div>' +
                '<div class="notif-toast-content"><p>' + escapeHtml(n.pesan) + '</p></div>' +
                '<button class="notif-toast-close" onclick="closeToast(this.parentElement)"><i class="fas fa-times"></i></button>';

            container.appendChild(toast);

            // Auto-dismiss setelah 5 detik
            setTimeout(function () {
                if (toast.parentElement) {
                    toast.classList.add('toast-hide');
                    setTimeout(function () { toast.remove(); }, 500);
                }
            }, 5000);
        }

        /**
         * closeToast(el) - Tutup toast manual (saat user klik X)
         * @param {HTMLElement} el - Elemen toast yang ditutup
         */
        function closeToast(el) {
            el.classList.add('toast-hide');
            setTimeout(function () { el.remove(); }, 500);
        }

        // ============================================
        // INISIALISASI SAAT HALAMAN PERTAMA KALI DIMUAT
        // ============================================

        // Load notifikasi saat halaman dibuka → isi dropdown + update badge
        loadNotifikasi();

        // Polling setiap 5 detik → update dropdown + badge + toast untuk notifikasi baru
        setInterval(checkNewNotifikasi, 5000);

        // ============================================
        // FLASH SUCCESS TOAST (dari session PHP)
        // Menampilkan toast sukses satu kali setelah peminjaman/pengembalian berhasil
        // Misalnya: "Permohonan peminjaman berhasil dikirim! Menunggu persetujuan admin."
        // Data berasal dari $_SESSION['flash_success'] yang sudah di-set oleh proses_peminjaman.php
        // ============================================
        <?php if ($flash_success): ?>
            setTimeout(function () {
                showNotifToast({ pesan: '<?= addslashes($flash_success) ?>', tipe: 'disetujui_pinjam' });
            }, 500);
        <?php endif; ?>
    </script>

    <?php include '../footer.php'; ?>

</body>

</html>