<?php
session_start();
require '../../koneksi.php';

/*
========================================
CEK ADMIN SAJA (AMAN)
========================================
*/
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login/login.php");
    exit;
}

/*
========================================
NONAKTIFKAN ANGGOTA
========================================
*/
if (isset($_GET['nonaktif'])) {
    $id = $_GET['nonaktif'];
    mysqli_query($conn, "UPDATE anggota SET status='nonaktif' WHERE id_anggota='$id'");
    header("Location: anggota.php");
    exit;
}

/*
========================================
AKTIFKAN ANGGOTA
========================================
*/
if (isset($_GET['aktifkan'])) {
    $id = $_GET['aktifkan'];
    mysqli_query($conn, "UPDATE anggota SET status='aktif' WHERE id_anggota='$id'");
    header("Location: anggota.php");
    exit;
}

/*
========================================
TAMBAH ANGGOTA (INLINE)
========================================
*/
$error_tambah = '';
$old_username = '';
$old_status = 'aktif';

if (isset($_POST['tambah'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $status = $_POST['status'];

    $old_username = $username;
    $old_status = $status;

    if (empty($username) || empty($password) || empty($status)) {
        $error_tambah = 'Semua field wajib diisi!';
    } else {
        $cek = $conn->prepare("SELECT id_user FROM user WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error_tambah = 'Username sudah digunakan! Silakan pilih username lain.';
            $cek->close();
        } else {
            $cek->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'anggota';

            $stmt_user = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
            $stmt_user->bind_param("sss", $username, $hashed_password, $role);

            if ($stmt_user->execute()) {
                $id_user = $conn->insert_id;
                $stmt_anggota = $conn->prepare("INSERT INTO anggota (id_user, nama, kelas, no_hp, status) VALUES (?, ?, '', '', ?)");
                $stmt_anggota->bind_param("iss", $id_user, $username, $status);
                if ($stmt_anggota->execute()) {
                    $_SESSION['flash_success'] = 'Anggota <strong>' . htmlspecialchars($username) . '</strong> berhasil ditambahkan!';
                    header("Location: anggota.php");
                    exit;
                } else {
                    $error_tambah = 'Gagal menyimpan data anggota.';
                }
                $stmt_anggota->close();
            } else {
                $error_tambah = 'Gagal membuat akun user.';
            }
            $stmt_user->close();
        }
    }
}

/*
========================================
KONTROL FORM (TOGGLE)
========================================
*/
$showForm = false;
if (isset($_GET['aksi']) && $_GET['aksi'] == 'tambah') {
    $showForm = true;
}
// Jika ada error, form tetap terbuka
if ($error_tambah != '') {
    $showForm = true;
}

/*
========================================
FLASH MESSAGE
========================================
*/
$flash_success = '';
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

/*
========================================
SEARCH & FILTER (semua via POST)
========================================
*/
$keyword = '';
$is_search = false;
$filter_peringatan = '';

if (isset($_POST['reset'])) {
    $keyword = '';
    $is_search = false;
    $filter_peringatan = '';
} else {
    if (isset($_POST['keyword'])) {
        $keyword = trim($_POST['keyword']);
        if ($keyword != '')
            $is_search = true;
    }
    if (isset($_POST['peringatan'])) {
        $filter_peringatan = $_POST['peringatan'];
    }
}

/*
========================================
AMBIL DATA ANGGOTA + PINJAMAN
========================================
*/
$query_sql = "
    SELECT
        anggota.id_anggota,
        anggota.nama,
        anggota.kelas,
        anggota.no_hp,
        anggota.status,
        COUNT(peminjaman.id_peminjaman) AS total_pinjam,
        MAX(DATEDIFF(NOW(), peminjaman.tanggal_peminjaman)) AS hari_terlama
    FROM anggota
    LEFT JOIN peminjaman
        ON anggota.id_anggota = peminjaman.id_anggota
        AND peminjaman.status = 'dipinjam'
";

$where = "";
if ($is_search && $keyword != '') {
    $where .= " WHERE anggota.nama LIKE '%$keyword%'";
}

$query_sql .= $where . " GROUP BY anggota.id_anggota";
$query = mysqli_query($conn, $query_sql);

/*
========================================
STATS
========================================
*/
$stats_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jumlah FROM anggota"))['jumlah'];
$stats_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jumlah FROM anggota WHERE status='aktif'"))['jumlah'];
$stats_nonaktif = $stats_total - $stats_aktif;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Anggota - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="admin-page">

        <a href="../dashboard_admin.php" class="back-link"><i class="fas fa-arrow-left"></i>Dashboard</a>

        <h2><i class="fas fa-users-gear"></i> Kelola Anggota</h2>

        <!-- FLASH SUCCESS -->
        <?php if ($flash_success): ?>
            <div style="
            background: var(--success-light);
            border: 1px solid #6EE7B7;
            border-left: 4px solid var(--success);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #065F46;
            font-size: 0.92rem;
            font-weight: 500;
        ">
                <i class="fas fa-circle-check" style="font-size:1.1rem;flex-shrink:0;"></i>
                <span><?= $flash_success ?></span>
            </div>
        <?php endif; ?>

        <!-- STATS INFO -->
        <div class="stats-row">
            <div class="stat-card stat-primary">
                <span class="stat-icon"><i class="fas fa-users"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_total ?></div>
                    <div class="stat-label">Total Anggota</div>
                </div>
            </div>
            <div class="stat-card stat-success">
                <span class="stat-icon"><i class="fas fa-user-check"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_aktif ?></div>
                    <div class="stat-label">Anggota Aktif</div>
                </div>
            </div>
            <div class="stat-card stat-danger">
                <span class="stat-icon"><i class="fas fa-user-xmark"></i></span>
                <div>
                    <div class="stat-value"><?= $stats_nonaktif ?></div>
                    <div class="stat-label">Anggota Nonaktif</div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="admin-search-bar">
            <form method="POST">
                <div class="search-input-with-btns">
                    <div class="form-group">
                        <label><i class="fas fa-magnifying-glass"></i> Cari Anggota:</label>
                        <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>"
                            placeholder="Cari nama anggota...">
                    </div>
                    <div class="search-btn-group">
                        <button type="submit" name="search"><i class="fas fa-search"></i> Cari</button>
                        <button type="submit" name="reset" class="btn-secondary"><i class="fas fa-rotate-left"></i>
                            Reset</button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Filter Peringatan:</label>
                    <select name="peringatan" onchange="this.form.submit()">
                        <option value="">-- Semua --</option>
                        <option value="aman" <?= $filter_peringatan == 'aman' ? 'selected' : '' ?>>Aman</option>
                        <option value="perhatian" <?= $filter_peringatan == 'perhatian' ? 'selected' : '' ?>>Perhatian
                        </option>
                        <option value="telat" <?= $filter_peringatan == 'telat' ? 'selected' : '' ?>>Telat</option>
                        <option value="kritis" <?= $filter_peringatan == 'kritis' ? 'selected' : '' ?>>Kritis</option>
                        <option value="nonaktif" <?= $filter_peringatan == 'nonaktif' ? 'selected' : '' ?>>Nonaktif
                        </option>
                    </select>
                </div>
            </form>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <?php if ($showForm): ?>
                <a href="anggota.php">
                    <button class="btn-secondary"><i class="fas fa-xmark"></i> Tutup Form</button>
                </a>
            <?php else: ?>
                <a href="?aksi=tambah">
                    <button><i class="fas fa-user-plus"></i> Tambah Anggota</button>
                </a>
            <?php endif; ?>
        </div>

        <!-- FORM TAMBAH (INLINE) -->
        <?php if ($showForm): ?>
            <div class="form-panel" id="formPanel">
                <h3><i class="fas fa-user-pen"></i> Tambah Anggota</h3>

                <?php if ($error_tambah): ?>
                    <div style="
                background: var(--danger-light);
                border: 1px solid #FCA5A5;
                border-left: 4px solid var(--danger);
                border-radius: var(--radius-md);
                padding: 12px 16px;
                margin-bottom: 1.25rem;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #B91C1C;
                font-size: 0.88rem;
                font-weight: 500;
            ">
                        <i class="fas fa-circle-exclamation" style="flex-shrink:0;"></i>
                        <span><?= $error_tambah ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="form-grid">

                        <div class="form-group">
                            <label>Username:</label>
                            <input type="text" name="username" required autocomplete="off"
                                value="<?= htmlspecialchars($old_username) ?>" placeholder="Masukkan username...">
                        </div>

                        <div class="form-group">
                            <label>Password:</label>
                            <div style="position:relative;">
                                <input type="password" name="password" id="inputPassword" required
                                    autocomplete="new-password" placeholder="Buat password..." style="padding-right:44px;">
                                <button type="button" onclick="togglePassword()"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:1rem;padding:0;"
                                    tabindex="-1">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Status:</label>
                            <select name="status" required>
                                <option value="aktif" <?= $old_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $old_status == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" name="tambah">
                            <i class="fas fa-floppy-disk"></i> Simpan Anggota
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- TABLE -->
        <table>
            <tr>
                <th>Nama</th>
                <th>Kelas</th>
                <th>No HP</th>
                <th>Status</th>
                <th>Buku Dipinjam</th>
                <th>Lama Pinjam</th>
                <th>Peringatan</th>
                <th>Aksi</th>
            </tr>

            <?php
            // Hitung total baris dari query untuk cek apakah ada data
            $jumlah_anggota = mysqli_num_rows($query);
            if ($jumlah_anggota == 0): ?>
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <p>Tidak ada data anggota.</p>
                    </div>
                </td>
            </tr>
            <?php else:
            // Loop tiap baris anggota dari hasil query
            while ($row = mysqli_fetch_assoc($query)): ?>

                <?php
                $hari = $row['hari_terlama'] ?? 0;
                $total = $row['total_pinjam'];

                if ($row['status'] == 'nonaktif') {
                    $row_class = "row-nonaktif";
                    $label = "NONAKTIF";
                    $peringatan_val = "nonaktif";
                } elseif ($hari <= 7) {
                    $row_class = "row-aman";
                    $label = "AMAN";
                    $peringatan_val = "aman";
                } elseif ($hari <= 14) {
                    $row_class = "row-perhatian";
                    $label = "PERHATIAN";
                    $peringatan_val = "perhatian";
                } elseif ($hari <= 30) {
                    $row_class = "row-telat";
                    $label = "TELAT";
                    $peringatan_val = "telat";
                } else {
                    $row_class = "row-kritis";
                    $label = "KRITIS";
                    $peringatan_val = "kritis";
                }

                if (!empty($filter_peringatan) && $peringatan_val != $filter_peringatan) {
                    continue;
                }
                ?>

                <tr class="<?= $row_class; ?>">
                    <td data-label="Nama"><?= $row['nama']; ?></td>
                    <td data-label="Kelas"><?= $row['kelas']; ?></td>
                    <td data-label="No HP"><?= $row['no_hp']; ?></td>
                    <td data-label="Status"><?= $row['status']; ?></td>
                    <td data-label="Dipinjam"><?= $total; ?> buku</td>
                    <td data-label="Lama"><?= $hari ?: 0; ?> hari</td>
                    <td data-label="Peringatan"><b><?= $label; ?></b></td>

                    <td data-label="Aksi">
                        <div class="table-actions">
                            <a href="detail_anggota.php?id=<?= $row['id_anggota']; ?>">
                                <button class="btn-detail"><i class="fas fa-eye"></i> Detail</button>
                            </a>

                            <?php if ($row['status'] == 'aktif'): ?>
                                <a href="#"
                                    onclick="showConfirm('Nonaktifkan Anggota', 'Yakin ingin menonaktifkan anggota &quot;<?= addslashes($row['nama']); ?>&quot;?', function(){ window.location.href='?nonaktif=<?= $row['id_anggota']; ?>'; })">
                                    <button class="btn-deactivate"><i class="fas fa-ban"></i> Nonaktifkan</button>
                                </a>
                            <?php else: ?>
                                <a href="#"
                                    onclick="showConfirmWarning('Aktifkan Anggota', 'Yakin ingin mengaktifkan kembali anggota &quot;<?= addslashes($row['nama']); ?>&quot;?', function(){ window.location.href='?aktifkan=<?= $row['id_anggota']; ?>'; })">
                                    <button class="btn-activate"><i class="fas fa-check"></i> Aktifkan</button>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

            <?php endwhile; // end loop anggota
            endif; // end cek data kosong ?>
        </table>

    </div>

    <?php include '../../footer.php'; ?>

    <!-- CUSTOM CONFIRM DIALOG -->
    <div id="confirmOverlay" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon icon-danger" id="confirmIcon">
                <i class="fas fa-triangle-exclamation" id="confirmIconEl"></i>
            </div>
            <div class="confirm-title" id="confirmTitle">Konfirmasi</div>
            <div class="confirm-message" id="confirmMessage">Apakah Anda yakin?</div>
            <div class="confirm-actions">
                <button class="btn-confirm-yes" id="confirmYes">Ya</button>
                <button class="btn-confirm-no" onclick="hideConfirm()">Batal</button>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var input = document.getElementById('inputPassword');
            var icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        var confirmCallback = null;

        function showConfirm(title, message, callback) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmIcon').className = 'confirm-icon icon-danger';
            document.getElementById('confirmIconEl').className = 'fas fa-triangle-exclamation';
            var yesBtn = document.getElementById('confirmYes');
            yesBtn.textContent = 'Ya, Lanjutkan';
            yesBtn.className = 'btn-confirm-yes';
            confirmCallback = callback;
            document.getElementById('confirmOverlay').classList.add('active');
        }

        function showConfirmWarning(title, message, callback) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmIcon').className = 'confirm-icon icon-warning';
            document.getElementById('confirmIconEl').className = 'fas fa-circle-question';
            var yesBtn = document.getElementById('confirmYes');
            yesBtn.textContent = 'Ya, Aktifkan';
            yesBtn.className = 'btn-confirm-yes btn-confirm-warning';
            confirmCallback = callback;
            document.getElementById('confirmOverlay').classList.add('active');
        }

        function hideConfirm() {
            document.getElementById('confirmOverlay').classList.remove('active');
            confirmCallback = null;
        }

        document.getElementById('confirmYes').addEventListener('click', function () {
            if (confirmCallback) confirmCallback();
            hideConfirm();
        });

        document.getElementById('confirmOverlay').addEventListener('click', function (e) {
            if (e.target === this) hideConfirm();
        });
    </script>

</body>

</html>