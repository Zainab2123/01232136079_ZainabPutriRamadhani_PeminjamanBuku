<?php
session_start();
require '../koneksi.php';

// ============================================
// KEAMANAN: Hanya admin
// ============================================
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login/login.php");
    exit;
}

$id_admin = $_SESSION['id_user'];

// ============================================
// SETUJUI PINJAM
// ============================================
if (isset($_POST['setujui_pinjam'])) {

    $id_peminjaman = $_POST['id_peminjaman'];

    $q = mysqli_query($conn, "
        SELECT p.id_anggota, a.id_user AS id_user_anggota,
               dp.id_buku, dp.jumlah, b.judul, b.stok
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
        JOIN buku b ON dp.id_buku = b.id_buku
        JOIN anggota a ON p.id_anggota = a.id_anggota
        WHERE p.id_peminjaman='$id_peminjaman' 
        AND p.status='menunggu_pinjam'
    ");

    $data = mysqli_fetch_assoc($q);

    if ($data) {

        if ($data['stok'] < $data['jumlah']) {

            mysqli_query($conn, "
                UPDATE peminjaman 
                SET status='ditolak_pinjam' 
                WHERE id_peminjaman='$id_peminjaman'
            ");

            // NOTIF KE ANGGOTA
            $pesan = "Permohonan peminjaman buku \"{$data['judul']}\" ditolak karena stok tidak mencukupi.";
            mysqli_query($conn, "
                INSERT INTO notifikasi 
                (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES 
                ('{$data['id_user_anggota']}', '$id_admin', 'ditolak_pinjam', '$pesan', '$id_peminjaman', 0)
            ");

            // NOTIF KE ADMIN SENDIRI (FIX)
            $pesan_admin = "Anda telah menolak peminjaman buku \"{$data['judul']}\" (stok tidak mencukupi).";
            mysqli_query($conn, "
                INSERT INTO notifikasi 
                (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES 
                ('$id_admin', '$id_admin', 'info_admin', '$pesan_admin', '$id_peminjaman', 0)
            ");

            // UPDATE notifikasi asli admin -> ganti tipe pesan permohonan jadi info ditolak
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                WHERE id_admin = '$id_admin' 
                AND id_peminjaman = '$id_peminjaman' 
                AND tipe = 'permohonan_pinjam'
            ");

        } else {

            mysqli_query($conn, "
                UPDATE peminjaman 
                SET status='dipinjam' 
                WHERE id_peminjaman='$id_peminjaman'
            ");

            mysqli_query($conn, "
                UPDATE buku 
                SET stok = stok - {$data['jumlah']} 
                WHERE id_buku='{$data['id_buku']}'
            ");

            // NOTIF KE ANGGOTA
            $pesan = "Permohonan peminjaman buku \"{$data['judul']}\" disetujui.";
            mysqli_query($conn, "
                INSERT INTO notifikasi 
                (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES 
                ('{$data['id_user_anggota']}', '$id_admin', 'disetujui_pinjam', '$pesan', '$id_peminjaman', 0)
            ");

            // NOTIF KE ADMIN SENDIRI (FIX)
            $pesan_admin = "Anda telah menyetujui peminjaman buku \"{$data['judul']}\".";
            mysqli_query($conn, "
                INSERT INTO notifikasi 
                (id_user, id_admin, tipe, pesan, id_peminjaman, dibaca)
                VALUES 
                ('$id_admin', '$id_admin', 'info_admin', '$pesan_admin', '$id_peminjaman', 0)
            ");

            // UPDATE notifikasi asli admin -> ganti tipe pesan permohonan jadi info setujui
            mysqli_query($conn, "
                UPDATE notifikasi 
                SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
                WHERE id_admin = '$id_admin' 
                AND id_peminjaman = '$id_peminjaman' 
                AND tipe = 'permohonan_pinjam'
            ");
        }
    }

    header("Location: ../dashboard/admin/transaksi.php");
    exit;
}

// ============================================
// TOLAK PINJAM
// ============================================
if (isset($_POST['tolak_pinjam'])) {

    $id_peminjaman = $_POST['id_peminjaman'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? 'Ditolak oleh admin.');

    $q = mysqli_query($conn, "
        SELECT a.id_user AS id_user_anggota, b.judul
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
        JOIN buku b ON dp.id_buku = b.id_buku
        JOIN anggota a ON p.id_anggota = a.id_anggota
        WHERE p.id_peminjaman='$id_peminjaman'
    ");

    $data = mysqli_fetch_assoc($q);

    if ($data) {

        mysqli_query($conn, "
            UPDATE peminjaman 
            SET status='ditolak_pinjam' 
            WHERE id_peminjaman='$id_peminjaman'
        ");

        // ANGGOTA
        $pesan = "Permohonan peminjaman buku \"{$data['judul']}\" ditolak. $alasan";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'{$data['id_user_anggota']}','$id_admin','ditolak_pinjam','$pesan','$id_peminjaman',0,NOW())
        ");

        // ADMIN (FIX)
        $pesan_admin = "Anda telah menolak peminjaman buku \"{$data['judul']}\".";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'$id_admin','$id_admin','info_admin','$pesan_admin','$id_peminjaman',0,NOW())
        ");

        // UPDATE notifikasi asli admin -> ganti tipe pesan permohonan jadi info ditolak
        mysqli_query($conn, "
            UPDATE notifikasi 
            SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
            WHERE id_admin = '$id_admin' 
            AND id_peminjaman = '$id_peminjaman' 
            AND tipe = 'permohonan_pinjam'
        ");
    }

    header("Location: ../dashboard/admin/transaksi.php");
    exit;
}

// ============================================
// SETUJUI KEMBALI
// ============================================
if (isset($_POST['setujui_kembali'])) {

    $id_peminjaman = $_POST['id_peminjaman'];

    $q = mysqli_query($conn, "
        SELECT a.id_user AS id_user_anggota,
               dp.id_buku, dp.jumlah, b.judul
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
        JOIN buku b ON dp.id_buku = b.id_buku
        JOIN anggota a ON p.id_anggota = a.id_anggota
        WHERE p.id_peminjaman='$id_peminjaman'
    ");

    $data = mysqli_fetch_assoc($q);

    if ($data) {

        $today = date('Y-m-d');

        mysqli_query($conn, "
            UPDATE peminjaman 
            SET status='dikembalikan', tanggal_kembali='$today'
            WHERE id_peminjaman='$id_peminjaman'
        ");

        mysqli_query($conn, "
            UPDATE buku 
            SET stok = stok + {$data['jumlah']} 
            WHERE id_buku='{$data['id_buku']}'
        ");

        // ANGGOTA
        $pesan = "Pengembalian buku \"{$data['judul']}\" disetujui.";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'{$data['id_user_anggota']}','$id_admin','disetujui_kembali','$pesan','$id_peminjaman',0,NOW())
        ");

        // ADMIN (FIX)
        $pesan_admin = "Anda telah menyetujui pengembalian buku \"{$data['judul']}\".";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'$id_admin','$id_admin','info_admin','$pesan_admin','$id_peminjaman',0,NOW())
        ");

        // UPDATE notifikasi asli admin -> ganti tipe pesan permohonan jadi info setujui
        mysqli_query($conn, "
            UPDATE notifikasi 
            SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
            WHERE id_admin = '$id_admin' 
            AND id_peminjaman = '$id_peminjaman' 
            AND tipe = 'permohonan_kembali'
        ");
    }

    header("Location: ../dashboard/admin/transaksi.php");
    exit;
}

// ============================================
// TOLAK KEMBALI
// ============================================
if (isset($_POST['tolak_kembali'])) {

    $id_peminjaman = $_POST['id_peminjaman'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? 'Ditolak oleh admin.');

    $q = mysqli_query($conn, "
        SELECT a.id_user AS id_user_anggota, b.judul
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
        JOIN buku b ON dp.id_buku = b.id_buku
        JOIN anggota a ON p.id_anggota = a.id_anggota
        WHERE p.id_peminjaman='$id_peminjaman'
    ");

    $data = mysqli_fetch_assoc($q);

    if ($data) {

        mysqli_query($conn, "
            UPDATE peminjaman 
            SET status='dipinjam' 
            WHERE id_peminjaman='$id_peminjaman'
        ");

        // ANGGOTA
        $pesan = "Pengembalian buku \"{$data['judul']}\" ditolak. $alasan";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'{$data['id_user_anggota']}','$id_admin','ditolak_kembali','$pesan','$id_peminjaman',0,NOW())
        ");

        // ADMIN (FIX)
        $pesan_admin = "Anda telah menolak pengembalian buku \"{$data['judul']}\".";
        mysqli_query($conn, "
            INSERT INTO notifikasi 
            VALUES (NULL,'$id_admin','$id_admin','info_admin','$pesan_admin','$id_peminjaman',0,NOW())
        ");

        // UPDATE notifikasi asli admin -> ganti tipe pesan permohonan jadi info ditolak
        mysqli_query($conn, "
            UPDATE notifikasi 
            SET tipe = 'info_admin', pesan = '$pesan_admin', dibaca = 0
            WHERE id_admin = '$id_admin' 
            AND id_peminjaman = '$id_peminjaman' 
            AND tipe = 'permohonan_kembali'
        ");
    }

    header("Location: ../dashboard/admin/transaksi.php");
    exit;
}
?>