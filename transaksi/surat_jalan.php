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

// Ambil data master untuk dropdown
$barang = $conn->query("SELECT * FROM barang");
$kapal = $conn->query("SELECT * FROM kapal WHERE status='AKTIF'");
$dermaga = $conn->query("SELECT * FROM dermaga");
$warehouse = $conn->query("SELECT * FROM warehouse");
$kendaraan = $conn->query("SELECT k.*, p.kategori FROM kendaraan k JOIN partner p ON k.partner_id = p.partner_id");

// Konversi result ke array
$barangArr = $barang->fetch_all(MYSQLI_ASSOC);
$kapalArr = $kapal->fetch_all(MYSQLI_ASSOC);
$dermagaArr = $dermaga->fetch_all(MYSQLI_ASSOC);
$warehouseArr = $warehouse->fetch_all(MYSQLI_ASSOC);
$kendaraanArr = $kendaraan->fetch_all(MYSQLI_ASSOC);

// Inisialisasi variabel untuk prefill edit
$editMode = false;
$editData = [];

// Proses hapus surat jalan
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM surat_jalan WHERE sj_id=$id");
    echo "<script>alert('Data berhasil dihapus');window.location='surat_jalan.php';</script>";
    exit;
}

// Proses hapus semua data kapal
if(isset($_GET['delete_kapal'])){
    $kapal_id = intval($_GET['delete_kapal']);
    
    // Cek apakah kapal memiliki data surat jalan
    $checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM surat_jalan WHERE kapal_id=?");
    $checkStmt->bind_param("i", $kapal_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkData = $checkResult->fetch_assoc();
    
    if($checkData['total'] > 0){
        // Hapus semua surat jalan terkait kapal
        $deleteStmt = $conn->prepare("DELETE FROM surat_jalan WHERE kapal_id=?");
        $deleteStmt->bind_param("i", $kapal_id);
        $deleteStmt->execute();
        
        echo "<script>alert('Berhasil menghapus " . $checkData['total'] . " data surat jalan dari kapal ini');window.location='surat_jalan.php';</script>";
    } else {
        echo "<script>alert('Tidak ada data surat jalan untuk kapal ini');window.location='surat_jalan.php';</script>";
    }
    exit;
}

// Proses tandai kapal selesai
if(isset($_GET['mark_done'])){
    $kapal_id = intval($_GET['mark_done']);
    $conn->query("UPDATE kapal SET status='TIDAK AKTIF' WHERE kapal_id=$kapal_id");
    echo "<script>alert('Kapal berhasil ditandai SELESAI');window.location='surat_jalan.php';</script>";
    exit;
}

// API: Get filter options (tanggal dan warehouse)
if(isset($_GET['get_filter_options'])){
    $kapal_id = intval($_GET['kapal_id']);
    $barang_id = intval($_GET['barang_id']);
    
    $query = "SELECT DISTINCT sj.tanggal, sj.warehouse_id, w.nama_warehouse
              FROM surat_jalan sj
              JOIN warehouse w ON sj.warehouse_id = w.warehouse_id
              WHERE sj.kapal_id = ? AND sj.barang_id = ?
              ORDER BY sj.tanggal DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $kapal_id, $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// API: Get Rit options for filter (BARU)
if(isset($_GET['get_rit_for_filter'])){
    $kapal_id = intval($_GET['kapal_id']);
    $barang_id = intval($_GET['barang_id']);
    $tanggal = $_GET['tanggal'];
    $warehouse_id = intval($_GET['warehouse_id']);
    $shift = intval($_GET['shift']);

    $query = "SELECT DISTINCT sj.no_rit 
              FROM surat_jalan sj
              WHERE sj.kapal_id = ? 
              AND sj.barang_id = ?
              AND sj.tanggal = ?
              AND sj.warehouse_id = ?
              AND sj.shift = ?
              ORDER BY sj.no_rit ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisii", $kapal_id, $barang_id, $tanggal, $warehouse_id, $shift);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row['no_rit']; // Return just an array of numbers
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


// API: Get surat jalan untuk multi-edit berdasarkan filter
if(isset($_GET['get_sj_for_edit'])){
    $kapal_id = intval($_GET['kapal_id']);
    $barang_id = intval($_GET['barang_id']);
    $tanggal = $_GET['tanggal'];
    $warehouse_id = intval($_GET['warehouse_id']);
    $shift = intval($_GET['shift']);
    // $no_rit_filter = isset($_GET['no_rit']) ? trim($_GET['no_rit']) : ''; // LAMA

    $query = "SELECT sj.*, ken.nopol, p.kategori as partner
              FROM surat_jalan sj
              JOIN kendaraan ken ON sj.kendaraan_id = ken.kendaraan_id
              JOIN partner p ON ken.partner_id = p.partner_id
              WHERE sj.kapal_id = ? 
              AND sj.barang_id = ?
              AND sj.tanggal = ?
              AND sj.warehouse_id = ?
              AND sj.shift = ?";
    
    $params = [$kapal_id, $barang_id, $tanggal, $warehouse_id, $shift];
    $types = "iisii";

    // DIPERBARUI: Menerima array no_rit_list
    if (isset($_GET['no_rit_list']) && is_array($_GET['no_rit_list'])) {
        $no_rit_list = $_GET['no_rit_list'];
        if (count($no_rit_list) > 0) {
            // Pastikan semua adalah integer
            $int_rit_list = array_map('intval', $no_rit_list);
            
            $placeholders = implode(',', array_fill(0, count($int_rit_list), '?'));
            $query .= " AND sj.no_rit IN ($placeholders)";
            
            foreach ($int_rit_list as $rit) {
                $params[] = $rit;
            }
            $types .= str_repeat('i', count($int_rit_list));
        }
    }
    
    $query .= " ORDER BY sj.no_rit ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params); // DIPERBARUI
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Proses update multi-edit
if(isset($_POST['multi_edit_submit'])){
    $sj_ids = $_POST['sj_ids'];
    $nopols = $_POST['nopol_edit'];
    $tonases = $_POST['tonase_edit'];
    $no_rits = $_POST['no_rit_edit']; // BARU
    $warehouse_id = intval($_POST['warehouse_edit']);
    $shift = intval($_POST['shift_edit']);

    
    $successCount = 0;
    $errorMessages = [];
    
    foreach($sj_ids as $key => $sj_id){
        $sj_id = intval($sj_id);
        $nopol = trim($nopols[$key]);
        $tonase = floatval($tonases[$key]);
        $no_rit = intval($no_rits[$key]); // BARU
        
        // Cek nopol di database dan ambil kendaraan_id
        $stmt_check = $conn->prepare("SELECT kendaraan_id FROM kendaraan WHERE BINARY nopol=?");
        $stmt_check->bind_param("s", $nopol);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if($result_check->num_rows == 0){
            $errorMessages[] = "Baris " . ($key+1) . ": Nopol \"$nopol\" tidak ditemukan";
            continue;
        }
        
        $kendaraan_id = $result_check->fetch_assoc()['kendaraan_id'];
        
        // Update surat jalan
        $stmt_update = $conn->prepare("UPDATE surat_jalan 
                               SET kendaraan_id=?, tonase=?, warehouse_id=?, shift=?, no_rit=? 
                               WHERE sj_id=?"); // DIPERBARUI
        $stmt_update->bind_param("idiiii", $kendaraan_id, $tonase, $warehouse_id, $shift, $no_rit, $sj_id); // DIPERBARUI
        
        if($stmt_update->execute()){
            $successCount++;
        } else {
            $errorMessages[] = "Baris " . ($key+1) . ": " . $stmt_update->error;
        }
    }
    
    if($successCount > 0){
        $msg = "Berhasil mengupdate $successCount data";
        if(count($errorMessages) > 0){
            $msg .= "\\n\\nError:\\n" . implode("\\n", $errorMessages);
        }
        echo "<script>alert('$msg');window.location='surat_jalan.php';</script>";
    } else {
        $msg = "Gagal mengupdate data:\\n" . implode("\\n", $errorMessages);
        echo "<script>alert('$msg');history.back();</script>";
    }
    exit;
}

// Proses edit: ambil data
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $editResult = $conn->query("SELECT sj.*, k.nopol, p.kategori as partner 
                                FROM surat_jalan sj 
                                JOIN kendaraan k ON sj.kendaraan_id = k.kendaraan_id
                                JOIN partner p ON k.partner_id = p.partner_id
                                WHERE sj.sj_id=$id");
    if($editResult->num_rows > 0){
        $editData = $editResult->fetch_assoc();
        $editMode = true;
    }
}

// Proses simpan atau update surat jalan
if(isset($_POST['submit'])){
    $tanggal = $_POST['tanggal'];
    $shift = intval($_POST['shift']);
    $barang_id = intval($_POST['barang_id']);
    $kapal_id = isset($_POST['kapal_id']) ? intval($_POST['kapal_id']) : null;
    $dermaga_id = intval($_POST['dermaga_id']);
    $warehouse_id = intval($_POST['warehouse_id']);
    
    $nomor = $_POST['nomor'];
    $nopol = $_POST['nopol'];
    $tonase = $_POST['tonase'];

    // === UPDATE ===
    if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])){
        $edit_id = intval($_POST['edit_id']);
        $np = trim($nopol[0]); 
        $tn = floatval($tonase[0]);
        $nomorRit = intval($nomor[0]);
        
        // Cek nopol di database dan ambil kendaraan_id
        $stmt_check = $conn->prepare("SELECT kendaraan_id, nopol FROM kendaraan WHERE BINARY nopol=?");
        $stmt_check->bind_param("s", $np);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if($result_check->num_rows == 0){
            // Tampilkan nopol yang tersedia
            $stmt_similar = $conn->query("SELECT nopol FROM kendaraan");
            $available = [];
            while($row = $stmt_similar->fetch_assoc()){
                $available[] = "'" . $row['nopol'] . "'";
            }
            
            echo "<script>alert('Error: Nopol \"$np\" tidak ditemukan!\\n\\nNopol yang tersedia:\\n" . implode("\\n", $available) . "\\n\\nPastikan huruf besar/kecil dan spasi.');history.back();</script>";
            exit;
        }
        
        // Ambil kendaraan_id
        $rowKendaraan = $result_check->fetch_assoc();
        $kendaraan_id = $rowKendaraan['kendaraan_id'];

        // Update surat jalan
        $stmt = $conn->prepare("UPDATE surat_jalan 
            SET barang_id=?, tanggal=?, shift=?, kapal_id=?, dermaga_id=?, warehouse_id=?, kendaraan_id=?, tonase=?, no_rit=? 
            WHERE sj_id=?");

        $stmt->bind_param("isiiiiisii", 
            $barang_id, $tanggal, $shift, $kapal_id, $dermaga_id, 
            $warehouse_id, $kendaraan_id, $tn, $nomorRit, $edit_id
        );

        if($stmt->execute()){
            echo "<script>alert('Data Surat Jalan berhasil diupdate');window.location='surat_jalan.php';</script>";
        } else {
            echo "<script>alert('Error: " . addslashes($stmt->error) . "');history.back();</script>";
        }
        exit;
    }

    // === INSERT ===
    $successCount = 0;
    $errorMessages = [];
    
    foreach($nomor as $key => $val){
        if($val != '' && isset($nopol[$key]) && $nopol[$key] != ''){
            $np = trim($nopol[$key]);
            $tn = floatval($tonase[$key]);
            $nomorRit = intval($val);
            
            // Cek apakah nopol ada di database dan ambil kendaraan_id
            $stmt_check = $conn->prepare("SELECT kendaraan_id, nopol FROM kendaraan WHERE BINARY nopol=?");
            $stmt_check->bind_param("s", $np);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if($result_check->num_rows == 0){
                $stmt_similar = $conn->query("SELECT nopol FROM kendaraan");
                $available = [];
                while($row = $stmt_similar->fetch_assoc()){
                    $available[] = $row['nopol'];
                }
                
                $errorMessages[] = "Baris " . ($key+1) . ": Nopol \"$np\" tidak ditemukan.\\nNopol tersedia: " . implode(", ", $available);
                continue;
            }
            
            // Ambil kendaraan_id
            $kendaraan_id = $result_check->fetch_assoc()['kendaraan_id'];
            
            $stmt = $conn->prepare("INSERT INTO surat_jalan 
                (nomor_sj, tanggal, barang_id, kapal_id, dermaga_id, warehouse_id, shift, no_rit, kendaraan_id, tonase)
                VALUES (?,?,?,?,?,?,?,?,?,?)");

            // Generate nomor_sj unik
            $nomor_sj = 'SJ-' . date('Ymd') . '-' . str_pad($nomorRit, 4, '0', STR_PAD_LEFT);

            $stmt->bind_param("ssiiiiiiid", 
                $nomor_sj, $tanggal, $barang_id, $kapal_id, 
                $dermaga_id, $warehouse_id, $shift, $nomorRit, $kendaraan_id, $tn
            );

            if($stmt->execute()){
                $successCount++;
            } else {
                $errorMessages[] = "Baris " . ($key+1) . ": " . $stmt->error;
            }
        }
    }
    
    if($successCount > 0){
        $msg = "Berhasil menyimpan $successCount data";
        if(count($errorMessages) > 0){
            $msg .= "\\n\\nError:\\n" . implode("\\n", $errorMessages);
        }
        echo "<script>alert('$msg');window.location='surat_jalan.php';</script>";
    } else {
        $msg = "Gagal menyimpan data:\\n" . implode("\\n", $errorMessages) . "\\n\\nPastikan nopol yang diinput PERSIS SAMA dengan Master Kendaraan.";
        echo "<script>alert('$msg');history.back();</script>";
    }
}

// Ambil data surat jalan dikelompokkan berdasarkan barang, kapal, dan dermaga
$sqlGrouped = "
SELECT 
    b.barang_id, 
    b.nama_barang, 
    k.kapal_id,
    k.nama_kapal, 
    d.dermaga_id,
    d.nama_dermaga,
    k.status as kapal_status
FROM surat_jalan sj
JOIN barang b ON sj.barang_id = b.barang_id
JOIN kapal k ON sj.kapal_id = k.kapal_id
JOIN dermaga d ON sj.dermaga_id = d.dermaga_id
JOIN warehouse w ON sj.warehouse_id = w.warehouse_id
GROUP BY b.barang_id, k.kapal_id, d.dermaga_id
ORDER BY k.status ASC, b.nama_barang, k.nama_kapal, d.nama_dermaga;
";

$groupedResult = $conn->query($sqlGrouped);

// Ambil nomor rit terakhir dari DB
$resultLastRit = $conn->query("SELECT MAX(no_rit) AS max_rit FROM surat_jalan");
$rowLastRit = $resultLastRit->fetch_assoc();
$nextRit = $rowLastRit['max_rit'] ? intval($rowLastRit['max_rit']) + 1 : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Surat Jalan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<link rel="stylesheet" href="../assets/css/style_transaksi.css"> 
</head>
<body>

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

<div id="overlay"></div>

<div class="header">
    <div class="title">Surat Jalan</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="modal fade" id="modalMultiEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Data - Filter Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Pilih filter untuk menentukan surat jalan yang akan diedit.
                </div>
                
                <form id="formFilterMultiEdit">
                    <input type="hidden" id="filterKapalId" name="kapal_id">
                    <input type="hidden" id="filterBarangId" name="barang_id">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Kapal - Barang</label>
                            <input type="text" id="filterKapalBarang" class="form-control" readonly>
                        </div>
                        <div class="col-md-3 mb-3"> 
                            <label class="form-label fw-bold">Tanggal <span class="text-danger">*</span></label>
                            <select id="filterTanggal" class="form-select" required>
                                <option value="">- Pilih Tanggal -</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"> 
                            <label class="form-label fw-bold">Warehouse <span class="text-danger">*</span></label>
                            <select id="filterWarehouse" class="form-select" required>
                                <option value="">- Pilih Warehouse -</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"> 
                            <label class="form-label fw-bold">Shift <span class="text-danger">*</span></label>
                            <select id="filterShift" class="form-select" required>
                                <option value="">- Pilih Shift -</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3" id="ritSelectionContainer" style="display: none;">
                            <label class="form-label fw-bold">Pilih No Rit <span class="text-danger">*</span></label>
                            <select id="filterNoRitList" name="no_rit_list[]" class="form-select" multiple required size="3">
                                </select>
                            <small class="form-text text-muted">Ctrl+Click untuk pilih banyak.</small>
                        </div>
                        
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tampilkan Data
                    </button>
                </form>
                
                <hr>
                
                <div id="multiEditTableContainer" style="display: none;">
                    <form method="POST" id="formMultiEdit">
                        <input type="hidden" name="multi_edit_submit" value="1">
                        <div class="row mt-3">
    <div class="col-md-6">
        <label class="form-label fw-bold">Warehouse Baru <span class="text-danger">*</span></label>
        <select name="warehouse_edit" id="warehouseEdit" class="form-select" required>
            <option value="">- Pilih Warehouse Baru -</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-bold">Shift Baru <span class="text-danger">*</span></label>
        <select name="shift_edit" id="shiftEdit" class="form-select" required>
            <option value="">- Pilih Shift Baru -</option>
            <option value="1">Shift 1</option>
            <option value="2">Shift 2</option>
            <option value="3">Shift 3</option>
        </select>
    </div>
</div>

                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <thead class="table-warning">
                                    <tr>
                                        <th style="width: 10%">No Rit</th>
                                        <th style="width: 30%">Nopol</th>
                                        <th style="width: 20%">Tonase</th>
                                        <th style="width: 20%">Partner</th>
                                    </tr>
                                </thead>
                                <tbody id="multiEditTableBody">
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save"></i> Simpan Semua Perubahan
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid main-content">

    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-file-alt"></i> <?= $editMode ? 'Edit' : 'Input' ?> Surat Jalan</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="formSuratJalan">
                <?php if($editMode): ?>
                    <input type="hidden" name="edit_id" value="<?= $editData['sj_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Barang</label>
                        <select name="barang_id" class="form-select" required>
                            <option value="">- Pilih Barang -</option>
                            <?php foreach($barangArr as $b): ?>
                                <option value="<?= $b['barang_id'] ?>" <?= $editMode && $editData['barang_id']==$b['barang_id']?'selected':'' ?>><?= htmlspecialchars($b['nama_barang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Kapal</label>
                        <select name="kapal_id" class="form-select" required>
                            <option value="">- Pilih Kapal -</option>
                            <?php foreach($kapalArr as $k): ?>
                                <option value="<?= $k['kapal_id'] ?>" <?= $editMode && $editData['kapal_id']==$k['kapal_id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kapal']) ?> <?= $k['status']=='TIDAK AKTIF' ? '(SELESAI)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Dermaga</label>
                        <select name="dermaga_id" class="form-select" required>
                            <option value="">- Pilih Dermaga -</option>
                            <?php foreach($dermagaArr as $d): ?>
                                <option value="<?= $d['dermaga_id'] ?>" <?= $editMode && $editData['dermaga_id']==$d['dermaga_id']?'selected':'' ?>><?= htmlspecialchars($d['nama_dermaga']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Warehouse</label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">- Pilih Warehouse -</option>
                            <?php foreach($warehouseArr as $w): ?>
                                <option value="<?= $w['warehouse_id'] ?>" <?= $editMode && $editData['warehouse_id']==$w['warehouse_id']?'selected':'' ?>><?= htmlspecialchars($w['nama_warehouse']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= $editMode ? $editData['tanggal'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-3">
                        <label class="form-label fw-bold">Shift</label>
                        <select name="shift" class="form-select" required>
                            <option value="">- Pilih Shift -</option>
                            <option value="1" <?= $editMode && $editData['shift']=='1'?'selected':'' ?>>1</option>
                            <option value="2" <?= $editMode && $editData['shift']=='2'?'selected':'' ?>>2</option>
                            <option value="3" <?= $editMode && $editData['shift']=='3'?'selected':'' ?>>3</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-form" id="ritTable">
                        <thead>
                            <tr>
                                <th style="width: 10%">No Rit</th>
                                <th style="width: 20%">Nopol</th>
                                <th style="width: 15%">Tonase</th>
                                <th style="width: 10%">Aksi</th>
                                <th style="width: 45%">Kategori Partner</th>

                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="number" name="nomor[]" class="form-control nomor-rit" placeholder="1" value="<?= $editMode ? $editData['no_rit'] : '1' ?>" required></td>
                                <td>
                                    <input type="text" name="nopol[]" class="form-control nopol" placeholder="B 1234 ABC" value="<?= $editMode ? $editData['nopol'] : '' ?>" required list="nopolList">
                                    <datalist id="nopolList">
                                        <?php foreach($kendaraanArr as $k): ?>
                                            <option value="<?= $k['nopol'] ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <small class="text-muted nopol-hint"></small>
                                </td>
                                <td><input type="number" name="tonase[]" class="form-control tonase" step="0.01" placeholder="0.00" value="<?= $editMode ? $editData['tonase'] : '' ?>" required></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger remove-row" <?= $editMode ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <td><input type="text" class="form-control partner" readonly placeholder="Auto terisi" value="<?= $editMode ? $editData['partner'] : '' ?>"></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td class="text-end"><strong>Total Rit:</strong></td>
                                <td><input type="number" id="totalRit" class="form-control" readonly></td>
                                <td colspan="3"></td>
                            </tr>
                            <tr class="total-row">
                                <td class="text-end"><strong>Total Tonase:</strong></td>
                                <td colspan="4"><input type="number" id="totalTonase" class="form-control" readonly></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if(!$editMode): ?>
                <button type="button" class="btn btn-secondary mb-3" id="addRow">
                    <i class="bi bi-plus-circle"></i> Tambah Baris
                </button>
                <?php endif; ?>

                <div>
                    <button type="submit" name="submit" class="btn btn-success btn-action">
                        <i class="bi bi-save"></i> <?= $editMode ? "Update" : "Simpan" ?>
                    </button>
                    <?php if($editMode): ?>
                        <a href="surat_jalan.php" class="btn btn-secondary btn-action">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Daftar Surat Jalan per Barang & Kapal</h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="accordionSuratJalan">
                <?php 
                $accordionIndex = 0;
                while($group = $groupedResult->fetch_assoc()): 
                    $accordionIndex++;
                    $barang_id = $group['barang_id'];
                    $kapal_id = $group['kapal_id'];
                    $dermaga_id = $group['dermaga_id'];
                    $kapal_status = $group['kapal_status'];
                    $label = $group['nama_barang'] . " - " . " (" . $group['nama_kapal'] . ")";
                    
                    // Query detail per group
                    $sqlDetail = "
                    SELECT sj.*, w.nama_warehouse, d.nama_dermaga, b.nama_barang, k.nama_kapal, ken.nopol, p.kategori as partner
                    FROM surat_jalan sj
                    JOIN warehouse w ON sj.warehouse_id = w.warehouse_id
                    JOIN dermaga d ON sj.dermaga_id = d.dermaga_id
                    JOIN barang b ON sj.barang_id = b.barang_id
                    JOIN kapal k ON sj.kapal_id = k.kapal_id
                    JOIN kendaraan ken ON sj.kendaraan_id = ken.kendaraan_id
                    JOIN partner p ON ken.partner_id = p.partner_id
                    WHERE sj.barang_id = $barang_id 
                    AND sj.kapal_id = $kapal_id 
                    AND sj.dermaga_id = $dermaga_id
                    ORDER BY 
                        sj.tanggal ASC,
                        sj.shift ASC,
                        w.nama_warehouse ASC,
                        d.nama_dermaga ASC,
                        p.kategori ASC,
                        sj.no_rit ASC
                    ";

                    $detailResult = $conn->query($sqlDetail);
                    
                    // Hitung total
                    $totalTonase = 0;
                    $totalRit = $detailResult->num_rows;
                    $detailData = [];
                    while($row = $detailResult->fetch_assoc()){
                        $totalTonase += $row['tonase'];
                        $detailData[] = $row;
                    }
                    
                    // Mapping shift untuk display
                    $shiftMap = ['1' => 'SHIFT 1', '2' => 'SHIFT 2', '3' => 'SHIFT 3'];
                    $isDone = ($kapal_status == 'TIDAK AKTIF');
                ?>
                <div class="accordion-item">
                    
                    <h2 class="accordion-header" id="heading<?= $accordionIndex ?>">
                        <button class="accordion-button collapsed <?= $isDone ? 'done' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $accordionIndex ?>" aria-expanded="false">
                            <strong><?= htmlspecialchars($label) ?></strong> 
                            <?php if($isDone): ?>
                                <span class="ms-3 badge bg-success">SELESAI</span>
                            <?php endif; ?>
                            <span class="ms-3 badge bg-info"><?= $totalRit ?> Rit</span>
                            <span class="ms-2 badge bg-<?= $isDone ? 'secondary' : 'success' ?>"><?= number_format($totalTonase, 3, ',', '.') ?> Ton</span>
                        </button>
                    </h2>
                    <div id="collapse<?= $accordionIndex ?>" class="accordion-collapse collapse" data-bs-parent="#accordionSuratJalan">
                        <div class="accordion-body">
                            <div class="filter-section">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <label class="form-label">Filter Warehouse</label>
                                        <select class="form-select filter-warehouse" data-group="<?= $accordionIndex ?>">
                                            <option value="">Semua Warehouse</option>
                                            <?php 
                                            $warehouseUsed = [];
                                            foreach($detailData as $d){
                                                if(!in_array($d['nama_warehouse'], $warehouseUsed)){
                                                    $warehouseUsed[] = $d['nama_warehouse'];
                                                    echo "<option value='".$d['nama_warehouse']."'>".$d['nama_warehouse']."</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Filter Dermaga</label>
                                        <select class="form-select filter-dermaga" data-group="<?= $accordionIndex ?>">
                                            <option value="">Semua Dermaga</option>
                                            <?php 
                                            $dermagaUsed = [];
                                            foreach($detailData as $d){
                                                if(!in_array($d['nama_dermaga'], $dermagaUsed)){
                                                    $dermagaUsed[] = $d['nama_dermaga'];
                                                    echo "<option value='".$d['nama_dermaga']."'>".$d['nama_dermaga']."</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Filter Tanggal</label>
                                        <input type="date" class="form-control filter-tanggal" data-group="<?= $accordionIndex ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Filter Shift</label>
                                        <select class="form-select filter-shift" data-group="<?= $accordionIndex ?>">
                                            <option value="">Semua Shift</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Filter Partner</label>
                                        <select class="form-select filter-partner" data-group="<?= $accordionIndex ?>">
                                            <option value="">Semua Partner</option>
                                            <option value="Internal">Internal</option>
                                            <option value="Rekanan">Rekanan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button class="btn btn-secondary w-100 btn-reset-filter" data-group="<?= $accordionIndex ?>">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stats-box">
                                        <p>Total Rit (Filtered)</p>
                                        <h4 class="filtered-rit" data-group="<?= $accordionIndex ?>"><?= $totalRit ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-box">
                                        <p>Total Tonase (Filtered)</p>
                                        <h4 class="filtered-tonase" data-group="<?= $accordionIndex ?>"><?= number_format($totalTonase, 3, ',', '.') ?></h4>
                                    </div>
                                </div>
                            </div>
<div class="mb-3 d-flex gap-2">

    <button class="btn btn-blue btn-sm btn-multi-edit" 
        data-kapal-id="<?= $kapal_id ?>" 
        data-barang-id="<?= $barang_id ?>"
        data-label="<?= htmlspecialchars($label) ?>">
        <i class="bi bi-pencil-square"></i> Edit Data
    </button>

    <button class="btn btn-success btn-sm btn-export-group" 
        data-group="<?= $accordionIndex ?>" 
        data-label="<?= htmlspecialchars($label) ?>">
        <i class="bi bi-file-excel"></i> Ekspor ke Excel
    </button>

    <?php if(!$isDone): ?>
        <button class="btn btn-orange btn-sm" 
            onclick="return markDone(<?= $kapal_id ?>, '<?= htmlspecialchars($group['nama_kapal']) ?>')">
            <i class="bi bi-check-circle"></i> Tandai SELESAI
        </button>
    <?php endif; ?>

<a href="../transaksi/koreksi.php?barang_id=<?= $barang_id ?>&kapal_id=<?= $kapal_id ?>" 
    class="btn btn-warning btn-sm">
    <i class="bi bi-pencil-square"></i> Koreksi
</a>

    <a href="../transaksi/realisasi.php?barang_id=<?= $barang_id ?>&kapal_id=<?= $kapal_id ?>" 
        class="btn btn-primary btn-sm">
        <i class="bi bi-list-check"></i> Lihat Realisasi
    </a>

    <button class="btn btn-danger btn-sm ms-auto"
        onclick="return deleteKapal(<?= $kapal_id ?>, '<?= htmlspecialchars($group['nama_kapal']) ?>', <?= $totalRit ?>)">
        <i class="bi bi-trash"></i> Hapus Semua Data
    </button>

</div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-striped table-sm table-detail" id="tableGroup<?= $accordionIndex ?>">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th class="text-center">TANGGAL</th>
                                            <th class="text-center">SHIFT</th>
                                            <th class="text-center">WAREHOUSE</th>
                                            <th class="text-center">DERMAGA</th>
                                            <th class="text-center">NOMOR RIT</th>
                                            <th class="text-center">NOPOL</th>
                                            <th class="text-center">TONASE</th>
                                            <th class="text-center">KONVERSI</th>
                                            <th class="text-center">KOREKSI</th>
                                            <th class="text-center">PARTNER</th>
                                            <th class="text-center">NOMOR BA</th>
                                            <th class="text-center">AREA</th>
                                            <th class="text-center">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach($detailData as $row): 
                                        ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td class="text-center" data-tanggal="<?= $row['tanggal'] ?>"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                            <td class="text-center" data-shift="<?= $row['shift'] ?>">
                                                <span class="badge bg-info"><?= $shiftMap[$row['shift']] ?? 'Shift ' . $row['shift'] ?></span>
                                            </td>
                                            <td data-warehouse="<?= htmlspecialchars($row['nama_warehouse']) ?>"><?= htmlspecialchars($row['nama_warehouse']) ?></td>
                                            <td data-dermaga="<?= htmlspecialchars($row['nama_dermaga']) ?>"><?= htmlspecialchars($row['nama_dermaga']) ?></td>
                                            <td class="text-center"><?= $row['no_rit'] ?></td>
                                            <td class="text-center"><?= htmlspecialchars($row['nopol']) ?></td>
                                            <td class="text-end tonase-val"><?= number_format($row['tonase'], 3, ',', '.') ?></td>
                                            <td class="text-end">
                                                <?php if($row['partner'] == 'Rekanan'): ?>
                                                    <strong class="text-success"><?= number_format($row['tonase'], 3, ',', '.') ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">-</td>
                                            <td class="text-center" data-partner="<?= htmlspecialchars($row['partner']) ?>">
                                                <?php if($row['partner']): ?>
                                                    <span class="badge bg-<?= $row['partner']=='Rekanan'?'success':'warning' ?>"><?= htmlspecialchars($row['partner']) ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">
                                                <a href="surat_jalan.php?edit=<?= $row['sj_id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="#" onclick="return confirmDelete('surat_jalan.php?delete=<?= $row['sj_id'] ?>')" class="btn btn-danger btn-sm" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Data kategori partner dari PHP
const kendaraanData = { 
<?php
$kendArr = [];
foreach($kendaraanArr as $k){
    $nopol_escaped = str_replace("'", "\\'", $k['nopol']);
    $kategori_escaped = str_replace("'", "\\'", $k['kategori']);
    $kendArr[] = "'".$nopol_escaped."':'".$kategori_escaped."'";
}
echo implode(",", $kendArr);
?>
};

// Daftar nopol yang valid (case-sensitive)
const validNopol = Object.keys(kendaraanData);

// Multi-Edit Modal Handler
let currentMultiEditData = {
    kapal_id: null,
    barang_id: null,
    label: ''
};

document.querySelectorAll('.btn-multi-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const kapalId = this.getAttribute('data-kapal-id');
        const barangId = this.getAttribute('data-barang-id');
        const label = this.getAttribute('data-label');
        
        currentMultiEditData = { kapal_id: kapalId, barang_id: barangId, label: label };
        
        // Set data ke modal
        document.getElementById('filterKapalId').value = kapalId;
        document.getElementById('filterBarangId').value = barangId;
        document.getElementById('filterKapalBarang').value = label;
        
        // Reset form
        document.getElementById('formFilterMultiEdit').reset();
        document.getElementById('filterKapalId').value = kapalId;
        document.getElementById('filterBarangId').value = barangId;
        document.getElementById('filterKapalBarang').value = label;
        document.getElementById('multiEditTableContainer').style.display = 'none';
        document.getElementById('ritSelectionContainer').style.display = 'none'; // BARU
        document.getElementById('filterNoRitList').innerHTML = ''; // BARU
        
        // Load tanggal dan warehouse yang tersedia
        loadFilterOptions(kapalId, barangId);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('modalMultiEdit'));
        modal.show();
    });
});

// Load filter options (tanggal dan warehouse)
function loadFilterOptions(kapalId, barangId) {
    fetch(`surat_jalan.php?get_filter_options=1&kapal_id=${kapalId}&barang_id=${barangId}`)
        .then(response => response.json())
        .then(data => {
            // Populate tanggal
            const tanggalSelect = document.getElementById('filterTanggal');
            tanggalSelect.innerHTML = '<option value="">- Pilih Tanggal -</option>';
            
            const uniqueDates = [...new Set(data.map(item => item.tanggal))];
            uniqueDates.forEach(date => {
                const dateObj = new Date(date);
                const formatted = dateObj.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
                tanggalSelect.innerHTML += `<option value="${date}">${formatted}</option>`;
            });
            
            // Populate warehouse
            const warehouseSelect = document.getElementById('filterWarehouse');
            warehouseSelect.innerHTML = '<option value="">- Pilih Warehouse -</option>';
            
            const uniqueWarehouses = [...new Set(data.map(item => ({id: item.warehouse_id, nama: item.nama_warehouse})))];
            uniqueWarehouses.forEach(wh => {
                warehouseSelect.innerHTML += `<option value="${wh.id}">${wh.nama}</option>`;
            });
            const whEdit = document.getElementById('warehouseEdit');
            whEdit.innerHTML = '<option value="">- Pilih Warehouse Baru -</option>';
            uniqueWarehouses.forEach(wh => {
                whEdit.innerHTML += `<option value="${wh.id}">${wh.nama}</option>`;
            });
        })
        
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

// BARU: Load RIT options based on filter
function loadRitOptions() {
    const kapalId = document.getElementById('filterKapalId').value;
    const barangId = document.getElementById('filterBarangId').value;
    const tanggal = document.getElementById('filterTanggal').value;
    const warehouseId = document.getElementById('filterWarehouse').value;
    const shift = document.getElementById('filterShift').value;

    const ritContainer = document.getElementById('ritSelectionContainer');
    const ritSelect = document.getElementById('filterNoRitList');
    
    // Sembunyikan dan bersihkan dulu
    ritContainer.style.display = 'none';
    ritSelect.innerHTML = '';
    document.getElementById('multiEditTableContainer').style.display = 'none'; // Sembunyikan tabel juga jika filter berubah

    if (kapalId && barangId && tanggal && warehouseId && shift) {
        fetch(`surat_jalan.php?get_rit_for_filter=1&kapal_id=${kapalId}&barang_id=${barangId}&tanggal=${tanggal}&warehouse_id=${warehouseId}&shift=${shift}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    data.forEach(rit => {
                        ritSelect.innerHTML += `<option value="${rit}">${rit}</option>`;
                    });
                    ritContainer.style.display = 'block';
                } else {
                    alert('Tidak ditemukan No. Rit untuk filter yang dipilih.');
                }
            })
            .catch(error => console.error('Error loading rit options:', error));
    }
}

// BARU: Attach listeners to filters
document.getElementById('filterTanggal').addEventListener('change', loadRitOptions);
document.getElementById('filterWarehouse').addEventListener('change', loadRitOptions);
document.getElementById('filterShift').addEventListener('change', loadRitOptions);


// Handle form filter submit
document.getElementById('formFilterMultiEdit').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const kapalId = document.getElementById('filterKapalId').value;
    const barangId = document.getElementById('filterBarangId').value;
    const tanggal = document.getElementById('filterTanggal').value;
    const warehouseId = document.getElementById('filterWarehouse').value;
    const shift = document.getElementById('filterShift').value;
    // const noRit = document.getElementById('filterNoRit').value; // LAMA

    // BARU: Get multiple rit
    const selectedRitOptions = document.getElementById('filterNoRitList').selectedOptions;
    const selectedRit = Array.from(selectedRitOptions).map(option => option.value);
    
    if(!tanggal || !warehouseId || !shift) {
        alert('Filter Tanggal, Warehouse, dan Shift harus diisi!');
        return;
    }

    // BARU: Validasi No Rit
    if (selectedRit.length === 0) {
        alert('Anda harus memilih minimal satu No Rit dari daftar.');
        return;
    }
    
    // BARU: Bangun URL Fetch dengan array no_rit_list
    let fetchUrl = `surat_jalan.php?get_sj_for_edit=1&kapal_id=${kapalId}&barang_id=${barangId}&tanggal=${tanggal}&warehouse_id=${warehouseId}&shift=${shift}`;
    
    const ritParams = selectedRit.map(rit => `no_rit_list[]=${encodeURIComponent(rit)}`).join('&');
    fetchUrl += `&${ritParams}`;
    
    // Fetch data surat jalan
    fetch(fetchUrl) // DIPERBARUI
        .then(response => response.json())
        .then(data => {
            if(data.length === 0) {
                alert('Tidak ada data surat jalan dengan filter yang dipilih.');
                document.getElementById('multiEditTableContainer').style.display = 'none'; // Sembunyikan tabel jika tidak ada data
                return;
            }
            
            // Populate table
            const tbody = document.getElementById('multiEditTableBody');
            tbody.innerHTML = '';
            
            data.forEach((item, index) => {
                // DIPERBARUI: Kolom No Rit menjadi input
                const row = `
                    <tr>
                        <td class="text-center">
                            <input type="hidden" name="sj_ids[]" value="${item.sj_id}">
                            <input type="number" name="no_rit_edit[]" class="form-control no-rit-edit" 
                                   value="${item.no_rit}" required>
                        </td>
                        <td>
                            <input type="text" name="nopol_edit[]" class="form-control nopol-edit" 
                                value="${item.nopol}" required list="nopolListEdit">
                            <small class="text-muted nopol-hint-edit"></small>
                        </td>
                        <td>
                            <input type="number" name="tonase_edit[]" class="form-control tonase-edit" 
                                step="0.001" value="${item.tonase}" required>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-${item.partner === 'Rekanan' ? 'success' : 'warning'} partner-badge">
                                ${item.partner}
                            </span>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
            
            // Add datalist
            if(!document.getElementById('nopolListEdit')) {
                const datalist = document.createElement('datalist');
                datalist.id = 'nopolListEdit';
                validNopol.forEach(nopol => {
                    datalist.innerHTML += `<option value="${nopol}">`;
                });
                document.body.appendChild(datalist);
            }
            
            // Show table
            document.getElementById('multiEditTableContainer').style.display = 'block';
            
            // Attach event listeners
            attachMultiEditListeners();
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            alert('Terjadi kesalahan saat mengambil data.');
        });
});

// Attach event listeners untuk validasi nopol di multi-edit
function attachMultiEditListeners() {
    document.querySelectorAll('.nopol-edit').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const hint = row.querySelector('.nopol-hint-edit');
            const partnerBadge = row.querySelector('.partner-badge');
            const val = this.value;
            
            if(val === '') {
                this.classList.remove('nopol-warning', 'nopol-valid');
                hint.textContent = '';
                return;
            }
            
            if(validNopol.includes(val)) {
                this.classList.remove('nopol-warning');
                this.classList.add('nopol-valid');
                hint.textContent = '✓ Nopol valid';
                hint.className = 'text-success nopol-hint-edit';
                
                // Update partner badge
                const kategori = kendaraanData[val];
                partnerBadge.textContent = kategori;
                partnerBadge.className = `badge bg-${kategori === 'Rekanan' ? 'success' : 'warning'} partner-badge`;
            } else {
                this.classList.remove('nopol-valid');
                this.classList.add('nopol-warning');
                hint.textContent = '✗ Nopol tidak ditemukan!';
                hint.className = 'text-danger nopol-hint-edit';
            }
        });
    });
}

// Validasi form multi-edit sebelum submit
document.getElementById('formMultiEdit').addEventListener('submit', function(e) {
    const nopolInputs = document.querySelectorAll('.nopol-edit');
    let hasInvalidNopol = false;
    let invalidNopols = [];
    
    nopolInputs.forEach(input => {
        const val = input.value;
        if(val !== '' && !validNopol.includes(val)) {
            hasInvalidNopol = true;
            invalidNopols.push(val);
            input.classList.add('nopol-warning');
        }
    });
    
    if(hasInvalidNopol) {
        e.preventDefault();
        alert('Ada nopol yang tidak valid!\n\nNopol yang salah:\n- ' + invalidNopols.join('\n- ') + 
              '\n\nSilakan periksa kembali nopol yang diinput.');
        return false;
    }

    // DIPERBARUI: Validasi Warehouse & Shift (dipindah dari confirmDelete)
    const wEdit = document.getElementById('warehouseEdit').value;
    const sEdit = document.getElementById('shiftEdit').value;

    if(!wEdit || !sEdit) {
        alert('Warehouse dan shift baru harus dipilih!');
        e.preventDefault();
        return false;
    }
    
    if(!confirm('Apakah Anda yakin ingin menyimpan semua perubahan?')) {
        e.preventDefault();
        return false;
    }
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

// Hitung total rit dan tonase
function hitungTotal(){
    const rows = document.querySelectorAll("#ritTable tbody tr");
    let totalRit = 0;
    let totalTonase = 0;
    
    rows.forEach(r => {
        const nomorInput = r.querySelector("input[name='nomor[]']");
        const tonaseInput = r.querySelector(".tonase");
        
        if(nomorInput && nomorInput.value && nomorInput.value.trim() !== ''){
            totalRit++;
            const t = parseFloat(tonaseInput.value) || 0;
            totalTonase += t;
        }
    });
    
    document.getElementById("totalRit").value = totalRit;
    document.getElementById("totalTonase").value = totalTonase.toFixed(2);
}

// Validasi nopol (case-sensitive)
function validateNopol(input){
    const row = input.closest("tr");
    const hint = row.querySelector(".nopol-hint");
    const val = input.value;
    
    if(val === ''){
        input.classList.remove('nopol-warning', 'nopol-valid');
        hint.textContent = '';
        return;
    }
    
    if(validNopol.includes(val)){
        input.classList.remove('nopol-warning');
        input.classList.add('nopol-valid');
        hint.textContent = '✓ Nopol valid';
        hint.className = 'text-success nopol-hint';
    } else {
        input.classList.remove('nopol-valid');
        input.classList.add('nopol-warning');
        hint.textContent = '✗ Nopol tidak ditemukan! (perhatikan huruf besar/kecil)';
        hint.className = 'text-danger nopol-hint';
    }
}

// Tambah baris dengan Enter key
document.getElementById("ritTable").addEventListener("keydown", function(e){
    if(e.key === "Enter"){
        e.preventDefault();
        const tbody = document.querySelector("#ritTable tbody");
        const currentRowCount = tbody.rows.length;
        const newRow = tbody.rows[0].cloneNode(true);
        
        newRow.querySelectorAll("input").forEach(inp => {
            if(inp.classList.contains('nomor-rit')){
                inp.value = currentRowCount + 1;
            } else if(!inp.classList.contains('partner')){
                inp.value = '';
                inp.classList.remove('nopol-warning', 'nopol-valid');
            } else {
                inp.value = '';
            }
        });
        
        const hint = newRow.querySelector('.nopol-hint');
        if(hint) hint.textContent = '';
        
        newRow.querySelector('.tonase').readOnly = false;
        newRow.querySelector('.remove-row').disabled = false;
        tbody.appendChild(newRow);
        
        newRow.querySelector('.nomor-rit').focus();
        hitungTotal();
    }
});

// Tambah baris dengan button
document.getElementById("addRow")?.addEventListener("click", function(){
    const tbody = document.querySelector("#ritTable tbody");
    const currentRowCount = tbody.rows.length;
    const newRow = tbody.rows[0].cloneNode(true);
    
    newRow.querySelectorAll("input").forEach(inp => {
        if(inp.classList.contains('nomor-rit')){
            inp.value = currentRowCount + 1;
        } else if(!inp.classList.contains('partner')){
            inp.value = '';
            inp.classList.remove('nopol-warning', 'nopol-valid');
        } else {
            inp.value = '';
        }
    });
    
    const hint = newRow.querySelector('.nopol-hint');
    if(hint) hint.textContent = '';
    
    newRow.querySelector('.tonase').readOnly = false;
    newRow.querySelector('.remove-row').disabled = false;
    tbody.appendChild(newRow);
    hitungTotal();
});

// Hapus baris
document.getElementById("ritTable").addEventListener("click", function(e){
    if(e.target.closest('.remove-row')){
        const btn = e.target.closest('.remove-row');
        if(btn.disabled) return;
        
        const row = e.target.closest('tr');
        const tbody = document.querySelector("#ritTable tbody");
        if(tbody.rows.length > 1){
            row.remove();
            updateNomorRit();
            hitungTotal();
        } else {
            alert('Minimal harus ada 1 baris');
        }
    }
});

// Update nomor rit otomatis
function updateNomorRit(){
    const rows = document.querySelectorAll("#ritTable tbody tr");
    rows.forEach((row, index) => {
        const nomorInput = row.querySelector('.nomor-rit');
        if(nomorInput){
            nomorInput.value = index + 1;
        }
    });
}

// Update partner & kontrol tonase otomatis
document.getElementById("ritTable").addEventListener("input", function(e){
    if(e.target.classList.contains("nopol")){
        const row = e.target.closest("tr");
        const partnerCell = row.querySelector(".partner");
        const tonaseInput = row.querySelector(".tonase");
        const val = e.target.value;
        const kategori = kendaraanData[val] || '';

        validateNopol(e.target);

        partnerCell.value = kategori;

        hitungTotal();
    }

    if(e.target.classList.contains("tonase") || e.target.classList.contains("nomor-rit")){
        hitungTotal();
    }
});

// Validasi sebelum submit
document.getElementById("formSuratJalan").addEventListener("submit", function(e){
    const nopolInputs = document.querySelectorAll(".nopol");
    let hasInvalidNopol = false;
    let invalidNopols = [];
    
    nopolInputs.forEach(input => {
        const val = input.value;
        if(val !== '' && !validNopol.includes(val)){
            hasInvalidNopol = true;
            invalidNopols.push(val);
            input.classList.add('nopol-warning');
        }
    });
    
    if(hasInvalidNopol){
        e.preventDefault();
        alert('Ada nopol yang tidak valid!\n\nNopol yang salah:\n- ' + invalidNopols.join('\n- ') + 
              '\n\nSilakan periksa kembali:\n1. Pastikan nopol sudah terdaftar di Master Kendaraan\n2. Perhatikan huruf BESAR/kecil\n3. Perhatikan spasi');
        return false;
    }
});

// Filter functions
function applyFilters(groupId) {
    const table = document.querySelector(`#tableGroup${groupId}`);
    const rows = table.querySelectorAll('tbody tr');
    
    const warehouseFilter = document.querySelector(`.filter-warehouse[data-group="${groupId}"]`).value;
    const dermagaFilter = document.querySelector(`.filter-dermaga[data-group="${groupId}"]`).value;
    const tanggalFilter = document.querySelector(`.filter-tanggal[data-group="${groupId}"]`).value;
    const shiftFilter = document.querySelector(`.filter-shift[data-group="${groupId}"]`).value;
    const partnerFilter = document.querySelector(`.filter-partner[data-group="${groupId}"]`).value;
    
    let visibleCount = 0;
    let totalTonase = 0;
    
    rows.forEach(row => {
        const warehouse = row.querySelector('[data-warehouse]')?.getAttribute('data-warehouse') || '';
        const dermaga = row.querySelector('[data-dermaga]')?.getAttribute('data-dermaga') || '';
        const tanggal = row.querySelector('[data-tanggal]')?.getAttribute('data-tanggal') || '';
        const shift = row.querySelector('[data-shift]')?.getAttribute('data-shift') || '';
        const partner = row.querySelector('[data-partner]')?.getAttribute('data-partner') || '';
        const tonaseText = row.querySelector('.tonase-val')?.textContent || '0';
        const tonase = parseFloat(tonaseText.replace(/\./g, '').replace(',', '.'));
        
        let show = true;
        
        if(warehouseFilter && warehouse !== warehouseFilter) show = false;
        if(dermagaFilter && dermaga !== dermagaFilter) show = false;
        if(tanggalFilter && tanggal !== tanggalFilter) show = false;
        if(shiftFilter && shift !== shiftFilter) show = false;
        if(partnerFilter && partner !== partnerFilter) show = false;
        
        if(show) {
            row.style.display = '';
            visibleCount++;
            totalTonase += tonase;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.querySelector(`.filtered-rit[data-group="${groupId}"]`).textContent = visibleCount;
    document.querySelector(`.filtered-tonase[data-group="${groupId}"]`).textContent = totalTonase.toFixed(3).replace('.', ',');
}

// Attach filter events
document.querySelectorAll('.filter-warehouse, .filter-dermaga, .filter-tanggal, .filter-shift, .filter-partner').forEach(filter => {
    filter.addEventListener('change', function() {
        const groupId = this.getAttribute('data-group');
        applyFilters(groupId);
    });
});

// Reset filter
document.querySelectorAll('.btn-reset-filter').forEach(btn => {
    btn.addEventListener('click', function() {
        const groupId = this.getAttribute('data-group');
        document.querySelector(`.filter-warehouse[data-group="${groupId}"]`).value = '';
        document.querySelector(`.filter-dermaga[data-group="${groupId}"]`).value = '';
        document.querySelector(`.filter-tanggal[data-group="${groupId}"]`).value = '';
        document.querySelector(`.filter-shift[data-group="${groupId}"]`).value = '';
        document.querySelector(`.filter-partner[data-group="${groupId}"]`).value = '';
        applyFilters(groupId);
    });
});

// Export to Excel
document.querySelectorAll('.btn-export-group').forEach(btn => {
    btn.addEventListener('click', function() {
        const groupId = this.getAttribute('data-group');
        const label = this.getAttribute('data-label');
        const table = document.querySelector(`#tableGroup${groupId}`);
        
        const rows = table.querySelectorAll('tbody tr');
        const data = [];
        
        data.push(['TANGGAL', 'SHIFT', 'WAREHOUSE', 'DERMAGA', 'NOMOR', 'NOPOL', 'TONASE', '#', 'KONVERSI', 'KOREKSI', 'PARTNER', 'NOMOR BA', 'AREA']);
        
        rows.forEach(row => {
            if(row.style.display !== 'none') {
                const tanggalCell = row.querySelector('[data-tanggal]');
                const shiftCell = row.querySelector('[data-shift]');
                const warehouseCell = row.querySelector('[data-warehouse]');
                const dermagaCell = row.querySelector('[data-dermaga]');
                const nomorCell = row.cells[5];
                const nopolCell = row.cells[6];
                const tonaseCell = row.querySelector('.tonase-val');
                
                let tanggalRaw = tanggalCell.getAttribute('data-tanggal');
                let tanggalObj = new Date(tanggalRaw);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
                let tanggalFormat = tanggalObj.getDate() + ' ' + months[tanggalObj.getMonth()] + ' ' + tanggalObj.getFullYear();
                
                const shiftMap = {'1': '1', '2': '2', '3': '3'};
                let shiftValue = shiftCell.getAttribute('data-shift');
                let shiftFormat = shiftMap[shiftValue] || shiftValue;
                
                let warehouse = warehouseCell.getAttribute('data-warehouse');
                let dermaga = dermagaCell.getAttribute('data-dermaga');
                
                let nomor = nomorCell.textContent.trim();
                
                let nopol = nopolCell.textContent.trim();
                
                let tonaseText = tonaseCell.textContent.trim();
                let tonase = parseFloat(tonaseText.replace(/\./g, '').replace(',', '.'));
                
                let partnerCell = row.querySelector('[data-partner]');
                let partnerText = partnerCell.getAttribute('data-partner');
                let konversi = (partnerText === 'Rekanan') ? tonase : 0;
                
                data.push([
                    tanggalFormat,
                    shiftFormat,
                    warehouse,
                    dermaga,
                    nomor,
                    nopol,
                    tonase,
                    1,
                    konversi,
                    '',
                    partnerText,
                    '',
                    ''
                ]);
            }
        });
        
        const ws = XLSX.utils.aoa_to_sheet(data);
        
        ws['!cols'] = [
            {wch: 15},
            {wch: 8},
            {wch: 20},
            {wch: 25},
            {wch: 8},
            {wch: 15},
            {wch: 12},
            {wch: 5},
            {wch: 12},
            {wch: 12},
            {wch: 15},
            {wch: 15},
            {wch: 15}
        ];
        
        const range = XLSX.utils.decode_range(ws['!ref']);
        for(let R = 1; R <= range.e.r; ++R) {
            const tonaseAddr = XLSX.utils.encode_cell({r: R, c: 6});
            if(ws[tonaseAddr] && typeof ws[tonaseAddr].v === 'number') {
                ws[tonaseAddr].z = '#,##0.000';
            }
            
            const konversiAddr = XLSX.utils.encode_cell({r: R, c: 8});
            if(ws[konversiAddr] && typeof ws[konversiAddr].v === 'number') {
                ws[konversiAddr].z = '#,##0.000';
            }
        }
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Surat Jalan');
        XLSX.writeFile(wb, 'Surat_Jalan_' + label.replace(/[^a-zA-Z0-9]/g, '_') + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
    });
});

// Confirm delete surat jalan
function confirmDelete(url){
    // DIPERBARUI: Menghapus validasi yang salah tempat
    if(confirm('Apakah Anda yakin ingin menghapus data surat jalan ini?')){
        window.location.href = url;
        return true;
    }
    return false;
}

// Mark kapal as done
function markDone(kapalId, namaKapal){
    if(confirm('Apakah Anda yakin ingin menandai kapal "' + namaKapal + '" sebagai SELESAI?\n\nKapal yang sudah ditandai SELESAI tidak akan muncul di dropdown input.')){
        window.location.href = 'surat_jalan.php?mark_done=' + kapalId;
        return true;
    }
    return false;
}

// Delete all kapal data
function deleteKapal(kapalId, namaKapal, totalRit){
    if(confirm('⚠️ PERINGATAN ⚠️\n\nAnda akan menghapus SEMUA data surat jalan untuk kapal "' + namaKapal + '"!\n\nTotal data yang akan dihapus: ' + totalRit + ' rit\n\nTindakan ini TIDAK DAPAT DIBATALKAN!\n\nApakah Anda yakin ingin melanjutkan?')){
        if(confirm('Konfirmasi sekali lagi:\n\nHapus ' + totalRit + ' data surat jalan dari kapal "' + namaKapal + '"?')){
            window.location.href = 'surat_jalan.php?delete_kapal=' + kapalId;
            return true;
        }
    }
    return false;
}

hitungTotal();
</script>

</body>
</html>