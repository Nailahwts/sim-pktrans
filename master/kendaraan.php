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
    $nopol = $_POST['nopol'];
    $jenis = $_POST['jenis'];
    $kategori_partner = $_POST['kategori_partner'];
    $partner_id = $_POST['partner_id'];
    
    $stmt = $conn->prepare("INSERT INTO kendaraan (nopol, jenis, kategori_partner, partner_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $nopol, $jenis, $kategori_partner, $partner_id);
    
    if($stmt->execute()) {
        $success = "Data kendaraan berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data: " . $conn->error;
    }
}

// Proses edit data
if(isset($_POST['edit'])) {
    $kendaraan_id = $_POST['kendaraan_id'];
    $nopol = $_POST['nopol'];
    $jenis = $_POST['jenis'];
    $kategori_partner = $_POST['kategori_partner'];
    $partner_id = $_POST['partner_id'];
    
    $stmt = $conn->prepare("UPDATE kendaraan SET nopol=?, jenis=?, kategori_partner=?, partner_id=? WHERE kendaraan_id=?");
    $stmt->bind_param("sssii", $nopol, $jenis, $kategori_partner, $partner_id, $kendaraan_id);
    
    if($stmt->execute()) {
        $success = "Data kendaraan berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data: " . $conn->error;
    }
}

// Proses hapus data
if(isset($_GET['hapus'])) {
    $kendaraan_id = $_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM kendaraan WHERE kendaraan_id=?");
    $stmt->bind_param("i", $kendaraan_id);
    
    if($stmt->execute()) {
        $success = "Data kendaraan berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data: " . $conn->error;
    }
}

// Ambil semua data kendaraan dengan join partner
$query = "SELECT k.*, p.nama_partner 
          FROM kendaraan k 
          LEFT JOIN partner p ON k.partner_id = p.partner_id 
          ORDER BY k.kendaraan_id DESC";
$result = $conn->query($query);

// Ambil data partner untuk dropdown
$partner_query = "SELECT partner_id, nama_partner, kategori FROM partner ORDER BY nama_partner ASC";
$partner_result = $conn->query($partner_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Data Kendaraan</title>
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
    <div class="title">Master Data Kendaraan</div>
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
            <h5 class="mb-0"><i class="fas fa-truck"></i> Data Kendaraan</h5>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-circle"></i> Tambah Kendaraan
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableKendaraan" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No. Polisi</th>
                            <th>Jenis</th>
                            <th style>Kategori Partner</th>
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
                            <td><strong><?= htmlspecialchars($row['nopol']) ?></strong></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($row['jenis']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $row['kategori_partner']=='Rekanan' ? 'success' : 'primary' ?>">
                                    <?= htmlspecialchars($row['kategori_partner']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['nama_partner']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editKendaraan(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    Edit
                                </button>
                                <a href="?hapus=<?= $row['kendaraan_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
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
<!-- Modal Tambah Kendaraan -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahLabel">Tambah Kendaraan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nopol" class="form-label">No. Polisi</label>
                        <input type="text" class="form-control" id="nopol" name="nopol" required>
                    </div>
                    <div class="mb-3">
                        <label for="jenis" class="form-label">Jenis</label>
                        <input type="text" class="form-control" id="jenis" name="jenis" required>
                    </div>
                    <div class="mb-3">
                        <label for="kategori_partner" class="form-label">Kategori Partner</label>
                        <select class="form-select" id="kategori_partner" name="kategori_partner" required>
                            <option value="Perusahaan">Perusahaan</option>
                            <option value="Rekanan">Rekanan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="partner_id" class="form-label">Partner</label>
                        <select class="form-select" id="partner_id" name="partner_id" required>
                            <option value="">-- Pilih Partner --</option>
                            <?php while($partner = $partner_result->fetch_assoc()): ?>
                                <option value="<?= $partner['partner_id'] ?>">
                                    <?= htmlspecialchars($partner['nama_partner']) ?> (<?= htmlspecialchars($partner['kategori']) ?>)
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
<!-- Modal Edit Kendaraan -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditLabel">Edit Kendaraan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_kendaraan_id" name="kendaraan_id">
                    <div class="mb-3">
                        <label for="edit_nopol" class="form-label">No. Polisi</label>
                        <input type="text" class="form-control" id="edit_nopol" name="nopol" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jenis" class="form-label">Jenis</label>
                        <input type="text" class="form-control" id="edit_jenis" name="jenis" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_kategori_partner" class="form-label">Kategori Partner</label>
                        <select class="form-select" id="edit_kategori_partner" name="kategori_partner" required>
                            <option value="Perusahaan">Perusahaan</option>
                            <option value="Rekanan">Rekanan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_partner_id" class="form-label">Partner</label>
                        <select class="form-select" id="edit_partner_id" name="partner_id" required>
                            <option value="">-- Pilih Partner --</option>
                            <?php 
                            // Reset partner_result pointer and fetch again
                            $partner_result->data_seek(0);
                            while($partner = $partner_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $partner['partner_id'] ?>">
                                    <?= htmlspecialchars($partner['nama_partner']) ?> (<?= htmlspecialchars($partner['kategori']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#tableKendaraan').DataTable();

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');

    hamburgerBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        hamburgerBtn.classList.toggle('shifted');
    });

    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        hamburgerBtn.classList.remove('shifted');
    });

    // Dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(function(dropdown) {
        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('active');
            const submenu = this.nextElementSibling;
            submenu.classList.toggle('active');
        });
    });
});
function editKendaraan(data) {
    $('#edit_kendaraan_id').val(data.kendaraan_id);
    $('#edit_nopol').val(data.nopol);
    $('#edit_jenis').val(data.jenis);
    $('#edit_kategori_partner').val(data.kategori_partner);
    $('#edit_partner_id').val(data.partner_id);
    var editModal = new bootstrap.Modal(document.getElementById('modalEdit'));
    editModal.show();
}
</script>
</body>
</html>
