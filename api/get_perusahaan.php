<?php
header('Content-Type: application/json');
include "../config/database.php";

$id = $_GET['id'] ?? null;

if(!$id){
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

$query = "SELECT perusahaan_id, nama_perusahaan, alamat, kategori 
          FROM perusahaan 
          WHERE perusahaan_id = {$id}";

$result = $conn->query($query);
$data = [];

if($result && $result->num_rows > 0){
    $data = $result->fetch_assoc();
}

echo json_encode(['success' => true, 'data' => $data]);
?>