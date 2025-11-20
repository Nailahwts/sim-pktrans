<?php
include "../config/database.php";

echo "<h1>Debug: Get Kapal by Barang</h1>";

$barang_id = 1; // Test dengan barang_id = 1 (Phospate)

// Query 1: Check surat_jalan data
echo "<h2>1. Data dari surat_jalan untuk no_barang = {$barang_id}</h2>";
$query1 = "SELECT nomor_sj, no_barang, kapal_id, warehouse_id FROM surat_jalan WHERE no_barang = {$barang_id}";
$result1 = $conn->query($query1);
echo "<p>Query: " . htmlspecialchars($query1) . "</p>";
echo "<p>Rows: " . $result1->num_rows . "</p>";
echo "<pre>";
while($row = $result1->fetch_assoc()){
    print_r($row);
}
echo "</pre>";

// Query 2: Check kapal table
echo "<h2>2. Data dari kapal</h2>";
$query2 = "SELECT kapal_id, nama_kapal, status FROM kapal";
$result2 = $conn->query($query2);
echo "<p>Query: " . htmlspecialchars($query2) . "</p>";
echo "<pre>";
while($row = $result2->fetch_assoc()){
    print_r($row);
}
echo "</pre>";

// Query 3: The actual API query
echo "<h2>3. API Query untuk kapal</h2>";
$query3 = "SELECT DISTINCT sj.kapal_id, k.nama_kapal 
          FROM surat_jalan sj
          LEFT JOIN kapal k ON k.kapal_id = sj.kapal_id
          WHERE sj.no_barang = {$barang_id}
          AND k.kapal_id IS NOT NULL
          AND k.status = 'Aktif'
          ORDER BY k.nama_kapal";
$result3 = $conn->query($query3);
echo "<p>Query: " . htmlspecialchars($query3) . "</p>";
echo "<p>Rows: " . ($result3 ? $result3->num_rows : 'ERROR: ' . $conn->error) . "</p>";
echo "<pre>";
if($result3){
    while($row = $result3->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

// Query 4: Warehouse
echo "<h2>4. API Query untuk warehouse (barang=1, kapal=1)</h2>";
$query4 = "SELECT DISTINCT sj.warehouse_id, w.nama_warehouse 
          FROM surat_jalan sj
          LEFT JOIN warehouse w ON w.warehouse_id = sj.warehouse_id
          WHERE sj.no_barang = 1
          AND sj.kapal_id = 1
          AND w.warehouse_id IS NOT NULL
          ORDER BY w.nama_warehouse";
$result4 = $conn->query($query4);
echo "<p>Query: " . htmlspecialchars($query4) . "</p>";
echo "<p>Rows: " . ($result4 ? $result4->num_rows : 'ERROR: ' . $conn->error) . "</p>";
echo "<pre>";
if($result4){
    while($row = $result4->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

// Query 5: Tonase
echo "<h2>5. API Query untuk tonase (barang=1, kapal=1, warehouse=3)</h2>";
$query5 = "SELECT SUM(sj.tonase) as total_tonase, t.tarif, t.area
          FROM surat_jalan sj
          LEFT JOIN tarif t ON t.warehouse = sj.warehouse_id AND t.kapal_id = sj.kapal_id
          WHERE sj.no_barang = 1 
          AND sj.kapal_id = 1
          AND sj.warehouse_id = 3
          GROUP BY sj.no_barang, sj.kapal_id, sj.warehouse_id";
$result5 = $conn->query($query5);
echo "<p>Query: " . htmlspecialchars($query5) . "</p>";
echo "<p>Rows: " . ($result5 ? $result5->num_rows : 'ERROR: ' . $conn->error) . "</p>";
echo "<pre>";
if($result5){
    while($row = $result5->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

?>