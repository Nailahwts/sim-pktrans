<?php
session_start();
if(!isset($_SESSION['admin'])){
    http_response_code(401);
    exit;
}

include "../config/database.php";

header('Content-Type: application/json');

$kapal_id = intval($_GET['kapal_id'] ?? 0);

if(!$kapal_id){
    echo json_encode([]);
    exit;
}

$query = "SELECT DISTINCT w.warehouse_id, w.nama_warehouse 
          FROM warehouse w
          INNER JOIN tarif t ON w.warehouse_id = t.warehouse
          WHERE t.kapal_id = $kapal_id
          ORDER BY w.nama_warehouse";

$result = $conn->query($query);
$warehouses = [];

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $warehouses[] = $row;
    }
}

echo json_encode($warehouses);
?>