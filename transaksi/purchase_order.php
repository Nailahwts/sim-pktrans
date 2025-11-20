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
    $so_id = $_POST['so_id'];
    $uraian_pekerjaan = $_POST['uraian_pekerjaan'];
    $periode = $_POST['periode'];
    $nomor_po = $_POST['nomor_po'];
    $tanggal_po = $_POST['tanggal_po'];
    $terima_po = isset($_POST['terima_po']) ? $_POST['terima_po'] : NULL;
    $nomor_berita_acara = $_POST['nomor_berita_acara'];
    $tanggal_ba = $_POST['tanggal_ba'];
    $terima_ba = isset($_POST['terima_ba']) ? $_POST['terima_ba'] : NULL;
    
$check = $conn->prepare("SELECT so_id FROM sales_order WHERE so_id = ?");
$check->bind_param("i", $so_id);
$check->execute();
$check_result = $check->get_result();

if($check_result->num_rows === 0) {
    $error = "SO ID tidak ditemukan di tabel sales_order!";
} else {
    $stmt = $conn->prepare("INSERT INTO purchase_order (so_id, uraian_pekerjaan, periode, nomor_po, tanggal_po, terima_po, nomor_berita_acara, tanggal_ba, terima_ba) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $so_id, $uraian_pekerjaan, $periode, $nomor_po, $tanggal_po, $terima_po, $nomor_berita_acara, $tanggal_ba, $terima_ba);

    if($stmt->execute()) {
        $success = "Data Purchase Order berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan data: " . $stmt->error;
    }
}

}

// Proses edit data
if(isset($_POST['edit'])) {
    $po_id = $_POST['po_id'];
    $so_id = $_POST['so_id'];
    $uraian_pekerjaan = $_POST['uraian_pekerjaan'];
    $periode = $_POST['periode'];
    $nomor_po = $_POST['nomor_po'];
    $tanggal_po = $_POST['tanggal_po'];
    $terima_po = isset($_POST['terima_po']) ? $_POST['terima_po'] : NULL;
    $nomor_berita_acara = $_POST['nomor_berita_acara'];
    $tanggal_ba = $_POST['tanggal_ba'];
    $terima_ba = isset($_POST['terima_ba']) ? $_POST['terima_ba'] : NULL;
    
    $stmt = $conn->prepare("UPDATE purchase_order SET so_id=?, uraian_pekerjaan=?, periode=?, nomor_po=?, tanggal_po=?, terima_po=?, nomor_berita_acara=?, tanggal_ba=?, terima_ba=? WHERE po_id=?");
    $stmt->bind_param("issssssssi", $so_id, $uraian_pekerjaan, $periode, $nomor_po, $tanggal_po, $terima_po, $nomor_berita_acara, $tanggal_ba, $terima_ba, $po_id);
    
    if($stmt->execute()) {
        $success = "Data Purchase Order berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data: " . $conn->error;
    }
}if (isset($_GET['hapus'])) {
    $po_id = intval($_GET['hapus']);

    // 1️⃣ Cek apakah masih ada invoice terkait
    $checkInvoice = $conn->prepare("SELECT COUNT(*) FROM invoice WHERE po_id = ?");
    $checkInvoice->bind_param("i", $po_id);
    $checkInvoice->execute();
    $checkInvoice->bind_result($invoice_count);
    $checkInvoice->fetch();
    $checkInvoice->close();

    if ($invoice_count > 0) {
        // Jika masih ada invoice, tampilkan pesan
        $error = "Tidak bisa menghapus Purchase Order karena masih ada Invoice terkait. 
                  Silakan hapus dulu Invoice yang menggunakan PO ini.";
    } else {
        // 2️⃣ Cek apakah masih ada sales_order terkait
        $checkSO = $conn->prepare("SELECT COUNT(*) FROM sales_order WHERE po_id = ?");
        $checkSO->bind_param("i", $po_id);
        $checkSO->execute();
        $checkSO->bind_result($so_count);
        $checkSO->fetch();
        $checkSO->close();

        if ($so_count > 0) {
            $error = "Tidak bisa menghapus Purchase Order karena masih ada Sales Order terkait. 
                      Silakan hapus dulu Dropdown PO di Sales Order.";
        } else {
            // 3️⃣ Kalau tidak ada invoice dan tidak ada SO → hapus PO
            $stmt = $conn->prepare("DELETE FROM purchase_order WHERE po_id=?");
            $stmt->bind_param("i", $po_id);
            if ($stmt->execute()) {
                $success = "Purchase Order berhasil dihapus!";
            } else {
                $error = "Gagal menghapus data: " . $conn->error;
            }
        }
    }
}


// Ambil semua data purchase order dengan join sales order
$query = "SELECT po.*, so.nomor_so 
          FROM purchase_order po 
          LEFT JOIN sales_order so ON po.so_id = so.so_id 
          ORDER BY po.po_id ASC";
$result = $conn->query($query);

// Ambil data sales order untuk dropdown
$query_so = "SELECT so_id, nomor_so FROM sales_order ORDER BY so_id DESC";
$result_so = $conn->query($query_so);

// Ambil kapal aktif untuk uraian pekerjaan
$query_kapal = "SELECT kapal_id, nama_kapal FROM kapal WHERE status='AKTIF' ORDER BY nama_kapal ASC";
$result_kapal = $conn->query($query_kapal);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Order</title>
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
    <div class="title">Purchase Order</div>
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
    <h5><i class="fas fa-shopping-bag"></i> Purchase Order</a></li></h5>
    <div>
        <button onclick="exportToExcel()" class="btn btn-success btn-sm me-2">
            <i class="bi bi-file-earmark-excel"></i> Export ke Excel
        </button>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-circle"></i> Tambah Purchase Order
        </button>
    </div>
</div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablePO" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Sales Order</th>
                            <th>Uraian Pekerjaan</th>
                            <th>Periode</th>
                            <th>Purchase Order</th>
                            <th>Tanggal PO</th>
                            <th>Terima PO</th>
                            <th>Nomor BA</th>
                            <th>Tanggal BA</th>
                            <th>Terima BA</th>
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
                            <td><?= htmlspecialchars($row['nomor_so'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['uraian_pekerjaan']) ?></td>
                            <td><?= htmlspecialchars($row['periode']) ?></td>
                            <td><?= htmlspecialchars($row['nomor_po']) ?></td>
                            <td><?= $row['tanggal_po'] ? date('d/m/Y', strtotime($row['tanggal_po'])) : '-' ?></td>
                            <td><?= $row['terima_po'] ? date('d/m/Y', strtotime($row['terima_po'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['nomor_berita_acara'] ?? '-') ?></td>
                            <td><?= $row['tanggal_ba'] ? date('d/m/Y', strtotime($row['tanggal_ba'])) : '-' ?></td>
                            <td><?= $row['terima_ba'] ? date('d/m/Y', strtotime($row['terima_ba'])) : '-' ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-action" 
                                        onclick="editPO(<?= htmlspecialchars(json_encode($row)) ?>)"> <i class="fas fa-edit"></i>
                                </button>
                                <a href="?hapus=<?= $row['po_id'] ?>" 
                                   class="btn btn-danger btn-sm btn-action"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')"><i class="fas fa-trash"></i>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tambah Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sales Order</label>
                            <select class="form-select" name="so_id" required>
                                <option value="">Pilih Sales Order</option>
                                <?php 
                                $result_so->data_seek(0);
                                while($so = $result_so->fetch_assoc()): 
                                ?>
                                <option value="<?= $so['so_id'] ?>"><?= htmlspecialchars($so['nomor_so']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Uraian Pekerjaan <span class="text-danger">*</span></label>
                            <select class="form-select" name="uraian_pekerjaan" required>
                                <option value="">Pilih Kapal (Uraian Pekerjaan)</option>
                                <?php 
                                $result_kapal->data_seek(0);
                                while($kapal = $result_kapal->fetch_assoc()): 
                                ?>
                                <option value="<?= htmlspecialchars($kapal['nama_kapal']) ?>"><?= htmlspecialchars($kapal['nama_kapal']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Periode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="periode" placeholder="contoh: 01 - 05 DESEMBER 2021" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_po" placeholder="contoh: 5120249596" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal PO</label>
                            <input type="date" class="form-control" name="tanggal_po">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Terima PO</label>
                            <input type="date" class="form-control" name="terima_po">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Berita Acara</label>
                            <input type="text" class="form-control" name="nomor_berita_acara">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal BA</label>
                            <input type="date" class="form-control" name="tanggal_ba">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Terima BA</label>
                        <input type="date" class="form-control" name="terima_ba">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="po_id" id="edit_po_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sales Order</label>
                            <select class="form-select" name="so_id" id="edit_so_id">
                                <option value="">Pilih Sales Order</option>
                                <?php 
                                $result_so->data_seek(0);
                                while($so = $result_so->fetch_assoc()): 
                                ?>
                                <option value="<?= $so['so_id'] ?>"><?= htmlspecialchars($so['nomor_so']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Uraian Pekerjaan <span class="text-danger">*</span></label>
                            <select class="form-select" name="uraian_pekerjaan" id="edit_uraian_pekerjaan" required>
                                <option value="">Pilih Kapal (Uraian Pekerjaan)</option>
                                <?php 
                                $result_kapal->data_seek(0);
                                while($kapal = $result_kapal->fetch_assoc()): 
                                ?>
                                <option value="<?= htmlspecialchars($kapal['nama_kapal']) ?>"><?= htmlspecialchars($kapal['nama_kapal']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Periode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="periode" id="edit_periode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_po" id="edit_nomor_po" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal PO</label>
                            <input type="date" class="form-control" name="tanggal_po" id="edit_tanggal_po">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Terima PO</label>
                            <input type="date" class="form-control" name="terima_po" id="edit_terima_po">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Berita Acara</label>
                            <input type="text" class="form-control" name="nomor_berita_acara" id="edit_nomor_berita_acara">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal BA</label>
                            <input type="date" class="form-control" name="tanggal_ba" id="edit_tanggal_ba">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Terima BA</label>
                        <input type="date" class="form-control" name="terima_ba" id="edit_terima_ba">
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// DataTable
$(document).ready(function() {
    $('#tablePO').DataTable({
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

// Edit PO
function editPO(data) {
    document.getElementById('edit_po_id').value = data.po_id;
    document.getElementById('edit_so_id').value = data.so_id || '';
    document.getElementById('edit_uraian_pekerjaan').value = data.uraian_pekerjaan;
    document.getElementById('edit_periode').value = data.periode;
    document.getElementById('edit_nomor_po').value = data.nomor_po;
    document.getElementById('edit_tanggal_po').value = data.tanggal_po || '';
    document.getElementById('edit_terima_po').value = data.terima_po || '';
    document.getElementById('edit_nomor_berita_acara').value = data.nomor_berita_acara || '';
    document.getElementById('edit_tanggal_ba').value = data.tanggal_ba || '';
    document.getElementById('edit_terima_ba').value = data.terima_ba || '';
    
    var modalEdit = new bootstrap.Modal(document.getElementById('modalEdit'));
    modalEdit.show();
}

function exportToExcel() {
    // Ambil data dari DataTable
    const table = $('#tablePO').DataTable();
    const data = [];

    // Header
    const headers = [
        'No', 'Sales Order', 'Uraian Pekerjaan', 'Periode',
        'Purchase Order', 'Tanggal PO', 'Terima PO',
        'Nomor BA', 'Tanggal BA', 'Terima BA'
    ];
    data.push(headers);

    // Ambil semua data (termasuk yang difilter)
    table.rows({ search: 'applied' }).every(function() {
        const rowNode = this.node();

        // Extract text content dari setiap cell
        const row = [
            $(rowNode).find('td').eq(0).text().trim(), // No
            $(rowNode).find('td').eq(1).text().trim(), // Sales Order
            $(rowNode).find('td').eq(2).text().trim(), // Uraian Pekerjaan
            $(rowNode).find('td').eq(3).text().trim(), // Periode
            $(rowNode).find('td').eq(4).text().trim(), // Purchase Order
            $(rowNode).find('td').eq(5).text().trim(), // Tanggal PO
            $(rowNode).find('td').eq(6).text().trim(), // Terima PO
            $(rowNode).find('td').eq(7).text().trim(), // Nomor BA
            $(rowNode).find('td').eq(8).text().trim(), // Tanggal BA
            $(rowNode).find('td').eq(9).text().trim(), // Terima BA
        ];
        data.push(row);
    });

    // Buat workbook dan worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);

    // Set column widths
    ws['!cols'] = [
        { wch: 5 },  // No
        { wch: 20 }, // Sales Order
        { wch: 30 }, // Uraian Pekerjaan
        { wch: 30 }, // Periode
        { wch: 20 }, // Purchase Order
        { wch: 12 }, // Tanggal PO
        { wch: 12 }, // Terima PO
        { wch: 25 }, // Nomor BA
        { wch: 12 }, // Tanggal BA
        { wch: 12 }  // Terima BA
    ];

    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Purchase Order');

    // Generate filename dengan tanggal
    const today = new Date();
    const filename = `Purchase_Order_${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}.xlsx`;

    // Download file
    XLSX.writeFile(wb, filename);
}
</script>

</body>
</html>