<?php
include "../config/database.php";

$warehouse_id = $_GET['warehouse_id'] ?? null;
header('Content-Type: application/json');

if(!$warehouse_id){
    echo json_encode([]);
    exit;
}

$query = "SELECT DISTINCT k.kapal_id, k.nama_kapal 
          FROM kapal k
          JOIN surat_jalan sj ON sj.kapal_id = k.kapal_id
          WHERE sj.warehouse_id = {$warehouse_id} 
          AND k.status = 'Aktif'
          AND sj.status = 'Aktif'";
$result = $conn->query($query);

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);;
?>