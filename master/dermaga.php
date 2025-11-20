<?php
session_start();
include "../config/database.php";

// cek session admin
if(!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin'];

// ambil username admin
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$username = $admin ? $admin['username'] : "Admin";

// ===== PROSES KAPAL =====
if(isset($_POST['tambah_kapal'])) {
    $nama_kapal = $_POST['nama_kapal'];
    $keterangan = $_POST['keterangan'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO kapal (nama_kapal, keterangan, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama_kapal, $keterangan, $status);
    
    if($stmt->execute()) {
        $success = "Data kapal berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data kapal: " . $conn->error;
    }
}

if(isset($_POST['edit_kapal'])) {
    $kapal_id = $_POST['kapal_id'];
    $nama_kapal = $_POST['nama_kapal'];
    $keterangan = $_POST['keterangan'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE kapal SET nama_kapal=?, keterangan=?, status=? WHERE kapal_id=?");
    $stmt->bind_param("sssi", $nama_kapal, $keterangan, $status, $kapal_id);
    
    if($stmt->execute()) {
        $success = "Data kapal berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data kapal: " . $conn->error;
    }
}

if(isset($_GET['hapus_kapal'])) {
    $kapal_id = $_GET['hapus_kapal'];
    $stmt = $conn->prepare("DELETE FROM kapal WHERE kapal_id=?");
    $stmt->bind_param("i", $kapal_id);
    
    if($stmt->execute()) {
        $success = "Data kapal berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data kapal: " . $conn->error;
    }
}

// ===== PROSES DERMAGA =====
if(isset($_POST['tambah_dermaga'])) {
    $nama_dermaga = $_POST['nama_dermaga'];
    
    $stmt = $conn->prepare("INSERT INTO dermaga (nama_dermaga) VALUES (?)");
    $stmt->bind_param("s", $nama_dermaga);
    
    if($stmt->execute()) {
        $success = "Data dermaga berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data dermaga: " . $conn->error;
    }
}

if(isset($_POST['edit_dermaga'])) {
    $dermaga_id = $_POST['dermaga_id'];
    $nama_dermaga = $_POST['nama_dermaga'];
    
    $stmt = $conn->prepare("UPDATE dermaga SET nama_dermaga=? WHERE dermaga_id=?");
    $stmt->bind_param("si", $nama_dermaga, $dermaga_id);
    
    if($stmt->execute()) {
        $success = "Data dermaga berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data dermaga: " . $conn->error;
    }
}

if(isset($_GET['hapus_dermaga'])) {
    $dermaga_id = $_GET['hapus_dermaga'];
    $stmt = $conn->prepare("DELETE FROM dermaga WHERE dermaga_id=?");
    $stmt->bind_param("i", $dermaga_id);
    
    if($stmt->execute()) {
        $success = "Data dermaga berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data dermaga: " . $conn->error;
    }
}

// ===== PROSES WAREHOUSE =====
if(isset($_POST['tambah_warehouse'])) {
    $nama_warehouse = $_POST['nama_warehouse'];
    
    $stmt = $conn->prepare("INSERT INTO warehouse (nama_warehouse) VALUES (?)");
    $stmt->bind_param("s", $nama_warehouse);
    
    if($stmt->execute()) {
        $success = "Data warehouse berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data warehouse: " . $conn->error;
    }
}

if(isset($_POST['edit_warehouse'])) {
    $warehouse_id = $_POST['warehouse_id'];
    $nama_warehouse = $_POST['nama_warehouse'];
    
    $stmt = $conn->prepare("UPDATE warehouse SET nama_warehouse=? WHERE warehouse_id=?");
    $stmt->bind_param("si", $nama_warehouse, $warehouse_id);
    
    if($stmt->execute()) {
        $success = "Data warehouse berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data warehouse: " . $conn->error;
    }
}

if(isset($_GET['hapus_warehouse'])) {
    $warehouse_id = $_GET['hapus_warehouse'];
    $stmt = $conn->prepare("DELETE FROM warehouse WHERE warehouse_id=?");
    $stmt->bind_param("i", $warehouse_id);
    
    if($stmt->execute()) {
        $success = "Data warehouse berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data warehouse: " . $conn->error;
    }
}

// Ambil data
$kapal_result = $conn->query("SELECT * FROM kapal ORDER BY kapal_id DESC");
$dermaga_result = $conn->query("SELECT * FROM dermaga ORDER BY dermaga_id DESC");
$warehouse_result = $conn->query("SELECT * FROM warehouse ORDER BY warehouse_id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Data Kapal, Dermaga & Warehouse</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="../assets/css/style_master.css"> 
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
    <div class="title">Master Data Kapal, Dermaga, Dan Warehouse</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="container-fluid mt-4 main-content">
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- SECTION KAPAL -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">🚢 Data Kapal</h5>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKapal">
                <i class="bi bi-plus-circle"></i> Tambah Kapal
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableKapal" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kapal</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = $kapal_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_kapal']) ?></strong></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['status']=='AKTIF' ? 'success' : 'secondary' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editKapal(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?hapus_kapal=<?= $row['kapal_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- SECTION DERMAGA -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">⚓ Data Dermaga</h5>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahDermaga">
                <i class="bi bi-plus-circle"></i> Tambah Dermaga
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableDermaga" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Dermaga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = $dermaga_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_dermaga']) ?></strong></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editDermaga(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?hapus_dermaga=<?= $row['dermaga_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- SECTION WAREHOUSE -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Data Warehouse</h5>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahWarehouse">
                <i class="bi bi-plus-circle"></i> Tambah Warehouse
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableWarehouse" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Warehouse</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = $warehouse_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_warehouse']) ?></strong></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editWarehouse(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?hapus_warehouse=<?= $row['warehouse_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KAPAL - Tambah -->
<div class="modal fade" id="modalTambahKapal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tambah Kapal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kapal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kapal" placeholder="Contoh: MV HUA" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2" placeholder="Keterangan tambahan"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" required>
                            <option value="TIDAK AKTIF">TIDAK AKTIF</option>
                            <option value="AKTIF">AKTIF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_kapal" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KAPAL - Edit -->
<div class="modal fade" id="modalEditKapal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Kapal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="kapal_id" id="edit_kapal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kapal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kapal" id="edit_nama_kapal" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="edit_keterangan" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="TIDAK AKTIF">TIDAK AKTIF</option>
                            <option value="AKTIF">AKTIF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_kapal" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DERMAGA - Tambah -->
<div class="modal fade" id="modalTambahDermaga" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Tambah Dermaga</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Dermaga <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_dermaga" placeholder="Contoh: Dermaga Tanjung Perak" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_dermaga" class="btn btn-info">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DERMAGA - Edit -->
<div class="modal fade" id="modalEditDermaga" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Dermaga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="dermaga_id" id="edit_dermaga_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Dermaga <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_dermaga" id="edit_nama_dermaga" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_dermaga" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WAREHOUSE - Tambah -->
<div class="modal fade" id="modalTambahWarehouse" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Tambah Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Warehouse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_warehouse" placeholder="Contoh: Warehouse Surabaya" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_warehouse" class="btn btn-warning">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WAREHOUSE - Edit -->
<div class="modal fade" id="modalEditWarehouse" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="warehouse_id" id="edit_warehouse_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Warehouse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_warehouse" id="edit_nama_warehouse" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_warehouse" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTable
$(document).ready(function() {
    $('#tableKapal').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
    });
    $('#tableDermaga').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
    });
    $('#tableWarehouse').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
    });
});

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
hamburger.addEventListener('click', () => {
    if(sidebar.classList.contains('active')){
        closeSidebar();
    } else {
        openSidebar();
    }
});
overlay.addEventListener('click', closeSidebar);

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        this.classList.toggle('active');
        const submenu = this.nextElementSibling;
        if(submenu){
            submenu.classList.toggle('active');
        }
    });
});

// Edit Kapal
function editKapal(data) {
    document.getElementById('edit_kapal_id').value = data.kapal_id;
    document.getElementById('edit_nama_kapal').value = data.nama_kapal;
    document.getElementById('edit_keterangan').value = data.keterangan || '';
    document.getElementById('edit_status').value = data.status;
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEditKapal'));
    modalEdit.show();
}

// Edit Dermaga
function editDermaga(data) {
    document.getElementById('edit_dermaga_id').value = data.dermaga_id;
    document.getElementById('edit_nama_dermaga').value = data.nama_dermaga;
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEditDermaga'));
    modalEdit.show();
}

// Edit Warehouse
function editWarehouse(data) {
    document.getElementById('edit_warehouse_id').value = data.warehouse_id;
    document.getElementById('edit_nama_warehouse').value = data.nama_warehouse;
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEditWarehouse'));
    modalEdit.show();
}
</script>

</body>
</html>