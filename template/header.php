<?php
session_start();
// Gunakan __DIR__ agar path include database selalu benar
// __DIR__ adalah folder 'template', jadi kita perlu '../' untuk naik ke root
include __DIR__ . "/../config/database.php"; 

// Cek session admin
if(!isset($_SESSION['admin'])) {
    // Asumsi login.php ada di root folder
    header("Location: /login.php"); // Gunakan path absolut
    exit;
}

$admin_id = $_SESSION['admin'];

// Prepare statement
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if($admin) {
    $username = $admin['username'];
} else {
    $username = "Admin"; // fallback
}

// Untuk judul halaman dinamis
if(!isset($pageTitle)) {
    $pageTitle = "Dashboard Admin"; // Judul default
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> - Petro Karya Trans</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>

<button class="hamburger-btn" id="hamburgerBtn">
    <i class="fas fa-bars"></i>
</button>

<div id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-chart-line"></i> Dashboard Utama</h3>
    </div>
    <br>
    <ul class="sidebar-menu">
        <li><a href="../ptkaryatrans/index.php"><i class="fas fa-home"></i>Dashboard</a></li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-database"></i> Master Data <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../ptkaryatrans/master/partner.php"><i class="fas fa-handshake"></i> Partner</a></li>
                <li><a href="../ptkaryatrans/master/ttd.php"><i class="fas fa-signature"></i> Tanda Tangan</a></li>
                <li><a href="../ptkaryatrans/master/kendaraan.php"><i class="fas fa-truck"></i> Kendaraan</a></li>
                <li><a href="../ptkaryatrans/master/barang.php"><i class="fas fa-box"></i> Barang</a></li>
                <li><a href="../ptkaryatrans/master/dermaga.php"><i class="fas fa-anchor"></i> Dermaga</a></li>
                <li><a href="../ptkaryatrans/master/harga.php"><i class="fas fa-dollar-sign"></i> Harga</a></li>
            </ul>
        </li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-exchange-alt"></i> Transaksi <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../ptkaryatrans/transaksi/sales_order.php"><i class="fas fa-shopping-cart"></i> Sales Order</a></li>
                <li><a href="../ptkaryatrans/transaksi/purchase_order.php"><i class="fas fa-shopping-bag"></i> Purchase Order</a></li>
                <li><a href="../ptkaryatrans/transaksi/surat_jalan.php"><i class="fas fa-file-alt"></i> Surat Jalan</a></li>
            </ul>
        </li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-file-invoice"></i> Laporan <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../ptkaryatrans/laporan/order_kerja.php"><i class="fas fa-clipboard-list"></i> Order Kerja</a></li>
                <li><a href="../ptkaryatrans/laporan/invoice.php"><i class="fas fa-file-invoice-dollar"></i> Invoice</a></li>
                <li><a href="../ptkaryatrans/laporan/kwitansi.php"><i class="fas fa-receipt"></i> Kwitansi</a></li>
                <li><a href="../ptkaryatrans/laporan/laporan_realisasi.php"><i class="fas fa-chart-bar"></i> Realisasi</a></li>
            </ul>
        </li>
        <hr>
        <li><a href="../ptkaryatrans/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <br>
    </ul>
</div>

<div id="overlay"></div>

<div class="header">
    <div class="title">Dashboard Petro Karya Trans</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container mt-4 main-content">