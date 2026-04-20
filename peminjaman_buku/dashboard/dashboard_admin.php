<?php
session_start();

// ============================================
// KEAMANAN: Cek apakah user sudah login dan rolenya admin
// Jika belum login atau bukan admin, redirect ke halaman login
// ============================================
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ============================================
// KONEKSI DATABASE
// File koneksi.php berisi konfigurasi ke MySQL (host, user, pass, db)
// ============================================
require '../koneksi.php';

// ============================================
// HITUNG NOTIFIKASI BELUM DIBACA UNTUK BADGE
// Query mengambil jumlah notifikasi unread admin untuk ditampilkan
// sebagai angka merah di ikon bel (badge) navbar
// ============================================
$id_admin = $_SESSION['id_user'];
$notif_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifikasi WHERE id_admin = '$id_admin' AND dibaca = 0");
$notif_unread = mysqli_fetch_assoc($notif_q)['total'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Perpustakaan</title>
    <!-- CSS utama, dashboard, dan notifikasi -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/notifikasi.css">
    <!-- Font Awesome untuk ikon (bell, check, dll) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="dashboard-wrapper">
        <!-- ============================================
             NAVBAR - Bar navigasi atas dashboard admin
             Berisi: Judul, Hamburger menu (mobile), Notifikasi bell, Logout
             ============================================ -->
        <div class="dashboard-navbar">
            <h2><i class="fas fa-shield-halved"></i> Dashboard Admin</h2>

            <!-- Tombol hamburger untuk menu mobile -->
            <button class="hamburger-btn" onclick="toggleMobileMenu()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="nav-links" id="navLinks">
                <!-- ============================================
                     IKON BELL NOTIFIKASI + BADGE + DROPDOWN
                     Struktur:
                       - notif-bell: Tombol ikon bel yang bisa diklik
                       - notif-badge: Angka merah jumlah notifikasi unread
                       - notif-dropdown: Panel dropdown yang muncul saat bell diklik
                         - notif-dropdown-header: Header dengan tombol "Baca Semua" & "Hapus Semua"
                         - notif-dropdown-body (#notifList): Container daftar notifikasi (diisi via AJAX)
                     ============================================ -->
                <div style="position:relative;" id="notifWrapper">
                    <span class="notif-bell" onclick="toggleNotifDropdown(event)">
                        <i class="fas fa-bell"></i>
                        <span class="notif-bell-text">Notifikasi</span>
                        <!-- Badge unread: tampil jika $notif_unread > 0, sembunyikan jika 0 -->
                        <span class="notif-badge <?= $notif_unread > 0 ? '' : 'hidden' ?>" id="notifBadge"><?= $notif_unread ?></span>
                    </span>

                    <!-- DROPDOWN NOTIFIKASI -->
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown-header">
                            <h4><i class="fas fa-bell"></i> Notifikasi</h4>
                            <div class="notif-header-actions">
                                <!-- Tombol: tandai semua notifikasi sebagai sudah dibaca -->
                                <button class="btn-secondary btn-sm" onclick="tandaiSemuaDibaca()"><i class="fas fa-check-double"></i> Baca Semua</button>
                                <!-- Tombol: hapus semua notifikasi dari database -->
                                <button class="btn-danger btn-sm" onclick="clearSemuaNotif()"><i class="fas fa-trash"></i> Hapus Semua</button>
                            </div>
                        </div>
                        <!-- Container list notifikasi - isinya di-render oleh JavaScript via AJAX -->
                        <div class="notif-dropdown-body" id="notifList">
                            <!-- Diisi via AJAX -->
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>Tidak ada notifikasi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Logout -->
                <a href="#" class="logout-link" onclick="showLogout(); return false;">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <!-- ============================================
             MODAL LOGOUT - Konfirmasi sebelum logout
             Muncul saat tombol logout diklik (bukan alert native)
             ============================================ -->
        <div id="modalLogout" class="modal-overlay">
            <div class="modal-box">
                <div style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;background:var(--danger-light);color:#DC2626;">
                    <i class="fas fa-right-from-bracket"></i>
                </div>
                <p style="font-size:1rem;color:var(--gray-700);margin-bottom:1.5rem;font-weight:500;">Kamu yakin mau logout?</p>
                <div class="modal-actions">
                    <button onclick="logout()">Ya</button>
                    <button class="btn-secondary" onclick="tutup()">Tidak</button>
                </div>
            </div>
        </div>

        <!-- ============================================
             TOAST CONTAINER - Popup notifikasi di kanan atas
             Toast muncul otomatis setiap 5 detik jika ada notifikasi baru
             ============================================ -->
        <div class="notif-toast-container" id="toastContainer"></div>

        <!-- ============================================
             QUICK CONFIRM MODAL - untuk aksi validasi dari dropdown notif
             Menggantikan confirm() dan prompt() bawaan browser
             ============================================ -->
        <div id="quickConfirmOverlay" class="confirm-overlay" onclick="if(event.target===this){this.classList.remove('active');document.getElementById('quickConfirmAlasanWrap').style.display='none';}">
            <div class="confirm-box">
                <div id="quickConfirmIcon" class="confirm-icon" style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;background:#D1FAE5;color:#059669;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div id="quickConfirmTitle" class="confirm-title">Konfirmasi</div>
                <div id="quickConfirmMessage" class="confirm-message">Yakin ingin melanjutkan?</div>
                <div id="quickConfirmAlasanWrap" style="display:none;margin-bottom:1rem;">
                    <input type="text" id="quickConfirmAlasan" placeholder="Alasan penolakan (opsional)" style="width:100%;max-width:100%;padding:8px 12px;border:2px solid var(--gray-200);border-radius:8px;font-size:0.85rem;font-family:var(--font-family);">
                </div>
                <div class="confirm-actions">
                    <button id="quickConfirmYes" class="btn-confirm-yes" style="min-width:120px;">Ya</button>
                    <button class="btn-confirm-no" onclick="this.closest('.confirm-overlay').classList.remove('active');document.getElementById('quickConfirmAlasanWrap').style.display='none';">Batal</button>
                </div>
            </div>
        </div>

        <!-- ============================================
             FITUR DASHBOARD - Menu utama admin
             3 fitur: Kelola Buku, Kelola Anggota, Transaksi
             ============================================ -->
        <div class="dashboard-features">

            <!-- KOTAK 1: Kelola Data Buku -->
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-book-open" style="color: var(--primary);"></i></span>
                <div class="feature-text">
                    <h3>Kelola Data Buku</h3>
                    <p>Tambah, edit, dan hapus buku</p>
                    <a href="admin/buku.php">
                        <button><i class="fas fa-arrow-right"></i> Buka</button>
                    </a>
                </div>
            </div>

            <!-- KOTAK 2: Kelola Anggota -->
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-users" style="color: var(--success);"></i></span>
                <div class="feature-text">
                    <h3>Kelola Anggota</h3>
                    <p>Manajemen data anggota</p>
                    <a href="admin/anggota.php">
                        <button><i class="fas fa-arrow-right"></i> Buka</button>
                    </a>
                </div>
            </div>

            <!-- KOTAK 3: Transaksi (Peminjaman & Pengembalian) -->
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-arrow-right-arrow-left" style="color: var(--warning);"></i></span>
                <div class="feature-text">
                    <h3>Transaksi</h3>
                    <p>Peminjaman & pengembalian buku</p>
                    <a href="admin/transaksi.php">
                        <button><i class="fas fa-arrow-right"></i> Buka</button>
                    </a>
                </div>
            </div>

            <!-- KOTAK 4: Laporan -->
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-chart-bar" style="color: var(--accent);"></i></span>
                <div class="feature-text">
                    <h3>Laporan</h3>
                    <p>Cetak & ekspor laporan data</p>
                    <a href="admin/laporan.php">
                        <button><i class="fas fa-arrow-right"></i> Buka</button>
                    </a>
                </div>
            </div>

        </div>
    </div>

    <script>
    // ============================================
    // FUNGSI NAVIGASI & MODAL
    // ============================================

    /**
     * showLogout() - Menampilkan modal konfirmasi logout
     * Mengubah display modal dari none menjadi flex agar terlihat
     */
    function showLogout() {
        document.getElementById("modalLogout").style.display = "flex";
    }

    /**
     * tutup() - Menyembunyikan modal logout
     * Mengubah display modal kembali menjadi none
     */
    function tutup() {
        document.getElementById("modalLogout").style.display = "none";
    }

    /**
     * logout() - Redirect ke halaman login
     * Dipanggil saat user klik "Ya" pada modal logout
     */
    function logout() {
        window.location.href = "../login/login.php";
    }

    /**
     * toggleMobileMenu() - Buka/tutup menu navigasi di tampilan mobile
     * Menambah/menghapus class "active" pada nav-links
     */
    function toggleMobileMenu() {
        var nav = document.getElementById("navLinks");
        if (nav) nav.classList.toggle("active");
    }

    /**
     * Event listener global: Tutup mobile menu saat klik di luar area
     * Mendeteksi klik di luar navLinks dan hamburger button
     */
    document.addEventListener('click', function(e) {
        var nav = document.getElementById("navLinks");
        var btn = document.querySelector('.hamburger-btn');
        if (!nav || !btn) return;
        if (nav.contains(e.target) || btn.contains(e.target)) return;
        nav.classList.remove("active");
    });

    // ============================================
    // SISTEM NOTIFIKASI DROPDOWN
    // Bagian ini mengatur tampilan dropdown notifikasi
    // di navbar, termasuk render list, badge, dll.
    // ============================================

    // Variabel global untuk tracking ID notifikasi terakhir (tidak dipakai saat ini, cadangan)
    var lastNotifId = 0;

    /**
     * shownToastIds - Array untuk melacak ID notifikasi yang sudah ditampilkan
     * sebagai toast popup. Agar satu notifikasi tidak muncul toast berkali-kali.
     * Format: ["1", "5", "12", ...]
     */
    var shownToastIds = [];

    /**
     * toggleNotifDropdown(e) - Buka/tutup dropdown notifikasi saat ikon bell diklik
     * @param {Event} e - Event click dari tombol bell
     * 
     * Alur:
     * 1. e.stopPropagation() - Mencegah event bubbling ke document
     * 2. Toggle class "active" pada dropdown (muncul/hilang)
     * 3. Jika dropdown dibuka, panggil loadNotifikasi() untuk fetch data terbaru
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
     * Agar dropdown tidak menutupi konten lain saat user klik sembarangan
     */
    document.addEventListener('click', function(e) {
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
     * 1. Fetch ke API notifikasi dengan limit 15 item
     * 2. Parse response JSON
     * 3. Jika sukses:
     *    - updateBadge(data.unread) → Update angka badge merah
     *    - renderNotifList(data.data) → Render daftar notifikasi ke dropdown
     * 4. Jika gagal, log error ke console
     * 
     * Dipanggil oleh:
     * - toggleNotifDropdown() saat dropdown dibuka
     * - Pada saat halaman pertama kali dimuat (di bawah script)
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
     * Alur:
     * - Jika count > 0: tampilkan badge dengan angka (max "99+")
     * - Jika count = 0: sembunyikan badge dengan class "hidden"
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
     * @param {Array} items - Array objek notifikasi dari API response
     * 
     * Setiap notifikasi memiliki properti:
     *   - id_notifikasi: ID notifikasi
     *   - tipe: Jenis notifikasi (permohonan_pinjam, disetujui_pinjam, dll)
     *   - pesan: Teks pesan notifikasi
     *   - tanggal: Waktu notifikasi dibuat
     *   - dibaca: 0 (belum dibaca) atau 1 (sudah dibaca)
     *   - id_peminjaman: ID peminjaman terkait (untuk tombol validasi)
     * 
     * Tipe notifikasi dan ikon yang sesuai:
     *   - permohonan_pinjam  → 🤚 fa-hand-holding (warna permohonan) + tombol Setujui/Tolak
     *   - permohonan_kembali → 🔄 fa-rotate-left (warna permohonan) + tombol Setujui/Tolak
     *   - disetujui_pinjam   → ✅ fa-check-circle (warna hijau)
     *   - disetujui_kembali  → ✅ fa-check-circle (warna hijau)
     *   - ditolak_pinjam     → ❌ fa-times-circle (warna merah)
     *   - ditolak_kembali    → ❌ fa-times-circle (warna merah)
     *   - info_pinjam        → 📤 fa-paper-plane (warna info)
     *   - info_kembali       → 📤 fa-paper-plane (warna info)
     *   - info_admin         → 🛡️ fa-shield-halved (warna info admin)
     * 
     * Spesial untuk admin:
     *   - Notifikasi tipe "permohonan_pinjam" & "permohonan_kembali" 
     *     memiliki tombol aksi [Setujui] dan [Tolak] untuk validasi langsung dari dropdown
     */
    function renderNotifList(items) {
        var container = document.getElementById('notifList');

        // Jika tidak ada notifikasi, tampilkan pesan kosong
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><p>Tidak ada notifikasi</p></div>';
            return;
        }

        var html = '';
        items.forEach(function(n) {
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

            // Tandai style unread (background berbeda) jika belum dibaca
            var unreadClass = n.dibaca == 0 ? ' unread' : '';
            var time = formatTime(n.tanggal);

            // Tombol aksi validasi (hanya untuk admin, hanya tipe permohonan)
            var actionHtml = '';
            if (n.tipe === 'permohonan_pinjam') {
                actionHtml = '<div class="notif-item-actions">' +
                    '<button class="notif-btn-acc" onclick="event.stopPropagation(); validasiSetujui(' + n.id_peminjaman + ', ' + n.id_notifikasi + ', \'pinjam\')" title="Setujui"><i class="fas fa-check"></i> Setujui</button>' +
                    '<button class="notif-btn-tolak" onclick="event.stopPropagation(); validasiTolak(' + n.id_peminjaman + ', ' + n.id_notifikasi + ', \'pinjam\')" title="Tolak"><i class="fas fa-times"></i> Tolak</button>' +
                    '</div>';
            } else if (n.tipe === 'permohonan_kembali') {
                actionHtml = '<div class="notif-item-actions">' +
                    '<button class="notif-btn-acc" onclick="event.stopPropagation(); validasiSetujui(' + n.id_peminjaman + ', ' + n.id_notifikasi + ', \'kembali\')" title="Setujui"><i class="fas fa-check"></i> Setujui</button>' +
                    '<button class="notif-btn-tolak" onclick="event.stopPropagation(); validasiTolak(' + n.id_peminjaman + ', ' + n.id_notifikasi + ', \'kembali\')" title="Tolak"><i class="fas fa-times"></i> Tolak</button>' +
                    '</div>';
            }

            // Bangun HTML untuk setiap item notifikasi
            html += '<div class="notif-item' + unreadClass + '" data-id="' + n.id_notifikasi + '">';
            html += '  <div class="notif-item-icon ' + iconClass + '"><i class="' + icon + '"></i></div>';
            html += '  <div class="notif-item-content">';
            html += '    <p>' + escapeHtml(n.pesan) + '</p>';
            html += '    <span class="notif-time">' + time + '</span>';
            html += actionHtml;
            html += '  </div>';
            // Tombol hapus individual per notifikasi
            html += '  <button class="notif-item-delete" onclick="event.stopPropagation(); hapusNotif(' + n.id_notifikasi + ')" title="Hapus"><i class="fas fa-trash-alt"></i></button>';
            html += '</div>';
        });

        container.innerHTML = html;
    }

    /**
     * formatTime(dateStr) - Format waktu relatif untuk notifikasi
     * @param {string} dateStr - Tanggal dari database (format YYYY-MM-DD HH:MM:SS)
     * @returns {string} Waktu relatif dalam Bahasa Indonesia
     * 
     * Contoh output:
     *   - "Baru saja" (< 60 detik)
     *   - "5 menit lalu" (< 1 jam)
     *   - "2 jam lalu" (< 24 jam)
     *   - "3 hari lalu" (< 7 hari)
     *   - "14 Jan 2025" (>= 7 hari, format tanggal lengkap)
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
     * escapeHtml(text) - Sanitasi teks agar aman dari XSS attack
     * @param {string} text - Teks mentah dari database
     * @returns {string} Teks yang sudah di-escape (tag HTML berubah jadi entity)
     * 
     * Contoh: "<script>" → "&lt;script&gt;"
     * Penting karena pesan notifikasi berasal dari input user (nama, judul buku)
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * hapusNotif(id) - Hapus satu notifikasi berdasarkan ID
     * @param {number} id - ID notifikasi yang akan dihapus
     * 
     * Mengirim POST request ke API dengan action 'hapus'
     * Jika berhasil, memanggil loadNotifikasi() untuk refresh dropdown
     */
    function hapusNotif(id) {
        var formData = new FormData();
        formData.append('action', 'hapus');
        formData.append('id_notifikasi', id);

        fetch('../api/notifikasi.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadNotifikasi(); // Refresh dropdown setelah hapus
                }
            });
    }

    /**
     * tandaiSemuaDibaca() - Tandai semua notifikasi sebagai sudah dibaca
     * 
     * Mengirim POST request ke API dengan action 'baca_semua'
     * Semua notifikasi admin (dibaca=0) akan diupdate jadi dibaca=1
     * Setelah itu, badge akan hilang dan style unread dihilangkan
     */
    function tandaiSemuaDibaca() {
        var formData = new FormData();
        formData.append('action', 'baca_semua');

        fetch('../api/notifikasi.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadNotifikasi(); // Refresh dropdown
                }
            });
    }

    /**
     * clearSemuaNotif() - Hapus semua notifikasi milik admin dari database
     * 
     * Mengirim POST request ke API dengan action 'clear_all'
     * SEMUA notifikasi akan di-DELETE dari tabel notifikasi
     * Setelah itu, dropdown akan menampilkan "Tidak ada notifikasi"
     */
    function clearSemuaNotif() {
        var formData = new FormData();
        formData.append('action', 'clear_all');

        fetch('../api/notifikasi.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadNotifikasi(); // Refresh dropdown
                }
            });
    }

    // ============================================
    // VALIDASI DARI DROPDOWN NOTIFIKASI (ADMIN ONLY)
    // Admin bisa langsung menyetujui/menolak peminjaman/pengembalian
    // dari dalam dropdown notifikasi tanpa harus buka halaman transaksi
    // ============================================

    /**
     * validasiSetujui(idPeminjaman, idNotif, tipe) - Setujui permohonan dari dropdown
     * @param {number} idPeminjaman - ID peminjaman yang akan disetujui
     * @param {number} idNotif - ID notifikasi (tidak dipakai di API, cadangan)
     * @param {string} tipe - Jenis: 'pinjam' atau 'kembali'
     * 
     * Alur:
     * 1. Tampilkan konfirmasi ke admin
     * 2. Kirim POST ke API dengan action 'validasi_setujui_pinjam' atau 'validasi_setujui_kembali'
     * 3. API akan: update status peminjaman, update stok buku, buat notifikasi ke anggota
     * 4. Tampilkan toast sukses dan refresh dropdown
     */
    function validasiSetujui(idPeminjaman, idNotif, tipe) {
        var actionName = tipe === 'kembali' ? 'validasi_setujui_kembali' : 'validasi_setujui_pinjam';
        var confirmMsg = tipe === 'kembali' ? 'Setujui Pengembalian?' : 'Setujui Peminjaman?';
        var confirmDetail = tipe === 'kembali' ? 'Yakin ingin menyetujui pengembalian buku ini?' : 'Yakin ingin menyetujui permohonan peminjaman ini?';
        var confirmIcon = tipe === 'kembali' ? 'fa-rotate-left' : 'fa-hand-holding';

        document.getElementById('quickConfirmTitle').textContent = confirmMsg;
        document.getElementById('quickConfirmMessage').textContent = confirmDetail;
        document.getElementById('quickConfirmIcon').innerHTML = '<i class="fas ' + confirmIcon + '"></i>';
        document.getElementById('quickConfirmIcon').style.background = 'linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%)';
        document.getElementById('quickConfirmIcon').style.color = '#059669';
        document.getElementById('quickConfirmYes').style.background = 'linear-gradient(135deg, #10B981 0%, #34D399 100%)';
        document.getElementById('quickConfirmYes').textContent = 'Ya, Setujui';
        document.getElementById('quickConfirmOverlay').classList.add('active');

        document.getElementById('quickConfirmYes').onclick = function() {
            document.getElementById('quickConfirmOverlay').classList.remove('active');
            document.getElementById('quickConfirmYes').onclick = null;
            doValidasiSetujui(idPeminjaman, actionName, tipe);
        };

    }

    function doValidasiSetujui(idPeminjaman, actionName, tipe) {
        var formData = new FormData();
        formData.append('action', actionName);
        formData.append('id_peminjaman', idPeminjaman);

        fetch('../api/notifikasi.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotifToast({ pesan: data.message || 'Berhasil!', tipe: tipe === 'kembali' ? 'disetujui_kembali' : 'disetujui_pinjam' });
                    loadNotifikasi();
                } else {
                    showNotifToast({ pesan: data.error || 'Gagal melakukan validasi.', tipe: 'ditolak_pinjam' });
                }
            })
            .catch(err => console.error('Error validasi:', err));
    }

    function doValidasiTolak(idPeminjaman, tipe, alasan) {
        var actionName = tipe === 'kembali' ? 'validasi_tolak_kembali' : 'validasi_tolak_pinjam';

        var formData = new FormData();
        formData.append('action', actionName);
        formData.append('id_peminjaman', idPeminjaman);
        formData.append('alasan', alasan);

        fetch('../api/notifikasi.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotifToast({ pesan: data.message || 'Ditolak.', tipe: tipe === 'kembali' ? 'ditolak_kembali' : 'ditolak_pinjam' });
                    loadNotifikasi();
                } else {
                    showNotifToast({ pesan: data.error || 'Gagal melakukan validasi.', tipe: 'ditolak_pinjam' });
                }
            })
            .catch(err => console.error('Error validasi:', err));
    }

    /**
     * validasiTolak(idPeminjaman, idNotif, tipe) - Tolak permohonan dari dropdown
     * @param {number} idPeminjaman - ID peminjaman yang akan ditolak
     * @param {number} idNotif - ID notifikasi (tidak dipakai di API, cadangan)
     * @param {string} tipe - Jenis: 'pinjam' atau 'kembali'
     * 
     * Alur:
     * 1. Minta admin memasukkan alasan penolakan (opsional, via prompt)
     * 2. Kirim POST ke API dengan action 'validasi_tolak_pinjam' atau 'validasi_tolak_kembali'
     * 3. API akan: update status peminjaman jadi 'ditolak', buat notifikasi ke anggota
     * 4. Tampilkan toast notifikasi dan refresh dropdown
     */
    function validasiTolak(idPeminjaman, idNotif, tipe) {
        var tolakMsg = tipe === 'kembali' ? 'Tolak Pengembalian?' : 'Tolak Peminjaman?';
        var tolakDetail = tipe === 'kembali' ? 'Yakin ingin menolak pengembalian buku ini?' : 'Yakin ingin menolak permohonan peminjaman ini?';

        document.getElementById('quickConfirmTitle').textContent = tolakMsg;
        document.getElementById('quickConfirmMessage').textContent = tolakDetail;
        document.getElementById('quickConfirmIcon').innerHTML = '<i class="fas fa-times-circle"></i>';
        document.getElementById('quickConfirmIcon').style.background = 'var(--danger-light)';
        document.getElementById('quickConfirmIcon').style.color = '#DC2626';
        document.getElementById('quickConfirmYes').style.background = 'linear-gradient(135deg, #EF4444 0%, #F87171 100%)';
        document.getElementById('quickConfirmYes').textContent = 'Ya, Tolak';
        document.getElementById('quickConfirmOverlay').classList.add('active');

        // Show alasan input
        document.getElementById('quickConfirmAlasanWrap').style.display = 'block';
        document.getElementById('quickConfirmAlasan').value = '';

        document.getElementById('quickConfirmYes').onclick = function() {
            document.getElementById('quickConfirmOverlay').classList.remove('active');
        document.getElementById('quickConfirmAlasanWrap').style.display = 'none';
            document.getElementById('quickConfirmYes').onclick = null;
            var alasan = document.getElementById('quickConfirmAlasan').value || 'Ditolak oleh admin.';
            doValidasiTolak(idPeminjaman, tipe, alasan);
        };

    }

    // ============================================
    // AUTO POLLING NOTIFIKASI (berjalan otomatis setiap 5 detik)
    // Fungsi ini mengecek notifikasi baru secara berkala
    // dan melakukan 3 hal sekaligus:
    //   1. Update badge (angka merah di ikon bell)
    //   2. Render ulang isi dropdown (agar selalu sinkron)
    //   3. Tampilkan toast popup untuk notifikasi yang belum pernah ditampilkan
    // ============================================

    /**
     * checkNewNotifikasi() - Polling otomatis untuk cek notifikasi baru
     * Dipanggil setiap 5 detik via setInterval (di bawah)
     * 
     * Alur:
     * 1. Fetch ke API notifikasi (sama seperti loadNotifikasi)
     * 2. Update badge angka unread
     * 3. Render ulang dropdown agar selalu sinkron dengan database
     * 4. Loop semua notifikasi:
     *    - Jika dibaca=0 DAN belum ada di shownToastIds → tampilkan toast
     *    - Tambahkan ID ke shownToastIds agar tidak muncul lagi
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
                    // Cek notifikasi baru (yang belum ditampilkan di toast)
                    data.data.forEach(function(n) {
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
     * Toast adalah popup kecil yang muncul otomatis saat ada notifikasi baru.
     * Toast memiliki:
     *   - Ikon warna sesuai tipe notifikasi
     *   - Teks pesan notifikasi
     *   - Tombol close (X)
     *   - Auto-dismiss setelah 5 detik (fade out + remove dari DOM)
     * 
     * Tipe toast:
     *   - permohonan → toast-warning (kuning/oranye)
     *   - disetujui  → toast-success (hijau)
     *   - ditolak    → toast-danger (merah)
     *   - info       → default (abu-abu)
     */
    function showNotifToast(n) {
        var container = document.getElementById('toastContainer');
        if (!container) return;

        // Tentukan ikon dan warna toast berdasarkan tipe
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

        // Buat elemen toast DOM
        var toast = document.createElement('div');
        toast.className = 'notif-toast ' + toastClass;
        toast.dataset.id = n.id_notifikasi;
        toast.innerHTML = '<div class="notif-toast-icon ' + iconClass + '"><i class="' + icon + '"></i></div>' +
            '<div class="notif-toast-content"><p>' + escapeHtml(n.pesan) + '</p></div>' +
            '<button class="notif-toast-close" onclick="closeToast(this.parentElement)"><i class="fas fa-times"></i></button>';

        container.appendChild(toast);

        // Auto-dismiss: toast hilang otomatis setelah 5 detik
        setTimeout(function() {
            if (toast.parentElement) {
                toast.classList.add('toast-hide'); // Fade out animation
                setTimeout(function() { toast.remove(); }, 500); // Hapus dari DOM setelah animasi
            }
        }, 5000);
    }

    /**
     * closeToast(el) - Tutup toast secara manual (saat user klik tombol X)
     * @param {HTMLElement} el - Elemen toast yang akan ditutup
     * 
     * Menambahkan class "toast-hide" untuk fade out, lalu hapus dari DOM
     */
    function closeToast(el) {
        el.classList.add('toast-hide');
        setTimeout(function() { el.remove(); }, 500);
    }

    // ============================================
    // INISIALISASI SAAT HALAMAN PERTAMA KALI DIMUAT
    // ============================================

    // Load notifikasi saat halaman dibuka → isi dropdown + update badge
    loadNotifikasi();

    // Polling setiap 5 detik → update dropdown + badge + toast untuk notifikasi baru
    setInterval(checkNewNotifikasi, 5000);
    </script>

    <?php include '../footer.php'; ?>

</body>

</html>
