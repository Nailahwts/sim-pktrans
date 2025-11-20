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

// ===== FUNGSI GENERATE NOMOR OK =====
function generateNomorOK($conn) {
    $year = date('Y');
    $month = date('m');
    
    // Format: 001/XI/OK/PK-Trans/2025
    $bulan_romawi = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII'
    ];
    
    // Ambil nomor terakhir di bulan dan tahun ini
    $stmt = $conn->prepare("
        SELECT nomor_ok 
        FROM order_kerja 
        WHERE YEAR(tanggal_ok) = ? AND MONTH(tanggal_ok) = ?
        ORDER BY ok_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['nomor_ok'];
        
        // Ekstrak nomor urut dari format 001/XI/OK/PK-Trans/2025
        $parts = explode('/', $lastNomor);
        $lastNumber = intval($parts[0]);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    // Format nomor dengan leading zero
    $nomorUrut = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    $bulanRomawi = $bulan_romawi[$month];
    
    return "$nomorUrut/$bulanRomawi/OK/PK-Trans/$year";
}

// ===== HAPUS =====
if(isset($_POST['delete'])){
    $ok_id = intval($_POST['delete']);
    $stmt = $conn->prepare("DELETE FROM order_kerja WHERE ok_id=?");
    $stmt->bind_param("i", $ok_id);
    if($stmt->execute()){
        echo "<script>alert('Order Kerja berhasil dihapus!'); window.location.href='order_kerja.php';</script>";
    } else {
        echo "<script>alert('Gagal hapus: ".$stmt->error."'); window.location.href='order_kerja.php';</script>";
    }
    exit;
}

// ===== EDIT/UPDATE =====
if(isset($_POST['update'])){
    $ok_id = intval($_POST['ok_id']);
    $nomor_ok = trim($_POST['nomor_ok']);
    $tanggal_ok = $_POST['tanggal_ok'];
    $partner_id = intval($_POST['partner_id']);
    $kapal_id = intval($_POST['kapal_id']);
    $potongan_persentase = floatval($_POST['potongan_persentase']);
    $denda = floatval($_POST['denda']);
    $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

    // Hitung total tonase dari SO untuk kapal ini
    $stmt = $conn->prepare("SELECT SUM(qty) as total_qty FROM sales_order WHERE kapal_id = ?");
    $stmt->bind_param("i", $kapal_id);
    $stmt->execute();
    $so_data = $stmt->get_result()->fetch_assoc();
    $total_tonase = floatval($so_data['total_qty'] ?? 0);

    // Update order kerja
    $stmt = $conn->prepare("UPDATE order_kerja SET 
        nomor_ok=?, tanggal_ok=?, partner_id=?, kapal_id=?, 
        total_tonase=?, potongan_persentase=?, denda=?, ttd_id=?
        WHERE ok_id=?");
    
    $stmt->bind_param(
        "ssiidddii",
        $nomor_ok,
        $tanggal_ok,
        $partner_id,
        $kapal_id,
        $total_tonase,
        $potongan_persentase,
        $denda,
        $ttd_id,
        $ok_id
    );
    
    if($stmt->execute()){
        echo "<script>alert('Order Kerja berhasil diupdate!'); window.location.href='order_kerja.php';</script>";
    } else {
        echo "<script>alert('Gagal update: ".$stmt->error."'); window.history.back();</script>";
    }
    exit;
}

// ===== TAMBAH =====
if(isset($_POST['submit'])){
    $nomor_ok = generateNomorOK($conn); // Auto generate
    $tanggal_ok = $_POST['tanggal_ok'];
    $partner_id = intval($_POST['partner_id']);
    $kapal_id = intval($_POST['kapal_id']);
    $potongan_persentase = floatval($_POST['potongan_persentase']);
    $denda = floatval($_POST['denda']);
    $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

    // Hitung total tonase dari SO untuk kapal ini
    $stmt = $conn->prepare("SELECT SUM(qty) as total_qty FROM sales_order WHERE kapal_id = ?");
    $stmt->bind_param("i", $kapal_id);
    $stmt->execute();
    $so_data = $stmt->get_result()->fetch_assoc();
    $total_tonase = floatval($so_data['total_qty'] ?? 0);

    // Insert order kerja
    $harga_id = null;
    $stmt = $conn->prepare("INSERT INTO order_kerja 
    (nomor_ok, tanggal_ok, partner_id, kapal_id, total_tonase, potongan_persentase, denda, ttd_id, harga_id)
    VALUES (?,?,?,?,?,?,?,?,?)");
    
    if(!$stmt){
        echo "<script>alert('Error: ".$conn->error."'); window.history.back();</script>";
        exit;
    }

    $stmt->bind_param(
        "ssiidddii",
        $nomor_ok,
        $tanggal_ok,
        $partner_id,
        $kapal_id,
        $total_tonase,
        $potongan_persentase,
        $denda,
        $ttd_id,
        $harga_id
    );
    
    if($stmt->execute()){
        echo "<script>alert('Order Kerja berhasil ditambahkan dengan nomor: $nomor_ok'); window.location.href='order_kerja.php';</script>";
    } else {
        echo "<script>alert('Gagal tambah: ".$stmt->error."'); window.history.back();</script>";
    }
    exit;
}

// Ambil data Order Kerja
$result = $conn->query("
    SELECT ok.*, k.nama_kapal, p.partner as singkatan_partner, p.nama_partner
    FROM order_kerja ok
    LEFT JOIN kapal k ON ok.kapal_id = k.kapal_id
    LEFT JOIN partner p ON ok.partner_id = p.partner_id
    ORDER BY ok.tanggal_ok DESC, ok.ok_id DESC
");

// Mode edit
$editRow = null;
if(isset($_GET['edit'])){
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT ok.*
        FROM order_kerja ok
        WHERE ok.ok_id=?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editRow = $res->fetch_assoc();
}

// Ambil data untuk dropdown
$partner_list = $conn->query("SELECT partner_id, partner, nama_partner, kategori FROM partner ORDER BY kategori, nama_partner");

// Hanya kapal aktif (untuk tambah baru)
if(!$editRow) {
    $kapal_list = $conn->query("
        SELECT k.kapal_id, k.nama_kapal 
        FROM kapal k
        LEFT JOIN order_kerja ok ON k.kapal_id = ok.kapal_id
        WHERE k.status='aktif' AND ok.ok_id IS NULL
        ORDER BY k.nama_kapal
    ");
} else {
    // Untuk edit, tampilkan semua kapal aktif + kapal yang sedang diedit
    $kapal_list = $conn->query("
        SELECT k.kapal_id, k.nama_kapal 
        FROM kapal k
        WHERE k.status='aktif' OR k.kapal_id = {$editRow['kapal_id']}
        ORDER BY k.nama_kapal
    ");
}

// TTD
$ttd_list = $conn->query("SELECT ttd_id, nama, jabatan FROM ttd ORDER BY nama");

// Generate preview nomor OK untuk form baru
$previewNomorOK = '';
if(!$editRow) {
    $previewNomorOK = generateNomorOK($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Kerja Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    <div class="title">Order Kerja Management</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-fluid main-content">
    <!-- Form Tambah/Edit Order Kerja -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-file-<?= $editRow ? 'pen' : 'plus' ?>"></i> 
                <?= $editRow ? 'Edit Order Kerja' : 'Form Order Kerja Baru' ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="formOrderKerja">
                <?php if($editRow): ?>
                <input type="hidden" name="ok_id" value="<?= $editRow['ok_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor OK <span class="text-danger">*</span></label>
                        <?php if($editRow): ?>
                            <input type="text" name="nomor_ok" class="form-control" 
                                value="<?= htmlspecialchars($editRow['nomor_ok']) ?>" required>
                            <small class="text-muted">Edit nomor OK jika diperlukan</small>
                        <?php else: ?>
                            <input type="text" class="form-control bg-light" 
                                value="<?= $previewNomorOK ?>" readonly>
                            <small class="text-success"><i class="fas fa-info-circle"></i> Nomor otomatis akan digenerate saat simpan</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal OK <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_ok" class="form-control" 
                            value="<?= $editRow ? $editRow['tanggal_ok'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Partner <span class="text-danger">*</span></label>
                        <select name="partner_id" id="partner_id" class="form-select" required>
                            <option value="">-- Pilih Partner --</option>
                            <?php 
                            while($row = $partner_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['partner_id'] ?>" 
                                    <?= ($editRow && $editRow['partner_id'] == $row['partner_id']) ? 'selected' : '' ?>
                                    data-singkatan="<?= $row['partner'] ?>">
                                    <?= htmlspecialchars($row['partner']) ?> - <?= htmlspecialchars($row['nama_partner']) ?> (<?= $row['kategori'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Kapal <span class="text-danger">*</span></label>
                        <select name="kapal_id" id="kapal_id" class="form-select" required>
                            <option value="">-- Pilih Kapal --</option>
                            <?php 
                            while($row = $kapal_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['kapal_id'] ?>" 
                                    <?= ($editRow && $editRow['kapal_id'] == $row['kapal_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama_kapal']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Sistem akan otomatis menampilkan semua area dari kapal yang dipilih</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Potongan (%)</label>
                        <input type="number" name="potongan_persentase" id="potongan_persentase" class="form-control" step="0.01" 
                            value="<?= $editRow ? $editRow['potongan_persentase'] : '0' ?>" placeholder="Masukkan persentase potongan">
                        <small class="text-muted">Contoh: 5 untuk potongan 5%</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Denda (Rp)</label>
                        <input type="number" name="denda" id="denda" class="form-control" step="0.01" 
                            value="<?= $editRow ? $editRow['denda'] : '0' ?>" placeholder="Masukkan nominal denda">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanda Tangan</label>
                        <select name="ttd_id" class="form-select">
                            <option value="">-- Pilih Tanda Tangan Penerima --</option>
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
                    <label class="form-label">Preview Detail per Area</label>
                    <div id="previewDetail" class="border rounded p-3 bg-light">
                        <p class="text-muted text-center">Pilih Kapal untuk melihat detail per area</p>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <?php if($editRow): ?>
                    <button type="submit" name="update" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Order Kerja
                    </button>
                    <a href="order_kerja.php" class="btn btn-secondary">
                        <i class="fas fa-times-circle"></i> Batal
                    </a>
                    <?php else: ?>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Order Kerja
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Order Kerja -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Order Kerja</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <input type="text" id="searchOK" class="form-control" placeholder="🔍 Cari nomor OK, partner, kapal...">
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="exportToExcel()" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export ke Excel
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="20">No</th>
                            <th>No. OK</th>
                            <th>Tanggal</th>
                            <th>Partner</th>
                            <th>Kapal</th>
                            <th>Total Tonase</th>
                            <th>Potongan %</th>
                            <th>Denda</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="okTable">
                        <?php if($result && $result->num_rows > 0): 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong class="text-primary"><?= htmlspecialchars($row['nomor_ok']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_ok'])) ?></td>
                            <td><?= htmlspecialchars($row['singkatan_partner']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                            <td><?= number_format($row['total_tonase'], 3, ',', '.') ?> Ton</td>
                            <td><?= number_format($row['potongan_persentase'], 2, ',', '.') ?>%</td>
                            <td>Rp <?= number_format($row['denda'], 0, ',', '.') ?></td>
                            <td>
                                <a href="?edit=<?= $row['ok_id'] ?>" class="btn btn-sm btn-warning btn-action">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="cetak_order_kerja.php?ok_id=<?= $row['ok_id'] ?>" target="_blank" class="btn btn-sm btn-success btn-action">
                                    <i class="fas fa-print"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus order kerja ini?')">
                                    <input type="hidden" name="delete" value="<?= $row['ok_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-action">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Tidak ada data order kerja</td>
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

// Preview Detail - Load on page load if editing
<?php if($editRow): ?>
window.addEventListener('DOMContentLoaded', function() {
    loadPreview(<?= $editRow['kapal_id'] ?>);
});
<?php endif; ?>

// Preview Detail
document.getElementById('kapal_id').addEventListener('change', function() {
    loadPreview(this.value);
});

async function loadPreview(kapalId) {
    const preview = document.getElementById('previewDetail');
    
    if(!kapalId) {
        preview.innerHTML = '<p class="text-muted text-center">Pilih Kapal untuk melihat detail per area</p>';
        return;
    }
    
    preview.innerHTML = '<p class="text-center"><i class="fas fa-hourglass-split"></i> Memuat data...</p>';
    
    try {
        const response = await fetch('get_ok_detail.php?kapal_id=' + kapalId);
        const data = await response.json();
        
        if(data.success && data.areas.length > 0) {
            let html = '<div class="table-responsive"><table class="table table-sm detail-table mb-0">';
            html += '<thead><tr><th>No</th><th>Area</th><th>QTY (Ton)</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';
            
            let no = 1;
            data.areas.forEach(area => {
                html += `<tr>
                    <td>${no++}</td>
                    <td>${area.area}</td>
                    <td class="text-justify">${area.qty}</td>
                    <td class="text-justify">Rp ${area.harga}</td>
                    <td class="text-justify fw-bold">Rp ${area.subtotal}</td>
                </tr>`;
            });
            
            html += `</tbody><tfoot>
                <tr class="table-info">
                    <th colspan="2">TOTAL</th>
                    <th class="text-justify">${data.total_qty} Ton</th>
                    <th></th>
                    <th class="text-justify">Rp ${data.total_biaya}</th>
                </tr>
            </tfoot></table></div>`;
            
            preview.innerHTML = html;
        } else {
            preview.innerHTML = '<p class="text-danger">Tidak ada data Sales Order untuk kapal ini</p>';
        }
    } catch(e) {
        preview.innerHTML = '<p class="text-danger">Error: ' + e.message + '</p>';
    }
}

// Search Order Kerja
document.getElementById('searchOK').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#okTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

function exportToExcel() {
    // Ambil data dari tabel
    const table = document.querySelector('#okTable').closest('table');
    const rows = [];
    
    // Header
    const headers = ['No', 'No. OK', 'Tanggal', 'Partner', 'Kapal', 'Total Tonase', 'Potongan %', 'Denda'];
    rows.push(headers);
    
    // Data rows (hanya yang visible)
    const tableRows = document.querySelectorAll('#okTable tr');
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
                row.cells[7].textContent.replace('Rp ', '').trim()
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
        { wch: 25 }, // No. OK
        { wch: 12 }, // Tanggal
        { wch: 15 }, // Partner
        { wch: 25 }, // Kapal
        { wch: 15 }, // Total Tonase
        { wch: 12 }, // Potongan %
        { wch: 15 }  // Denda
    ];
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Order Kerja');
    
    // Generate filename dengan tanggal
    const today = new Date();
    const filename = `Order_Kerja_${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}.xlsx`;
    
    // Download file
    XLSX.writeFile(wb, filename);
}
</script>

</body>
</html>