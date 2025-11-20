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

// Proses tambah data
if(isset($_POST['tambah'])) {
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $partner_id = $_POST['partner_id'];
    
    $stmt = $conn->prepare("INSERT INTO ttd (nama, jabatan, partner_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nama, $jabatan, $partner_id);
    
    if($stmt->execute()) {
        $success = "Data tanda tangan berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data: " . $conn->error;
    }
}

// Proses edit data
if(isset($_POST['edit'])) {
    $ttd_id = $_POST['ttd_id'];
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $partner_id = $_POST['partner_id'];
    
    $stmt = $conn->prepare("UPDATE ttd SET nama=?, jabatan=?, partner_id=? WHERE ttd_id=?");
    $stmt->bind_param("ssii", $nama, $jabatan, $partner_id, $ttd_id);
    
    if($stmt->execute()) {
        $success = "Data tanda tangan berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data: " . $conn->error;
    }
}

// Proses hapus data
if(isset($_GET['hapus'])) {
    $ttd_id = $_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM ttd WHERE ttd_id=?");
    $stmt->bind_param("i", $ttd_id);
    
    if($stmt->execute()) {
        $success = "Data tanda tangan berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data: " . $conn->error;
    }
}

// Ambil semua data ttd dengan join partner
$query = "SELECT t.*, p.nama_partner 
          FROM ttd t 
          LEFT JOIN partner p ON t.partner_id = p.partner_id 
          ORDER BY t.ttd_id ASC";
$result = $conn->query($query);

// Ambil data partner untuk dropdown
$partner_query = "SELECT partner_id, nama_partner FROM partner ORDER BY nama_partner DESC";
$partner_result = $conn->query($partner_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Data Tanda Tangan</title>
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
    <div class="title">Master Data Tanda Tangan</div>
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

    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-signature"></i>. Data Tanda Tangan</h5>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-circle"></i> Tambah Tanda Tangan
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableTTD" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Partner</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                            <td><?= htmlspecialchars($row['jabatan']) ?></td>
                            <td><?= htmlspecialchars($row['nama_partner']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editTTD(<?= htmlspecialchars(json_encode($row)) ?>)"> <i class="fas fa-edit"></i>
                                    Edit
                                </button>
                                <a href="?hapus=<?= $row['ttd_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')"> <i class="fas fa-trash"></i>
                                    Hapus
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tambah Tanda Tangan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" placeholder="Nama lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" name="jabatan" placeholder="Direktur, Manager, dll">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Partner <span class="text-danger">*</span></label>
                        <select class="form-select" name="partner_id" required>
                            <option value="">Pilih Partner</option>
                            <?php 
                            $partner_result->data_seek(0);
                            while($p = $partner_result->fetch_assoc()): 
                            ?>
                            <option value="<?= $p['partner_id'] ?>">
                                <?= htmlspecialchars($p['nama_partner']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Tanda Tangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="ttd_id" id="edit_ttd_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" name="jabatan" id="edit_jabatan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Partner <span class="text-danger">*</span></label>
                        <select class="form-select" name="partner_id" id="edit_partner_id" required>
                            <option value="">Pilih Partner</option>
                            <?php 
                            $partner_result->data_seek(0);
                            while($p = $partner_result->fetch_assoc()): 
                            ?>
                            <option value="<?= $p['partner_id'] ?>">
                                <?= htmlspecialchars($p['nama_partner']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-warning">Update</button>
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
    $('#tableTTD').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        }
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

// Edit TTD
function editTTD(data) {
    document.getElementById('edit_ttd_id').value = data.ttd_id;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_jabatan').value = data.jabatan || '';
    document.getElementById('edit_partner_id').value = data.partner_id;
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));
    modalEdit.show();
}
</script>

</body>
</html>