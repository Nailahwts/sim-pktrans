<?php
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

include "../config/database.php";

$kapal_id = intval($_GET['kapal_id']);

// Query untuk mendapatkan detail per area dari Sales Order
$stmt = $conn->prepare("
    SELECT 
        h.area,
        so.qty,
        h.harga,
        (so.qty * h.harga) as subtotal
    FROM sales_order so
    JOIN harga h ON so.harga_id = h.harga_id
    WHERE so.kapal_id = ?
    ORDER BY h.area
");
$stmt->bind_param("i", $kapal_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo json_encode(['success'=>false, 'message'=>'Tidak ada Sales Order untuk kapal ini']);
    exit;
}

$areas = [];
$total_qty = 0;
$total_biaya = 0;

while($row = $result->fetch_assoc()){
    $qty = floatval($row['qty']);
    $harga = floatval($row['harga']);
    $subtotal = floatval($row['subtotal']);
    
    $areas[] = [
        'area' => $row['area'],
        'qty' => number_format($qty, 3, ',', '.'),
        'harga' => number_format($harga, 0, ',', '.'),
        'subtotal' => number_format($subtotal, 0, ',', '.')
    ];
    
    $total_qty += $qty;
    $total_biaya += $subtotal;
}

echo json_encode([
    'success' => true,
    'areas' => $areas,
    'total_qty' => number_format($total_qty, 3, ',', '.'),
    'total_biaya' => number_format($total_biaya, 0, ',', '.')
]);
?>