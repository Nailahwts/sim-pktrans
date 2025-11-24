<?php
// File: laporan/get_nomor_ba.php
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

function generateNomorBA($conn) {
    $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT berita_acara 
        FROM laporan_realisasi 
        WHERE berita_acara LIKE CONCAT('BA/', ?, '/%')
        ORDER BY realisasi_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['berita_acara'];
        
        $parts = explode('/', $lastNomor);
        if(count($parts) >= 3) {
            $lastNumber = intval($parts[2]);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
    } else {
        $newNumber = 1;
    }
    
    return "BA/$year/$newNumber";
}

$nomorBA = generateNomorBA($conn);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'nomor_ba' => $nomorBA,
    'year' => date('Y'),
    'timestamp' => date('Y-m-d H:i:s')
]);