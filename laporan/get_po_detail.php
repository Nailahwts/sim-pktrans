<?php
session_start();
if(!isset($_SESSION['admin'])){
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include "../config/database.php";

if(!isset($_GET['po_id'])){
    die(json_encode(['success' => false, 'message' => 'PO ID required']));
}

$po_id = intval($_GET['po_id']);

// Ambil semua SO yang terkait dengan PO ini - LANGSUNG AMBIL QTY DARI SO
$stmt = $conn->prepare("
    SELECT 
        so.so_id,
        so.partner_id,
        so.barang_id,
        so.kapal_id,
        so.harga_id,
        so.periode,
        so.qty,
        p.nama_partner,
        b.nama_barang,
        k.nama_kapal,
        h.area,
        h.harga,
        h.tujuan_id,
        h.tujuan_tipe,
        w.nama_warehouse,
        d.nama_dermaga
    FROM sales_order so
    JOIN partner p ON so.partner_id = p.partner_id
    JOIN barang b ON so.barang_id = b.barang_id
    JOIN kapal k ON so.kapal_id = k.kapal_id
    JOIN harga h ON so.harga_id = h.harga_id
    LEFT JOIN warehouse w ON h.tujuan_id = w.warehouse_id AND h.tujuan_tipe = 'warehouse'
    LEFT JOIN dermaga d ON h.tujuan_id = d.dermaga_id AND h.tujuan_tipe = 'dermaga'
    WHERE so.po_id = ?
    ORDER BY h.area
");

$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    die(json_encode(['success' => false, 'message' => 'Tidak ada data SO untuk PO ini']));
}

$data = [];
$grand_total = 0;

while($row = $result->fetch_assoc()){
    // LANGSUNG AMBIL TONASE DARI SO.QTY
    $tonase = floatval($row['qty']);
    $harga_satuan = floatval($row['harga']);
    $estimasi_total = $tonase * $harga_satuan;
    $grand_total += $estimasi_total;
    
    $tujuan_nama = $row['tujuan_tipe'] == 'warehouse' ? $row['nama_warehouse'] : $row['nama_dermaga'];
    
    $data[] = [
        'area' => $row['area'],
        'kapal' => $row['nama_kapal'],
        'barang' => $row['nama_barang'],
        'partner' => $row['nama_partner'],
        'periode' => $row['periode'],
        'tujuan' => $tujuan_nama,
        'tujuan_tipe' => $row['tujuan_tipe'],
        'warehouse_id' => $row['tujuan_id'],
        'tonase' => number_format($tonase, 3, ',', '.'),
        'tonase_raw' => $tonase,
        'harga' => number_format($harga_satuan, 2, ',', '.'),
        'harga_raw' => $harga_satuan,
        'estimasi_total' => number_format($estimasi_total, 2, ',', '.'),
        'estimasi_total_raw' => $estimasi_total,
        'harga_id' => $row['harga_id']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'total_est' => number_format($grand_total, 2, ',', '.'),
    'total_est_raw' => $grand_total
]);
?>