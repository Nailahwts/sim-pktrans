<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

// ===== FUNGSI GENERATE NOMOR BA =====
function generateNomorBA($conn) {
    $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT berita_acara 
        FROM laporan_realisasi 
        WHERE berita_acara LIKE CONCAT('BA/', ?, '/%')
        ORDER BY realisasi_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['berita_acara'];
        
        $parts = explode('/', $lastNomor);
        if(count($parts) >= 3) {
            $lastNumber = intval($parts[2]);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
    } else {
        $newNumber = 1;
    }
    
    $newNumber = isset($lastNumber) ? $lastNumber + 1 : 1;

}

// Generate nomor BA baru untuk form
$nomorBABaru = generateNomorBA($conn);

// Ambil username admin
$admin_id = $_SESSION['admin'];
$stmt = $conn->prepare("SELECT username FROM user_admin WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$username = $admin['username'];

// Proses filter report
$report_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$report_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validasi bulan dan tahun
if($report_month < 1 || $report_month > 12) $report_month = date('n');
if($report_year < 2020 || $report_year > 2030) $report_year = date('Y');

// Proses hapus
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    
    // Ambil data untuk mendapatkan BA dan Memo
    $stmt = $conn->prepare("SELECT berita_acara, memo FROM laporan_realisasi WHERE realisasi_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0){
        $data = $result->fetch_assoc();
        
        // Hapus semua record dengan BA dan Memo yang sama
        $stmt_del = $conn->prepare("DELETE FROM laporan_realisasi WHERE berita_acara=? AND memo=?");
        $stmt_del->bind_param("ss", $data['berita_acara'], $data['memo']);
        
        if($stmt_del->execute()){
            $_SESSION['success'] = 'Data berhasil dihapus';
        } else {
            $_SESSION['error'] = 'Gagal menghapus data';
        }
    }
    
    header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
    exit;
}

// Proses tandai selesai
if(isset($_POST['mark_done'])){
    $realisasi_id = intval($_POST['realisasi_id']);
    
    $stmt = $conn->prepare("SELECT berita_acara, memo FROM laporan_realisasi WHERE realisasi_id=?");
    $stmt->bind_param("i", $realisasi_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0){
        $data = $result->fetch_assoc();
        
        $stmt_upd = $conn->prepare("UPDATE laporan_realisasi SET status_selesai=1 WHERE berita_acara=? AND memo=?");
        $stmt_upd->bind_param("ss", $data['berita_acara'], $data['memo']);
        
        if($stmt_upd->execute()){
            $_SESSION['success'] = 'Laporan berhasil ditandai selesai';
        } else {
            $_SESSION['error'] = 'Gagal menandai selesai';
        }
    }
    
    header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
    exit;
}

// Proses edit
if(isset($_POST['edit'])){
    $realisasi_id = intval($_POST['edit_realisasi_id']);
    $berita_acara = trim($_POST['edit_berita_acara']);
    $memo = trim($_POST['edit_memo']);
    $tanggal = $_POST['edit_tanggal'];
    $tanggal_ba = $_POST['edit_tanggal_ba'];
    $tanggal_invoice = $_POST['edit_tanggal_invoice'];
    $rekanan_persen = floatval($_POST['edit_rekanan_persen']);
    
    // Validasi input
    if(empty($berita_acara) || empty($memo)) {
        $_SESSION['error'] = 'Berita Acara dan Memo tidak boleh kosong';
        header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
        exit;
    }
    
    $stmt = $conn->prepare("SELECT berita_acara, memo FROM laporan_realisasi WHERE realisasi_id=?");
    $stmt->bind_param("i", $realisasi_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0){
        $old_data = $result->fetch_assoc();
        
        $stmt_upd = $conn->prepare("UPDATE laporan_realisasi 
            SET berita_acara=?, memo=?, tanggal=?, tanggal_ba=?, tanggal_invoice=?, 
                rekanan_persen=?,
                rekanan_harga = harga * (? / 100),
                hpp = (harga * (? / 100) * qty) - denda,
                margin = omzet - ((harga * (? / 100) * qty) - denda)
            WHERE berita_acara=? AND memo=?");
        $stmt_upd->bind_param("sssssddddss", 
            $berita_acara, $memo, $tanggal, $tanggal_ba, $tanggal_invoice, 
            $rekanan_persen, $rekanan_persen, $rekanan_persen, $rekanan_persen,
            $old_data['berita_acara'], $old_data['memo']
        );
        
        if($stmt_upd->execute()){
            $_SESSION['success'] = 'Data berhasil diupdate';
        } else {
            $_SESSION['error'] = 'Gagal mengupdate data';
        }
    }
    
    header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
    exit;
}

// Proses simpan laporan realisasi
if(isset($_POST['submit'])){
    $report_month = intval($_POST['report_month']);
    $report_year = intval($_POST['report_year']);
    
    // Validasi bulan dan tahun
    if($report_month < 1 || $report_month > 12 || $report_year < 2020 || $report_year > 2030) {
        $_SESSION['error'] = 'Bulan atau tahun tidak valid';
        header("Location: laporan_realisasi.php");
        exit;
    }
    
    // Gunakan nomor BA dari input form
    $berita_acara = trim($_POST['berita_acara']);
    
    // Validasi: Jika BA kosong, generate otomatis
    if(empty($berita_acara)) {
        $berita_acara = generateNomorBA($conn);
    }
    
    $memo = trim($_POST['memo']);
    $tanggal = $_POST['tanggal'];
    $partner_id = intval($_POST['partner_id']);
    $so_id = intval($_POST['so_id']);
    $rate = floatval($_POST['rate']);
    $rekanan_persen = floatval($_POST['rekanan_persen']);
    $tanggal_ba = $_POST['tanggal_ba'];
    $tanggal_invoice = $_POST['tanggal_invoice'];
    
    // Validasi input required
    if(empty($tanggal) || $partner_id <= 0 || $so_id <= 0) {
        $_SESSION['error'] = 'Semua field wajib diisi';
        header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
        exit;
    }
    
    // Ambil nomor SO
    $stmt_so_nomor = $conn->prepare("SELECT nomor_so FROM sales_order WHERE so_id = ?");
    $stmt_so_nomor->bind_param("i", $so_id);
    $stmt_so_nomor->execute();
    $so_nomor_result = $stmt_so_nomor->get_result();
    
    if($so_nomor_result->num_rows == 0){
        $_SESSION['error'] = 'Sales Order tidak ditemukan';
        header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
        exit;
    }
    
    $nomor_so = $so_nomor_result->fetch_assoc()['nomor_so'];
    
    // Ambil semua SO dengan nomor_so yang sama
    $stmt_all_so = $conn->prepare("
        SELECT 
            so.so_id,
            so.nomor_so,
            so.barang_id,
            so.qty,
            so.denda,
            b.nama_barang,
            k.nama_kapal,
            po.nomor_po,
            po.uraian_pekerjaan,
            po.periode,
            h.harga,
            h.area,
            h.asal_tipe,
            h.asal_id,
            h.tujuan_tipe,
            h.tujuan_id,
            CASE 
                WHEN h.asal_tipe = 'dermaga' THEN (SELECT nama_dermaga FROM dermaga WHERE dermaga_id = h.asal_id)
                WHEN h.asal_tipe = 'warehouse' THEN (SELECT nama_warehouse FROM warehouse WHERE warehouse_id = h.asal_id)
                ELSE '-'
            END as asal,
            CASE 
                WHEN h.tujuan_tipe = 'dermaga' THEN (SELECT nama_dermaga FROM dermaga WHERE dermaga_id = h.tujuan_id)
                WHEN h.tujuan_tipe = 'warehouse' THEN (SELECT nama_warehouse FROM warehouse WHERE warehouse_id = h.tujuan_id)
                ELSE '-'
            END as tujuan
        FROM sales_order so
        JOIN barang b ON so.barang_id = b.barang_id
        JOIN kapal k ON so.kapal_id = k.kapal_id
        JOIN harga h ON so.harga_id = h.harga_id
        LEFT JOIN purchase_order po ON so.po_id = po.po_id
        WHERE so.nomor_so = ?
        ORDER BY so.so_id
    ");
    $stmt_all_so->bind_param("s", $nomor_so);
    $stmt_all_so->execute();
    $all_so_result = $stmt_all_so->get_result();
    
    if($all_so_result->num_rows == 0){
        $_SESSION['error'] = 'Detail Sales Order tidak ditemukan';
        header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
        exit;
    }
    
    // Ambil invoice
    $stmt_inv = $conn->prepare("SELECT nomor_invoice FROM invoice WHERE po_id = (SELECT po_id FROM sales_order WHERE so_id = ?) ORDER BY tanggal_invoice DESC LIMIT 1");
    $stmt_inv->bind_param("i", $so_id);
    $stmt_inv->execute();
    $inv_result = $stmt_inv->get_result();
    $nomor_invoice = $inv_result->num_rows > 0 ? $inv_result->fetch_assoc()['nomor_invoice'] : '';
    
    $line = 1;
    $successCount = 0;
    $errors = [];
    
    // Insert data untuk setiap line
    while($so_row = $all_so_result->fetch_assoc()){
        $area = $so_row['area'] ?: 'Area ' . $line;
        $asal = $so_row['asal'] ?: '-';
        $tujuan = $so_row['tujuan'] ?: '-';
        $harga = $so_row['harga'] ?: 0;
        $qty = $so_row['qty'];
        $denda = $so_row['denda'] ?: 0;
        
        $rekanan_harga = $harga * ($rekanan_persen / 100);
        $omzet = ($harga * $qty) - $denda;
        $hpp = ($rekanan_harga * $qty) - $denda;
        $margin = $omzet - $hpp;
        
        $stmt_insert = $conn->prepare("INSERT INTO laporan_realisasi 
            (report_month, report_year, berita_acara, memo, tanggal, partner_id, so_id, nomor_so, barang_id, nama_barang, 
             uraian_kerjaan, periode, nomor_po, line, asal, tujuan, area, rate, qty, harga, rekanan_persen, rekanan_harga, 
             omzet, hpp, denda, margin, nomor_invoice, tanggal_ba, tanggal_invoice)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        
        if($stmt_insert){
            $stmt_insert->bind_param("iisssiisissssisssddddddddssss", 
                $report_month, $report_year, $berita_acara, $memo, $tanggal, $partner_id, $so_row['so_id'], $so_row['nomor_so'],
                $so_row['barang_id'], $so_row['nama_barang'], $so_row['nama_kapal'], $so_row['periode'], $so_row['nomor_po'],
                $line, $asal, $tujuan, $area, $rate, $qty, $harga, $rekanan_persen, $rekanan_harga, $omzet, $hpp, $denda,
                $margin, $nomor_invoice, $tanggal_ba, $tanggal_invoice
            );
            
            if($stmt_insert->execute()){
                $successCount++;
            } else {
                $errors[] = "Gagal menyimpan line $line: " . $stmt_insert->error;
            }
        } else {
            $errors[] = "Gagal mempersiapkan statement untuk line $line";
        }
        $line++;
    }
    
    if($successCount > 0){
        $_SESSION['success'] = "Berhasil menyimpan $successCount baris data realisasi dengan nomor BA: $berita_acara";
    } else {
        $_SESSION['error'] = 'Gagal menyimpan data: ' . implode(', ', $errors);
    }
    
    header("Location: laporan_realisasi.php?month=$report_month&year=$report_year");
    exit;
}

// Ambil data master dengan prepared statements
$partners = $conn->query("SELECT * FROM partner ORDER BY nama_partner");

// Query sales orders yang belum direalisasi bulan ini
$stmt_sales_orders = $conn->prepare("
    SELECT so.*, p.nama_partner, b.nama_barang, k.nama_kapal 
    FROM sales_order so 
    JOIN partner p ON so.partner_id = p.partner_id
    JOIN barang b ON so.barang_id = b.barang_id
    JOIN kapal k ON so.kapal_id = k.kapal_id
    WHERE so.so_id NOT IN (
        SELECT DISTINCT so_id FROM laporan_realisasi 
        WHERE report_month = ? AND report_year = ?
    )
    ORDER BY so.tanggal DESC
");
$stmt_sales_orders->bind_param("ii", $report_month, $report_year);
$stmt_sales_orders->execute();
$sales_orders = $stmt_sales_orders->get_result();

// Query laporan dengan prepared statement
$stmt_reports = $conn->prepare("
    SELECT lr.*, p.nama_partner,
           (SELECT COUNT(*) FROM laporan_realisasi lr2 
            WHERE lr2.berita_acara = lr.berita_acara AND lr2.memo = lr.memo) as total_lines
    FROM laporan_realisasi lr
    JOIN partner p ON lr.partner_id = p.partner_id
    WHERE lr.report_month = ? AND lr.report_year = ?
    GROUP BY lr.berita_acara, lr.memo
    ORDER BY lr.tanggal DESC, lr.berita_acara, lr.memo
");
$stmt_reports->bind_param("ii", $report_month, $report_year);
$stmt_reports->execute();
$realisasiResult = $stmt_reports->get_result();

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Statistics dengan prepared statement
$stmt_stats = $conn->prepare("
    SELECT 
        SUM(omzet) as total_omzet,
        SUM(hpp) as total_hpp,
        SUM(margin) as total_margin,
        SUM(qty) as total_qty
    FROM laporan_realisasi
    WHERE report_month = ? AND report_year = ?
");
$stmt_stats->bind_param("ii", $report_month, $report_year);
$stmt_stats->execute();
$totalResult = $stmt_stats->get_result();
$totalData = $totalResult->fetch_assoc();

$totalOmzet = $totalData['total_omzet'] ?? 0;
$totalHPP = $totalData['total_hpp'] ?? 0;
$totalMargin = $totalData['total_margin'] ?? 0;
$totalQty = $totalData['total_qty'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Realisasi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #3e4e92ff 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ===== Sidebar ===== */
.main-content {
    margin-left: 0px;
    transition: all 0.3s ease;
}

.sidebar {
    position: fixed;
    top: 0;
    left: -320px;
    width: 320px;
    height: 100%;
    background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
    color: #fff;
    overflow-y: auto;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 1050;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar.active { 
    left: 0;
    box-shadow: 4px 0 30px rgba(0, 0, 0, 0.5);
}

.sidebar-header { 
    padding: 25px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h3 {
    font-size: 22px;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.sidebar-menu { 
    list-style: none;
    padding: 1px 0;
    margin: 0;
}

.sidebar-menu li { 
    list-style: none;
    padding: 2px 5px;
    margin: 3px 0;
}

.sidebar-menu li a {
    color: #fff;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 12px;
    transition: all 0.3s ease;
    font-size: 15px;
    font-weight: 500;
    position: relative;
    overflow: hidden;
}

.sidebar-menu li a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.15);
    padding-left: 28px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.sidebar-menu li a:hover::before {
    transform: scaleY(1);
}

.sidebar-menu li a i {
    margin-right: 12px;
    font-size: 18px;
    width: 25px;
    text-align: center;
}

/* ===== Hamburger ===== */
.hamburger-btn {
    position: fixed;
    top: 10px;
    left: 18px;
    z-index: 1100;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: #fff;
    padding: 12px 16px;
    font-size: 22px;
    cursor: pointer;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.hamburger-btn:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.hamburger-btn.shifted { 
    left: 335px;
}

/* ===== Overlay ===== */
#overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(3px);
    display: none;
    z-index: 1040;
    transition: all 0.3s ease;
}

#overlay.active { display: block; }

/* ===== Dropdown custom ===== */
.submenu { 
    display: none;
    padding-left: 20px;
    list-style: none;
    margin-top: 1px;
}

.submenu.active { 
    display: block !important;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.submenu li a {
    padding: 10px 18px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.05);
    margin: 1px 0;
}

.arrow { 
    float: right;
    transition: transform 0.3s ease;
    font-size: 12px;
}

.arrow::before { 
    font-size: 11px;
}

.dropdown-toggle.active .arrow {
    transform: rotate(180deg);
}

.dropdown-toggle::after {
    display: none;
}

/* ===== Header ===== */
.header {
    position: sticky;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 1030;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.header .title { 
    margin-left: 50px;
    font-size: 24px;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.header .user-info { 
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
}

.header .user-info .username {
    padding: 8px 18px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(10px);
}

.header .user-info a.logout-btn {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: #fff;
    padding: 10px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(52, 11, 21, 0.4);
    display: inline-block;
}

.header .user-info a.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 65, 108, 0.5);
    filter: brightness(1.1);
}

/* ===== Main Content ===== */
.container-fluid {
    padding: 30px;
}

/* ===== Card Styles ===== */
.card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    animation: fadeInUp 0.5s ease;
    margin-bottom: 30px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none;
    border-radius: 18px 18px 0 0 !important;
    padding: 20px 25px;
    color: white;
}

.card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 20px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.card-body {
    padding: 25px;
}

/* ===== Stats Box ===== */
.stats-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.stats-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
}

.stats-box h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.stats-box p {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* ===== Filter Section ===== */
.filter-section {
    background: rgba(255, 255, 255, 0.98);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* ===== Button Styles ===== */
.btn {
    border-radius: 10px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
}

.btn-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: #fff;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
    filter: brightness(1.1);
}

.btn-danger {
    background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
    color: #fff;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 65, 108, 0.4);
    filter: brightness(1.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    filter: brightness(1.1);
}

.btn-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: #fff;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
    filter: brightness(1.1);
}

.btn-secondary {
    background: linear-gradient(135deg, #868f96 0%, #596164 100%);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(134, 143, 150, 0.4);
    filter: brightness(1.1);
}

.btn-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: #fff;
}

.btn-light {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
    border: 2px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(5px);
}

.btn-light:hover {
    background: rgba(255, 255, 255, 1);
    color: #667eea;
}

/* ===== Table Styles ===== */
.table-responsive {
    border-radius: 12px;
    overflow-x: auto;
    overflow-y: auto;
    max-height: 600px;
    -webkit-overflow-scrolling: touch;
}

.table-responsive::-webkit-scrollbar {
    height: 10px;
    width: 10px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
}

.table {
    margin: 0;
    background: white;
    font-size: 0.85rem;
}

.table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-weight: 600;
    border: none;
    padding: 12px 8px;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.5px;
    text-align: center;
    vertical-align: middle;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(102, 126, 234, 0.1);
    transform: scale(1.001);
}

.table tbody td {
    padding: 10px 8px;
    vertical-align: middle;
}

.form-label {
    font-weight: 600;
    color: #2a5298;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-control, .form-select {
    border: 2px solid #e0e6ed;
    border-radius: 10px;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* ===== Badge Styles ===== */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-selesai {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.badge-belum {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

/* ===== Preview Table ===== */
.preview-table {
    font-size: 0.85rem;
}

.preview-table th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    font-weight: 600;
    padding: 10px;
}

/* ===== Modal Styles ===== */
.modal-content {
    border: none;
    border-radius: 18px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
    border-radius: 18px 18px 0 0;
    border-bottom: none;
    padding: 20px 25px;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: none;
    padding: 15px 25px 20px;
}

/* ===== Alert Styles ===== */
.alert {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .header .title {
        font-size: 18px;
        margin-left: 20px;
    }
    
    .hamburger-btn.shifted {
        left: 295px;
    }
    
    .sidebar {
        width: 280px;
        left: -280px;
    }
    
    .stats-box h3 {
        font-size: 1.5rem;
    }
}
</style>

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
    <div class="title">Laporan Realisasi</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="container-fluid main-content">
    <h2 class="mb-3 text-white"><i class="fas fa-chart-line"></i> Laporan Realisasi Bulanan</h2>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filter Report -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Bulan</label>
                <select name="month" class="form-select" required>
                    <?php
                    for($m=1; $m<=12; $m++){
                        $selected = ($m == $report_month) ? 'selected' : '';
                        echo "<option value='$m' $selected>{$months[$m-1]}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tahun</label>
                <select name="year" class="form-select" required>
                    <?php
                    $currentYear = date('Y');
                    for($y=$currentYear-2; $y<=$currentYear+2; $y++){
                        $selected = ($y == $report_year) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Tampilkan Laporan
                </button>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalInput">
                    <i class="fas fa-plus-circle"></i> Input Realisasi
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="stats-box">
                <p><i class="fas fa-dollar-sign"></i> Total Omzet</p>
                <h4>Rp <?= number_format($totalOmzet, 2, ',', '.') ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box">
                <p><i class="fas fa-calculator"></i> Total HPP</p>
                <h4>Rp <?= number_format($totalHPP, 2, ',', '.') ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box">
                <p><i class="fas fa-chart-line"></i> Total Margin</p>
                <h4>Rp <?= number_format($totalMargin, 2, ',', '.') ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box">
                <p><i class="fas fa-weight"></i> Total Qty</p>
                <h4><?= number_format($totalQty, 3, ',', '.') ?> Ton</h4>
            </div>
        </div>
    </div>

    <!-- Tabel Laporan -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-table"></i> Data Realisasi - <?= $months[$report_month-1] ?> <?= $report_year ?></h5>
            <button class="btn btn-light btn-sm" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped table-sm table-realisasi" id="tableRealisasi">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Status</th>
                            <th rowspan="2">Berita Acara</th>
                            <th rowspan="2">Memo</th>
                            <th rowspan="2">Tanggal</th>
                            <th rowspan="2">Partner</th>
                            <th rowspan="2">No SO</th>
                            <th rowspan="2">Barang</th>
                            <th rowspan="2">Uraian Pekerjaan</th>
                            <th rowspan="2">Periode</th>
                            <th rowspan="2">No PO</th>
                            <th rowspan="2">Line</th>
                            <th rowspan="2">Asal</th>
                            <th rowspan="2">Tujuan</th>
                            <th rowspan="2">Area</th>
                            <th rowspan="2">Rate</th>
                            <th rowspan="2">Qty (Ton)</th>
                            <th rowspan="2">Harga</th>
                            <th colspan="2">Rekanan</th>
                            <th rowspan="2">Omzet</th>
                            <th rowspan="2">HPP</th>
                            <th rowspan="2">Denda</th>
                            <th rowspan="2">Margin</th>
                            <th rowspan="2">No Invoice</th>
                            <th rowspan="2">Tgl BA</th>
                            <th rowspan="2">Tgl Invoice</th>
                            <th rowspan="2">Aksi</th>
                        </tr>
                        <tr>
                            <th>%</th>
                            <th>Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Query untuk menampilkan semua detail lines
                        $stmt_detail = $conn->prepare("
                            SELECT lr.*, p.nama_partner 
                            FROM laporan_realisasi lr
                            JOIN partner p ON lr.partner_id = p.partner_id
                            WHERE lr.report_month = ? AND lr.report_year = ?
                            ORDER BY lr.tanggal DESC, lr.berita_acara, lr.memo, lr.line
                        ");
                        $stmt_detail->bind_param("ii", $report_month, $report_year);
                        $stmt_detail->execute();
                        $detailResult = $stmt_detail->get_result();
                        
                        $no = 1;
                        $current_ba_memo = '';
                        $rowspan_data = [];
                        
                        // Hitung rowspan untuk setiap BA/Memo
                        $detailResult->data_seek(0);
                        while($row = $detailResult->fetch_assoc()){
                            $key = $row['berita_acara'] . '|' . $row['memo'];
                            if(!isset($rowspan_data[$key])){
                                $rowspan_data[$key] = [
                                    'count' => 0,
                                    'data' => $row
                                ];
                            }
                            $rowspan_data[$key]['count']++;
                        }
                        
                        $detailResult->data_seek(0);
                        if($detailResult->num_rows > 0):
                            while($row = $detailResult->fetch_assoc()): 
                                $key = $row['berita_acara'] . '|' . $row['memo'];
                                $is_first_row = ($current_ba_memo != $key);
                                if($is_first_row) $current_ba_memo = $key;
                                $rowspan = $rowspan_data[$key]['count'];
                        ?>
                        <tr>
                            <?php if($is_first_row): ?>
                            <td class="text-center" rowspan="<?= $rowspan ?>"><?= $no++ ?></td>
                            <td class="text-center" rowspan="<?= $rowspan ?>">
                                <?php if(isset($row['status_selesai']) && $row['status_selesai'] == 1): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Selesai</span>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="realisasi_id" value="<?= $row['realisasi_id'] ?>">
                                        <button type="submit" name="mark_done" class="btn btn-sm btn-warning" title="Tandai Selesai" onclick="return confirm('Tandai laporan ini sebagai selesai?')">
                                            <i class="fas fa-check-circle"></i> Proses
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['berita_acara']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['berita_acara']) ?></td>
                            <td class="text-center" rowspan="<?= $rowspan ?>"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['nama_partner']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['nomor_so']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['uraian_kerjaan']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['periode']) ?></td>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['nomor_po']) ?></td>
                            <?php endif; ?>
                            
                            <td class="text-center"><?= $row['line'] ?></td>
                            <td><?= htmlspecialchars($row['asal'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['tujuan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['area']) ?></td>
                            <td class="text-end"><?= number_format($row['rate'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($row['qty'], 3, ',', '.') ?></td>
                            <td class="text-end">Rp <?= number_format($row['harga'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($row['rekanan_persen'], 2, ',', '.') ?>%</td>
                            <td class="text-end">Rp <?= number_format($row['rekanan_harga'], 2, ',', '.') ?></td>
                            <td class="text-end"><strong>Rp <?= number_format($row['omzet'], 2, ',', '.') ?></strong></td>
                            <td class="text-end">Rp <?= number_format($row['hpp'], 2, ',', '.') ?></td>
                            <td class="text-end">Rp <?= number_format($row['denda'], 2, ',', '.') ?></td>
                            <td class="text-end"><strong class="text-success">Rp <?= number_format($row['margin'], 2, ',', '.') ?></strong></td>
                            
                            <?php if($is_first_row): ?>
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['nomor_invoice']) ?></td>
                            <td class="text-center" rowspan="<?= $rowspan ?>"><?= $row['tanggal_ba'] ? date('d/m/Y', strtotime($row['tanggal_ba'])) : '-' ?></td>
                            <td class="text-center" rowspan="<?= $rowspan ?>"><?= $row['tanggal_invoice'] ? date('d/m/Y', strtotime($row['tanggal_invoice'])) : '-' ?></td>
                            <td class="text-center" rowspan="<?= $rowspan ?>">
                                <button class="btn btn-primary btn-sm mb-1" onclick="editData(<?= $row['realisasi_id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $row['realisasi_id'] ?>&month=<?= $report_month ?>&year=<?= $report_year ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Hapus semua data dengan BA: <?= htmlspecialchars($row['berita_acara']) ?> dan Memo: <?= htmlspecialchars($row['memo']) ?>?')" 
                                   title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="29" class="text-center text-muted py-4">
                            <i class="fas fa-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2">Belum ada data realisasi untuk bulan ini</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Modal Input Realisasi -->
<div class="modal fade" id="modalInput" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Input Laporan Realisasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formRealisasi">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Bulan</label>
                            <select name="report_month" class="form-select" required>
                                <?php
                                for($m=1; $m<=12; $m++){
                                    $selected = ($m == $report_month) ? 'selected' : '';
                                    echo "<option value='$m' $selected>{$months[$m-1]}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Tahun</label>
                            <select name="report_year" class="form-select" required>
                                <?php
                                $currentYear = date('Y');
                                for($y=$currentYear-2; $y<=$currentYear+2; $y++){
                                    $selected = ($y == $report_year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Berita Acara <span class="text-danger">*</span></label>
                            <input type="text" name="berita_acara" id="input_berita_acara" class="form-control" 
                                   value="<?= htmlspecialchars($nomorBABaru) ?>" 
                                   placeholder="Contoh: BA/2025/001" 
                                   maxlength="100" 
                                   required>
                            <small class="text-success"><i class="fas fa-info-circle"></i> Nomor BA otomatis</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Rate</label>
                            <input type="number" name="rate" class="form-control" step="0.01" value="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Partner <span class="text-danger">*</span></label>
                            <select name="partner_id" class="form-select" required>
                                <option value="">- Pilih Partner -</option>
                                <?php
                                $partners->data_seek(0);
                                while($p = $partners->fetch_assoc()):
                                ?>
                                <option value="<?= $p['partner_id'] ?>"><?= htmlspecialchars($p['nama_partner']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sales Order <span class="text-danger">*</span></label>
                            <select name="so_id" class="form-select" id="selectSO" required>
                                <option value="">- Pilih Sales Order -</option>
                                <?php
                                $sales_orders->data_seek(0);
                                while($so = $sales_orders->fetch_assoc()):
                                ?>
                                <option value="<?= $so['so_id'] ?>" 
                                        data-barang="<?= htmlspecialchars($so['nama_barang']) ?>"
                                        data-kapal="<?= htmlspecialchars($so['nama_kapal']) ?>">
                                    <?= htmlspecialchars($so['nomor_so']) ?> - <?= htmlspecialchars($so['nama_barang']) ?> (<?= htmlspecialchars($so['nama_kapal']) ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Rekanan % <small class="text-muted">(untuk HPP)</small></label>
                            <input type="number" name="rekanan_persen" class="form-control" step="0.01" placeholder="Contoh: 85" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal BA</label>
                            <input type="date" name="tanggal_ba" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal Invoice</label>
                            <input type="date" name="tanggal_invoice" class="form-control" required>
                        </div>
                    </div>
                    
                    <!-- Preview Area -->
                    <div id="previewArea" class="mt-4" style="display:none;">
                        <h6 class="text-primary"><i class="fas fa-eye"></i> Preview Detail per Area</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm preview-table">
                                <thead>
                                    <tr>
                                        <th>Line</th>
                                        <th>Area</th>
                                        <th>Asal → Tujuan</th>
                                        <th>QTY (Ton)</th>
                                        <th>Harga</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="previewTableBody">
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <th colspan="3" class="text-end">TOTAL:</th>
                                        <th class="text-end" id="previewTotalQty">0.000</th>
                                        <th></th>
                                        <th class="text-end" id="previewTotalBiaya">Rp 0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Informasi:</strong> Nomor BA otomatis digenerate (format BA/TAHUN/NOMOR). Anda dapat mengubahnya sebelum menyimpan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Realisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Realisasi -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Laporan Realisasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEdit">
                <input type="hidden" name="edit_realisasi_id" id="edit_realisasi_id">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Perubahan akan diterapkan ke semua line dengan BA/Memo yang sama
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Berita Acara <span class="text-danger">*</span></label>
                            <input type="text" name="edit_berita_acara" id="edit_berita_acara" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Memo <span class="text-danger">*</span></label>
                            <input type="text" name="edit_memo" id="edit_memo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="edit_tanggal" id="edit_tanggal" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal BA</label>
                            <input type="date" name="edit_tanggal_ba" id="edit_tanggal_ba" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal Invoice</label>
                            <input type="date" name="edit_tanggal_invoice" id="edit_tanggal_invoice" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Rekanan % <span class="text-danger">*</span></label>
                            <input type="number" name="edit_rekanan_persen" id="edit_rekanan_persen" class="form-control" step="0.01" required>
                            <small class="text-muted">Perubahan ini akan mempengaruhi perhitungan HPP dan Margin untuk semua line</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

// Refresh Nomor BA setiap modal dibuka
document.getElementById('modalInput')?.addEventListener('show.bs.modal', function() {
    fetch('get_nomor_ba.php')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('input_berita_acara').value = data.nomor_ba;
            }
        })
        .catch(error => {
            console.log('Error refreshing BA:', error);
        });
});

// Preview SO Detail
document.getElementById('selectSO')?.addEventListener('change', function() {
    const soId = this.value;
    if(!soId) {
        document.getElementById('previewArea').style.display = 'none';
        return;
    }
    
    fetch(`get_so_detail.php?so_id=${soId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                const tbody = document.getElementById('previewTableBody');
                tbody.innerHTML = '';
                
                let totalQty = 0;
                let totalBiaya = 0;
                
                data.areas.forEach((area, index) => {
                    const subtotal = area.qty * area.harga;
                    totalQty += parseFloat(area.qty);
                    totalBiaya += subtotal;
                    
                    tbody.innerHTML += `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td>${area.area}</td>
                            <td>${area.asal} → ${area.tujuan}</td>
                            <td class="text-end">${parseFloat(area.qty).toFixed(3)}</td>
                            <td class="text-end">Rp ${parseFloat(area.harga).toLocaleString('id-ID', {minimumFractionDigits: 2})}</td>
                            <td class="text-end">Rp ${subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
                
                document.getElementById('previewTotalQty').textContent = totalQty.toFixed(3);
                document.getElementById('previewTotalBiaya').textContent = 'Rp ' + totalBiaya.toLocaleString('id-ID', {minimumFractionDigits: 2});
                document.getElementById('previewArea').style.display = 'block';
            } else {
                alert(data.message || 'Gagal memuat preview');
                document.getElementById('previewArea').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memuat preview');
        });
});

// Edit Data
function editData(realisasiId) {
    fetch(`get_realisasi_detail.php?realisasi_id=${realisasiId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('edit_realisasi_id').value = realisasiId;
                document.getElementById('edit_berita_acara').value = data.data.berita_acara;
                document.getElementById('edit_memo').value = data.data.memo;
                document.getElementById('edit_tanggal').value = data.data.tanggal;
                document.getElementById('edit_tanggal_ba').value = data.data.tanggal_ba;
                document.getElementById('edit_tanggal_invoice').value = data.data.tanggal_invoice;
                document.getElementById('edit_rekanan_persen').value = data.data.rekanan_persen;
                
                new bootstrap.Modal(document.getElementById('modalEdit')).show();
            } else {
                alert(data.message || 'Gagal memuat data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan');
        });
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('tableRealisasi'); 
    const wb = XLSX.utils.table_to_book(table, {sheet: "Laporan Realisasi"});
    
    const monthName = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const month = <?= $report_month ?> - 1;
    const year = <?= $report_year ?>;
    
    XLSX.writeFile(wb, 'Laporan_Realisasi_' + monthName[month] + '_' + year + '.xlsx');
}
</script>
</body>
</html>