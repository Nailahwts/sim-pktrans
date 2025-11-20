<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

// Ambil username admin
$admin_id = $_SESSION['admin'];
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$username = $admin['username'];

// ===== FUNGSI GENERATE NOMOR KWITANSI =====
function generateNomorKwitansi($conn) {
    $year = date('Y');
    $month = date('m');
    
    // Format: 001/11/KEU/PK-TRANS/2025
    // Ambil nomor terakhir di tahun dan bulan ini
    $stmt = $conn->prepare("
        SELECT nomor_kwitansi 
        FROM kwitansi 
        WHERE YEAR(tanggal_invoice) = ? AND MONTH(tanggal_invoice) = ?
        ORDER BY kwitansi_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['nomor_kwitansi'];
        
        // Ekstrak nomor urut dari format 001/11/KEU/PK-TRANS/2025
        $parts = explode('/', $lastNomor);
        if(count($parts) >= 1 && is_numeric($parts[0])) {
            $lastNumber = intval($parts[0]);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
    } else {
        $newNumber = 1;
    }
    
    $nomorUrut = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    return "{$nomorUrut}/{$month}/KEU/PK-TRANS/{$year}";
}

// ===== HAPUS =====
if(isset($_POST['delete'])){
    $kwitansi_id = intval($_POST['delete']);
    $stmt = $conn->prepare("DELETE FROM kwitansi WHERE kwitansi_id=?");
    $stmt->bind_param("i", $kwitansi_id);
    if($stmt->execute()){
        echo "<script>alert('Kwitansi berhasil dihapus!'); window.location.href='kwitansi.php';</script>";
    } else {
        echo "<script>alert('Gagal hapus: ".$stmt->error."'); window.location.href='kwitansi.php';</script>";
    }
    exit;
}

// ===== EDIT/UPDATE =====
if(isset($_POST['update'])){
    $kwitansi_id = intval($_POST['kwitansi_id']);
    $nomor_kwitansi = trim($_POST['nomor_kwitansi']);
    $invoice_id = intval($_POST['invoice_id']);
    $partner_penerima_id = intval($_POST['partner_penerima_id']);
    $tanggal_kwitansi = $_POST['tanggal_kwitansi'];
    $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

    // Ambil data dari invoice
    $stmt = $conn->prepare("
        SELECT i.po_id, i.partner_id, i.barang_id, i.kapal_id, 
               i.harga_id, i.ppn, i.total_biaya
        FROM invoice i
        WHERE i.invoice_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $inv_data = $stmt->get_result()->fetch_assoc();

    if(!$inv_data){
        echo "<script>alert('Data invoice tidak ditemukan!'); window.history.back();</script>";
        exit;
    }

    // Update kwitansi
    if($ttd_id !== null){
        $stmt = $conn->prepare("UPDATE kwitansi SET 
            nomor_kwitansi=?, invoice_id=?, po_id=?, tanggal_invoice=?, 
            partner_id=?, barang_id=?, kapal_id=?, harga_id=?, ppn=?, total_biaya=?, ttd_id=?
            WHERE kwitansi_id=?");
        $stmt->bind_param(
            "siisiiiiddii",
            $nomor_kwitansi, $invoice_id, $inv_data['po_id'], $tanggal_kwitansi,
            $partner_penerima_id, $inv_data['barang_id'], $inv_data['kapal_id'], 
            $inv_data['harga_id'], $inv_data['ppn'], $inv_data['total_biaya'], $ttd_id, $kwitansi_id
        );
    } else {
        $stmt = $conn->prepare("UPDATE kwitansi SET 
            nomor_kwitansi=?, invoice_id=?, po_id=?, tanggal_invoice=?, 
            partner_id=?, barang_id=?, kapal_id=?, harga_id=?, ppn=?, total_biaya=?, ttd_id=NULL
            WHERE kwitansi_id=?");
        $stmt->bind_param(
            "siisiiiiddi",
            $nomor_kwitansi, $invoice_id, $inv_data['po_id'], $tanggal_kwitansi,
            $partner_penerima_id, $inv_data['barang_id'], $inv_data['kapal_id'], 
            $inv_data['harga_id'], $inv_data['ppn'], $inv_data['total_biaya'], $kwitansi_id
        );
    }

    if($stmt->execute()){
        echo "<script>alert('Kwitansi berhasil diupdate!'); window.location.href='kwitansi.php';</script>";
    } else {
        echo "<script>alert('Gagal update: ".$stmt->error."'); window.history.back();</script>";
    }
    exit;
}

// ===== TAMBAH =====
if(isset($_POST['submit'])){
    $nomor_kwitansi = generateNomorKwitansi($conn); // Auto generate
    $invoice_id = intval($_POST['invoice_id']);
    $partner_penerima_id = intval($_POST['partner_penerima_id']);
    $tanggal_kwitansi = $_POST['tanggal_kwitansi'];
    $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

    // Ambil data dari invoice
    $stmt = $conn->prepare("
        SELECT i.po_id, i.partner_id, i.barang_id, i.kapal_id, 
               i.harga_id, i.ppn, i.total_biaya
        FROM invoice i
        WHERE i.invoice_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $inv_data = $stmt->get_result()->fetch_assoc();

    if(!$inv_data){
        echo "<script>alert('Data invoice tidak ditemukan!'); window.history.back();</script>";
        exit;
    }

    // Insert kwitansi
    if($ttd_id !== null){
        $stmt = $conn->prepare("INSERT INTO kwitansi 
            (nomor_kwitansi, invoice_id, po_id, tanggal_invoice, partner_id, barang_id, kapal_id, harga_id, ppn, total_biaya, ttd_id) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "siisiiiiddi",
            $nomor_kwitansi, $invoice_id, $inv_data['po_id'], $tanggal_kwitansi,
            $partner_penerima_id, $inv_data['barang_id'], $inv_data['kapal_id'], 
            $inv_data['harga_id'], $inv_data['ppn'], $inv_data['total_biaya'], $ttd_id
        );
    } else {
        $stmt = $conn->prepare("INSERT INTO kwitansi 
            (nomor_kwitansi, invoice_id, po_id, tanggal_invoice, partner_id, barang_id, kapal_id, harga_id, ppn, total_biaya, ttd_id) 
            VALUES (?,?,?,?,?,?,?,?,?,?,NULL)");
$stmt->bind_param(
    "siisiiiidd",
    $nomor_kwitansi, $invoice_id, $inv_data['po_id'], $tanggal_kwitansi,
    $partner_penerima_id, $inv_data['barang_id'], $inv_data['kapal_id'], 
    $inv_data['harga_id'], $inv_data['ppn'], $inv_data['total_biaya']
);

    }

    if($stmt->execute()){
        echo "<script>alert('Kwitansi berhasil ditambahkan dengan nomor: $nomor_kwitansi'); window.location.href='kwitansi.php';</script>";
    } else {
        echo "<script>alert('Gagal tambah: ".$stmt->error."'); window.history.back();</script>";
    }
    exit;
}

// Ambil data Kwitansi
$result = $conn->query("
    SELECT 
        k.*,
        kp.nama_kapal,
        p.nama_partner,
        b.nama_barang,
        po.nomor_po,
        i.nomor_invoice,
        GROUP_CONCAT(DISTINCT h.area SEPARATOR ', ') AS area_list
    FROM kwitansi k
    LEFT JOIN kapal kp ON k.kapal_id = kp.kapal_id
    LEFT JOIN partner p ON k.partner_id = p.partner_id
    LEFT JOIN barang b ON k.barang_id = b.barang_id
    LEFT JOIN purchase_order po ON k.po_id = po.po_id
    LEFT JOIN invoice i ON k.invoice_id = i.invoice_id
    LEFT JOIN sales_order so ON so.po_id = po.po_id
    LEFT JOIN harga h ON so.harga_id = h.harga_id
    GROUP BY k.kwitansi_id
    ORDER BY k.tanggal_invoice DESC, k.kwitansi_id DESC
");

// Mode edit
$editRow = null;
if(isset($_GET['edit'])){
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT k.*, i.nomor_invoice
        FROM kwitansi k
        LEFT JOIN invoice i ON k.invoice_id = i.invoice_id
        WHERE k.kwitansi_id=?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editRow = $res->fetch_assoc();
}

// Ambil data untuk dropdown
if(!$editRow) {
    // Untuk tambah baru - hanya invoice yang belum punya kwitansi
    $invoice_list = $conn->query("
        SELECT i.invoice_id, i.nomor_invoice, i.tanggal_invoice, 
               po.nomor_po, po.uraian_pekerjaan
        FROM invoice i
        LEFT JOIN purchase_order po ON i.po_id = po.po_id
        WHERE NOT EXISTS (
            SELECT 1 FROM kwitansi k WHERE k.invoice_id = i.invoice_id
        )
        ORDER BY i.tanggal_invoice DESC
    ");
} else {
    // Untuk edit - tampilkan semua invoice yang belum punya kwitansi + invoice yang sedang diedit
    $invoice_list = $conn->query("
        SELECT i.invoice_id, i.nomor_invoice, i.tanggal_invoice, 
               po.nomor_po, po.uraian_pekerjaan
        FROM invoice i
        LEFT JOIN purchase_order po ON i.po_id = po.po_id
        WHERE (NOT EXISTS (
            SELECT 1 FROM kwitansi k WHERE k.invoice_id = i.invoice_id
        )) OR i.invoice_id = {$editRow['invoice_id']}
        ORDER BY i.tanggal_invoice DESC
    ");
}

$partner_list = $conn->query("SELECT partner_id, nama_partner FROM partner ORDER BY nama_partner");
$ttd_list = $conn->query("SELECT ttd_id, nama, jabatan FROM ttd ORDER BY nama");

// Generate preview nomor kwitansi untuk form baru
$previewNomorKwitansi = '';
if(!$editRow) {
    $previewNomorKwitansi = generateNomorKwitansi($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kwitansi Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link rel="stylesheet" href="../assets/css/style_lap.css"> 
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
    <div class="title">Kwitansi Management</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-fluid main-content">
    <!-- Form Tambah/Edit Kwitansi -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-receipt-<?= $editRow ? 'cutoff' : '' ?>"></i> 
                <?= $editRow ? 'Edit Kwitansi' : 'Form Kwitansi Baru' ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="formKwitansi">
                <?php if($editRow): ?>
                <input type="hidden" name="kwitansi_id" value="<?= $editRow['kwitansi_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Kwitansi <span class="text-danger">*</span></label>
                        <?php if($editRow): ?>
                            <input type="text" name="nomor_kwitansi" class="form-control" 
                                value="<?= htmlspecialchars($editRow['nomor_kwitansi']) ?>" required>
                            <small class="text-muted">Edit nomor kwitansi jika diperlukan</small>
                        <?php else: ?>
                            <input type="text" class="form-control bg-light" 
                                value="<?= $previewNomorKwitansi ?>" readonly>
                            <small class="text-success"><i class="fas fa-info-circle"></i> Nomor otomatis akan digenerate saat simpan</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">No. Invoice <span class="text-danger">*</span></label>
                        <select name="invoice_id" id="invoice_id" class="form-select" required>
                            <option value="">-- Pilih Invoice --</option>
                            <?php 
                            while($row = $invoice_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['invoice_id'] ?>" 
                                    <?= ($editRow && $editRow['invoice_id'] == $row['invoice_id']) ? 'selected' : '' ?>
                                    data-nomor-po="<?= htmlspecialchars($row['nomor_po']) ?>"
                                    data-uraian="<?= htmlspecialchars($row['uraian_pekerjaan']) ?>">
                                    <?= htmlspecialchars($row['nomor_invoice']) ?> - (PO: <?= htmlspecialchars($row['nomor_po']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Kwitansi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_kwitansi" class="form-control" 
                            value="<?= $editRow ? $editRow['tanggal_invoice'] : date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sudah Terima Dari <span class="text-danger">*</span></label>
                        <select name="partner_penerima_id" class="form-select" required>
                            <option value="">-- Pilih Partner Penerima --</option>
                            <?php 
                            $partner_list->data_seek(0); 
                            while($row = $partner_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['partner_id'] ?>"
                                    <?= ($editRow && $editRow['partner_id'] == $row['partner_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama_partner']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Partner yang tertera di bagian "Sudah Terima Dari"</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanda Tangan (Direktur)</label>
                        <select name="ttd_id" class="form-select">
                            <option value="">-- Pilih Tanda Tangan --</option>
                            <?php 
                            $ttd_list->data_seek(0); 
                            while($row = $ttd_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['ttd_id'] ?>"
                                    <?= ($editRow && $editRow['ttd_id'] == $row['ttd_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama']) ?> - <?= htmlspecialchars($row['jabatan']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Preview Detail</label>
                    <div id="previewDetail" class="border rounded p-3 bg-light">
                        <p class="text-muted text-center">Pilih Invoice untuk melihat detail</p>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <?php if($editRow): ?>
                    <button type="submit" name="update" class="btn btn-success">
                        <i class="bi bi-save"></i> Update Kwitansi
                    </button>
                    <a href="kwitansi.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                    <?php else: ?>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Kwitansi
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Kwitansi -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Daftar Kwitansi</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <input type="text" id="searchKwitansi" class="form-control" placeholder="🔍 Cari nomor kwitansi, invoice, PO, kapal...">
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="exportToExcel()" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export ke Excel
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="20">No</th>
                            <th>No. Kwitansi</th>
                            <th>No. Invoice</th>
                            <th>No. PO</th>
                            <th>Tanggal</th>
                            <th>Sudah Terima Dari</th>
                            <th>Kapal</th>
                            <th>Barang</th>
                            <th>Area</th>
                            <th>Total biaya</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="kwitansiTable">
                        <?php if($result && $result->num_rows > 0): 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong class="text-primary"><?= htmlspecialchars($row['nomor_kwitansi']) ?></strong></td>
                            <td><?= htmlspecialchars($row['nomor_invoice']) ?></td>
                            <td><?= htmlspecialchars($row['nomor_po']) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_invoice'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_partner']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><span class="badge-area"><?= htmlspecialchars($row['area_list'] ?? '-') ?></span></td>
                            <td>
                                Rp <?= number_format($row['total_biaya'], 2, ',', '.') ?>
                            </td>
                            <td>
                                <a href="?edit=<?= $row['kwitansi_id'] ?>" class="btn btn-sm btn-warning btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="cetak_kwitansi.php?kwitansi_id=<?= $row['kwitansi_id'] ?>&print=1" target="_blank" class="btn btn-sm btn-success btn-action">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus kwitansi ini?')">
                                    <input type="hidden" name="delete" value="<?= $row['kwitansi_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-action">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Tidak ada data kwitansi</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// Sidebar Toggle
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

hamburger.addEventListener('click', () => {
    if(sidebar.classList.contains('active')){
        closeSidebar();
    } else {
        openSidebar();
    }
});

overlay.addEventListener('click', closeSidebar);

// Dropdown submenu
document.querySelectorAll('.dropdown-toggle').forEach(toggle=>{
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        this.classList.toggle('active');
        const submenu = this.nextElementSibling;
        if(submenu){ submenu.classList.toggle('active'); }
    });
});

// Preview Invoice Details - Load on page load if editing
<?php if($editRow): ?>
window.addEventListener('DOMContentLoaded', function() {
    loadPreview(<?= $editRow['invoice_id'] ?>);
});
<?php endif; ?>

// Preview Invoice Details
document.getElementById('invoice_id').addEventListener('change', function() {
    loadPreview(this.value);
});

async function loadPreview(invoiceId) {
    const preview = document.getElementById('previewDetail');
    
    if(!invoiceId) {
        preview.innerHTML = '<p class="text-muted text-center">Pilih Invoice untuk melihat detail</p>';
        return;
    }
    
    preview.innerHTML = '<p class="text-center"><i class="bi bi-hourglass-split"></i> Memuat data...</p>';
    
    try {
        const response = await fetch('get_invoice_detail.php?invoice_id=' + invoiceId);
        const data = await response.json();
        
        if(data.success) {
            let html = `
                <div class="mb-3 p-2">
                    <table class="table table-sm detail-table mb-0">
                        <tr><th width="150">No. Invoice:</th><td>${data.data.nomor_invoice}</td></tr>
                        <tr><th>No. PO:</th><td>${data.data.nomor_po}</td></tr>
                        <tr><th>Partner:</th><td>${data.data.partner}</td></tr>
                        <tr><th>Kapal:</th><td>${data.data.kapal}</td></tr>
                        <tr><th>Barang:</th><td>${data.data.barang}</td></tr>
                        <tr><th>Periode:</th><td>${data.data.periode}</td></tr>
                    </table>
                </div>
            `;
            
            // Tambahkan detail per area jika ada
            if(data.data.area_details && data.data.area_details.length > 0) {
                html += '<div class="mb-3"><h6 class="text-primary">Detail Per Area:</h6>';
                html += '<table class="table table-sm table-bordered">';
                html += '<thead><tr><th>Area</th><th>Tonase</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead><tbody>';
                
                data.data.area_details.forEach(area => {
                    html += `<tr>
                        <td>${area.area}</td>
                        <td>${area.tonase_formatted} Ton</td>
                        <td>Rp ${area.harga_satuan_formatted}</td>
                        <td>Rp ${area.subtotal_formatted}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            }
            
            html += `
                <div class="mb-3 p-2">
                    <table class="table table-sm detail-table mb-0">
                        <tr><th width="150">Total Tonase:</th><td>${data.data.total_tonase} Ton</td></tr>
                        <tr><th>Subtotal:</th><td>Rp ${data.data.subtotal}</td></tr>
                        <tr><th>PPN (${data.data.ppn}%):</th><td>Rp ${data.data.ppn_nominal}</td></tr>
                        <tr><th class="text-primary">Total Biaya:</th><td class="text-primary fw-bold">Rp ${data.data.total_biaya}</td></tr>
                    </table>
                </div>
            `;
            preview.innerHTML = html;
        } else {
            preview.innerHTML = '<p class="text-danger">Gagal memuat data: ' + (data.message || 'Unknown error') + '</p>';
        }
    } catch(e) {
        preview.innerHTML = '<p class="text-danger">Error: ' + e.message + '</p>';
    }
}

// Search Kwitansi
document.getElementById('searchKwitansi').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#kwitansiTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

function exportToExcel() {
    // Ambil data dari tabel
    const table = document.querySelector('#kwitansiTable').closest('table');
    const rows = [];
    
    // Header
    const headers = ['No', 'No. Kwitansi', 'No. Invoice', 'No. PO', 'Tanggal', 'Sudah Terima Dari', 'Kapal', 'Barang', 'Area', 'Total Biaya'];
    rows.push(headers);
    
    // Data rows (hanya yang visible)
    const tableRows = document.querySelectorAll('#kwitansiTable tr');
    tableRows.forEach((row, index) => {
        if (row.style.display !== 'none' && row.cells.length > 1) {
            const rowData = [
                row.cells[0].textContent.trim(),
                row.cells[1].textContent.trim(),
                row.cells[2].textContent.trim(),
                row.cells[3].textContent.trim(),
                row.cells[4].textContent.trim(),
                row.cells[5].textContent.trim(),
                row.cells[6].textContent.trim(),
                row.cells[7].textContent.trim(),
                row.cells[8].textContent.trim(),
                row.cells[9].textContent.replace('Rp ', '').trim()
            ];
            rows.push(rowData);
        }
    });
    
    // Buat workbook dan worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(rows);
    
    // Set column widths
    ws['!cols'] = [
        { wch: 5 },  // No
        { wch: 20 }, // No. Kwitansi
        { wch: 20 }, // No. Invoice
        { wch: 20 }, // No. PO
        { wch: 12 }, // Tanggal
        { wch: 25 }, // Sudah Terima Dari
        { wch: 25 }, // Kapal
        { wch: 20 }, // Barang
        { wch: 20 }, // Area
        { wch: 20 }  // Total Biaya
    ];
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Kwitansi');
    
    // Generate filename dengan tanggal
    const today = new Date();
    const filename = `Kwitansi_${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}.xlsx`;
    
    // Download file
    XLSX.writeFile(wb, filename);
}
</script>

</body>
</html>