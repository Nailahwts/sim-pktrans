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

// ===== KAPAL =====
if(isset($_GET['delete_kapal'])){
    $id = intval($_GET['delete_kapal']);
    $conn->query("DELETE FROM kapal WHERE kapal_id=$id");
    echo "<script>alert('Data kapal berhasil dihapus!'); window.location.href='dermaga.php?tab=kapal';</script>";
    exit;
}
if(isset($_POST['update_kapal'])){
    $id = intval($_POST['kapal_id']);
    $nama = $conn->real_escape_string($_POST['nama_kapal']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $status = $_POST['status'];
    $conn->query("UPDATE kapal SET nama_kapal='$nama', keterangan='$ket', status='$status' WHERE kapal_id=$id");
    echo "<script>alert('Data kapal berhasil diupdate!'); window.location.href='dermaga.php?tab=kapal';</script>";
    exit;
}
if(isset($_POST['submit_kapal'])){
    $nama = $conn->real_escape_string($_POST['nama_kapal']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $status = $_POST['status'];
    $conn->query("INSERT INTO kapal (nama_kapal, keterangan, status) VALUES ('$nama','$ket','$status')");
    echo "<script>alert('Data kapal berhasil ditambahkan!'); window.location.href='dermaga.php?tab=kapal';</script>";
    exit;
}

// ===== DERMAGA =====
if(isset($_GET['delete_dermaga'])){
    $id = intval($_GET['delete_dermaga']);
    $conn->query("DELETE FROM dermaga WHERE dermaga_id=$id");
    echo "<script>alert('Data dermaga berhasil dihapus!'); window.location.href='dermaga.php?tab=dermaga';</script>";
    exit;
}
if(isset($_POST['update_dermaga'])){
    $id = intval($_POST['dermaga_id']);
    $nama = $conn->real_escape_string($_POST['nama_dermaga']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $status = $_POST['status'];
    $conn->query("UPDATE dermaga SET nama_dermaga='$nama', keterangan='$ket', status='$status' WHERE dermaga_id=$id");
    echo "<script>alert('Data dermaga berhasil diupdate!'); window.location.href='dermaga.php?tab=dermaga';</script>";
    exit;
}
if(isset($_POST['submit_dermaga'])){
    $nama = $conn->real_escape_string($_POST['nama_dermaga']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $status = $_POST['status'];
    $conn->query("INSERT INTO dermaga (nama_dermaga, keterangan, status) VALUES ('$nama','$ket','$status')");
    echo "<script>alert('Data dermaga berhasil ditambahkan!'); window.location.href='dermaga.php?tab=dermaga';</script>";
    exit;
}

// ===== WAREHOUSE =====
if(isset($_GET['delete_warehouse'])){
    $id = intval($_GET['delete_warehouse']);
    $conn->query("DELETE FROM warehouse WHERE warehouse_id=$id");
    echo "<script>alert('Data warehouse berhasil dihapus!'); window.location.href='dermaga.php?tab=warehouse';</script>";
    exit;
}
if(isset($_POST['update_warehouse'])){
    $id = intval($_POST['warehouse_id']);
    $nama = $conn->real_escape_string($_POST['nama_warehouse']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $conn->query("UPDATE warehouse SET nama_warehouse='$nama', keterangan='$ket' WHERE warehouse_id=$id");
    echo "<script>alert('Data warehouse berhasil diupdate!'); window.location.href='dermaga.php?tab=warehouse';</script>";
    exit;
}
if(isset($_POST['submit_warehouse'])){
    $nama = $conn->real_escape_string($_POST['nama_warehouse']);
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $conn->query("INSERT INTO warehouse (nama_warehouse, keterangan) VALUES ('$nama','$ket')");
    echo "<script>alert('Data warehouse berhasil ditambahkan!'); window.location.href='dermaga.php?tab=warehouse';</script>";
    exit;
}

// Ambil data
$kapal = $conn->query("SELECT * FROM kapal ORDER BY nama_kapal ASC");
$dermaga = $conn->query("SELECT * FROM dermaga ORDER BY nama_dermaga ASC");
$warehouse = $conn->query("SELECT * FROM warehouse ORDER BY nama_warehouse ASC");

// Mode edit
$editKapal = null;
$editDermaga = null;
$editWarehouse = null;
if(isset($_GET['edit_kapal'])){
    $id = intval($_GET['edit_kapal']);
    $res = $conn->query("SELECT * FROM kapal WHERE kapal_id=$id");
    $editKapal = $res->fetch_assoc();
}
if(isset($_GET['edit_dermaga'])){
    $id = intval($_GET['edit_dermaga']);
    $res = $conn->query("SELECT * FROM dermaga WHERE dermaga_id=$id");
    $editDermaga = $res->fetch_assoc();
}
if(isset($_GET['edit_warehouse'])){
    $id = intval($_GET['edit_warehouse']);
    $res = $conn->query("SELECT * FROM warehouse WHERE warehouse_id=$id");
    $editWarehouse = $res->fetch_assoc();
}

// Tab aktif
$activeTab = $_GET['tab'] ?? 'kapal';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Dermaga, Kapal & Warehouse</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
.main-content { margin-right: 10px; }
.sidebar { position: fixed; top:0; left:-285px; width:285px; height:100%; background:#0056b3; color:#fff; overflow-y:auto; transition:left 0.3s ease; z-index:1050;}
.sidebar.active { left:0; }
.sidebar-header { padding:15px; background:#007bff; }
.sidebar-menu { list-style:none; padding:0; margin:0; }
.sidebar-menu li { padding:3px 10px; }
.sidebar-menu li a { color:#fff; text-decoration:none; display:block; padding:10px; border-radius:3px; transition:0.3s;}
.sidebar-menu li a:hover { background:#3399ff; padding-left:20px; }
.hamburger-btn { position: fixed; top:15px; left:15px; z-index:1100; background:#007bff; border:none; color:#fff; padding:8px 12px; font-size:20px; cursor:pointer; border-radius:4px; transition:left 0.3s ease;}
.hamburger-btn.shifted { left:300px; }
#overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:none; z-index:1040;}
#overlay.active { display:block; }
.submenu { display:none; padding-left:15px; list-style:none; }
.submenu.active { display:block !important; }
.arrow { float:right; transition:transform 0.3s ease; }
.arrow::before { content:'▼'; font-size:12px; margin-left:5px; }
.dropdown-toggle.active .arrow::before { content:'▲'; }
.dropdown-toggle::after { display:none; }
.header { position:sticky; top:0; left:0; right:0; height:75px; background:#007bff; color:#fff; display:flex; align-items:center; justify-content:space-between; padding:0 20px; z-index:1030; box-shadow:0 2px 5px rgba(0,0,0,0.2);}
.header .title { margin-left:50px; font-size:20px; font-weight:bold; }
.header .user-info { display:flex; align-items:center; gap:10px; font-size:14px; }
.header .user-info a { color:#fff; text-decoration:none; }
.header .user-info a.logout-btn {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color:#fff;
    padding:6px 14px;
    border-radius:20px;
    text-decoration:none;
    font-weight:500;
    transition:all 0.3s ease;
    box-shadow:0 3px 6px rgba(0,0,0,0.16);
}
.header .user-info a.logout-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 12px rgba(0,0,0,0.2);
    filter:brightness(1.1);
}

/* Tab Navigation Style */
.nav-tabs-custom {
    border-bottom: 2px solid #007bff;
    margin-bottom: 20px;
}
.nav-tabs-custom .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 24px;
    transition: all 0.3s ease;
    border-radius: 0;
}
.nav-tabs-custom .nav-link:hover {
    color: #007bff;
    background-color: #f8f9fa;
}
.nav-tabs-custom .nav-link.active {
    color: #007bff;
    border-bottom: 3px solid #007bff;
    background-color: transparent;
}
.tab-content-wrapper {
    animation: fadeIn 0.3s ease-in;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<!-- Hamburger Button -->
<button class="hamburger-btn" id="hamburgerBtn">&#9776;</button>

<!-- Sidebar -->
<div id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h3>Data Dermaga</h3>
    </div>
    <br>
    <ul class="sidebar-menu">
        <li><a href="../index.php">Dashboard</a></li>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">Master Data <span class="arrow"></span></a>
            <ul class="submenu">
                <li><a href="../master/perusahaan.php">Perusahaan</a></li>
                <li><a href="../master/barang.php">Barang</a></li>
                <li><a href="../master/dermaga.php">Dermaga</a></li>
                <li><a href="../master/kendaraan.php">Kendaraan</a></li>
                <li><a href="../master/kontrak.php">Kontrak</a></li>
                <li><a href="../master/ttd.php">Tanda Tangan</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">Transaksi <span class="arrow"></span></a>
            <ul class="submenu">
                <li><a href="../transaksi/order_kerja.php">Order Kerja</a></li>
                
                <li><a href="../laporan/surat_jalan.php">Surat Jalan</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">Laporan <span class="arrow"></span></a>
            <ul class="submenu">
                
                <li><a href="../laporan/realisasi.php">Realisasi</a></li>
            </ul>
        </li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</div>

<!-- Overlay -->
<div id="overlay"></div>

<!-- Header -->
<div class="header">
    <div class="title">Master Data - Dermaga, Kapal & Warehouse</div>
    <div class="user-info">
        <?= htmlspecialchars($username) ?>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="container-fluid mt-4 main-content">
    
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs nav-tabs-custom">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab=='kapal' ? 'active' : '' ?>" href="?tab=kapal">
                <i class="bi bi-ship"></i> Kapal (MV)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab=='dermaga' ? 'active' : '' ?>" href="?tab=dermaga">
                <i class="bi bi-water"></i> Dermaga
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab=='warehouse' ? 'active' : '' ?>" href="?tab=warehouse">
                <i class="bi bi-building"></i> Warehouse (GD)
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content-wrapper">
        
        <!-- ===== TAB KAPAL ===== -->
        <?php if($activeTab == 'kapal'): ?>
        <div class="row">
            <div class="col-12">
                <h2>Data Kapal (MV)</h2>
                
                <!-- Form Tambah / Update Kapal -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= isset($editKapal) ? 'Edit' : 'Tambah' ?> Kapal</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="kapal_id" value="<?= $editKapal['kapal_id'] ?? '' ?>">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Nama Kapal</label>
                                    <input type="text" name="nama_kapal" class="form-control" value="<?= $editKapal['nama_kapal'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control" value="<?= $editKapal['keterangan'] ?? '' ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="Aktif" <?= isset($editKapal) && $editKapal['status']=='Aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="Nonaktif" <?= isset($editKapal) && $editKapal['status']=='Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="<?= isset($editKapal) ? 'update_kapal' : 'submit_kapal' ?>" class="btn btn-primary">
                                <?= isset($editKapal) ? 'Update' : 'Tambah' ?>
                            </button>
                            <?php if(isset($editKapal)): ?>
                                <a href="dermaga.php?tab=kapal" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-success" onclick="exportExcel('tableKapal', 'Data_Kapal.xlsx')">Ekspor ke Excel</button>
                </div>

                <!-- Table Kapal -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Daftar Kapal</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="tableKapal">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width:5%">No</th>
                                        <th>Nama Kapal</th>
                                        <th>Keterangan</th>
                                        <th style="width:10%">Status</th>
                                        <th style="width:15%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($kapal->num_rows>0): $no=1; while($row=$kapal->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                                        <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge <?= $row['status']=='Aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-warning btn-sm" href="dermaga.php?tab=kapal&edit_kapal=<?= $row['kapal_id'] ?>">Edit</a>
                                            <a class="btn btn-danger btn-sm" href="#" onclick="return confirmDelete('dermaga.php?tab=kapal&delete_kapal=<?= $row['kapal_id'] ?>')">Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Tidak ada data kapal</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== TAB DERMAGA ===== -->
        <?php if($activeTab == 'dermaga'): ?>
        <div class="row">
            <div class="col-12">
                <h2>Data Dermaga</h2>
                
                <!-- Form Tambah / Update Dermaga -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= isset($editDermaga) ? 'Edit' : 'Tambah' ?> Dermaga</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="dermaga_id" value="<?= $editDermaga['dermaga_id'] ?? '' ?>">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Nama Dermaga</label>
                                    <input type="text" name="nama_dermaga" class="form-control" value="<?= $editDermaga['nama_dermaga'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control" value="<?= $editDermaga['keterangan'] ?? '' ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="Aktif" <?= isset($editDermaga) && $editDermaga['status']=='Aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="Nonaktif" <?= isset($editDermaga) && $editDermaga['status']=='Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="<?= isset($editDermaga) ? 'update_dermaga' : 'submit_dermaga' ?>" class="btn btn-primary">
                                <?= isset($editDermaga) ? 'Update' : 'Tambah' ?>
                            </button>
                            <?php if(isset($editDermaga)): ?>
                                <a href="dermaga.php?tab=dermaga" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-success" onclick="exportExcel('tableDermaga', 'Data_Dermaga.xlsx')">Ekspor ke Excel</button>
                </div>

                <!-- Table Dermaga -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Daftar Dermaga</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="tableDermaga">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width:5%">No</th>
                                        <th>Nama Dermaga</th>
                                        <th>Keterangan</th>
                                        <th style="width:10%">Status</th>
                                        <th style="width:15%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($dermaga->num_rows>0): $no=1; while($row=$dermaga->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_dermaga']) ?></td>
                                        <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge <?= $row['status']=='Aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-warning btn-sm" href="dermaga.php?tab=dermaga&edit_dermaga=<?= $row['dermaga_id'] ?>">Edit</a>
                                            <a class="btn btn-danger btn-sm" href="#" onclick="return confirmDelete('dermaga.php?tab=dermaga&delete_dermaga=<?= $row['dermaga_id'] ?>')">Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Tidak ada data dermaga</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== TAB WAREHOUSE ===== -->
        <?php if($activeTab == 'warehouse'): ?>
        <div class="row">
            <div class="col-12">
                <h2>Data Warehouse (GD)</h2>
                
                <!-- Form Tambah / Update Warehouse -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= isset($editWarehouse) ? 'Edit' : 'Tambah' ?> Warehouse</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="warehouse_id" value="<?= $editWarehouse['warehouse_id'] ?? '' ?>">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nama Warehouse</label>
                                    <input type="text" name="nama_warehouse" class="form-control" value="<?= $editWarehouse['nama_warehouse'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control" value="<?= $editWarehouse['keterangan'] ?? '' ?>">
                                </div>
                            </div>
                            <button type="submit" name="<?= isset($editWarehouse) ? 'update_warehouse' : 'submit_warehouse' ?>" class="btn btn-primary">
                                <?= isset($editWarehouse) ? 'Update' : 'Tambah' ?>
                            </button>
                            <?php if(isset($editWarehouse)): ?>
                                <a href="dermaga.php?tab=warehouse" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-success" onclick="exportExcel('tableWarehouse', 'Data_Warehouse.xlsx')">Ekspor ke Excel</button>
                </div>

                <!-- Table Warehouse -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Daftar Warehouse</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="tableWarehouse">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width:5%">No</th>
                                        <th>Nama Warehouse</th>
                                        <th>Keterangan</th>
                                        <th style="width:15%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($warehouse->num_rows>0): $no=1; while($row=$warehouse->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_warehouse']) ?></td>
                                        <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <a class="btn btn-warning btn-sm" href="dermaga.php?tab=warehouse&edit_warehouse=<?= $row['warehouse_id'] ?>">Edit</a>
                                            <a class="btn btn-danger btn-sm" href="#" onclick="return confirmDelete('dermaga.php?tab=warehouse&delete_warehouse=<?= $row['warehouse_id'] ?>')">Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Tidak ada data warehouse</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hamburger toggle
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
hamburger.addEventListener('click',()=>{ sidebar.classList.toggle('active'); overlay.classList.toggle('active'); hamburger.classList.toggle('shifted'); });
overlay.addEventListener('click', ()=>{ sidebar.classList.remove('active'); overlay.classList.remove('active'); hamburger.classList.remove('shifted'); });

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(toggle=>{
    toggle.addEventListener('click', function(e){ e.preventDefault(); this.classList.toggle('active'); this.nextElementSibling.classList.toggle('active'); });
});

// Ekspor Excel
function exportExcel(tableId, filename){
    var wb = XLSX.utils.table_to_book(document.getElementById(tableId), {sheet:"Data"});
    XLSX.writeFile(wb, filename);
}

// Confirm delete
function confirmDelete(url){
    if(confirm('Apakah Anda yakin ingin menghapus data ini?')){
        window.location.href=url;
        return true;
    }
    return false;
}
</script>
</body>
</html>