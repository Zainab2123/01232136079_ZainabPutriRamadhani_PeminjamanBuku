<?php
session_start();
require '../koneksi.php';

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['id_user'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// ===============================
// GET NOTIFIKASI
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($role === 'admin') {
        // ADMIN: lihat notif berdasarkan id_admin
        $query = mysqli_query($conn, "
            SELECT * FROM notifikasi
            WHERE id_admin = '$id_user'
            ORDER BY tanggal DESC
            LIMIT $limit
        ");
    } else {
        // ANGGOTA: lihat notif berdasarkan id_user
        $query = mysqli_query($conn, "
            SELECT * FROM notifikasi
            WHERE id_user = '$id_user'
            ORDER BY tanggal DESC
            LIMIT $limit
        ");
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

// ===============================
// ACTION POST
// ===============================
$action = $_POST['action'] ?? '';

if ($action === 'baca') {

    $id = $_POST['id_notifikasi'];

    mysqli_query($conn, "
        UPDATE notifikasi 
        SET dibaca = 1 
        WHERE id_notifikasi = '$id'
    ");

    echo json_encode(['success' => true]);
    exit;
}

// ===============================
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

// ===============================
if ($action === 'hapus') {

    $id = $_POST['id_notifikasi'];

    mysqli_query($conn, "
        DELETE FROM notifikasi 
        WHERE id_notifikasi = '$id'
    ");

    echo json_encode(['success' => true]);
    exit;
}

// ===============================
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

echo json_encode([
    'success' => false,
    'error' => 'Invalid action'
]);