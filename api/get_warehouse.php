<?php
header('Content-Type: application/json');
include "../config/database.php";

$no_barang = $_GET['no_barang'] ?? null;
$kapal_id = $_GET['kapal_id'] ?? null;

if(!$no_barang || !$kapal_id){
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

// Get warehouse berdasarkan barang dan kapal dari surat_jalan
$query = "SELECT DISTINCT sj.warehouse_id, w.nama_warehouse 
          FROM surat_jalan sj
          LEFT JOIN warehouse w ON w.warehouse_id = sj.warehouse_id
          WHERE sj.no_barang = {$no_barang}
          AND sj.kapal_id = {$kapal_id}
          AND sj.status = 'Aktif'
          ORDER BY w.nama_warehouse";

$result = $conn->query($query);
$data = [];

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $data[] = [
            'warehouse_id' => $row['warehouse_id'],
            'nama_warehouse' => $row['nama_warehouse'] ?? 'Warehouse ' . $row['warehouse_id']
        ];
    }
}

echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
?>