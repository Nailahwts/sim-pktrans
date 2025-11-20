<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// Ambil username admin
$admin_id = $_SESSION['admin'];
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$username = $admin['username'];

// Inisialisasi variabel
$dataRealisasi = [];
$nomorBA = '';
$selectedBarang = null;
$selectedKapal = null;
$namaBarang = '';
$namaKapal = '';
$filterPartner = isset($_GET['filter_partner']) ? $_GET['filter_partner'] : 'all';
$selectedPoId = isset($_GET['po_id']) ? intval($_GET['po_id']) : null;
$statusSO = '';
$listPO = [];
$successMsg = '';
$errorMsg = '';

// Cek apakah ada parameter dari surat_jalan.php
if(isset($_GET['barang_id']) && isset($_GET['kapal_id'])){
    $selectedBarang = intval($_GET['barang_id']);
    $selectedKapal = intval($_GET['kapal_id']);
    
    // Ambil nama barang dan kapal
    $stmtBarang = $conn->prepare("SELECT nama_barang FROM barang WHERE barang_id = ?");
    $stmtBarang->bind_param("i", $selectedBarang);
    $stmtBarang->execute();
    $resultBarang = $stmtBarang->get_result();
    if($rowBarang = $resultBarang->fetch_assoc()){
        $namaBarang = $rowBarang['nama_barang'];
    }
    
    $stmtKapal = $conn->prepare("SELECT nama_kapal FROM kapal WHERE kapal_id = ?");
    $stmtKapal->bind_param("i", $selectedKapal);
    $stmtKapal->execute();
    $resultKapal = $stmtKapal->get_result();
    if($rowKapal = $resultKapal->fetch_assoc()){
        $namaKapal = $rowKapal['nama_kapal'];
    }
    
    // Ambil berita acara yang sudah ada dari surat_jalan
    $stmtGetBA = $conn->prepare("
        SELECT berita_acara 
        FROM surat_jalan 
        WHERE barang_id = ? AND kapal_id = ? AND berita_acara IS NOT NULL AND berita_acara != ''
        LIMIT 1
    ");
    $stmtGetBA->bind_param("ii", $selectedBarang, $selectedKapal);
    $stmtGetBA->execute();
    $resultGetBA = $stmtGetBA->get_result();
    if($rowBA = $resultGetBA->fetch_assoc()){
        $nomorBA = $rowBA['berita_acara'];
    }
    
    // Ambil status SO dan list PO
    $sqlSO = "
        SELECT so.status, so.po_id
        FROM sales_order so
        WHERE so.barang_id = ? AND so.kapal_id = ?
        LIMIT 1
    ";
    $stmtSO = $conn->prepare($sqlSO);
    $stmtSO->bind_param("ii", $selectedBarang, $selectedKapal);
    $stmtSO->execute();
    $resultSO = $stmtSO->get_result();
    if($rowSO = $resultSO->fetch_assoc()){
        $statusSO = $rowSO['status'];
        
        // Jika belum ada po_id yang dipilih, gunakan dari SO
        if(!$selectedPoId && $rowSO['po_id']){
            $selectedPoId = intval($rowSO['po_id']);
        }
    }
    
    // Update berita acara jika form dikirim
    if(isset($_POST['updateBA']) && isset($_POST['nomor_berita_acara'])){
        $newBA = trim($_POST['nomor_berita_acara']);

        // Update semua surat_jalan dengan barang_id dan kapal_id yang sama
        $stmtUpdateBA = $conn->prepare("
            UPDATE surat_jalan 
            SET berita_acara = ? 
            WHERE barang_id = ? AND kapal_id = ?
        ");
        $stmtUpdateBA->bind_param("sii", $newBA, $selectedBarang, $selectedKapal);

        if($stmtUpdateBA->execute()){
            $affected_rows = $stmtUpdateBA->affected_rows;
            $nomorBA = $newBA; // update variabel agar langsung tampil
            $successMsg = "Berita Acara berhasil diperbarui untuk {$affected_rows} surat jalan.";
        } else {
            $errorMsg = "Gagal memperbarui Berita Acara: " . $conn->error;
        }
    }

    // Query untuk mengambil data realisasi dengan filter partner
    $wherePartner = "";
    if($filterPartner == 'Rekanan'){
        $wherePartner = " AND p.kategori = 'Rekanan'";
    } else if($filterPartner == 'Internal'){
        $wherePartner = " AND p.kategori = 'Internal'";
    }
    
    $sqlRealisasi = "
        SELECT 
            sj.tanggal,
            sj.shift,
            w.warehouse_id,
            w.nama_warehouse,
            ken.nopol,
            ken.kendaraan_id,
            p.kategori as partner,
            COUNT(sj.sj_id) as qty_rit,
            SUM(sj.tonase) as total_tonase
        FROM surat_jalan sj
        JOIN warehouse w ON sj.warehouse_id = w.warehouse_id
        JOIN kendaraan ken ON sj.kendaraan_id = ken.kendaraan_id
        JOIN partner p ON ken.partner_id = p.partner_id
        WHERE sj.barang_id = ? 
            AND sj.kapal_id = ?
            $wherePartner
        GROUP BY sj.tanggal, sj.shift, w.warehouse_id, ken.kendaraan_id
        ORDER BY sj.tanggal ASC, sj.shift ASC, w.nama_warehouse ASC, ken.nopol ASC
    ";
    
    $stmt = $conn->prepare($sqlRealisasi);
    $stmt->bind_param("ii", $selectedBarang, $selectedKapal);
    $stmt->execute();
    $resultRealisasi = $stmt->get_result();
    
    while($row = $resultRealisasi->fetch_assoc()){
        $dataRealisasi[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Realisasi Surat Jalan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
/* ===== Sidebar ===== */
.main-content {
    margin-left: 0px;
    transition: all 0.3s ease;
}



/* ===== Header ===== */
.header {
    position: sticky;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 1030;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.header .title { 
    margin-left: 50px;
    font-size: 24px;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.header .user-info { 
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
}

.header .user-info .username {
    padding: 8px 18px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(10px);
}

.header .user-info a.logout-btn {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: #fff;
    padding: 10px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(52, 11, 21, 0.4);
    display: inline-block;
}

.header .user-info a.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 65, 108, 0.5);
    filter: brightness(1.1);
}

.sidebar {
    position: fixed;
    top: 0;
    left: -320px;
    width: 320px;
    height: 100%;
    background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
    color: #fff;
    overflow-y: auto;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 1050;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
}

.sidebar::-webkit-scrollbar {
    width: 8px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar.active { 
    left: 0;
    box-shadow: 4px 0 30px rgba(0, 0, 0, 0.5);
}

.sidebar-header { 
    padding: 25px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h3 {
    font-size: 22px;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.sidebar-menu { 
    list-style: none;
    padding: 15px 0;
    margin: 0;
}

.sidebar-menu li { 
    list-style: none;
    padding: 5px 15px;
    margin: 3px 0;
}

.sidebar-menu li a {
    color: #fff;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 14px 18px;
    border-radius: 12px;
    transition: all 0.3s ease;
    font-size: 15px;
    font-weight: 500;
    position: relative;
    overflow: hidden;
}

.sidebar-menu li a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.15);
    padding-left: 28px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.sidebar-menu li a:hover::before {
    transform: scaleY(1);
}

.sidebar-menu li a i {
    margin-right: 12px;
    font-size: 18px;
    width: 25px;
    text-align: center;
}

/* ===== Hamburger ===== */
.hamburger-btn {
    position: fixed;
    top: 10px;
    left: 18px;
    z-index: 1100;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: #fff;
    padding: 12px 16px;
    font-size: 22px;
    cursor: pointer;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.hamburger-btn:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.hamburger-btn.shifted { 
    left: 335px;
}

/* ===== Overlay ===== */
#overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(3px);
    display: none;
    z-index: 1040;
    transition: all 0.3s ease;
}

#overlay.active { display: block; }

/* ===== Dropdown custom ===== */
.submenu { 
    display: none;
    padding-left: 20px;
    list-style: none;
    margin-top: 5px;
}

.submenu.active { 
    display: block !important;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.submenu li a {
    padding: 10px 18px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.05);
    margin: 3px 0;
}

.arrow { 
    float: right;
    transition: transform 0.3s ease;
    font-size: 12px;
}

.arrow::before { 
    font-size: 11px;
}

.dropdown-toggle.active .arrow {
    transform: rotate(180deg);
}

.dropdown-toggle::after {
    display: none;
}

/* ===== Header ===== */
.header {
    position: sticky;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 1030;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.header .title { 
    margin-left: 50px;
    font-size: 24px;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.header .user-info { 
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
}

.header .user-info .username {
    padding: 8px 18px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(10px);
}

.header .user-info a.logout-btn {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: #fff;
    padding: 10px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(52, 11, 21, 0.4);
    display: inline-block;
}

.header .user-info a.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 65, 108, 0.5);
    filter: brightness(1.1);
}

/* ===== Main Content ===== */
.container-fluid {
    padding: 30px;
}

/* ===== Alert Styles ===== */
.alert {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    animation: slideInDown 0.4s ease;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.header .user-info a.logout-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 12px rgba(0,0,0,0.2);
    filter:brightness(1.1);
}

.table-realisasi { font-size: 0.9rem; border-collapse: collapse; }
.table-realisasi th { 
    background-color: #ffffff; 
    color: black; 
    text-align: center; 
    vertical-align: middle;
    border: 1px solid #000;
    padding: 8px;
}
.table-realisasi td { 
    padding: 6px 8px; 
    vertical-align: middle;
    border: 1px solid #000;
}
.subtotal-row { 
    background-color: #e9ecef; 
}
.total-row { 
    background-color: #d1e7dd; 
    font-weight: bold; 
    font-size: 10pt;
}
.grand-total-row { 
    background-color: #0d6efd; 
    color: white;  
    font-size: 10pt;
    font-weight: bold;
}
.nomor-ba-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    margin-bottom: 0px;
    border-radius: 8px;
}
.nomor-ba-header h3 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: bold;
}

/* Hide image in normal view */
.print-only-image {
    display: none;
}

@media print {
    .nomor-ba-header form {
    display: block !important;
}

.nomor-ba-header input,
.nomor-ba-header button {
    font-size: 10pt;
}


/* ===== Button Styles ===== */
.btn {
    border-radius: 10px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
}

.btn-light {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
    border: 2px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(5px);
}

.btn-light:hover {
    background: rgba(255, 255, 255, 1);
    color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: #fff;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
    filter: brightness(1.1);
}

.btn-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: #fff;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
    filter: brightness(1.1);
}

.btn-danger {
    background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
    color: #fff;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 65, 108, 0.4);
    filter: brightness(1.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    filter: brightness(1.1);
}

.btn-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: #fff;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
    filter: brightness(1.1);
}

.btn-action {
    margin: 0 3px;
}

    @page {
        margin: 0;
    }

    html, body {
        margin: 0;
        padding: 0;
    }

    .no-print { display: none !important; }
    .header, .sidebar, .hamburger-btn { display: none !important; }
    .card { border: none; box-shadow: none; }
    .card-header { display: none; }
    .table-realisasi { font-size: 10pt; }
    .table-realisasi th, .table-realisasi td { padding: 4px; }
    .nomor-ba-header { 
        background: white !important; 
        color: black !important; 
        margin-top: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Show image only when printing */
    .print-only-image {
        display: block !important;
        width: 100%;
        margin: 0;
        padding: 0;
        border: none;
    }
}
</style>
</head>
<body>

<!-- Hamburger Button -->
<button class="hamburger-btn" id="hamburgerBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-chart-line"></i> Dashboard Utama</h3>
    </div>
    <br>
    <ul class="sidebar-menu">
        <li><a href="../index.php"><i class="fas fa-home"></i> Dashboard</a></li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-database"></i> Master Data <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../master/partner.php"><i class="fas fa-handshake"></i> Partner</a></li>
                <li><a href="../master/ttd.php"><i class="fas fa-signature"></i> Tanda Tangan</a></li>
                <li><a href="../master/kendaraan.php"><i class="fas fa-truck"></i> Kendaraan</a></li>
                <li><a href="../master/barang.php"><i class="fas fa-box"></i> Barang</a></li>
                <li><a href="../master/dermaga.php"><i class="fas fa-anchor"></i> Dermaga</a></li>
                <li><a href="../master/harga.php"><i class="fas fa-dollar-sign"></i> Harga</a></li>
            </ul>
        </li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-exchange-alt"></i> Transaksi <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../transaksi/sales_order.php"><i class="fas fa-shopping-cart"></i> Sales Order</a></li>
                <li><a href="../transaksi/purchase_order.php"><i class="fas fa-shopping-bag"></i> Purchase Order</a></li>
                <li><a href="../transaksi/surat_jalan.php"><i class="fas fa-file-alt"></i> Surat Jalan</a></li>
            </ul>
        </li>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fas fa-file-invoice"></i> Laporan <span class="arrow"></span></a>
            <ul class="submenu active">
                <li><a href="../laporan/order_kerja.php"><i class="fas fa-clipboard-list"></i> Order Kerja</a></li>
                <li><a href="../laporan/invoice.php"><i class="fas fa-file-invoice-dollar"></i> Invoice</a></li>
                <li><a href="../laporan/kwitansi.php"><i class="fas fa-receipt"></i> Kwitansi</a></li>
                <li><a href="../laporan/laporan_realisasi.php"><i class="fas fa-chart-bar"></i> Realisasi</a></li>
            </ul>
        </li>
        <hr>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <br>
    </ul>
</div>

<!-- Overlay -->
<div id="overlay"></div>

<!-- Header -->
<div class="header">
    <div class="title">Realisasi Surat Jalan</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="container-fluid main-content">
    
    <?php if(!empty($dataRealisasi)): ?>

    <!-- Image - Hidden in menu, visible in print -->
    <img src="../assets/images/kopPKN.png" alt="Kop Surat" class="print-only-image">

    <!-- Nomor BA Header -->
    <div class="nomor-ba-header mb-3 no-print">
        <form method="post" action="">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-file-text"></i> Nomor BA</span>
                <input type="text" name="nomor_berita_acara" class="form-control" value="<?= htmlspecialchars($nomorBA) ?>" placeholder="Masukkan Nomor Berita Acara" required>
                <button class="btn btn-light" type="submit" name="updateBA">
                    <i class="bi bi-check-circle"></i> Update
                </button>
            </div>
        </form>
    </div>

    <!-- Alert Messages -->
    <?php if(!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?= $successMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= $errorMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Info Barang & Kapal -->
    <div class="alert alert-info no-print">
        <h5><strong><?= htmlspecialchars($namaBarang) ?> - <?= htmlspecialchars($namaKapal) ?></strong></h5>
        <?php if($nomorBA): ?>
        <small class="text-muted">Berita Acara: <strong><?= htmlspecialchars($nomorBA) ?></strong></small>
        <?php endif; ?>
    </div>

    <!-- Filter & Tombol Aksi -->
    <div class="mb-3 no-print">
        <div class="row">
            <div class="col-md-3">
                <label class="form-label fw-bold">Filter Partner</label>
                <select class="form-select" id="filterPartner" onchange="changeFilter()">
                    <option value="all" <?= $filterPartner=='all'?'selected':'' ?>>Tampilkan Semua</option>
                    <option value="Rekanan" <?= $filterPartner=='Rekanan'?'selected':'' ?>>Rekanan</option>
                    <option value="Internal" <?= $filterPartner=='Internal'?'selected':'' ?>>Internal</option>
                </select>
            </div>
            <div class="col-md-9 d-flex align-items-end gap-2">
                <button class="btn btn-success" onclick="exportExcel()">
                    <i class="bi bi-file-excel"></i> Ekspor Excel
                </button>
                <button class="btn btn-danger" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak PDF
                </button>
                <a href="surat_jalan.php" class="btn btn-info">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Tabel Realisasi -->

    <div class="card">
        <div class="card-header bg-success text-white no-print">
            <h5 class="mb-0">Data Realisasi</h5>
        </div>
      
    <?php if($nomorBA): ?>
    <div class="mt-3" style="text-align: right; font-weight: bold; font-size: 12pt;">
        Nomor: <?= htmlspecialchars($nomorBA) ?>
    </div>
    <?php endif; ?>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-realisasi" id="tableRealisasi">
                    <thead>
                        <tr>
                            <th>TANGGAL</th>
                            <th>SHIFT</th>
                            <th>WAREHOUSE</th>
                            <th>NOPOL</th>
                            <th>RIT</th>
                            <th>QTY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grandTotalRit = 0;
                        $grandTotalQty = 0;
                        
                        // Group by tanggal
                        $groupedByDate = [];
                        foreach($dataRealisasi as $row){
                            $tanggal = $row['tanggal'];
                            if(!isset($groupedByDate[$tanggal])){
                                $groupedByDate[$tanggal] = [];
                            }
                            $groupedByDate[$tanggal][] = $row;
                        }
                        
                        foreach($groupedByDate as $tanggal => $dataPerTanggal):
                            // Group by shift dalam tanggal
                            $groupedByShift = [];
                            foreach($dataPerTanggal as $row){
                                $shift = $row['shift'];
                                if(!isset($groupedByShift[$shift])){
                                    $groupedByShift[$shift] = [];
                                }
                                $groupedByShift[$shift][] = $row;
                            }
                            
                            $tanggalTotalRit = 0;
                            $tanggalTotalQty = 0;
                            
                            foreach($groupedByShift as $shift => $dataPerShift):
                                // Group by warehouse dalam shift
                                $groupedByWarehouse = [];
                                foreach($dataPerShift as $row){
                                    $warehouse = $row['nama_warehouse'];
                                    if(!isset($groupedByWarehouse[$warehouse])){
                                        $groupedByWarehouse[$warehouse] = [];
                                    }
                                    $groupedByWarehouse[$warehouse][] = $row;
                                }
                                
                                foreach($groupedByWarehouse as $warehouse => $dataPerWarehouse):
                                    $warehouseTotalRit = 0;
                                    $warehouseTotalQty = 0;
                                    
                                    foreach($dataPerWarehouse as $row):
                                        $ritCount = intval($row['qty_rit']);
                                        $qty = floatval($row['total_tonase']);
                                        
                                        $warehouseTotalRit += $ritCount;
                                        $warehouseTotalQty += $qty;
                        ?>
                        <tr>
                            <td class="text-center" style="background-color: #e0e0e0;">
                                <?= date('d/m/Y', strtotime($tanggal)) ?>
                            </td>
                            <td class="text-center"><?= $shift ?></td>
                            <td><?= htmlspecialchars($warehouse) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['nopol']) ?></td>
                            <td class="text-center"><?= $ritCount ?></td>
                            <td class="text-end"><?= number_format($qty, 3, '.', '') ?></td>
                        </tr>
                        <?php
                                    endforeach;
                                    
                                    $tanggalTotalRit += $warehouseTotalRit;
                                    $tanggalTotalQty += $warehouseTotalQty;
                        ?>
                        <tr class="subtotal-row">
                            <td class="text-center" style="background-color: #e0e0e0; font-weight: bold;">
                                <?= date('d/m/Y', strtotime($tanggal)) ?>
                            </td>
                            <td class="text-center"><?= $shift ?></td>
                            <td colspan="2"><?= htmlspecialchars($warehouse) ?> Total</td>
                            <td class="text-center"><?= $warehouseTotalRit ?></td>
                            <td class="text-end" style="text-align: right; padding-right: 8px;">
                                <?= number_format($warehouseTotalQty, 3, '.', '') ?>
                            </td>
                        </tr>
                        <?php
                                endforeach;
                        ?>
                        <?php
                            endforeach;
                            
                            $grandTotalRit += $tanggalTotalRit;
                            $grandTotalQty += $tanggalTotalQty;
                        ?>
                        <tr class="total-row">
                            <td colspan="4" class="text-end"><?= date('d/m/Y', strtotime($tanggal)) ?> Total</td>
                            <td class="text-center"><?= $tanggalTotalRit ?></td>
                            <td class="text-end"><?= number_format($tanggalTotalQty, 3, '.', '') ?></td>
                        </tr>
                        <?php
                        endforeach;
                        ?>
                        <tr class="grand-total-row">
                            <td colspan="4" class="text-center">Grand Total</td>
                            <td class="text-center"><?= $grandTotalRit ?></td>
                            <td class="text-end"><?= number_format($grandTotalQty, 3, '.', '') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    
    <div class="alert alert-warning">
        <h5><i class="bi bi-exclamation-triangle"></i> Tidak Ada Data</h5>
        <p>Silakan pilih barang dan kapal dari halaman <a href="surat_jalan.php">Surat Jalan</a> terlebih dahulu.</p>
    </div>
    
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hamburger toggle
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

function openSidebar(){ 
    sidebar.classList.add('active'); 
    overlay.classList.add('active'); 
    hamburger.classList.add('shifted'); 
}

function closeSidebar(){ 
    sidebar.classList.remove('active'); 
    overlay.classList.remove('active'); 
    hamburger.classList.remove('shifted'); 
}

hamburger.addEventListener('click',()=>{ 
    sidebar.classList.contains('active') ? closeSidebar() : openSidebar(); 
});

overlay.addEventListener('click', closeSidebar);

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(toggle=>{
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        this.classList.toggle('active');
        const submenu = this.nextElementSibling;
        if(submenu){ submenu.classList.toggle('active'); }
    });
});

// Change filter
function changeFilter() {
    const filter = document.getElementById('filterPartner').value;
    const url = new URL(window.location.href);
    url.searchParams.set('filter_partner', filter);
    window.location.href = url.toString();
}

// Export to Excel
function exportExcel() {
    const table = document.getElementById('tableRealisasi');
    const nomorBA = '<?= $nomorBA ?>';
    
    const rows = table.querySelectorAll('tbody tr');
    const data = [];
    
    // Header dengan nomor BA
    if(nomorBA) {
        data.push(['NOMOR : ' + nomorBA]);
        data.push([]);
    }
    data.push(['TANGGAL', 'SHIFT', 'WAREHOUSE', 'NOPOL', 'RIT', 'QTY']);
    
    rows.forEach(row => {
        const cells = row.cells;
        const rowData = [];
        
        for(let i = 0; i < cells.length; i++){
            let cellText = cells[i].textContent.trim();
            
            // Convert qty to number
            if(i === 5 && cellText !== '' && cellText !== '-'){
                cellText = parseFloat(cellText.replace(/\./g, '').replace(',', '.'));
            }
            
            rowData.push(cellText);
        }
        
        data.push(rowData);
    });
    
    // Buat workbook
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Merge cell untuk nomor BA
    if(nomorBA) {
        ws['!merges'] = [{s: {r: 0, c: 0}, e: {r: 0, c: 5}}];
    }
    
    // Set lebar kolom
    ws['!cols'] = [
        {wch: 15},
        {wch: 8},
        {wch: 25},
        {wch: 15},
        {wch: 8},
        {wch: 15}
    ];
    
    // Format angka untuk qty
    const range = XLSX.utils.decode_range(ws['!ref']);
    const startRow = nomorBA ? 3 : 1;
    for(let R = startRow; R <= range.e.r; ++R) {
        const qtyAddr = XLSX.utils.encode_cell({r: R, c: 5});
        if(ws[qtyAddr] && typeof ws[qtyAddr].v === 'number') {
            ws[qtyAddr].z = '#,##0.000';
        }
    }
    
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Realisasi');
    
    const baNumber = nomorBA ? nomorBA.replace(/\//g, '_') : 'NoBA';
    const filename = 'Realisasi_' + baNumber + '_<?= $namaBarang ?>_<?= $namaKapal ?>_' + new Date().toISOString().slice(0,10) + '.xlsx';
    XLSX.writeFile(wb, filename);
}
</script>

</body>
</html>