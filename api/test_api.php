<?php
include "../config/database.php";

echo "<h1>API Testing Page</h1>";

// Test 0: Check Column Names
echo "<h2>Test 0: Struktur Tabel surat_jalan</h2>";
$result = $conn->query("DESCRIBE surat_jalan");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while($row = $result->fetch_assoc()){
    echo "<tr><td>".$row['Field']."</td><td>".$row['Type']."</td><td>".$row['Null']."</td><td>".$row['Key']."</td></tr>";
}
echo "</table>";

// Test 0b: Check sample data surat_jalan
echo "<h2>Test 0b: Sample Data surat_jalan</h2>";
$result = $conn->query("SELECT * FROM surat_jalan LIMIT 3");
echo "<pre>";
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "No data found";
}
echo "</pre>";

// Test 1: Get all warehouse_id
echo "<h2>Test 1: Get All Warehouse (dengan warehouse_id)</h2>";
$result = $conn->query("SELECT DISTINCT warehouse_id FROM surat_jalan WHERE warehouse_id IS NOT NULL");
echo "<pre>";
echo "Query: SELECT DISTINCT warehouse_id FROM surat_jalan<br>";
if($result){
    echo "Rows: " . $result->num_rows . "<br>";
    while($row = $result->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

// Test 2: Check kapal table
echo "<h2>Test 2: Struktur Tabel kapal</h2>";
$result = $conn->query("DESCRIBE kapal");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while($row = $result->fetch_assoc()){
    echo "<tr><td>".$row['Field']."</td><td>".$row['Type']."</td></tr>";
}
echo "</table>";

// Test 3: Get kapal sample
echo "<h2>Test 3: Sample Data kapal</h2>";
$result = $conn->query("SELECT * FROM kapal LIMIT 3");
echo "<pre>";
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        print_r($row);
    }
}
echo "</pre>";

// Test 4: Check tanda_tangan table
echo "<h2>Test 4: Struktur Tabel tanda_tangan</h2>";
$result = $conn->query("DESCRIBE tanda_tangan");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while($row = $result->fetch_assoc()){
    echo "<tr><td>".$row['Field']."</td><td>".$row['Type']."</td></tr>";
}
echo "</table>";

// Test 5: Sample tanda_tangan
echo "<h2>Test 5: Sample Data tanda_tangan</h2>";
$result = $conn->query("SELECT * FROM tanda_tangan LIMIT 3");
echo "<pre>";
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        print_r($row);
    }
}
echo "</pre>";

// Test 6: Check perusahaan table
echo "<h2>Test 6: Struktur Tabel perusahaan</h2>";
$result = $conn->query("DESCRIBE perusahaan");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while($row = $result->fetch_assoc()){
    echo "<tr><td>".$row['Field']."</td><td>".$row['Type']."</td></tr>";
}
echo "</table>";

// Test 7: Sample perusahaan
echo "<h2>Test 7: Sample Data perusahaan</h2>";
$result = $conn->query("SELECT * FROM perusahaan LIMIT 3");
echo "<pre>";
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        print_r($row);
    }
}
echo "</pre>";
?>