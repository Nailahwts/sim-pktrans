<?php
session_start();
include "../config/database.php"; // koneksi DB

$username = $_SESSION['admin'];

// 🔹 Update data lama agar kolom foreign key terisi sesuai tipe
$conn->query("UPDATE harga SET asal_dermaga_id = asal_id WHERE asal_tipe = 'dermaga'");
$conn->query("UPDATE harga SET asal_warehouse_id = asal_id WHERE asal_tipe = 'warehouse'");
$conn->query("UPDATE harga SET tujuan_dermaga_id = tujuan_id WHERE tujuan_tipe = 'dermaga'");
$conn->query("UPDATE harga SET tujuan_warehouse_id = tujuan_id WHERE tujuan_tipe = 'warehouse'");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete') {
        $harga_id = $_POST['harga_id'] ?? null;
        if ($harga_id) {
            // Check if harga is used in sales_order
            $check_usage = $conn->prepare("SELECT COUNT(*) FROM sales_order WHERE harga_id = ?");
            $check_usage->bind_param("i", $harga_id);
            $check_usage->execute();
            $check_usage->bind_result($usage_count);
            $check_usage->fetch();
            $check_usage->close();

            if ($usage_count > 0) {
                $error_message = "Data harga tidak dapat dihapus karena masih digunakan dalam Sales Order.";
            } else {
                $stmt = $conn->prepare("DELETE FROM harga WHERE harga_id = ?");
                $stmt->bind_param("i", $harga_id);
                $stmt->execute();
                $stmt->close();
                $success_message = "Data harga berhasil dihapus.";
            }
        }
    } else {
        // For add and edit
        $harga_id = $_POST['harga_id'] ?? null;
        $asal_tipe = $_POST['asal_tipe'];
        $asal_id = $_POST['asal_id'];
        $tujuan_tipe = $_POST['tujuan_tipe'];
        $tujuan_id = $_POST['tujuan_id'];
        $area = $_POST['area'];
        $partner_id = !empty($_POST['partner_id']) ? $_POST['partner_id'] : NULL;
        $harga = $_POST['harga'];
        $tanggal = !empty($_POST['tanggal']) ? $_POST['tanggal'] : NULL;

        // Validasi asal
        $asal_table = $asal_tipe === 'dermaga' ? 'dermaga' : 'warehouse';
        $asal_col = $asal_tipe === 'dermaga' ? 'dermaga_id' : 'warehouse_id';
        $check_asal = $conn->prepare("SELECT COUNT(*) FROM $asal_table WHERE $asal_col = ?");
        $check_asal->bind_param("i", $asal_id);
        $check_asal->execute();
        $check_asal->bind_result($asal_count);
        $check_asal->fetch();
        $check_asal->close();

        // Validasi tujuan
        $tujuan_table = $tujuan_tipe === 'dermaga' ? 'dermaga' : 'warehouse';
        $tujuan_col = $tujuan_tipe === 'dermaga' ? 'dermaga_id' : 'warehouse_id';
        $check_tujuan = $conn->prepare("SELECT COUNT(*) FROM $tujuan_table WHERE $tujuan_col = ?");
        $check_tujuan->bind_param("i", $tujuan_id);
        $check_tujuan->execute();
        $check_tujuan->bind_result($tujuan_count);
        $check_tujuan->fetch();
        $check_tujuan->close();

        if ($asal_count == 0) {
            $error_message = "Asal ID tidak valid untuk tipe $asal_tipe.";
        } elseif ($tujuan_count == 0) {
            $error_message = "Tujuan ID tidak valid untuk tipe $tujuan_tipe.";
        } else {
            // Tentukan kolom foreign key
            $asal_dermaga_id = $asal_tipe === 'dermaga' ? $asal_id : NULL;
            $asal_warehouse_id = $asal_tipe === 'warehouse' ? $asal_id : NULL;
            $tujuan_dermaga_id = $tujuan_tipe === 'dermaga' ? $tujuan_id : NULL;
            $tujuan_warehouse_id = $tujuan_tipe === 'warehouse' ? $tujuan_id : NULL;

            if ($action === 'add') {
                $stmt = $conn->prepare("
                    INSERT INTO harga (
                        asal_tipe, asal_id, asal_dermaga_id, asal_warehouse_id,
                        tujuan_tipe, tujuan_id, tujuan_dermaga_id, tujuan_warehouse_id,
                        area, partner_id, harga, tanggal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "siiisiiisids",
                    $asal_tipe, $asal_id, $asal_dermaga_id, $asal_warehouse_id,
                    $tujuan_tipe, $tujuan_id, $tujuan_dermaga_id, $tujuan_warehouse_id,
                    $area, $partner_id, $harga, $tanggal
                );
                $stmt->execute();
                $stmt->close();
                $success_message = "Data harga berhasil ditambahkan.";
            } elseif ($action === 'edit' && $harga_id) {
                $stmt = $conn->prepare("
                    UPDATE harga SET
                        asal_tipe = ?, asal_id = ?, asal_dermaga_id = ?, asal_warehouse_id = ?,
                        tujuan_tipe = ?, tujuan_id = ?, tujuan_dermaga_id = ?, tujuan_warehouse_id = ?,
                        area = ?, partner_id = ?, harga = ?, tanggal = ?
                    WHERE harga_id = ?
                ");
                $stmt->bind_param(
                    "siiisiiisidsi",
                    $asal_tipe, $asal_id, $asal_dermaga_id, $asal_warehouse_id,
                    $tujuan_tipe, $tujuan_id, $tujuan_dermaga_id, $tujuan_warehouse_id,
                    $area, $partner_id, $harga, $tanggal, $harga_id
                );
                $stmt->execute();
                $stmt->close();
                $success_message = "Data harga berhasil diupdate.";
            }
        }
    }
}

// --- Fetch data untuk tampilan (opsional filter) ---
$filter_asal_tipe = $_GET['filter_asal_tipe'] ?? '';
$filter_tujuan_tipe = $_GET['filter_tujuan_tipe'] ?? '';
$filter_partner = $_GET['filter_partner'] ?? '';
$search = $_GET['search'] ?? '';

$query = "
SELECT h.*, p.nama_partner,
       COALESCE(d.nama_dermaga, w.nama_warehouse) AS nama_asal,
       COALESCE(td.nama_dermaga, tw.nama_warehouse) AS nama_tujuan
FROM harga h
LEFT JOIN partner p ON h.partner_id = p.partner_id
LEFT JOIN dermaga d ON h.asal_dermaga_id = d.dermaga_id
LEFT JOIN warehouse w ON h.asal_warehouse_id = w.warehouse_id
LEFT JOIN dermaga td ON h.tujuan_dermaga_id = td.dermaga_id
LEFT JOIN warehouse tw ON h.tujuan_warehouse_id = tw.warehouse_id
WHERE 1=1
";

// Filter opsional
if (!empty($filter_asal_tipe)) {
    $query .= " AND h.asal_tipe = '" . $conn->real_escape_string($filter_asal_tipe) . "'";
}
if (!empty($filter_tujuan_tipe)) {
    $query .= " AND h.tujuan_tipe = '" . $conn->real_escape_string($filter_tujuan_tipe) . "'";
}
if (!empty($filter_partner)) {
    $query .= " AND h.partner_id = " . intval($filter_partner);
}
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $query .= " AND (h.area LIKE '%$search_term%' 
                OR p.nama_partner LIKE '%$search_term%'
                OR h.harga LIKE '%$search_term%')";
}

$query .= " ORDER BY h.harga_id DESC";
$result = $conn->query($query);

// Get dermaga list
$dermaga_result = $conn->query("SELECT * FROM dermaga ORDER BY nama_dermaga");

// Get warehouse list
$warehouse_result = $conn->query("SELECT * FROM warehouse ORDER BY nama_warehouse");

// Get partner list
$partner_result = $conn->query("SELECT * FROM partner ORDER BY nama_partner");
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Harga - PKT System</title>
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

        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Overlay -->
<div id="overlay"></div>

<!-- Header -->
<div class="header">
    <div class="title">Master Data Harga</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-fluid mt-4 px-4">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Data Harga</h5>
            
                <br>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle"></i> Tambah Harga
                    </button>
                </div>
        </div>
        <div class="card-body">
            <!-- Filter & Search Section -->
             
            <div class="filter-section">
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-2">
                        <label class="form-label">Asal Tipe</label>
                        <select class="form-select form-select-sm" name="filter_asal_tipe" onchange="this.form.submit()">
                            <option value="">Semua</option>
                            <option value="dermaga" <?= $filter_asal_tipe == 'dermaga' ? 'selected' : '' ?>>Dermaga</option>
                            <option value="warehouse" <?= $filter_asal_tipe == 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tujuan Tipe</label>
                        <select class="form-select form-select-sm" name="filter_tujuan_tipe" onchange="this.form.submit()">
                            <option value="">Semua</option>
                            <option value="dermaga" <?= $filter_tujuan_tipe == 'dermaga' ? 'selected' : '' ?>>Dermaga</option>
                            <option value="warehouse" <?= $filter_tujuan_tipe == 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Partner</label>
                        <select class="form-select form-select-sm" name="filter_partner" onchange="this.form.submit()">
                            <option value="">Semua Partner</option>
                            <?php 
                            mysqli_data_seek($partner_result, 0);
                            while ($p = $partner_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $p['partner_id'] ?>" <?= $filter_partner == $p['partner_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama_partner']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pencarian</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="search" placeholder="Cari area, partner, harga..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> Cari
                            </button>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <a href="harga.php" class="btn btn-secondary btn-sm d-block">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </form>
            </div>


            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Asal</th>
                            <th>Tujuan</th>
                            <th>Area</th>
                            <th>Partner</th>
                            <th>Harga</th>
                            <th>Tanggal</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result->num_rows > 0):
                            $no = 1;
                            while ($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo strtoupper($row['asal_tipe']); ?></span><br>
                                <small><?php echo htmlspecialchars($row['nama_asal']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo strtoupper($row['tujuan_tipe']); ?></span><br>
                                <small><?php echo htmlspecialchars($row['nama_tujuan']); ?></small>
                            </td>
                            <td><?php echo $row['area'] ? htmlspecialchars($row['area']) : '-'; ?></td>
                            <td><?php echo $row['nama_partner'] ? htmlspecialchars($row['nama_partner']) : '-'; ?></td>
                            <td><strong>Rp <?php echo number_format($row['harga'], 2, ',', '.'); ?></strong></td>
                            <td><?php echo $row['tanggal'] ? date('d/m/Y', strtotime($row['tanggal'])) : '-'; ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning btn-action" onclick="editHarga(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteHarga(<?php echo $row['harga_id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data harga ditemukan</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Harga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Asal Tipe *</label>
                        <select class="form-select" name="asal_tipe" id="asal_tipe_add" required onchange="updateAsalOptions('add')">
                            <option value="">Pilih Tipe</option>
                            <option value="dermaga">Dermaga</option>
                            <option value="warehouse">Warehouse</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Asal *</label>
                        <select class="form-select" name="asal_id" id="asal_id_add" required disabled>
                            <option value="">Pilih Asal</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tujuan Tipe *</label>
                        <select class="form-select" name="tujuan_tipe" id="tujuan_tipe_add" required onchange="updateTujuanOptions('add')">
                            <option value="">Pilih Tipe</option>
                            <option value="dermaga">Dermaga</option>
                            <option value="warehouse">Warehouse</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tujuan *</label>
                        <select class="form-select" name="tujuan_id" id="tujuan_id_add" required disabled>
                            <option value="">Pilih Tujuan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Area</label>
                        <input type="text" class="form-control" name="area" placeholder="Masukkan area">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partner</label>
                        <select class="form-select" name="partner_id">
                            <option value="">Pilih Partner (Opsional)</option>
                            <?php 
                            mysqli_data_seek($partner_result, 0);
                            while ($partner = $partner_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $partner['partner_id']; ?>">
                                    <?php echo htmlspecialchars($partner['nama_partner']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Harga *</label>
                        <input type="number" step="0.01" class="form-control" name="harga" required placeholder="Masukkan harga">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Harga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="harga_id" id="edit_harga_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Asal Tipe *</label>
                        <select class="form-select" name="asal_tipe" id="asal_tipe_edit" required onchange="updateAsalOptions('edit')">
                            <option value="">Pilih Tipe</option>
                            <option value="dermaga">Dermaga</option>
                            <option value="warehouse">Warehouse</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Asal *</label>
                        <select class="form-select" name="asal_id" id="asal_id_edit" required>
                            <option value="">Pilih Asal</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tujuan Tipe *</label>
                        <select class="form-select" name="tujuan_tipe" id="tujuan_tipe_edit" required onchange="updateTujuanOptions('edit')">
                            <option value="">Pilih Tipe</option>
                            <option value="dermaga">Dermaga</option>
                            <option value="warehouse">Warehouse</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tujuan *</label>
                        <select class="form-select" name="tujuan_id" id="tujuan_id_edit" required>
                            <option value="">Pilih Tujuan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Area</label>
                        <input type="text" class="form-control" name="area" id="edit_area" placeholder="Masukkan area">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partner</label>
                        <select class="form-select" name="partner_id" id="edit_partner_id">
                            <option value="">Pilih Partner (Opsional)</option>
                            <?php 
                            mysqli_data_seek($partner_result, 0);
                            while ($partner = $partner_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $partner['partner_id']; ?>">
                                    <?php echo htmlspecialchars($partner['nama_partner']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Harga *</label>
                        <input type="number" step="0.01" class="form-control" name="harga" id="edit_harga" required placeholder="Masukkan harga">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" id="edit_tanggal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Harga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="harga_id" id="delete_harga_id">
                    <p>Apakah Anda yakin ingin menghapus data harga ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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

// Data dermaga dan warehouse
const dermagaData = <?php 
    mysqli_data_seek($dermaga_result, 0);
    $dermaga_arr = [];
    while ($d = $dermaga_result->fetch_assoc()) {
        $dermaga_arr[] = $d;
    }
    echo json_encode($dermaga_arr);
?>;

const warehouseData = <?php 
    mysqli_data_seek($warehouse_result, 0);
    $warehouse_arr = [];
    while ($w = $warehouse_result->fetch_assoc()) {
        $warehouse_arr[] = $w;
    }
    echo json_encode($warehouse_arr);
?>;

// Update asal options based on selected type
function updateAsalOptions(mode) {
    const tipeSelect = document.getElementById('asal_tipe_' + mode);
    const asalSelect = document.getElementById('asal_id_' + mode);
    const selectedTipe = tipeSelect.value;
    
    // Reset and disable if no type selected
    asalSelect.innerHTML = '<option value="">Pilih Asal</option>';
    
    if (!selectedTipe) {
        asalSelect.disabled = true;
        return;
    }
    
    asalSelect.disabled = false;
    
    // Populate based on type
    if (selectedTipe === 'dermaga') {
        dermagaData.forEach(item => {
            const option = document.createElement('option');
            option.value = item.dermaga_id;
            option.textContent = item.nama_dermaga;
            asalSelect.appendChild(option);
        });
    } else if (selectedTipe === 'warehouse') {
        warehouseData.forEach(item => {
            const option = document.createElement('option');
            option.value = item.warehouse_id;
            option.textContent = item.nama_warehouse;
            asalSelect.appendChild(option);
        });
    }
}

// Update tujuan options based on selected type
function updateTujuanOptions(mode) {
    const tipeSelect = document.getElementById('tujuan_tipe_' + mode);
    const tujuanSelect = document.getElementById('tujuan_id_' + mode);
    const selectedTipe = tipeSelect.value;
    
    // Reset and disable if no type selected
    tujuanSelect.innerHTML = '<option value="">Pilih Tujuan</option>';
    
    if (!selectedTipe) {
        tujuanSelect.disabled = true;
        return;
    }
    
    tujuanSelect.disabled = false;
    
    // Populate based on type
    if (selectedTipe === 'dermaga') {
        dermagaData.forEach(item => {
            const option = document.createElement('option');
            option.value = item.dermaga_id;
            option.textContent = item.nama_dermaga;
            tujuanSelect.appendChild(option);
        });
    } else if (selectedTipe === 'warehouse') {
        warehouseData.forEach(item => {
            const option = document.createElement('option');
            option.value = item.warehouse_id;
            option.textContent = item.nama_warehouse;
            tujuanSelect.appendChild(option);
        });
    }
}

function editHarga(data) {
    document.getElementById('edit_harga_id').value = data.harga_id;
    document.getElementById('asal_tipe_edit').value = data.asal_tipe;
    updateAsalOptions('edit');
    
    // Wait for options to be populated
    setTimeout(() => {
        document.getElementById('asal_id_edit').value = data.asal_id;
    }, 50);
    
    document.getElementById('tujuan_tipe_edit').value = data.tujuan_tipe;
    updateTujuanOptions('edit');
    
    // Wait for options to be populated
    setTimeout(() => {
        document.getElementById('tujuan_id_edit').value = data.tujuan_id;
    }, 50);
    
    document.getElementById('edit_area').value = data.area || '';
    document.getElementById('edit_partner_id').value = data.partner_id || '';
    document.getElementById('edit_harga').value = data.harga;
    document.getElementById('edit_tanggal').value = data.tanggal || '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteHarga(id) {
    document.getElementById('delete_harga_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html>
