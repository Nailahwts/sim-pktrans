<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

// ===== MODE DEBUG =====
$debug_mode = isset($_GET['debug']) ? true : false;

if($debug_mode) {
    echo "<div class='container mt-3'>";
    echo "<div class='alert alert-info'><h4>🔍 DEBUG MODE</h4>";
    
    // Debug 1: Cek struktur tabel purchase_order
    echo "<h5>1. Struktur Tabel Purchase Order:</h5>";
    $desc = $conn->query("DESCRIBE purchase_order");
    echo "<table class='table table-sm'><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while($d = $desc->fetch_assoc()) {
        echo "<tr><td>{$d['Field']}</td><td>{$d['Type']}</td><td>{$d['Null']}</td><td>{$d['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Debug 2: Cek data purchase_order
    echo "<h5>2. Data Purchase Order:</h5>";
    $po_debug = $conn->query("SELECT po_id, nomor_po, terima_po, tanggal_po FROM purchase_order ORDER BY tanggal_po DESC LIMIT 10");
    echo "<table class='table table-sm'><tr><th>PO ID</th><th>Nomor PO</th><th>Terima PO</th><th>Tanggal PO</th><th>Status</th></tr>";
    while($p = $po_debug->fetch_assoc()) {
        $status = empty($p['terima_po']) ? '❌ Belum Diterima' : '✅ Sudah Diterima';
        echo "<tr><td>{$p['po_id']}</td><td>{$p['nomor_po']}</td><td>" . ($p['terima_po'] ?? 'NULL') . "</td><td>{$p['tanggal_po']}</td><td>$status</td></tr>";
    }
    echo "</table>";
    
    // Debug 3: Cek relasi PO-Invoice
    echo "<h5>3. Relasi PO dengan Invoice:</h5>";
    $rel_debug = $conn->query("
        SELECT 
            po.po_id, 
            po.nomor_po, 
            po.terima_po,
            i.invoice_id,
            i.nomor_invoice
        FROM purchase_order po
        LEFT JOIN invoice i ON po.po_id = i.po_id
        ORDER BY po.tanggal_po DESC
        LIMIT 10
    ");
    echo "<table class='table table-sm'><tr><th>PO ID</th><th>Nomor PO</th><th>Terima PO</th><th>Invoice ID</th><th>Nomor Invoice</th><th>Available?</th></tr>";
    while($r = $rel_debug->fetch_assoc()) {
        $available = (empty($r['terima_po']) || !empty($r['invoice_id'])) ? '❌' : '✅';
        echo "<tr><td>{$r['po_id']}</td><td>{$r['nomor_po']}</td><td>" . ($r['terima_po'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['invoice_id'] ?? '-') . "</td><td>" . ($r['nomor_invoice'] ?? '-') . "</td><td>$available</td></tr>";
    }
    echo "</table>";
    
    // Debug 4: Query yang digunakan untuk dropdown
    echo "<h5>4. Query Dropdown PO (yang seharusnya muncul):</h5>";
    $dropdown_test = $conn->query("
        SELECT 
            po.po_id, 
            po.nomor_po, 
            po.terima_po,
            CASE 
                WHEN po.terima_po IS NULL THEN 'Belum Diterima'
                WHEN EXISTS (SELECT 1 FROM invoice WHERE po_id = po.po_id) THEN 'Sudah Ada Invoice'
                ELSE 'Available'
            END as status
        FROM purchase_order po
        ORDER BY po.tanggal_po DESC
    ");
    echo "<table class='table table-sm'><tr><th>PO ID</th><th>Nomor PO</th><th>Terima PO</th><th>Status</th></tr>";
    while($dt = $dropdown_test->fetch_assoc()) {
        $class = $dt['status'] == 'Available' ? 'table-success' : 'table-warning';
        echo "<tr class='$class'><td>{$dt['po_id']}</td><td>{$dt['nomor_po']}</td><td>" . ($dt['terima_po'] ?? 'NULL') . "</td><td>{$dt['status']}</td></tr>";
    }
    echo "</table>";
    
    echo "<a href='invoice.php' class='btn btn-primary'>Kembali ke Form Normal</a>";
    echo "</div></div>";
    exit;
}
// ===== END DEBUG MODE =====

// Ambil username admin
$admin_id = $_SESSION['admin'];
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$username = $admin['username'];

// ===== FUNGSI GENERATE NOMOR INVOICE =====
function generateNomorInvoice($conn) {
    $year = date('Y');
    $month = date('m');
    
    $stmt = $conn->prepare("
        SELECT nomor_invoice 
        FROM invoice 
        WHERE YEAR(tanggal_invoice) = ?
        ORDER BY invoice_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['nomor_invoice'];
        
        $parts = explode('/', $lastNomor);
        if(count($parts) >= 4) {
            $lastNumber = intval($parts[3]);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
    } else {
        $newNumber = 1;
    }
    
    $nomorUrut = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    return "INV/PK-TRANS/$month-$year/$nomorUrut";
}

// ===== HAPUS INVOICE =====
if(isset($_GET['delete'])) {
    $invoice_id_to_delete = intval($_GET['delete']);
    
    $conn->begin_transaction();
    
    try {
        $stmt_check = $conn->prepare("SELECT po_id, nomor_invoice FROM invoice WHERE invoice_id = ?");
        $stmt_check->bind_param("i", $invoice_id_to_delete);
        $stmt_check->execute();
        $invoice_data = $stmt_check->get_result()->fetch_assoc();
        
        if(!$invoice_data) {
            throw new Exception("Invoice tidak ditemukan.");
        }
        
        $nomor_invoice = $invoice_data['nomor_invoice'];
        
        $stmt_detail = $conn->prepare("DELETE FROM invoice_detail WHERE invoice_id = ?");
        $stmt_detail->bind_param("i", $invoice_id_to_delete);
        $stmt_detail->execute();
        
        $stmt_invoice = $conn->prepare("DELETE FROM invoice WHERE invoice_id = ?");
        $stmt_invoice->bind_param("i", $invoice_id_to_delete);
        $stmt_invoice->execute();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Invoice {$nomor_invoice} berhasil dihapus. PO telah tersedia kembali.";
        header("Location: invoice.php");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal menghapus: " . $e->getMessage();
        header("Location: invoice.php");
        exit;
    }
}

// ===== TAMBAH INVOICE (DENGAN ERROR HANDLING LENGKAP) =====
if(isset($_POST['submit'])){
    try {
        $nomor_invoice = generateNomorInvoice($conn);
        $po_id = intval($_POST['po_id']);
        $tanggal_invoice = $_POST['tanggal_invoice'];
        $ppn = floatval($_POST['ppn']);
        $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

        // Validasi PO ID
        if($po_id <= 0) {
            throw new Exception("Silakan pilih Purchase Order terlebih dahulu!");
        }

        // Validasi: Cek apakah PO ada dan sudah diterima
        $stmt_po = $conn->prepare("SELECT po_id, nomor_po, terima_po FROM purchase_order WHERE po_id = ?");
        $stmt_po->bind_param("i", $po_id);
        $stmt_po->execute();
        $po_data = $stmt_po->get_result()->fetch_assoc();
        
        if(!$po_data) {
            throw new Exception("Purchase Order tidak ditemukan!");
        }
        
        if(empty($po_data['terima_po'])) {
            throw new Exception("PO {$po_data['nomor_po']} belum diterima. Silakan terima PO terlebih dahulu di menu Purchase Order!");
        }

        // Validasi: Cek apakah PO sudah punya invoice
        $stmt_check = $conn->prepare("SELECT invoice_id, nomor_invoice FROM invoice WHERE po_id = ?");
        $stmt_check->bind_param("i", $po_id);
        $stmt_check->execute();
        $existing_invoice = $stmt_check->get_result()->fetch_assoc();

        if($existing_invoice) {
            throw new Exception("PO {$po_data['nomor_po']} sudah memiliki invoice: {$existing_invoice['nomor_invoice']}!");
        }

        // Ambil semua SO terkait PO
        $stmt = $conn->prepare("
            SELECT 
                so.partner_id, 
                so.barang_id, 
                so.kapal_id, 
                so.harga_id,
                so.qty,
                h.area,
                h.harga,
                h.tujuan_id,
                h.tujuan_tipe
            FROM sales_order so
            JOIN harga h ON so.harga_id = h.harga_id
            WHERE so.po_id = ?
        ");
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        $so_result = $stmt->get_result();

        if($so_result->num_rows == 0){
            throw new Exception("Tidak ada data Sales Order untuk PO ini! Silakan buat Sales Order terlebih dahulu.");
        }

        $total_tonase = 0;
        $subtotal = 0;
        $partner_id = $barang_id = $kapal_id = $harga_id = null;
        $detail_data = [];
        
        while($so = $so_result->fetch_assoc()){
            $partner_id = $so['partner_id'];
            $barang_id = $so['barang_id'];
            $kapal_id = $so['kapal_id'];
            $harga_id = $so['harga_id'];
            
            $area = $so['area'];
            $harga_satuan = floatval($so['harga']);
            $warehouse_id = null;
            
            if($so['tujuan_tipe'] == 'warehouse'){
                $warehouse_id = $so['tujuan_id'];
            }
            
            $tonase_area = floatval($so['qty']);
            $subtotal_area = $tonase_area * $harga_satuan;
            
            $total_tonase += $tonase_area;
            $subtotal += $subtotal_area;
            
            $detail_data[] = [
                'harga_id' => $so['harga_id'],
                'area' => $area,
                'tonase' => $tonase_area,
                'harga_satuan' => $harga_satuan,
                'subtotal' => $subtotal_area,
                'warehouse_id' => $warehouse_id
            ];
        }

        $ppn_nominal = ($subtotal * $ppn) / 100;
        $total_biaya = $subtotal + $ppn_nominal;

        $conn->begin_transaction();
        
        // Insert invoice utama
        if($ttd_id !== null){
            $stmt = $conn->prepare("INSERT INTO invoice 
                (nomor_invoice, po_id, tanggal_invoice, partner_id, barang_id, kapal_id, harga_id, total_tonase, ppn, total_biaya, ttd_id) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                "sisiiiidddi",
                $nomor_invoice,
                $po_id,
                $tanggal_invoice,
                $partner_id,
                $barang_id,
                $kapal_id,
                $harga_id,
                $total_tonase,
                $ppn,
                $total_biaya,
                $ttd_id
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO invoice 
                (nomor_invoice, po_id, tanggal_invoice, partner_id, barang_id, kapal_id, harga_id, total_tonase, ppn, total_biaya) 
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                "sisiiiiddd",
                $nomor_invoice,
                $po_id,
                $tanggal_invoice,
                $partner_id,
                $barang_id,
                $kapal_id,
                $harga_id,
                $total_tonase,
                $ppn,
                $total_biaya
            );
        }

        if(!$stmt->execute()){
            throw new Exception("Gagal menyimpan invoice: " . $stmt->error);
        }
        
        $invoice_id = $conn->insert_id;
        
        // Insert invoice_detail
        $stmt_detail = $conn->prepare("
            INSERT INTO invoice_detail 
            (invoice_id, harga_id, area, tonase, harga_satuan, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach($detail_data as $detail){
            $stmt_detail->bind_param(
                "iisddd",
                $invoice_id,
                $detail['harga_id'],
                $detail['area'],
                $detail['tonase'],
                $detail['harga_satuan'],
                $detail['subtotal']
            );
            
            if(!$stmt_detail->execute()){
                throw new Exception("Gagal menyimpan detail invoice: " . $stmt_detail->error);
            }
        }
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Invoice berhasil ditambahkan dengan nomor: $nomor_invoice";
        header("Location: invoice.php");
        exit;
        
    } catch(Exception $e){
        if(isset($conn)){
            $conn->rollback();
        }
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: invoice.php");
        exit;
    }
}

// ===== UPDATE INVOICE =====
if(isset($_POST['update'])){
    try {
        $invoice_id = intval($_POST['invoice_id']);
        $nomor_invoice = trim($_POST['nomor_invoice']);
        $po_id = intval($_POST['po_id']);
        $tanggal_invoice = $_POST['tanggal_invoice'];
        $ppn = floatval($_POST['ppn']);
        $ttd_id = !empty($_POST['ttd_id']) ? intval($_POST['ttd_id']) : null;

        // Validasi: Cek apakah PO sudah diterima
        $stmt_po = $conn->prepare("SELECT po_id, nomor_po, terima_po FROM purchase_order WHERE po_id = ?");
        $stmt_po->bind_param("i", $po_id);
        $stmt_po->execute();
        $po_data = $stmt_po->get_result()->fetch_assoc();
        
        if(!$po_data) {
            throw new Exception("Purchase Order tidak ditemukan!");
        }
        
        if(empty($po_data['terima_po'])) {
            throw new Exception("PO {$po_data['nomor_po']} belum diterima!");
        }

        // Validasi: Cek apakah PO lain sudah punya invoice dengan PO ini
        $stmt_check = $conn->prepare("SELECT invoice_id, nomor_invoice FROM invoice WHERE po_id = ? AND invoice_id != ?");
        $stmt_check->bind_param("ii", $po_id, $invoice_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if($check_result->num_rows > 0) {
            $existing = $check_result->fetch_assoc();
            throw new Exception("PO ini sudah memiliki invoice lain: {$existing['nomor_invoice']}!");
        }

        // Ambil data SO terkait PO
        $stmt = $conn->prepare("
            SELECT 
                so.partner_id, 
                so.barang_id, 
                so.kapal_id, 
                so.harga_id,
                so.qty,
                h.area,
                h.harga,
                h.tujuan_id,
                h.tujuan_tipe
            FROM sales_order so
            JOIN harga h ON so.harga_id = h.harga_id
            WHERE so.po_id = ?
        ");
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        $so_result = $stmt->get_result();

        if($so_result->num_rows == 0){
            throw new Exception("Tidak ada data Sales Order untuk PO ini!");
        }

        $total_tonase = 0;
        $subtotal = 0;
        $partner_id = $barang_id = $kapal_id = $harga_id = null;
        $detail_data = [];
        
        while($so = $so_result->fetch_assoc()){
            $partner_id = $so['partner_id'];
            $barang_id = $so['barang_id'];
            $kapal_id = $so['kapal_id'];
            $harga_id = $so['harga_id'];
            
            $area = $so['area'];
            $harga_satuan = floatval($so['harga']);
            $warehouse_id = null;
            
            if($so['tujuan_tipe'] == 'warehouse'){
                $warehouse_id = $so['tujuan_id'];
            }
            
            $tonase_area = floatval($so['qty']);
            $subtotal_area = $tonase_area * $harga_satuan;
            
            $total_tonase += $tonase_area;
            $subtotal += $subtotal_area;
            
            $detail_data[] = [
                'harga_id' => $so['harga_id'],
                'area' => $area,
                'tonase' => $tonase_area,
                'harga_satuan' => $harga_satuan,
                'subtotal' => $subtotal_area,
                'warehouse_id' => $warehouse_id
            ];
        }

        $ppn_nominal = ($subtotal * $ppn) / 100;
        $total_biaya = $subtotal + $ppn_nominal;

        $conn->begin_transaction();
        
        // Update invoice utama
        if($ttd_id !== null){
            $stmt = $conn->prepare("UPDATE invoice SET 
                nomor_invoice=?, po_id=?, tanggal_invoice=?, partner_id=?, barang_id=?, 
                kapal_id=?, harga_id=?, total_tonase=?, ppn=?, total_biaya=?, ttd_id=? 
                WHERE invoice_id=?");
            $stmt->bind_param(
                "sisiiiidddii",
                $nomor_invoice,
                $po_id,
                $tanggal_invoice,
                $partner_id,
                $barang_id,
                $kapal_id,
                $harga_id,
                $total_tonase,
                $ppn,
                $total_biaya,
                $ttd_id,
                $invoice_id
            );
        } else {
            $stmt = $conn->prepare("UPDATE invoice SET 
                nomor_invoice=?, po_id=?, tanggal_invoice=?, partner_id=?, barang_id=?, 
                kapal_id=?, harga_id=?, total_tonase=?, ppn=?, total_biaya=?, ttd_id=NULL 
                WHERE invoice_id=?");
            $stmt->bind_param(
                "sisiiiidddi",
                $nomor_invoice,
                $po_id,
                $tanggal_invoice,
                $partner_id,
                $barang_id,
                $kapal_id,
                $harga_id,
                $total_tonase,
                $ppn,
                $total_biaya,
                $invoice_id
            );
        }

        if(!$stmt->execute()){
            throw new Exception("Gagal mengupdate invoice: " . $stmt->error);
        }
        
        // Hapus detail lama
        $stmt_del = $conn->prepare("DELETE FROM invoice_detail WHERE invoice_id = ?");
        $stmt_del->bind_param("i", $invoice_id);
        $stmt_del->execute();
        
        // Insert detail baru
        $stmt_detail = $conn->prepare("
            INSERT INTO invoice_detail 
            (invoice_id, harga_id, area, tonase, harga_satuan, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach($detail_data as $detail){
            $stmt_detail->bind_param(
                "iisddd",
                $invoice_id,
                $detail['harga_id'],
                $detail['area'],
                $detail['tonase'],
                $detail['harga_satuan'],
                $detail['subtotal']
            );
            
            if(!$stmt_detail->execute()){
                throw new Exception("Gagal menyimpan detail invoice: " . $stmt_detail->error);
            }
        }
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Invoice $nomor_invoice berhasil diupdate!";
        header("Location: invoice.php");
        exit;
        
    } catch(Exception $e){
        if(isset($conn)){
            $conn->rollback();
        }
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: invoice.php");
        exit;
    }
}
// Ambil data Invoice
$result = $conn->query("
    SELECT 
        i.*,
        k.nama_kapal,
        p.nama_partner,
        b.nama_barang,
        po.nomor_po,
        GROUP_CONCAT(DISTINCT h.area SEPARATOR ', ') AS area_list
    FROM invoice i
    LEFT JOIN kapal k ON i.kapal_id = k.kapal_id
    LEFT JOIN partner p ON i.partner_id = p.partner_id
    LEFT JOIN barang b ON i.barang_id = b.barang_id
    LEFT JOIN purchase_order po ON i.po_id = po.po_id
    LEFT JOIN sales_order so ON so.po_id = po.po_id
    LEFT JOIN harga h ON so.harga_id = h.harga_id
    GROUP BY i.invoice_id
    ORDER BY i.tanggal_invoice DESC, i.invoice_id DESC
");

// Mode edit
$editRow = null;
if(isset($_GET['edit'])){
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT i.*, po.nomor_po
        FROM invoice i
        LEFT JOIN purchase_order po ON i.po_id = po.po_id
        WHERE i.invoice_id=?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editRow = $res->fetch_assoc();
}

// ===== QUERY DROPDOWN PO (PERBAIKAN DENGAN FALLBACK) =====
if(!$editRow) {
    // Cek dulu apakah ada PO yang sudah diterima
    $check_po = $conn->query("SELECT COUNT(*) as total FROM purchase_order WHERE terima_po IS NOT NULL");
    $total_po_received = $check_po->fetch_assoc()['total'];
    
    if($total_po_received == 0) {
        // Jika tidak ada PO yang diterima, tampilkan semua PO (untuk debugging)
        $po_list = $conn->query("
            SELECT 
                po.po_id, 
                po.nomor_po, 
                po.uraian_pekerjaan, 
                po.periode,
                po.terima_po,
                'Belum Diterima' as warning
            FROM purchase_order po
            WHERE NOT EXISTS (
                SELECT 1 FROM invoice i WHERE i.po_id = po.po_id
            )
            ORDER BY po.tanggal_po DESC
        ");
    } else {
        // Query normal - hanya PO yang sudah diterima
        $po_list = $conn->query("
            SELECT 
                po.po_id, 
                po.nomor_po, 
                po.uraian_pekerjaan, 
                po.periode,
                po.terima_po,
                NULL as warning
            FROM purchase_order po
            WHERE po.terima_po IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM invoice i WHERE i.po_id = po.po_id
            )
            ORDER BY po.tanggal_po DESC
        ");
    }
} else {
    $editPoId = intval($editRow['po_id']);
    $editInvoiceId = intval($editRow['invoice_id']);
    
    $po_list = $conn->query("
        SELECT DISTINCT 
            po.po_id, 
            po.nomor_po, 
            po.uraian_pekerjaan, 
            po.periode,
            po.terima_po,
            NULL as warning
        FROM purchase_order po
        WHERE po.terima_po IS NOT NULL
        AND (
            NOT EXISTS (
                SELECT 1 FROM invoice i 
                WHERE i.po_id = po.po_id 
                AND i.invoice_id != $editInvoiceId
            )
            OR po.po_id = $editPoId
        )
        ORDER BY po.tanggal_po DESC
    ");
}

$ttd_list = $conn->query("SELECT ttd_id, nama, jabatan FROM ttd ORDER BY nama");

$previewNomorInvoice = '';
if(!$editRow) {
    $previewNomorInvoice = generateNomorInvoice($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice Management</title>
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
    <div class="title">Invoice Management</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-fluid main-content">
    
    <?php
    // Tampilkan pesan sukses/error
    if(isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> ' . htmlspecialchars($_SESSION['success_message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['success_message']);
    }
    if(isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error_message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Form Tambah/Edit Invoice -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-<?= $editRow ? 'text' : 'plus' ?>"></i> 
                <?= $editRow ? 'Edit Invoice' : 'Form Invoice Baru' ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="formInvoice">
                <?php if($editRow): ?>
                <input type="hidden" name="invoice_id" value="<?= $editRow['invoice_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Invoice <span class="text-danger">*</span></label>
                        <?php if($editRow): ?>
                            <input type="text" name="nomor_invoice" class="form-control" 
                                value="<?= htmlspecialchars($editRow['nomor_invoice']) ?>" required>
                            <small class="text-muted">Edit nomor invoice jika diperlukan</small>
                        <?php else: ?>
                            <input type="text" class="form-control bg-light" 
                                value="<?= $previewNomorInvoice ?>" readonly>
                            <small class="text-success"><i class="fas fa-info-circle"></i> Nomor otomatis akan digenerate saat simpan</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">No. PO <span class="text-danger">*</span></label>
                        <select name="po_id" id="po_id" class="form-select" required>
                            <option value="">-- Pilih Purchase Order --</option>
                            <?php 
                            while($row = $po_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['po_id'] ?>" 
                                    <?= ($editRow && $editRow['po_id'] == $row['po_id']) ? 'selected' : '' ?>
                                    data-uraian="<?= htmlspecialchars($row['uraian_pekerjaan']) ?>"
                                    data-periode="<?= htmlspecialchars($row['periode']) ?>">
                                    <?= htmlspecialchars($row['nomor_po']) ?> - <?= htmlspecialchars($row['uraian_pekerjaan']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if($po_list->num_rows == 0 && !$editRow): ?>
                            <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Tidak ada PO yang tersedia. Pastikan PO sudah diterima.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_invoice" class="form-control" 
                            value="<?= $editRow ? $editRow['tanggal_invoice'] : date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">PPN (%)</label>
                        <input type="number" name="ppn" id="ppn" class="form-control" step="0.01" 
                            value="<?= $editRow ? $editRow['ppn'] : '11' ?>" placeholder="Masukkan persentase PPN">
                        <small class="text-muted">Contoh: 11 untuk PPN 11%</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanda Tangan (Hormat Kami)</label>
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
                        <p class="text-muted text-center">Pilih PO untuk melihat detail</p>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <?php if($editRow): ?>
                    <button type="submit" name="update" class="btn btn-success">
                        <i class="bi bi-save"></i> Update Invoice
                    </button>
                    <a href="invoice.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                    <?php else: ?>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Invoice
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Invoice -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Daftar Invoice</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-8">
                    <input type="text" id="searchInvoice" class="form-control" placeholder="🔍 Cari nomor invoice, PO, kapal...">
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
                            <th>No. Invoice</th>
                            <th>No. PO</th>
                            <th>Tanggal</th>
                            <th>Kapal</th>
                            <th>Barang</th>
                            <th>Area</th>
                            <th>Total Tonase</th>
                            <th>PPN %</th>
                            <th>Total Biaya</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTable">
                        <?php if($result && $result->num_rows > 0): 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong class="text-primary"><?= htmlspecialchars($row['nomor_invoice']) ?></strong></td>
                            <td><?= htmlspecialchars($row['nomor_po']) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_invoice'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_kapal']) ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><span class="badge-area"><?= htmlspecialchars($row['area_list'] ?? '-') ?></span></td>
                            <td><?= number_format($row['total_tonase'], 3, ',', '.') ?></td>
                            <td><?= number_format($row['ppn'], 2, ',', '.') ?>%</td>
                            <td>Rp <?= number_format($row['total_biaya'], 2, ',', '.') ?></td>
                            <td>
                                <a href="?edit=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="cetak_invoice.php?invoice_id=<?= $row['invoice_id'] ?>&print=1" target="_blank" class="btn btn-sm btn-success btn-action" title="Cetak">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <a href="?delete=<?= $row['invoice_id'] ?>" 
                                   class="btn btn-sm btn-danger btn-action" 
                                   title="Hapus"
                                   onclick="return confirm('⚠️ PERHATIAN!\n\nAnda akan menghapus invoice:\n<?= htmlspecialchars($row['nomor_invoice']) ?>\n\nSetelah dihapus:\n✓ PO akan tersedia kembali untuk membuat invoice baru\n✓ Data invoice dan detailnya akan dihapus permanen\n\nLanjutkan penghapusan?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Tidak ada data invoice</td>
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

// Preview PO Details - Load on page load if editing
<?php if($editRow): ?>
window.addEventListener('DOMContentLoaded', function() {
    loadPreview(<?= $editRow['po_id'] ?>);
});
<?php endif; ?>

// Preview PO Details
document.getElementById('po_id').addEventListener('change', function() {
    loadPreview(this.value);
});

async function loadPreview(poId) {
    const preview = document.getElementById('previewDetail');
    
    if(!poId) {
        preview.innerHTML = '<p class="text-muted text-center">Pilih PO untuk melihat detail</p>';
        return;
    }
    
    preview.innerHTML = '<p class="text-center"><i class="bi bi-hourglass-split"></i> Memuat data...</p>';
    
    try {
        const response = await fetch('get_po_detail.php?po_id=' + poId);
        const data = await response.json();
        
        if(data.success) {
            let html = '';
            data.data.forEach(item => {
                html += `
                    <div class="mb-3 p-2 border-bottom">
                        <table class="table table-sm detail-table mb-0">
                            <tr><th width="150">Area:</th><td>${item.area}</td></tr>
                            <tr><th>Kapal:</th><td>${item.kapal}</td></tr>
                            <tr><th>Barang:</th><td>${item.barang}</td></tr>
                            <tr><th>Partner:</th><td>${item.partner}</td></tr>
                            <tr><th>Periode:</th><td>${item.periode}</td></tr>
                            <tr><th>Total Tonase:</th><td>${item.tonase} Ton</td></tr>
                            <tr><th>Harga Satuan:</th><td>Rp ${item.harga}</td></tr>
                            <tr><th class="text-primary">Estimasi Total:</th><td class="text-primary fw-bold">Rp ${item.estimasi_total}</td></tr>
                        </table>
                    </div>
                `;
            });
            html += `<p class="fw-bold text-end mt-2">Total Keseluruhan: Rp ${data.total_est}</p>`;
            preview.innerHTML = html;
        } else {
            preview.innerHTML = '<p class="text-danger">Gagal memuat data: ' + (data.message || 'Unknown error') + '</p>';
        }
    } catch(e) {
        preview.innerHTML = '<p class="text-danger">Error: ' + e.message + '</p>';
    }
}

// Search Invoice
document.getElementById('searchInvoice').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#invoiceTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

function exportToExcel() {
    const table = document.querySelector('#invoiceTable').closest('table');
    const rows = [];
    
    // Header
    const headers = ['No', 'No. Invoice', 'No. PO', 'Tanggal', 'Kapal', 'Barang', 'Area', 'Total Tonase', 'PPN %', 'Total Biaya'];
    rows.push(headers);
    
    // Data rows (hanya yang visible)
    const tableRows = document.querySelectorAll('#invoiceTable tr');
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
        { wch: 20 }, // No. Invoice
        { wch: 20 }, // No. PO
        { wch: 12 }, // Tanggal
        { wch: 25 }, // Kapal
        { wch: 20 }, // Barang
        { wch: 20 }, // Area
        { wch: 15 }, // Total Tonase
        { wch: 10 }, // PPN %
        { wch: 20 }  // Total Biaya
    ];
    
    XLSX.utils.book_append_sheet(wb, ws, 'Invoice');
    
    const today = new Date();
    const filename = `Invoice_${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}.xlsx`;
    
    XLSX.writeFile(wb, filename);
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>