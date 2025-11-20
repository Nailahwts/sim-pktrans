<?php
session_start();
if(!isset($_SESSION['admin'])){
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

require_once '../config/database.php';

$realisasi_id = intval($_GET['realisasi_id']);

$stmt = $conn->prepare("
    SELECT berita_acara, memo, tanggal, tanggal_ba, tanggal_invoice, rekanan_persen
    FROM laporan_realisasi
    WHERE realisasi_id = ?
");
$stmt->bind_param("i", $realisasi_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo json_encode(['success'=>false, 'message'=>'Data tidak ditemukan']);
    exit;
}

$data = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>