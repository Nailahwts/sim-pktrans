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
    $nomor_so = $_POST['nomor_so'];
    $tanggal = $_POST['tanggal'];
    $partner_id = $_POST['partner_id'];
    $barang_id = $_POST['barang_id'];
    $kapal_id = $_POST['kapal_id'];
    $periode = $_POST['periode'];
    $po_id = !empty($_POST['po_id']) ? intval($_POST['po_id']) : NULL;
    $harga_id = $_POST['harga_id'];
    $qty = floatval($_POST['qty']);
    $denda = floatval($_POST['denda']);
    $status = 'UNDONE';
    
    $stmt = $conn->prepare("INSERT INTO sales_order (nomor_so, tanggal, status, partner_id, barang_id, kapal_id, periode, po_id, harga_id, qty, denda) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiisiidd", $nomor_so, $tanggal, $status, $partner_id, $barang_id, $kapal_id, $periode, $po_id, $harga_id, $qty, $denda);

    
    if($stmt->execute()) {
        $success = "Data Sales Order berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data: " . $conn->error;
    }
}

// Proses edit data
if(isset($_POST['edit'])) {
    $so_id = $_POST['so_id'];
    $nomor_so = $_POST['nomor_so'];
    $tanggal = $_POST['tanggal'];
    $partner_id = $_POST['partner_id'];
    $barang_id = $_POST['barang_id'];
    $kapal_id = $_POST['kapal_id'];
    $periode = $_POST['periode'];
    $po_id = !empty($_POST['po_id']) ? intval($_POST['po_id']) : NULL;
    $harga_id = $_POST['harga_id'];
    $qty = floatval($_POST['qty']);
    $denda = floatval($_POST['denda']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE sales_order SET nomor_so=?, tanggal=?, status=?, partner_id=?, barang_id=?, kapal_id=?, periode=?, po_id=?, harga_id=?, qty=?, denda=? WHERE so_id=?");
    $stmt->bind_param("sssiiisisddi", $nomor_so, $tanggal, $status, $partner_id, $barang_id, $kapal_id, $periode, $po_id, $harga_id, $qty, $denda, $so_id);
    
    if($stmt->execute()) {
        $success = "Data Sales Order berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data: " . $conn->error;
    }
}
if(isset($_GET['hapus'])) {
    $so_id = $_GET['hapus'];

    // Cek apakah ada PO yang masih terhubung
    $cek = $conn->prepare("SELECT COUNT(*) FROM purchase_order WHERE so_id=?");
    $cek->bind_param("i", $so_id);
    $cek->execute();
    $cek->bind_result($jumlah);
    $cek->fetch();
    $cek->close();

    if ($jumlah > 0) {
        $error = "Tidak dapat menghapus Sales Order ini karena masih memiliki Purchase Order terkait.";
    } else {
        $stmt = $conn->prepare("DELETE FROM sales_order WHERE so_id=?");
        $stmt->bind_param("i", $so_id);
        if($stmt->execute()) {
            $success = "Data Sales Order berhasil dihapus!";
        } else {
            $error = "Gagal menghapus data: " . $conn->error;
        }
    }
}

// Ambil semua data sales order dengan join dan hitung omzet
$query = "SELECT so.*, 
          p.partner, p.nama_partner,
          b.nama_barang,
          k.nama_kapal,
          h.area, h.harga, h.asal_tipe, h.tujuan_tipe,
          d_asal.nama_dermaga as dermaga_asal,
          w_asal.nama_warehouse as warehouse_asal,
          d_tujuan.nama_dermaga as dermaga_tujuan,
          w_tujuan.nama_warehouse as warehouse_tujuan,
          po.nomor_po,
          (so.qty * h.harga) as omzet
          FROM sales_order so
          JOIN partner p ON so.partner_id = p.partner_id
          JOIN barang b ON so.barang_id = b.barang_id
          JOIN kapal k ON so.kapal_id = k.kapal_id
          LEFT JOIN harga h ON so.harga_id = h.harga_id
          LEFT JOIN dermaga d_asal ON h.asal_id = d_asal.dermaga_id AND h.asal_tipe = 'dermaga'
          LEFT JOIN warehouse w_asal ON h.asal_id = w_asal.warehouse_id AND h.asal_tipe = 'warehouse'
          LEFT JOIN dermaga d_tujuan ON h.tujuan_id = d_tujuan.dermaga_id AND h.tujuan_tipe = 'dermaga'
          LEFT JOIN warehouse w_tujuan ON h.tujuan_id = w_tujuan.warehouse_id AND h.tujuan_tipe = 'warehouse'
          LEFT JOIN purchase_order po ON so.po_id = po.po_id
          ORDER BY so.tanggal ASC, so.so_id ASC";
$result = $conn->query($query);

// Ambil data master untuk dropdown
$partners = $conn->query("SELECT * FROM partner ORDER BY partner ASC");
$barangs = $conn->query("SELECT * FROM barang ORDER BY nama_barang ASC");
$kapals = $conn->query("SELECT * FROM kapal WHERE status = 'AKTIF' ORDER BY nama_kapal ASC");
$pos = $conn->query("SELECT * FROM purchase_order ORDER BY nomor_po ASC");
$hargas = $conn->query("SELECT h.*, 
                        h.asal_tipe, h.tujuan_tipe,
                        d_asal.nama_dermaga as dermaga_asal,
                        w_asal.nama_warehouse as warehouse_asal,
                        d_tujuan.nama_dermaga as dermaga_tujuan,
                        w_tujuan.nama_warehouse as warehouse_tujuan
                        FROM harga h
                        LEFT JOIN dermaga d_asal ON h.asal_id = d_asal.dermaga_id AND h.asal_tipe = 'dermaga'
                        LEFT JOIN warehouse w_asal ON h.asal_id = w_asal.warehouse_id AND h.asal_tipe = 'warehouse'
                        LEFT JOIN dermaga d_tujuan ON h.tujuan_id = d_tujuan.dermaga_id AND h.tujuan_tipe = 'dermaga'
                        LEFT JOIN warehouse w_tujuan ON h.tujuan_id = w_tujuan.warehouse_id AND h.tujuan_tipe = 'warehouse'
                        ORDER BY h.area ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Order</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="../assets/css/style_so.css"> 
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
    <div class="title">Sales Order</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="container-fluid mt-4 main-content">
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Data Sales Order</h5>
            <div>
                <button onclick="exportToExcel()" class="btn btn-success btn-sm me-2">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus-circle"></i> Tambah Sales Order
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="tableSO" class="table table-striped table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nomor SO</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Partner</th>
                            <th>Kapal</th>
                            <th>Periode</th>
                            <th>Nomor PO</th>
                            <th>Barang</th>
                            <th>Asal</th>
                            <th>Tujuan</th>
                            <th>Area</th>
                            <th>Qty (Ton)</th>
                            <th>Harga</th>
                            <th>Omzet</th>
                            <th>Denda</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = $result->fetch_assoc()): 
                            // Tentukan asal dan tujuan berdasarkan tipe
                            $asal = ($row['asal_tipe'] == 'dermaga') ? $row['dermaga_asal'] : $row['warehouse_asal'];
                            $tujuan = ($row['tujuan_tipe'] == 'dermaga') ? $row['dermaga_tujuan'] : $row['warehouse_tujuan'];
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nomor_so']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['status']=='DONE' ? 'success' : 'warning' ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['partner']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                            <td><?= htmlspecialchars($row['periode']) ?></td>
                            <td><?= htmlspecialchars($row['nomor_po'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td>
                                <span class="route-badge route-from">
                                    <?= htmlspecialchars($asal ?: '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="route-badge route-to">
                                    <?= htmlspecialchars($tujuan ?: '-') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['area'] ?: '-') ?></td>
                            <td class="text-end"><?= number_format($row['qty'], 3, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($row['harga'], 2, ',', '.') ?></td>
                            <td class="text-end"><strong><?= number_format($row['omzet'], 2, ',', '.') ?></strong></td>
                            <td class="text-end"><?= number_format($row['denda'], 2, ',', '.') ?></td>
                            <td class="text-nowrap">
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick='editSO(<?= json_encode($row) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?hapus=<?= $row['so_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash"></i>
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Sales Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formTambah">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Nomor SO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_so" required placeholder="PKG/2021/035">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Partner <span class="text-danger">*</span></label>
                            <select class="form-select" name="partner_id" required>
                                <option value="">Pilih Partner</option>
                                <?php 
                                $partners->data_seek(0);
                                while($p = $partners->fetch_assoc()): 
                                ?>
                                <option value="<?= $p['partner_id'] ?>"><?= htmlspecialchars($p['partner']) ?> - <?= htmlspecialchars($p['nama_partner']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Nomor PO</label>
                            <select class="form-select" name="po_id">
                                <option value="">- Pilih PO -</option>
                                <?php 
                                $pos->data_seek(0);
                                while($po = $pos->fetch_assoc()): 
                                ?>
                                <option value="<?= $po['po_id'] ?>"><?= htmlspecialchars($po['nomor_po']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Barang <span class="text-danger">*</span></label>
                            <select class="form-select" name="barang_id" id="add_barang_id" required>
                                <option value="">Pilih Barang</option>
                                <?php 
                                $barangs->data_seek(0);
                                while($b = $barangs->fetch_assoc()): 
                                ?>
                                <option value="<?= $b['barang_id'] ?>"><?= htmlspecialchars($b['nama_barang']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kapal <span class="text-danger">*</span></label>
                            <select class="form-select" name="kapal_id" id="add_kapal_id" required>
                                <option value="">Pilih Kapal</option>
                                <?php 
                                $kapals->data_seek(0);
                                while($k = $kapals->fetch_assoc()): 
                                ?>
                                <option value="<?= $k['kapal_id'] ?>"><?= htmlspecialchars($k['nama_kapal']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Periode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="periode" required placeholder="08 - 13 NOVEMBER 2021">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga (Area) <span class="text-danger">*</span></label>
                            <select class="form-select" name="harga_id" id="add_harga_id" required>
                                <option value="">Pilih Harga/Area</option>
                                <?php 
                                $hargas->data_seek(0);
                                while($h = $hargas->fetch_assoc()): 
                                    $asal_h = ($h['asal_tipe'] == 'dermaga') ? $h['dermaga_asal'] : $h['warehouse_asal'];
                                    $tujuan_h = ($h['tujuan_tipe'] == 'dermaga') ? $h['dermaga_tujuan'] : $h['warehouse_tujuan'];
                                ?>
                                <option value="<?= $h['harga_id'] ?>" data-harga="<?= $h['harga'] ?>">
                                    <?= htmlspecialchars($h['area']) ?> 
                                    (<?= htmlspecialchars($asal_h) ?> → <?= htmlspecialchars($tujuan_h) ?>) 
                                    - Rp <?= number_format($h['harga'], 2, ',', '.') ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Pilih area sesuai rute pengangkutan</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Qty/Tonase <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" name="qty" id="add_qty" required placeholder="0.000">
                            <button type="button" class="btn btn-sm btn-info mt-2 w-100" onclick="autoFillQty('add')">
                                <i class="fas fa-sync-alt"></i> Ambil dari Surat Jalan
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Denda</label>
                            <input type="number" step="0.01" class="form-control" name="denda" value="0" placeholder="0.00">
                        </div>
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Sales Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEdit">
                <input type="hidden" name="so_id" id="edit_so_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Nomor SO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_so" id="edit_nomor_so" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="UNDONE">UNDONE</option>
                                <option value="DONE">DONE</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Partner <span class="text-danger">*</span></label>
                            <select class="form-select" name="partner_id" id="edit_partner_id" required>
                                <option value="">Pilih Partner</option>
                                <?php 
                                $partners->data_seek(0);
                                while($p = $partners->fetch_assoc()): 
                                ?>
                                <option value="<?= $p['partner_id'] ?>"><?= htmlspecialchars($p['partner']) ?> - <?= htmlspecialchars($p['nama_partner']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Nomor PO</label>
                            <select class="form-select" name="po_id" id="edit_po_id">
                                <option value="">- Pilih PO -</option>
                                <?php 
                                $pos->data_seek(0);
                                while($po = $pos->fetch_assoc()): 
                                ?>
                                <option value="<?= $po['po_id'] ?>"><?= htmlspecialchars($po['nomor_po']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Barang <span class="text-danger">*</span></label>
                            <select class="form-select" name="barang_id" id="edit_barang_id" required>
                                <option value="">Pilih Barang</option>
                                <?php 
                                $barangs->data_seek(0);
                                while($b = $barangs->fetch_assoc()): 
                                ?>
                                <option value="<?= $b['barang_id'] ?>"><?= htmlspecialchars($b['nama_barang']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kapal <span class="text-danger">*</span></label>
                            <select class="form-select" name="kapal_id" id="edit_kapal_id" required>
                                <option value="">Pilih Kapal</option>
                                <?php 
                                $kapals->data_seek(0);
                                while($k = $kapals->fetch_assoc()): 
                                ?>
                                <option value="<?= $k['kapal_id'] ?>"><?= htmlspecialchars($k['nama_kapal']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Periode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="periode" id="edit_periode" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga (Area) <span class="text-danger">*</span></label>
                            <select class="form-select" name="harga_id" id="edit_harga_id" required>
                                <option value="">Pilih Harga/Area</option>
                                <?php 
                                $hargas->data_seek(0);
                                while($h = $hargas->fetch_assoc()): 
                                    $asal_h = ($h['asal_tipe'] == 'dermaga') ? $h['dermaga_asal'] : $h['warehouse_asal'];
                                    $tujuan_h = ($h['tujuan_tipe'] == 'dermaga') ? $h['dermaga_tujuan'] : $h['warehouse_tujuan'];
                                ?>
                                <option value="<?= $h['harga_id'] ?>" data-harga="<?= $h['harga'] ?>">
                                    <?= htmlspecialchars($h['area']) ?> 
                                    (<?= htmlspecialchars($asal_h) ?> → <?= htmlspecialchars($tujuan_h) ?>) 
                                    - Rp <?= number_format($h['harga'], 2, ',', '.') ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Pilih area sesuai rute pengangkutan</small>
                        </div>  
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Qty/Tonase <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" name="qty" id="edit_qty" required>
                            <button type="button" class="btn btn-sm btn-info mt-2 w-100" onclick="autoFillQty('edit')">
                                <i class="fas fa-sync-alt"></i> Ambil dari Surat Jalan
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Denda</label>
                            <input type="number" step="0.01" class="form-control" name="denda" id="edit_denda" value="0">
                        </div>  
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    $('#add_harga_id').select2({
        placeholder: "Pilih Harga/Area",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#modalTambah')
    });
    
    $('#edit_harga_id').select2({
        placeholder: "Pilih Harga/Area",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#modalEdit')
    });
});
</script>

<script>
// DataTable
$(document).ready(function() {
    $('#tableSO').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        order: [[1, 'asc']],
        pageLength: 25
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

// Edit Sales Order
function editSO(data) {
    document.getElementById('edit_so_id').value = data.so_id;
    document.getElementById('edit_nomor_so').value = data.nomor_so;
    document.getElementById('edit_tanggal').value = data.tanggal;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_partner_id').value = data.partner_id;
    document.getElementById('edit_barang_id').value = data.barang_id;
    document.getElementById('edit_kapal_id').value = data.kapal_id;
    document.getElementById('edit_periode').value = data.periode || '';
    document.getElementById('edit_po_id').value = data.po_id || '';
    document.getElementById('edit_qty').value = data.qty || 0;
    document.getElementById('edit_denda').value = data.denda || 0;
    
    $('#edit_harga_id').val(data.harga_id).trigger('change');
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));
    modalEdit.show();
}

// Auto fill qty dari surat jalan
function autoFillQty(mode) {
    const prefix = mode === 'add' ? 'add' : 'edit';
    
    const barangId = document.getElementById(prefix + '_barang_id').value;
    const kapalId = document.getElementById(prefix + '_kapal_id').value;
    const hargaId = document.getElementById(prefix + '_harga_id').value;
    
    if(!barangId || !kapalId || !hargaId) {
        alert('Silakan pilih Barang, Kapal, dan Harga terlebih dahulu!');
        return;
    }
    
    $.ajax({
        url: 'get_total_tonase.php',
        method: 'POST',
        data: {
            barang_id: barangId,
            kapal_id: kapalId,
            harga_id: hargaId
        },
        dataType: 'json',
        success: function(response) {
            if(response.success) {
                document.getElementById(prefix + '_qty').value = response.total_tonase;
                alert('Total tonase berhasil diambil: ' + response.total_tonase + ' ton dari ' + response.jumlah_rit + ' rit');
            } else {
                alert('Data tidak ditemukan: ' + response.message);
            }
        },
        error: function() {
            alert('Terjadi kesalahan saat mengambil data!');
        }
    });
}

function exportToExcel() {
    const table = $('#tableSO').DataTable();
    const data = [];
    
    const headers = [
        'No', 'Nomor SO', 'Tanggal', 'Status', 'Partner', 'Kapal', 
        'Periode', 'Nomor PO', 'Barang', 'Asal', 'Tujuan', 'Area', 
        'Qty (Ton)', 'Harga', 'Omzet', 'Denda'
    ];
    data.push(headers);
    
    table.rows({ search: 'applied' }).every(function() {
        const rowData = this.data();
        const rowNode = this.node();
        
        const row = [
            $(rowNode).find('td').eq(0).text().trim(),
            $(rowNode).find('td').eq(1).text().trim(),
            $(rowNode).find('td').eq(2).text().trim(),
            $(rowNode).find('td').eq(3).text().trim(),
            $(rowNode).find('td').eq(4).text().trim(),
            $(rowNode).find('td').eq(5).text().trim(),
            $(rowNode).find('td').eq(6).text().trim(),
            $(rowNode).find('td').eq(7).text().trim(),
            $(rowNode).find('td').eq(8).text().trim(),
            $(rowNode).find('td').eq(9).text().trim(),
            $(rowNode).find('td').eq(10).text().trim(),
            $(rowNode).find('td').eq(11).text().trim(),
            $(rowNode).find('td').eq(12).text().trim().replace(/\./g, '').replace(',', '.'),
            $(rowNode).find('td').eq(13).text().trim().replace(/\./g, '').replace(',', '.'),
            $(rowNode).find('td').eq(14).text().trim().replace(/\./g, '').replace(',', '.'),
            $(rowNode).find('td').eq(15).text().trim().replace(/\./g, '').replace(',', '.'),
        ];
        data.push(row);
    });
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    ws['!cols'] = [
        { wch: 5 }, { wch: 20 }, { wch: 12 }, { wch: 10 }, { wch: 15 }, { wch: 25 },
        { wch: 25 }, { wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 20 }, { wch: 15 },
        { wch: 12 }, { wch: 15 }, { wch: 18 }, { wch: 15 }
    ];
    
    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let R = 1; R <= range.e.r; ++R) {
        const qtyCell = XLSX.utils.encode_cell({ r: R, c: 12 });
        if (ws[qtyCell] && ws[qtyCell].v) {
            ws[qtyCell].t = 'n';
            ws[qtyCell].v = parseFloat(ws[qtyCell].v) || 0;
        }
        
        const hargaCell = XLSX.utils.encode_cell({ r: R, c: 13 });
        if (ws[hargaCell] && ws[hargaCell].v) {
            ws[hargaCell].t = 'n';
            ws[hargaCell].v = parseFloat(ws[hargaCell].v) || 0;
        }
        
        const omzetCell = XLSX.utils.encode_cell({ r: R, c: 14 });
        if (ws[omzetCell] && ws[omzetCell].v) {
            ws[omzetCell].t = 'n';
            ws[omzetCell].v = parseFloat(ws[omzetCell].v) || 0;
        }
        
        const dendaCell = XLSX.utils.encode_cell({ r: R, c: 15 });
        if (ws[dendaCell] && ws[dendaCell].v) {
            ws[dendaCell].t = 'n';
            ws[dendaCell].v = parseFloat(ws[dendaCell].v) || 0;
        }
    }
    
    XLSX.utils.book_append_sheet(wb, ws, 'Sales Order');
    
    const today = new Date();
    const filename = `Sales_Order_${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}.xlsx`;
    
    XLSX.writeFile(wb, filename);
}
</script>

</body>
</html>