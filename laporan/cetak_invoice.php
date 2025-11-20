<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$auto_print = isset($_GET['print']) ? true : false;

if($invoice_id <= 0) {
    die("Invoice ID tidak valid");
}

// Ambil data invoice lengkap
$stmt = $conn->prepare("
    SELECT 
        i.*,
        po.nomor_po,
        po.periode,
        po.uraian_pekerjaan,
        k.nama_kapal,
        b.nama_barang,
        p.nama_partner,
        p.alamat,
        p.kota,
        t.nama as ttd_nama,
        t.jabatan as ttd_jabatan
    FROM invoice i
    LEFT JOIN purchase_order po ON i.po_id = po.po_id
    LEFT JOIN kapal k ON i.kapal_id = k.kapal_id
    LEFT JOIN barang b ON i.barang_id = b.barang_id
    LEFT JOIN partner p ON i.partner_id = p.partner_id
    LEFT JOIN ttd t ON i.ttd_id = t.ttd_id
    WHERE i.invoice_id = ?
");

$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if(!$invoice) {
    die("Invoice tidak ditemukan");
}

// Ambil semua sales order yang terkait dengan PO ini untuk mendapatkan area dan harga yang berbeda
$stmt_so = $conn->prepare("
    SELECT 
        so.so_id,
        so.harga_id,
        h.area,
        h.harga,
        so.qty
    FROM sales_order so
    LEFT JOIN harga h ON so.harga_id = h.harga_id
    WHERE so.po_id = ? AND so.kapal_id = ? AND so.barang_id = ?
    ORDER BY h.area
");

$stmt_so->bind_param("iii", $invoice['po_id'], $invoice['kapal_id'], $invoice['barang_id']);
$stmt_so->execute();
$so_result = $stmt_so->get_result();

$detail_items = [];
$jumlah = 0;

while($so_row = $so_result->fetch_assoc()) {
    $tonase = floatval($so_row['qty']); // langsung dari SO
    $harga_satuan = floatval($so_row['harga']);
    $subtotal = $tonase * $harga_satuan;

    $detail_items[] = [
        'area' => $so_row['area'] ?? '-',
        'qty' => $tonase,
        'harga_satuan' => $harga_satuan,
        'subtotal' => $subtotal
    ];

    $jumlah += $subtotal;
}


// Hitung PPN dan total
$ppn_persen = floatval($invoice['ppn']);
$ppn_nominal = ($jumlah * $ppn_persen) / 100;
$total_dengan_ppn = $jumlah + $ppn_nominal;

// Function terbilang
function terbilang($number) {
    $number = abs($number);
    $angka = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    
    if ($number < 12) {
        return $angka[$number];
    } elseif ($number < 20) {
        return terbilang($number - 10) . " belas";
    } elseif ($number < 100) {
        return terbilang($number / 10) . " puluh " . terbilang($number % 10);
    } elseif ($number < 200) {
        return "seratus " . terbilang($number - 100);
    } elseif ($number < 1000) {
        return terbilang($number / 100) . " ratus " . terbilang($number % 100);
    } elseif ($number < 2000) {
        return "seribu " . terbilang($number - 1000);
    } elseif ($number < 1000000) {
        return terbilang($number / 1000) . " ribu " . terbilang($number % 1000);
    } elseif ($number < 1000000000) {
        return terbilang($number / 1000000) . " juta " . terbilang($number % 1000000);
    } elseif ($number < 1000000000000) {
        return terbilang($number / 1000000000) . " miliar " . terbilang($number % 1000000000);
    } else {
        return terbilang($number / 1000000000000) . " triliun " . terbilang($number % 1000000000000);
    }
}

$terbilang_text = ucwords(trim(terbilang($total_dengan_ppn)));
// Ambil tanggal invoice dari database
$tanggal_invoice = $invoice['tanggal_invoice']; // misal formatnya YYYY-MM-DD

// Ubah ke format Indonesia
$bulan_indonesia = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];

// Konversi tanggal ke format d F Y
$tanggal_invoice = date('d F Y', strtotime($tanggal_invoice));
$tanggal_invoice = str_replace(array_keys($bulan_indonesia), array_values($bulan_indonesia), $tanggal_invoice);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($invoice['nomor_invoice']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11pt;
            line-height: 1.4;
            padding: 15px;
        }
        .container { 
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        .logo-header { 
            text-align: center; 
            margin-bottom: 15px;
        }
        .logo-header img { 
            max-width: 100%;
            height: auto;
        }
.header-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.header-right {
    text-align: justify;
}

        .title-center { 
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 15px 0;
        }
        .po-invoice-line { 
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        
        table { 
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    border: 1px solid #000; /* border luar */
}

th { 
    padding: 6px 8px;
    border-left: 1px solid #000; /* garis antar baris */
    border-bottom: none; /* hilangkan garis bawah antar baris */
}

td { 
    padding: 3px 8px;
    border-left: 1px solid #000; /* garis antar baris */
}

th { 
    background: #f5f5f5;
    font-weight: bold;
    text-align: center;
    border-top: 1px solid #000; /* border atas header */
    border-bottom: 1px solid #000; /* kalau mau garis bawah header tetap ada */
}

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .total-row-label { 
            text-align: right;
            padding-right: 10px;
            font-weight: normal;
        }
        .total-row-value { 
            text-align: right;
            padding-right: 10px;
        }
        .terbilang-section { 
            margin: 15px 0;
            font-size: 10pt;
        }
        .terbilang-label { 
            margin-bottom: 3px;
        }
        .signature-section { 
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 0 30px;
        }
        .signature-item { 
            text-align: center;
            width: 40%;
        }
        .signature-title { 
            margin-top: 50px;
            margin-bottom: 60px;
        }
        .signature-line { 
            margin: 50px 0 5px 0;
        }
        .signature-name { 
            font-weight: bold;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        tfoot td {
            padding: 2px 8px; /* atas-bawah 2px, kiri-kanan 8px */
        }

    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
    <button onclick="window.print()" style="padding: 10px 25px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
        🖨️ Cetak Invoice
    </button>
    <button onclick="window.close()" style="padding: 10px 25px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; margin-left: 10px;">
        ✖️ Tutup
    </button>
    <a href="invoice.php" style="padding: 10px 25px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; margin-left: 10px; text-decoration: none; display: inline-block;">
        ◀️ Kembali
    </a>
</div>

<div class="container">
    <!-- Header Perusahaan -->
    <div class="logo-header">
        <img src="../assets/images/kopPKT.png" alt="Logo Perusahaan">
    </div>
<!-- Header Info: Tanggal & Kepada Yth -->
<div class="header-info" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div class="header-left">
        <!-- Bisa dikosongkan atau isi logo/apa pun -->
    </div>
    <div class="header-right" style="text-align: justify;">
        <p>Gresik, <?= $tanggal_invoice ?></p>
        <p style="margin-top: 8px;">Kepada Yth :</p>
        <p><strong><?= htmlspecialchars($invoice['nama_partner']) ?></strong></p>
        <?php if(!empty($invoice['alamat'])): ?>
        <p><?= htmlspecialchars($invoice['alamat']) ?></p>
        <?php endif; ?>
        <?php if(!empty($invoice['kota'])): ?>
        <p><?= htmlspecialchars($invoice['kota']) ?></p>
        <?php endif; ?>
    </div>
</div>


    <!-- Title -->
    <div class="title-center">Nota / Invoice</div>

    <!-- No PO & No Invoice -->
    <div class="po-invoice-line">
        <div class="po-left">No PO : <?= htmlspecialchars($invoice['nomor_po']) ?></div>
        <div class="po-right">No Invoice : <?= htmlspecialchars($invoice['nomor_invoice']) ?></div>
    </div>

    <!-- Detail Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">NO</th>
                <th style="width: 5%;">P</th>
                <th style="width: 50%;">KETERANGAN</th>
                <th style="width: 10%;">QTY</th>
                <th style="width: 10%;">HARGA</th>
                <th style="width: 20%;">JUMLAH</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach($detail_items as $item):
            ?>
            <tr>
                <td class="text-center"><?= $no ?></td>
                <td class="text-center"></td>
                <td class="text-left">Pengangkutan <?= htmlspecialchars($item['area']) ?></td>
                <td class="text-right"><?= number_format($item['qty'], 3, ',', '.') ?></td>
                <td class="text-right"><?= number_format($item['harga_satuan'], 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format($item['subtotal'], 2, ',', '.') ?></td>
            </tr>
            <?php $no++; endforeach; ?>

            <!-- Keterangan Row -->
            <tr>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000"></td>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000"></td>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000">
                    Keterangan :<br>
                    <strong><?= htmlspecialchars($invoice['nama_kapal']) ?></strong><br>
                    <?php if(!empty($invoice['periode'])): ?>
                    Periode : <?= htmlspecialchars($invoice['periode']) ?>
                    <?php endif; ?>
                </td>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000"></td>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000"></td>
                <td style="padding-top: 50px; padding-bottom: 10px; border-bottom: 1px solid #000"></td>
            </tr>
        </tbody>
<tfoot>
    <tr>
        <td colspan="5" class="total-row-label" style="padding: 2px 10px;">Jumlah</td>
        <td class="total-row-value" style="padding: 2px 10px;"><?= number_format($jumlah, 2, ',', '.') ?></td>
    </tr>
    <tr>
        <td colspan="5" class="total-row-label" style="padding: 2px 10px;">PPN</td>
        <td class="total-row-value" style="padding: 2px 10px;"><?= number_format($ppn_nominal, 2, ',', '.') ?></td>
    </tr>
    <tr>
        <td colspan="5" class="total-row-label" style="padding: 2px 10px; font-weight: bold;">Total</td>
        <td class="total-row-value" style="padding: 2px 10px; font-weight: bold; border-top: 1px solid #000"><?= number_format($total_dengan_ppn, 2, ',', '.') ?></td>
    </tr>
</tfoot>

    </table>

    <!-- Terbilang -->
    <div class="terbilang-section">
        <div class="terbilang-label">Terbilang :</div>
        <div><?= $terbilang_text ?> Rupiah</div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-item">
            <div class="signature-title"></div>
            <div class="signature-line"></div>
            <div class="signature-name"></div>
        </div>

        <div class="signature-item">
            <div class="signature-title">Hormat Kami,</div>
            <div class="signature-line"></div>
            <div class="signature-name">
                <?php if(!empty($invoice['ttd_nama'])): ?>
                    <?= htmlspecialchars($invoice['ttd_nama']) ?>
                    <br>
                    <span style="font-weight: bold; font-size: 11pt;"><?= htmlspecialchars($invoice['ttd_jabatan']) ?></span>
                <?php else: ?>
                    ______________________
                <?php endif; ?>
            </div>
        </div>
    </div>
    

</div>

<?php if($auto_print): ?>
<script>
// Auto print ketika parameter print=1
window.onload = function() { 
    window.print(); 
}
</script>
<?php endif; ?>

</body>
</html>