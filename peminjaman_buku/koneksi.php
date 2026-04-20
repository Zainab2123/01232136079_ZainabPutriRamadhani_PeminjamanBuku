<?php
/**
 * ============================================
 * KONEKSI DATABASE
 * File: koneksi.php
 * 
 * Fungsi: Menghubungkan PHP ke database MySQL
 * Dipakai oleh: SEMUA file PHP yang membutuhkan akses database
 * 
 * Kenapa dipisah ke file tersendiri?
 * - Biar tidak menulis konfigurasi koneksi berulang-ulang di setiap file
 * - Jika ada perubahan config (password, port, nama db), cukup edit satu file ini
 * 
 * Konfigurasi:
 * - Host: localhost
 * - User: root
 * - Password: (kosong)
 * - Database: peminjaman_buku
 * - Port: 3307 (port custom, default MySQL biasanya 3306)
 * 
 * Menggunakan mysqli (MySQL Improved) - driver PHP untuk MySQL
 * ============================================
 */
$conn = mysqli_connect("localhost", "root", "", "peminjaman_buku", 3307);

// Cek koneksi: jika gagal, hentikan program dan tampilkan pesan error
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
