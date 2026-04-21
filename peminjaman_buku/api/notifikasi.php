<?php
session_start();
require '../koneksi.php';

header('Content-Type: application/json');

// ============================================
// CEK LOGIN
// ============================================
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'error' => 'Belum login']);
    exit;
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// ============================================
// GET NOTIFIKASI
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    if ($role === 'admin') {
        // ADMIN: lihat notif berdasarkan id_admin
        $query = mysqli_query($conn, "
            SELECT * FROM notifikasi
            WHERE id_admin = '$id_user'
            ORDER BY tanggal DESC
            LIMIT $limit
        ");

        $unread_q = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM notifikasi
            WHERE id_admin = '$id_user' AND dibaca = 0
        ");
    } else {
        // ANGGOTA: lihat notif berdasarkan id_user
        $query = mysqli_query($conn, "
            SELECT *
            FROM notifikasi
            WHERE id_user = '$id_user'
            ORDER BY tanggal DESC
            LIMIT $limit
        ");

        $unread_q = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM notifikasi
            WHERE id_user = '$id_user' AND dibaca = 0
        ");
    }

    $notifikasi = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $notifikasi[] = $row;
    }

    $unread = mysqli_fetch_assoc($unread_q)['total'];

    echo json_encode([
        'success' => true,
        'data' => $notifikasi,
        'unread' => (int)$unread
    ]);
    exit;
}

// ============================================
// POST ACTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ============================================
    // BACA 1
    // ============================================
    if ($action === 'baca') {

        $id_notif = $_POST['id_notifikasi'];

        if ($role === 'admin') {
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET dibaca = 1 
                WHERE id_notifikasi = '$id_notif' 
                AND id_admin = '$id_user'
            ");
        } else {
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET dibaca = 1 
                WHERE id_notifikasi = '$id_notif' 
                AND id_user = '$id_user'
            ");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================
    // BACA SEMUA
    // ============================================
    if ($action === 'baca_semua') {

        if ($role === 'admin') {
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET dibaca = 1 
                WHERE id_admin = '$id_user'
            ");
        } else {
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET dibaca = 1 
                WHERE id_user = '$id_user'
            ");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================
    // HAPUS 1
    // ============================================
    if ($action === 'hapus') {

        $id_notif = $_POST['id_notifikasi'];

        mysqli_query($conn, "
            DELETE FROM notifikasi 
            WHERE id_notifikasi = '$id_notif'
        ");

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================
    // HAPUS SEMUA
    // ============================================
    if ($action === 'clear_all') {

        if ($role === 'admin') {
            mysqli_query($conn, "
                DELETE FROM notifikasi 
                WHERE id_admin = '$id_user'
            ");
        } else {
            mysqli_query($conn, "
                DELETE FROM notifikasi 
                WHERE id_user = '$id_user'
            ");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================
    // VALIDASI (ADMIN ONLY)
    // ============================================
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Hanya admin']);
        exit;
    }

    // ============================================
    // SETUJUI PINJAM
    // ============================================
    if ($action === 'validasi_setujui_pinjam') {

        $id_peminjaman = (int)$_POST['id_peminjaman'];

        $q = mysqli_query($conn, "
            SELECT a.id_user AS id_user_anggota,
                   dp.id_buku, dp.jumlah, b.judul, b.stok
            FROM peminjaman p
            JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
            JOIN buku b ON dp.id_buku = b.id_buku
            JOIN anggota a ON p.id_anggota = a.id_anggota
            WHERE p.id_peminjaman = '$id_peminjaman'
        ");

        $data = mysqli_fetch_assoc($q);

        if ($data) {

            if ($data['stok'] < $data['jumlah']) {

                // Stok tidak cukup -> auto tolak
                mysqli_query($conn, "UPDATE peminjaman SET status='ditolak_pinjam' WHERE id_peminjaman='$id_peminjaman'");

                $pesan_anggota = "Permohonan peminjaman buku \"{$data['judul']}\" ditolak (stok tidak mencukupi).";
                mysqli_query($conn, "
                    INSERT INTO notifikasi (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                    VALUES ('{$data['id_user_anggota']}', '$id_user', 'ditolak_pinjam', '$pesan_anggota', '$id_peminjaman', 0)
                ");

                // UPDATE notifikasi admin asli -> ganti jadi info ditolak
                $pesan_admin = "Anda telah menolak peminjaman buku \"{$data['judul']}\" (stok tidak mencukupi).";
                mysqli_query($conn, "
                    UPDATE notifikasi 
                    SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                    WHERE id_admin = '$id_user' 
                    AND id_peminjaman = '$id_peminjaman' 
                    AND tipe = 'permohonan_pinjam'
                ");

                echo json_encode(['success' => true, 'message' => $pesan_admin]);

            } else {

                // Stok cukup -> setujui
                mysqli_query($conn, "UPDATE peminjaman SET status='dipinjam' WHERE id_peminjaman='$id_peminjaman'");
                mysqli_query($conn, "UPDATE buku SET stok = stok - {$data['jumlah']} WHERE id_buku='{$data['id_buku']}'");

                $pesan_anggota = "Permohonan peminjaman buku \"{$data['judul']}\" disetujui.";
                mysqli_query($conn, "
                    INSERT INTO notifikasi (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                    VALUES ('{$data['id_user_anggota']}', '$id_user', 'disetujui_pinjam', '$pesan_anggota', '$id_peminjaman', 0)
                ");

                // UPDATE notifikasi admin asli -> ganti jadi info setujui
                $pesan_admin = "Anda telah menyetujui peminjaman buku \"{$data['judul']}\".";
                mysqli_query($conn, "
                    UPDATE notifikasi 
                    SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                    WHERE id_admin = '$id_user' 
                    AND id_peminjaman = '$id_peminjaman' 
                    AND tipe = 'permohonan_pinjam'
                ");

                echo json_encode(['success' => true, 'message' => $pesan_admin]);
            }

        } else {
            echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
        }

        exit;
    }

    // ============================================
    // TOLAK PINJAM (BARU - sebelumnya tidak ada)
    // ============================================
    if ($action === 'validasi_tolak_pinjam') {

        $id_peminjaman = (int)$_POST['id_peminjaman'];
        $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? 'Ditolak oleh admin.');

        $q = mysqli_query($conn, "
            SELECT a.id_user AS id_user_anggota, b.judul
            FROM peminjaman p
            JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
            JOIN buku b ON dp.id_buku = b.id_buku
            JOIN anggota a ON p.id_anggota = a.id_anggota
            WHERE p.id_peminjaman = '$id_peminjaman'
        ");

        $data = mysqli_fetch_assoc($q);

        if ($data) {

            mysqli_query($conn, "UPDATE peminjaman SET status='ditolak_pinjam' WHERE id_peminjaman='$id_peminjaman'");

            // Notif ke anggota
            $pesan_anggota = "Permohonan peminjaman buku \"{$data['judul']}\" ditolak. $alasan";
            mysqli_query($conn, "
                INSERT INTO notifikasi (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES ('{$data['id_user_anggota']}', '$id_user', 'ditolak_pinjam', '$pesan_anggota', '$id_peminjaman', 0)
            ");

            // UPDATE notifikasi admin asli -> ganti jadi info ditolak
            $pesan_admin = "Anda telah menolak peminjaman buku \"{$data['judul']}\".";
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                WHERE id_admin = '$id_user' 
                AND id_peminjaman = '$id_peminjaman' 
                AND tipe = 'permohonan_pinjam'
            ");

            echo json_encode(['success' => true, 'message' => $pesan_admin]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
        }

        exit;
    }

    // ============================================
    // SETUJUI KEMBALI
    // ============================================
    if ($action === 'validasi_setujui_kembali') {

        $id_peminjaman = (int)$_POST['id_peminjaman'];

        $q = mysqli_query($conn, "
            SELECT a.id_user AS id_user_anggota,
                   dp.id_buku, dp.jumlah, b.judul
            FROM peminjaman p
            JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
            JOIN buku b ON dp.id_buku = b.id_buku
            JOIN anggota a ON p.id_anggota = a.id_anggota
            WHERE p.id_peminjaman = '$id_peminjaman'
        ");

        $data = mysqli_fetch_assoc($q);

        if ($data) {

            $today = date('Y-m-d');

            mysqli_query($conn, "UPDATE peminjaman SET status='dikembalikan', tanggal_kembali='$today' WHERE id_peminjaman='$id_peminjaman'");
            mysqli_query($conn, "UPDATE buku SET stok = stok + {$data['jumlah']} WHERE id_buku='{$data['id_buku']}'");

            // Notif ke anggota
            $pesan_anggota = "Pengembalian buku \"{$data['judul']}\" disetujui.";
            mysqli_query($conn, "
                INSERT INTO notifikasi (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES ('{$data['id_user_anggota']}', '$id_user', 'disetujui_kembali', '$pesan_anggota', '$id_peminjaman', 0)
            ");

            // UPDATE notifikasi admin asli -> ganti jadi info setujui
            $pesan_admin = "Anda telah menyetujui pengembalian buku \"{$data['judul']}\".";
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                WHERE id_admin = '$id_user' 
                AND id_peminjaman = '$id_peminjaman' 
                AND tipe = 'permohonan_kembali'
            ");

            echo json_encode(['success' => true, 'message' => $pesan_admin]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
        }

        exit;
    }

    // ============================================
    // TOLAK KEMBALI (BARU - sebelumnya tidak ada)
    // ============================================
    if ($action === 'validasi_tolak_kembali') {

        $id_peminjaman = (int)$_POST['id_peminjaman'];
        $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? 'Ditolak oleh admin.');

        $q = mysqli_query($conn, "
            SELECT a.id_user AS id_user_anggota, b.judul
            FROM peminjaman p
            JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
            JOIN buku b ON dp.id_buku = b.id_buku
            JOIN anggota a ON p.id_anggota = a.id_anggota
            WHERE p.id_peminjaman = '$id_peminjaman'
        ");

        $data = mysqli_fetch_assoc($q);

        if ($data) {

            // Status kembali ke 'dipinjam' karena pengembalian ditolak
            mysqli_query($conn, "UPDATE peminjaman SET status='dipinjam' WHERE id_peminjaman='$id_peminjaman'");

            // Notif ke anggota
            $pesan_anggota = "Pengembalian buku \"{$data['judul']}\" ditolak. $alasan";
            mysqli_query($conn, "
                INSERT INTO notifikasi (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES ('{$data['id_user_anggota']}', '$id_user', 'ditolak_kembali', '$pesan_anggota', '$id_peminjaman', 0)
            ");

            // UPDATE notifikasi admin asli -> ganti jadi info ditolak
            $pesan_admin = "Anda telah menolak pengembalian buku \"{$data['judul']}\".";
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                WHERE id_admin = '$id_user' 
                AND id_peminjaman = '$id_peminjaman' 
                AND tipe = 'permohonan_kembali'
            ");

            echo json_encode(['success' => true, 'message' => $pesan_admin]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
        }

        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action tidak dikenali']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Method tidak valid']);
?>