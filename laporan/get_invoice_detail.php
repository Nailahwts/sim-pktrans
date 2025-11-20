<?php
include "../config/database.php";

header('Content-Type: application/json');

if(!isset($_GET['invoice_id'])){
    echo json_encode(['success' => false, 'message' => 'Invoice ID required']);
    exit;
}

$invoice_id = intval($_GET['invoice_id']);

$stmt = $conn->prepare("
    SELECT 
        i.nomor_invoice,
        i.total_tonase,
        i.ppn,
        i.total_biaya,
        po.nomor_po,
        po.uraian_pekerjaan,
        po.periode,
        p.nama_partner,
        k.nama_kapal,
        b.nama_barang
    FROM invoice i
    LEFT JOIN purchase_order po ON i.po_id = po.po_id
    LEFT JOIN partner p ON i.partner_id = p.partner_id
    LEFT JOIN kapal k ON i.kapal_id = k.kapal_id
    LEFT JOIN barang b ON i.barang_id = b.barang_id
    WHERE i.invoice_id = ?
");

$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit;
}

$row = $result->fetch_assoc();

// Hitung subtotal (total_biaya / (1 + ppn/100))
$ppn_decimal = floatval($row['ppn']) / 100;
$subtotal = floatval($row['total_biaya']) / (1 + $ppn_decimal);
$ppn_nominal = floatval($row['total_biaya']) - $subtotal;

echo json_encode([
    'success' => true,
    'data' => [
        'nomor_invoice' => $row['nomor_invoice'],
        'nomor_po' => $row['nomor_po'],
        'uraian' => $row['uraian_pekerjaan'],
        'periode' => $row['periode'],
        'partner' => $row['nama_partner'],
        'kapal' => $row['nama_kapal'],
        'barang' => $row['nama_barang'],
        'total_tonase' => number_format($row['total_tonase'], 3, ',', '.'),
        'subtotal' => number_format($subtotal, 2, ',', '.'),
        'ppn' => number_format($row['ppn'], 2, ',', '.'),
        'ppn_nominal' => number_format($ppn_nominal, 2, ',', '.'),
        'total_biaya' => number_format($row['total_biaya'], 2, ',', '.')
    ]
]);
?>