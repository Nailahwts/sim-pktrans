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

// Ambil parameter dari URL
$barang_id = isset($_GET['barang_id']) ? intval($_GET['barang_id']) : 0;
$kapal_id = isset($_GET['kapal_id']) ? intval($_GET['kapal_id']) : 0;

if($barang_id == 0 || $kapal_id == 0){
    echo "<script>alert('Parameter tidak valid');history.back();</script>";
    exit;
}

// Ambil info barang dan kapal
$stmtInfo = $conn->prepare("SELECT b.nama_barang, k.nama_kapal 
                            FROM barang b, kapal k 
                            WHERE b.barang_id=? AND k.kapal_id=?");
$stmtInfo->bind_param("ii", $barang_id, $kapal_id);
$stmtInfo->execute();
$resultInfo = $stmtInfo->get_result();
$info = $resultInfo->fetch_assoc();
$nama_barang = $info['nama_barang'];
$nama_kapal = $info['nama_kapal'];

// Proses simpan/update koreksi
if(isset($_POST['submit_koreksi'])){
    $tanggal_list = $_POST['tanggal'];
    $warehouse_list = $_POST['warehouse_id'];
    $shift_list = $_POST['shift'];
    $petroport_list = $_POST['petroport'];
    
    $successCount = 0;
    $errorMessages = [];
    
    foreach($tanggal_list as $key => $tanggal){
        $warehouse_id = intval($warehouse_list[$key]);
        $shift = intval($shift_list[$key]);
        $petroport = floatval($petroport_list[$key]);
        
        // Cek apakah data koreksi sudah ada
        $checkStmt = $conn->prepare("SELECT koreksi_id FROM koreksi 
                                      WHERE barang_id=? AND kapal_id=? AND tanggal=? 
                                      AND warehouse_id=? AND shift=?");
        $checkStmt->bind_param("iisii", $barang_id, $kapal_id, $tanggal, $warehouse_id, $shift);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if($checkResult->num_rows > 0){
            // Update existing
            $row = $checkResult->fetch_assoc();
            $koreksi_id = $row['koreksi_id'];
            $updateStmt = $conn->prepare("UPDATE koreksi SET petroport=? WHERE koreksi_id=?");
            $updateStmt->bind_param("di", $petroport, $koreksi_id);
            if($updateStmt->execute()){
                $successCount++;
            } else {
                $errorMessages[] = "Error update: " . $updateStmt->error;
            }
        } else {
            // Insert new
            $insertStmt = $conn->prepare("INSERT INTO koreksi (barang_id, kapal_id, tanggal, warehouse_id, shift, petroport) 
                                          VALUES (?,?,?,?,?,?)");
            $insertStmt->bind_param("iisiii", $barang_id, $kapal_id, $tanggal, $warehouse_id, $shift, $petroport);
            if($insertStmt->execute()){
                $successCount++;
            } else {
                $errorMessages[] = "Error insert: " . $insertStmt->error;
            }
        }
    }
    
    if($successCount > 0){
        $msg = "Berhasil menyimpan $successCount data koreksi";
        if(count($errorMessages) > 0){
            $msg .= "\\n\\nError:\\n" . implode("\\n", $errorMessages);
        }
        echo "<script>alert('$msg');window.location='koreksi.php?barang_id=$barang_id&kapal_id=$kapal_id';</script>";
    } else {
        $msg = "Gagal menyimpan data:\\n" . implode("\\n", $errorMessages);
        echo "<script>alert('$msg');history.back();</script>";
    }
    exit;
}

// Query untuk mengambil data realisasi per tanggal-warehouse-shift
$sqlDetail = "
SELECT 
    sj.tanggal,
    sj.warehouse_id,
    w.nama_warehouse,
    sj.shift,
    SUM(sj.tonase) as realisasi,
    COALESCE(k.petroport, 0) as petroport
FROM surat_jalan sj
JOIN warehouse w ON sj.warehouse_id = w.warehouse_id
LEFT JOIN koreksi k ON k.barang_id = sj.barang_id 
    AND k.kapal_id = sj.kapal_id 
    AND k.tanggal = sj.tanggal 
    AND k.warehouse_id = sj.warehouse_id 
    AND k.shift = sj.shift
WHERE sj.barang_id = ? AND sj.kapal_id = ?
GROUP BY sj.tanggal, sj.warehouse_id, sj.shift
ORDER BY sj.tanggal ASC, w.nama_warehouse ASC, sj.shift ASC
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param("ii", $barang_id, $kapal_id);
$stmtDetail->execute();
$resultDetail = $stmtDetail->get_result();

$detailData = [];
$warehouseTotals = [];

while($row = $resultDetail->fetch_assoc()){
    $detailData[] = $row;
    
    // Hitung total per warehouse
    $wh_id = $row['warehouse_id'];
    $wh_name = $row['nama_warehouse'];
    
    if(!isset($warehouseTotals[$wh_id])){
        $warehouseTotals[$wh_id] = [
            'nama_warehouse' => $wh_name,
            'realisasi' => 0,
            'petroport' => 0,
            'selisih' => 0
        ];
    }
    
    $warehouseTotals[$wh_id]['realisasi'] += $row['realisasi'];
    $warehouseTotals[$wh_id]['petroport'] += $row['petroport'];
}

// Hitung selisih untuk setiap warehouse
foreach($warehouseTotals as $wh_id => $data){
    $warehouseTotals[$wh_id]['selisih'] = $data['petroport'] - $data['realisasi'];
}

$shiftMap = [1 => 'SHIFT 1', 2 => 'SHIFT 2', 3 => 'SHIFT 3'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Koreksi Realisasi - <?= htmlspecialchars($nama_barang) ?> (<?= htmlspecialchars($nama_kapal) ?>)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style_transaksi.css">
<style>
.table-koreksi {
    font-size: 0.9rem;
}
.table-koreksi th {
    background-color: #fff3cd;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
}
.table-koreksi td {
    vertical-align: middle;
}
.table-koreksi input[type="number"] {
    text-align: right;
}
.total-row {
    background-color: #fff3cd;
    font-weight: bold;
}
.negative-value {
    color: #dc3545;
}
.positive-value {
    color: #198754;
}
</style>
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
    <div class="title">Koreksi Realisasi</div>
    <div class="user-info">
        <span class="username"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-fluid main-content">
    
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-edit"></i> Koreksi: <?= htmlspecialchars($nama_barang) ?> - <?= htmlspecialchars($nama_kapal) ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <a href="surat_jalan.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Surat Jalan
                </a>
                <a href="realisasi.php?barang_id=<?= $barang_id ?>&kapal_id=<?= $kapal_id ?>" class="btn btn-primary">
                    <i class="bi bi-list-check"></i> Lihat Realisasi
                </a>
            </div>

            <form method="POST" id="formKoreksi">
                <h6 class="fw-bold mb-3">Tabel Detail per Tanggal - Warehouse - Shift</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-koreksi">
                        <thead>
                            <tr>
                                <th style="width: 12%">TANGGAL</th>
                                <th style="width: 8%">SHIFT</th>
                                <th style="width: 20%">WAREHOUSE</th>
                                <th style="width: 15%">REALISASI</th>
                                <th style="width: 15%">PETROPORT</th>
                                <th style="width: 15%">SELISIH (e - d)</th>
                                <th style="width: 15%">KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalRealisasi = 0;
                            $totalPetroport = 0;
                            foreach($detailData as $row): 
                                $selisih = $row['petroport'] - $row['realisasi'];
                                $totalRealisasi += $row['realisasi'];
                                $totalPetroport += $row['petroport'];
                            ?>
                            <tr>
                                <td class="text-center">
                                    <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                    <input type="hidden" name="tanggal[]" value="<?= $row['tanggal'] ?>">
                                    <input type="hidden" name="warehouse_id[]" value="<?= $row['warehouse_id'] ?>">
                                    <input type="hidden" name="shift[]" value="<?= $row['shift'] ?>">
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $shiftMap[$row['shift']] ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['nama_warehouse']) ?></td>
                                <td class="text-end"><?= number_format($row['realisasi'], 3, ',', '.') ?></td>
                                <td>
                                    <input type="number" name="petroport[]" class="form-control petroport-input" 
                                           step="0.001" value="<?= $row['petroport'] ?>" 
                                           data-row-index="<?= htmlspecialchars(json_encode($row)) ?>">
                                </td>
                                <td class="text-end selisih-cell">
                                    <span class="<?= $selisih < 0 ? 'negative-value' : ($selisih > 0 ? 'positive-value' : '') ?>">
                                        <?= $selisih < 0 ? '(' : '' ?><?= number_format(abs($selisih), 3, ',', '.') ?><?= $selisih < 0 ? ')' : '' ?>
                                    </span>
                                </td>
                                <td class="text-center keterangan-cell">
                                    <?php if($selisih < 0): ?>
                                        <span class="badge bg-danger">Full Timbang</span>
                                    <?php elseif($selisih > 0): ?>
                                        <span class="badge bg-success">Lebih</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3" class="text-end">TOTAL</td>
                                <td class="text-end" id="totalRealisasi"><?= number_format($totalRealisasi, 3, ',', '.') ?></td>
                                <td class="text-end" id="totalPetroport"><?= number_format($totalPetroport, 3, ',', '.') ?></td>
                                <td class="text-end" id="totalSelisih">
                                    <?php 
                                    $totalSelisih = $totalPetroport - $totalRealisasi;
                                    ?>
                                    <span class="<?= $totalSelisih < 0 ? 'negative-value' : ($totalSelisih > 0 ? 'positive-value' : '') ?>">
                                        <?= $totalSelisih < 0 ? '(' : '' ?><?= number_format(abs($totalSelisih), 3, ',', '.') ?><?= $totalSelisih < 0 ? ')' : '' ?>
                                    </span>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="submit_koreksi" class="btn btn-success">
                        <i class="bi bi-save"></i> Simpan Koreksi
                    </button>
                </div>
            </form>

            <hr class="my-4">

            <h6 class="fw-bold mb-3">Ringkasan per Warehouse</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm table-koreksi">
                    <thead>
                        <tr>
                            <th style="width: 35%">WAREHOUSE</th>
                            <th style="width: 20%">REALISASI</th>
                            <th style="width: 20%">PETROPORT</th>
                            <th style="width: 25%">SELISIH (e - d)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotalRealisasi = 0;
                        $grandTotalPetroport = 0;
                        foreach($warehouseTotals as $wh_id => $data): 
                            $grandTotalRealisasi += $data['realisasi'];
                            $grandTotalPetroport += $data['petroport'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($data['nama_warehouse']) ?></td>
                            <td class="text-end"><?= number_format($data['realisasi'], 3, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($data['petroport'], 3, ',', '.') ?></td>
                            <td class="text-end">
                                <span class="<?= $data['selisih'] < 0 ? 'negative-value' : ($data['selisih'] > 0 ? 'positive-value' : '') ?>">
                                    <?= $data['selisih'] < 0 ? '(' : '' ?><?= number_format(abs($data['selisih']), 3, ',', '.') ?><?= $data['selisih'] < 0 ? ')' : '' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td class="text-end">TOTAL</td>
                            <td class="text-end"><?= number_format($grandTotalRealisasi, 3, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($grandTotalPetroport, 3, ',', '.') ?></td>
                            <td class="text-end">
                                <?php 
                                $grandTotalSelisih = $grandTotalPetroport - $grandTotalRealisasi;
                                ?>
                                <span class="<?= $grandTotalSelisih < 0 ? 'negative-value' : ($grandTotalSelisih > 0 ? 'positive-value' : '') ?>">
                                    <?= $grandTotalSelisih < 0 ? '(' : '' ?><?= number_format(abs($grandTotalSelisih), 3, ',', '.') ?><?= $grandTotalSelisih < 0 ? ')' : '' ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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

// Auto-calculate selisih when petroport changes
document.querySelectorAll('.petroport-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        const realisasiText = row.cells[3].textContent.trim();
        const realisasi = parseFloat(realisasiText.replace(/\./g, '').replace(',', '.'));
        const petroport = parseFloat(this.value) || 0;
        const selisih = petroport - realisasi;
        
        const selisihCell = row.querySelector('.selisih-cell');
        const keteranganCell = row.querySelector('.keterangan-cell');
        
        // Update selisih display
        let selisihClass = '';
        if(selisih < 0) selisihClass = 'negative-value';
        else if(selisih > 0) selisihClass = 'positive-value';
        
        selisihCell.innerHTML = `<span class="${selisihClass}">${selisih < 0 ? '(' : ''}${Math.abs(selisih).toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3})}${selisih < 0 ? ')' : ''}</span>`;
        
        // Update keterangan badge
        let badgeHTML = '';
        if(selisih < 0) {
            badgeHTML = '<span class="badge bg-danger">Full Timbang</span>';
        } else if(selisih > 0) {
            badgeHTML = '<span class="badge bg-success">Lebih</span>';
        } else {
            badgeHTML = '<span class="badge bg-secondary">-</span>';
        }
        keteranganCell.innerHTML = badgeHTML;
        
        // Recalculate totals
        calculateTotals();
    });
});

function calculateTotals() {
    let totalRealisasi = 0;
    let totalPetroport = 0;
    
    document.querySelectorAll('.petroport-input').forEach((input, index) => {
        const row = input.closest('tr');
        const realisasiText = row.cells[3].textContent.trim();
        const realisasi = parseFloat(realisasiText.replace(/\./g, '').replace(',', '.'));
        const petroport = parseFloat(input.value) || 0;
        
        totalRealisasi += realisasi;
        totalPetroport += petroport;
    });
    
    const totalSelisih = totalPetroport - totalRealisasi;
    
    // Update total displays
    document.getElementById('totalRealisasi').textContent = totalRealisasi.toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3});
    document.getElementById('totalPetroport').textContent = totalPetroport.toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3});
    
    let selisihClass = '';
    if(totalSelisih < 0) selisihClass = 'negative-value';
    else if(totalSelisih > 0) selisihClass = 'positive-value';
    
    document.getElementById('totalSelisih').innerHTML = `<span class="${selisihClass}">${totalSelisih < 0 ? '(' : ''}${Math.abs(totalSelisih).toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3})}${totalSelisih < 0 ? ')' : ''}</span>`;
}

// Validasi form sebelum submit
document.getElementById('formKoreksi').addEventListener('submit', function(e) {
    const petroportInputs = document.querySelectorAll('.petroport-input');
    let hasValue = false;
    
    petroportInputs.forEach(input => {
        if(parseFloat(input.value) > 0) {
            hasValue = true;
        }
    });
    
    if(!hasValue) {
        e.preventDefault();
        alert('Harap isi minimal satu nilai Petroport!');
        return false;
    }
    
    if(!confirm('Apakah Anda yakin ingin menyimpan data koreksi ini?')) {
        e.preventDefault();
        return false;
    }
});
</script>

</body>
</html>