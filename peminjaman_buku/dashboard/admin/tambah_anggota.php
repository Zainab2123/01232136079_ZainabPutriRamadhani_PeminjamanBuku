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
$error = '';
$success = '';
$old_username = '';
$old_status = 'aktif';

// ============================================
// PROSES TAMBAH ANGGOTA
// ============================================
if (isset($_POST['tambah'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $status   = $_POST['status'];

    $old_username = $username;
    $old_status   = $status;

    // --- Validasi: Semua field wajib diisi ---
    if (empty($username) || empty($password) || empty($status)) {
        $error = 'Semua field wajib diisi!';

    } else {

        // --- Validasi: Cek duplikat username ---
        $cek = $conn->prepare("SELECT id_user FROM user WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error = 'Username sudah digunakan! Silakan pilih username lain.';
            $cek->close();

        } else {
            $cek->close();

            // --- Hash password ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'anggota';

            // --- Insert ke tabel user ---
            $stmt_user = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
            $stmt_user->bind_param("sss", $username, $hashed_password, $role);

            if ($stmt_user->execute()) {

                $id_user = $conn->insert_id;

                // --- Insert ke tabel anggota ---
                // Nama awal = username, bisa diubah admin atau anggota nanti lewat profil
                $stmt_anggota = $conn->prepare("
                    INSERT INTO anggota (id_user, nama, kelas, no_hp, status)
                    VALUES (?, ?, '', '', ?)
                ");
                $stmt_anggota->bind_param("iss", $id_user, $username, $status);

                if ($stmt_anggota->execute()) {
                    // --- Sukses: redirect ke kelola anggota dengan pesan sukses ---
                    $_SESSION['flash_success'] = 'Anggota <strong>' . htmlspecialchars($username) . '</strong> berhasil ditambahkan!';
                    header("Location: anggota.php");
                    exit;
                } else {
                    $error = 'Gagal menambahkan data anggota: ' . $stmt_anggota->error;
                }

                $stmt_anggota->close();

            } else {
                $error = 'Gagal membuat akun user: ' . $stmt_user->error;
            }

            $stmt_user->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Anggota - Perpustakaan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <div class="admin-page">

        <a href="anggota.php" class="back-link"><i class="fas fa-arrow-left"></i> Kelola Anggota</a>

        <h2><i class="fas fa-user-plus"></i> Tambah Anggota</h2>

        <!-- PESAN ERROR -->
        <?php if ($error): ?>
        <div class="notif-inline notif-error" style="
            background: var(--danger-light);
            border: 1px solid #FCA5A5;
            border-left: 4px solid var(--danger);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #B91C1C;
            font-size: 0.92rem;
            font-weight: 500;
        ">
            <i class="fas fa-circle-exclamation" style="font-size:1.1rem;flex-shrink:0;"></i>
            <span><?= $error ?></span>
        </div>
        <?php endif; ?>

        <!-- FORM TAMBAH ANGGOTA -->
        <div class="form-panel">
            <h3><i class="fas fa-user-pen"></i> Data Anggota Baru</h3>

            <form method="POST" autocomplete="off">

                <div class="form-grid">

                    <!-- USERNAME -->
                    <div class="form-group">
                        <label><i class="fas fa-user" style="color:var(--primary);margin-right:4px;"></i> Username <span style="color:var(--danger);">*</span></label>
                        <input
                            type="text"
                            name="username"
                            placeholder="Masukkan username..."
                            value="<?= htmlspecialchars($old_username) ?>"
                            required
                            autocomplete="off"
                        >
                        <small style="color:var(--gray-400);font-size:0.78rem;">Username harus unik, tidak boleh sama dengan anggota lain.</small>
                    </div>

                    <!-- PASSWORD -->
                    <div class="form-group" style="position:relative;">
                        <label><i class="fas fa-lock" style="color:var(--primary);margin-right:4px;"></i> Password <span style="color:var(--danger);">*</span></label>
                        <div style="position:relative;">
                            <input
                                type="password"
                                name="password"
                                id="inputPassword"
                                placeholder="Buat password untuk anggota..."
                                required
                                autocomplete="new-password"
                                style="padding-right:44px;"
                            >
                            <button
                                type="button"
                                onclick="togglePassword()"
                                style="
                                    position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                    background:none;border:none;cursor:pointer;color:var(--gray-400);
                                    font-size:1rem;padding:0;
                                "
                                tabindex="-1"
                                title="Tampilkan/Sembunyikan password"
                            >
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <small style="color:var(--gray-400);font-size:0.78rem;">Password akan di-hash secara otomatis untuk keamanan.</small>
                    </div>

                    <!-- STATUS -->
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on" style="color:var(--primary);margin-right:4px;"></i> Status <span style="color:var(--danger);">*</span></label>
                        <select name="status" required>
                            <option value="aktif"   <?= $old_status == 'aktif'    ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= $old_status == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                        <small style="color:var(--gray-400);font-size:0.78rem;">Anggota nonaktif tidak dapat melakukan peminjaman.</small>
                    </div>

                </div>

                <!-- AKSI -->
                <div class="form-actions">
                    <button type="submit" name="tambah">
                        <i class="fas fa-floppy-disk"></i> Simpan Anggota
                    </button>
                    <a href="anggota.php">
                        <button type="button" class="btn-secondary">
                            <i class="fas fa-xmark"></i> Batal
                        </button>
                    </a>
                </div>

            </form>
        </div>

        <!-- INFO BOX -->
        <div style="
            background: var(--primary-50);
            border: 1px solid var(--primary-200);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--primary-dark);
        ">
            <b><i class="fas fa-circle-info"></i> Catatan:</b>
            <ul style="margin:8px 0 0 16px;padding:0;line-height:1.8;">
                <li>Nama, kelas, dan no HP anggota bisa dilengkapi melalui halaman <b>Detail Anggota</b> setelah data berhasil disimpan.</li>
                <li>Password disimpan dalam format terenkripsi (tidak bisa dibaca langsung).</li>
                <li>Anggota dengan status <b>Nonaktif</b> tidak bisa login atau melakukan peminjaman.</li>
            </ul>
        </div>

    </div>

    <script>
        function togglePassword() {
            var input = document.getElementById('inputPassword');
            var icon  = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>
