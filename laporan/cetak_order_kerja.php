<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

$ok_id = intval($_GET['ok_id']);

// Ambil data Order Kerja
$stmt = $conn->prepare("
    SELECT 
        ok.*,
        p.partner as singkatan_partner, p.nama_partner, p.alamat as partner_alamat, p.kota as partner_kota, p.kategori,
        k.nama_kapal,
        t.nama as ttd_nama, t.jabatan as ttd_jabatan
    FROM order_kerja ok
    LEFT JOIN partner p ON ok.partner_id = p.partner_id
    LEFT JOIN kapal k ON ok.kapal_id = k.kapal_id
    LEFT JOIN ttd t ON ok.ttd_id = t.ttd_id
    WHERE ok.ok_id = ?
");
$stmt->bind_param("i", $ok_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo "<script>alert('Order Kerja tidak ditemukan!'); window.close();</script>";
    exit;
}

$ok = $result->fetch_assoc();

// Ambil detail per area dari Sales Order untuk kapal ini
$stmt = $conn->prepare("
    SELECT 
        h.area,
        so.qty,
        h.harga,
        (so.qty * h.harga) as subtotal
    FROM sales_order so
    JOIN harga h ON so.harga_id = h.harga_id
    WHERE so.kapal_id = ?
    ORDER BY h.area
");
$stmt->bind_param("i", $ok['kapal_id']);
$stmt->execute();
$detail_result = $stmt->get_result();

$details = [];
$subtotal = 0;
$potongan_persentase = floatval($ok['potongan_persentase']);
$denda = floatval($ok['denda']);

while($row = $detail_result->fetch_assoc()){
    $harga_awal = floatval($row['harga']);
    $potongan_item = ($harga_awal * $potongan_persentase) / 100; // potongan per item
    $harga_setelah_potongan = $harga_awal - $potongan_item;

    $subtotal_item = floatval($row['qty']) * $harga_setelah_potongan; // gunakan float tanpa pembulatan

    $row['harga_asli'] = $harga_awal;
    $row['harga_setelah_potongan'] = $harga_setelah_potongan;
    $row['subtotal'] = $subtotal_item;

    $details[] = $row;
    $subtotal += $subtotal_item;
}

$total_akhir = $subtotal - $denda; // total akhir presisi

// Perusahaan asal (default PT PKN)
$perusahaan_asal = $conn->query("SELECT * FROM partner WHERE partner_id = 2 LIMIT 1")->fetch_assoc();

$today = date('d/m/Y', strtotime($ok['tanggal_ok']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Kerja - <?= htmlspecialchars($ok['nomor_ok']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 950px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .page-header {
            text-align: right;
            font-size:  10px;
            margin-bottom: 20px;
            color: #000;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 10px;
        }

        .logo-section h4 {
            font-size: 12px;
            letter-spacing: 1px;
            margin: 0;
        }

        .content-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0px;
            border: 1px solid #000;
        }

        .left-content, .right-content {
            padding: 10px;
            border-right: 1px solid #000;
            margin-bottom: 1px;
        }

        .right-content {
            border-right: none;
        }

        .company-info {
            font-size:  11px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .company-info strong {
            display: block;
            margin-bottom: 0px;
            font-size: 11px;
        }

        .company-info p {
            margin: 1px 0;
            color: #000;
        }

        .kepada-label {
            margin-bottom: 1px;
            font-size:  11px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 1px;
            font-size:  11px;
            line-height: 1.5;
        }

        .syarat-section {
            font-size: 10px;
            text-align: center;
            margin-bottom: 10px;
            padding: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size:  11px;
        }

        table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-size:  11px;
        }

        table td {
            padding: 8px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .remarks-section {
            margin-bottom: 30px;
            font-size:  11px;
            padding: 10px;
            border: 1px solid #000;
            min-height: 30px;
        }

        .remarks-label {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 40px;
            font-size:  11px;
            text-align: center;
        }

        .signature-item {
            margin-top: 20px;
        }

        .signature-item .approval-title {
            font-size: 11px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .signature-item .name {
            font-weight: bold;
            margin-top: 80px;
            text-decoration: underline;
        }

        .signature-item .position {
            font-size: 11px;
            margin-top: 3px;
        }

        .btn-print {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-print:hover {
            background: #0056b3;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
            .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <button class="btn-print" onclick="window.print()">🖨️ Cetak Dokumen</button>
        <div>
        <img src="../assets/images/logoPKT.jpeg" style="top: 20px; left: 20px; width: 100px;">
    </div>
    <div class="page-header">Halaman 1 of 1</div>

    <!-- Logo & Title -->
    <div class="logo-section">
        <h4>ORDER KERJA</h4>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <!-- Left Content -->
        <div class="left-content">
            <div class="company-info"><strong><?= htmlspecialchars('PT PETRO KARYA TRANS') ?></strong>
                <p><?= htmlspecialchars($perusahaan_asal['alamat'] ?? 'Jl Dr Wahidin Sudiro Husodo 126') ?></p>
                <p><?= htmlspecialchars($perusahaan_asal['kota'] ?? 'Gresik') ?>, Jawa Timur Indonesia</p>
            </div>
            <div style="margin-top: 20px;">
                <div class="kepada-label"><strong>KEPADA: <?= htmlspecialchars($ok['nama_partner']) ?></strong></div>
                <div class="company-info">
                    <p><?= htmlspecialchars($ok['partner_alamat'] ?? '-') ?></p>
                    <p><?= htmlspecialchars($ok['partner_kota'] ?? '') ?>, Jawa Timur Indonesia</p>
                </div>
            </div>
        </div>

        <!-- Right Content -->
        <div class="right-content">
            <div class="info-grid">
                <div class="info-label">No. OK</div>
                <div class="info-value"><?= htmlspecialchars($ok['nomor_ok']) ?></div>
                <div class="info-label">Tanggal OK</div>
                <div class="info-value"><?= $today ?></div>
            </div>
        </div>
    </div>

    <!-- Syarat & Ketentuan -->
    <div class="syarat-section">
        Syarat syarat dan ketentuan lainnya dalam pelaksanaan pekerjaan tertuang dalam lampiran order kerja ini yang merupakan satu-kesatuan yang tidak terpisahkan
    </div>

    <!-- Detail Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Keterangan</th>
                <th style="width: 25%;">Deskripsi Material <br>Deskripsi</th>
                <th style="width: 12%;">QTY</th>
                <th style="width: 15%;">Harga</th>
                <th style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach($details as $item):
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= htmlspecialchars($ok['singkatan_partner']) ?></td>
                <td>Pengangkutan <?= htmlspecialchars($item['area']) ?></td>
                <td class="text-right"><?= number_format($item['qty'], 3, ',', '.') ?></td>
                <td class="text-right"><?= number_format($item['harga_setelah_potongan'], 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format($item['subtotal'], 2, ',', '.') ?></td>

            </tr>
            <?php endforeach; ?>

            <!-- Denda -->
            <?php if($ok['denda'] > 0): ?>
            <tr>
                <td></td>
                <td></td>
                <td>Denda</td>
                <td></td>
                <td></td>
                <td class="text-right">- <?= number_format($ok['denda'], 2, ',', '.') ?></td>
            </tr>
            <?php endif; ?>

            <!-- Total Akhir -->
            <tr class="total-row">
                <td colspan="5" class="text-right" style="font-size: 11px;">Total</td>
                <td class="text-right" style="font-size: 11px; font-weight: bold;"><?= number_format($total_akhir, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Remarks -->
    <div class="remarks-section">
        <div class="remarks-label">Remarks:</div>
        <div>Biaya EMKL <?= htmlspecialchars($ok['nama_kapal']) ?></div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-item">
            <div class="approval-title">
                Order Disetujui Oleh Rekanan<br><strong>
                <?= htmlspecialchars($ok['nama_partner']) ?></strong>
            </div>
            <div class="name">
                <?php 
                if($ok['ttd_nama']){
                    echo htmlspecialchars($ok['ttd_nama']);
                } else {
                    echo '( ............................. )';
                }
                ?>
            </div>
            <div class="position">
                <?php 
                if($ok['ttd_jabatan']){
                    echo htmlspecialchars($ok['ttd_jabatan']);
                }
                ?>
            </div>
        </div>

        <div class="signature-item">
            <div class="approval-title">
                <br><strong>PT PETRO KARYA TRANS</strong>
            </div>
            <div class="name">M Idror Ardani</div>
            <div class="position">Direktur</div>
        </div>
    </div>

</div>

<script>
    // Auto print jika parameter print=1
    <?php if(isset($_GET['print'])): ?>
        window.addEventListener('load', function() {
            window.print();
        });
    <?php endif; ?>
</script>

</body>
</html>