<?php
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

require_once '../config/database.php';

$so_id = intval($_GET['so_id']);

// Query untuk mendapatkan semua SO dengan nomor_so yang sama
$stmt = $conn->prepare("
    SELECT 
        so.so_id,
        so.qty,
        h.harga,
        h.area,
        h.asal_tipe,
        h.asal_id,
        h.tujuan_tipe,
        h.tujuan_id,
        (so.qty * h.harga) as subtotal,
        CASE 
            WHEN h.asal_tipe = 'dermaga' THEN (SELECT nama_dermaga FROM dermaga WHERE dermaga_id = h.asal_id)
            WHEN h.asal_tipe = 'warehouse' THEN (SELECT nama_warehouse FROM warehouse WHERE warehouse_id = h.asal_id)
        END as asal,
        CASE 
            WHEN h.tujuan_tipe = 'dermaga' THEN (SELECT nama_dermaga FROM dermaga WHERE dermaga_id = h.tujuan_id)
            WHEN h.tujuan_tipe = 'warehouse' THEN (SELECT nama_warehouse FROM warehouse WHERE warehouse_id = h.tujuan_id)
        END as tujuan
    FROM sales_order so
    JOIN harga h ON so.harga_id = h.harga_id
    WHERE so.nomor_so = (SELECT nomor_so FROM sales_order WHERE so_id = ?)
    ORDER BY so.so_id
");
$stmt->bind_param("i", $so_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo json_encode(['success'=>false, 'message'=>'Tidak ada data untuk Sales Order ini']);
    exit;
}

$areas = [];
$total_qty = 0;
$total_biaya = 0;
$line = 1;

while($row = $result->fetch_assoc()){
    $qty = floatval($row['qty']);
    $harga = floatval($row['harga']);
    $subtotal = $qty * $harga;
    
    $total_qty += $qty;
    $total_biaya += $subtotal;
    
    $areas[] = [
        'line' => $line++,
        'so_id' => $row['so_id'],
        'area' => $row['area'] ?: 'Area ' . $line,
        'asal' => $row['asal'] ?: '-',
        'tujuan' => $row['tujuan'] ?: '-',
        'qty' => number_format($qty, 3, ',', '.'),
        'harga' => number_format($harga, 0, ',', '.'),
        'subtotal' => number_format($subtotal, 0, ',', '.')
    ];
}

echo json_encode([
    'success' => true,
    'areas' => $areas,
    'total_qty' => number_format($total_qty, 3, ',', '.'),
    'total_biaya' => number_format($total_biaya, 0, ',', '.')
]);
?>