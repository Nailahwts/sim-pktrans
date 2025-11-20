<?php
session_start();
include "../config/database.php";

// Cek session admin
if(!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $barang_id = intval($_POST['barang_id']);
    $kapal_id = intval($_POST['kapal_id']);
    $harga_id = intval($_POST['harga_id']);
    
    // Ambil informasi harga (asal dan tujuan)
    $stmt_harga = $conn->prepare("SELECT asal_tipe, asal_id, tujuan_tipe, tujuan_id FROM harga WHERE harga_id = ?");
    $stmt_harga->bind_param("i", $harga_id);
    $stmt_harga->execute();
    $result_harga = $stmt_harga->get_result();
    
    if($result_harga->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Data harga tidak ditemukan']);
        exit;
    }
    
    $harga_data = $result_harga->fetch_assoc();
    $asal_tipe = $harga_data['asal_tipe'];
    $asal_id = $harga_data['asal_id'];
    $tujuan_tipe = $harga_data['tujuan_tipe'];
    $tujuan_id = $harga_data['tujuan_id'];
    
    // Identifikasi dermaga_id dan warehouse_id
    $dermaga_id = null;
    $warehouse_id = null;
    
    if($asal_tipe == 'dermaga') {
        $dermaga_id = $asal_id;
    } else {
        $warehouse_id = $asal_id;
    }
    
    if($tujuan_tipe == 'dermaga') {
        $dermaga_id = $tujuan_id;
    } else {
        $warehouse_id = $tujuan_id;
    }
    
    // Query untuk mengambil total tonase dari surat_jalan
    // Cari surat jalan yang memiliki kombinasi dermaga dan warehouse yang sama
    // (tidak peduli arah: dermaga->warehouse atau warehouse->dermaga)
    $query = "SELECT SUM(tonase) as total_tonase, COUNT(*) as jumlah_rit 
              FROM surat_jalan 
              WHERE barang_id = ? 
              AND kapal_id = ? 
              AND dermaga_id = ? 
              AND warehouse_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $barang_id, $kapal_id, $dermaga_id, $warehouse_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_tonase = $row['total_tonase'] ? floatval($row['total_tonase']) : 0;
        $jumlah_rit = intval($row['jumlah_rit']);
        
        if($total_tonase > 0) {
            echo json_encode([
                'success' => true, 
                'total_tonase' => number_format($total_tonase, 3, '.', ''),
                'jumlah_rit' => $jumlah_rit,
                'message' => "Data ditemukan dari Surat Jalan"
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Tidak ada data surat jalan untuk kombinasi Barang, Kapal, Dermaga, dan Warehouse yang dipilih'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Tidak ada data surat jalan yang sesuai'
        ]);
    }
    
    $stmt->close();
    $stmt_harga->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>