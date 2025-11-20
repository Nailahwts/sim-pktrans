<?php
header('Content-Type: application/json; charset=utf-8');
include "../config/database.php";

$barang_id = $_GET['no_barang'] ?? null;

if(!$barang_id){
    http_response_code(400);
    echo json_encode(['success' => false, 'data' => [], 'error' => 'Missing no_barang parameter']);
    exit;
}

// Get kapal berdasarkan barang (no_barang) dari surat_jalan
$query = "SELECT DISTINCT sj.kapal_id, k.nama_kapal 
          FROM surat_jalan sj
          LEFT JOIN kapal k ON k.kapal_id = sj.kapal_id
          WHERE sj.no_barang = " . intval($barang_id) . "
          AND k.kapal_id IS NOT NULL
          ORDER BY k.nama_kapal";

$result = $conn->query($query);

if(!$result){
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => [], 'error' => 'Query failed: ' . $conn->error]);
    exit;
}

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = [
        'kapal_id' => intval($row['kapal_id']),
        'nama_kapal' => trim($row['nama_kapal'])
    ];
}

echo json_encode([
    'success' => true, 
    'data' => $data, 
    'count' => count($data)
]);
?>