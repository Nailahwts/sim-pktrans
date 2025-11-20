<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

if(!isset($_GET['kwitansi_id'])){
    echo "<script>alert('Kwitansi ID tidak ditemukan!'); window.close();</script>";
    exit;
}

$kwitansi_id = intval($_GET['kwitansi_id']);

// Ambil data kwitansi lengkap dengan rekening bank dari partner TTD
$stmt = $conn->prepare("
    SELECT 
        k.*,
        i.nomor_invoice,
        po.nomor_po,
        po.uraian_pekerjaan,
        po.periode,
        p.nama_partner,
        p.alamat,
        p.kota,
        kp.nama_kapal,
        b.nama_barang,
        t.nama as ttd_nama,
        t.jabatan as ttd_jabatan,
        p2.nama_partner as partner_ttd,
        p2.no_rekening,
        p2.nama_bank
    FROM kwitansi k
    LEFT JOIN invoice i ON k.invoice_id = i.invoice_id
    LEFT JOIN purchase_order po ON k.po_id = po.po_id
    LEFT JOIN partner p ON k.partner_id = p.partner_id
    LEFT JOIN kapal kp ON k.kapal_id = kp.kapal_id
    LEFT JOIN barang b ON k.barang_id = b.barang_id
    LEFT JOIN ttd t ON k.ttd_id = t.ttd_id
    LEFT JOIN partner p2 ON t.partner_id = p2.partner_id
    WHERE k.kwitansi_id = ?
");

$stmt->bind_param("i", $kwitansi_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    echo "<script>alert('Data kwitansi tidak ditemukan!'); window.close();</script>";
    exit;
}

$data = $result->fetch_assoc();

// Hitung subtotal dan PPN
$total_biaya = floatval($data['total_biaya']);
$ppn_persen = floatval($data['ppn']);
$ppn_decimal = $ppn_persen / 100;
$subtotal = $total_biaya / (1 + $ppn_decimal);
$ppn_nominal = $total_biaya - $subtotal;

// Fungsi terbilang
function terbilang($nilai) {
    $nilai = abs($nilai);
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $temp = "";
    
    if ($nilai < 12) {
        $temp = " " . $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = terbilang($nilai - 10) . " Belas";
    } else if ($nilai < 100) {
        $temp = terbilang($nilai / 10) . " Puluh" . terbilang($nilai % 10);
    } else if ($nilai < 100) {
        $temp = " Seratus" . terbilang($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = terbilang($nilai / 100) . " Ratus" . terbilang($nilai % 100);
    } else if ($nilai < 1000) {
        $temp = " Seribu" . terbilang($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = terbilang($nilai / 1000) . " Ribu" . terbilang($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = terbilang($nilai / 1000000) . " Juta" . terbilang($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = terbilang($nilai / 1000000000) . " Milyar" . terbilang(fmod($nilai, 1000000000));
    } else if ($nilai < 1000000000000000) {
        $temp = terbilang($nilai / 1000000000000) . " Trilyun" . terbilang(fmod($nilai, 1000000000000));
    }
    
    return $temp;
}

// Format tanggal Indonesia
function tanggal_indonesia($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

$terbilang_total = terbilang($total_biaya);
$tanggal_cetak = tanggal_indonesia($data['tanggal_invoice']);

// Ambil info rekening dan bank
$no_rekening = $data['no_rekening'] ?? '2700085859';
$nama_bank = $data['nama_bank'] ?? 'BNI 46';
$nama_rekening = $data['partner_ttd'] ?? 'PT PETRO KARYA TRANS';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kwitansi - <?= htmlspecialchars($data['nomor_kwitansi']) ?></title>
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    body {
        margin: 0;
        padding: 0;
    }
    .no-print {
        display: none !important;
    }
    .page-break {
        page-break-after: always;
    }
    .kwitansi-wrapper {
        page-break-inside: avoid;
    }
}

body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    line-height: 1.3;
    color: #000;
    margin: 0;
    padding: 0;
    background: #fff;
}

.kwitansi-wrapper {
    width: 210mm;
    height: 148.5mm;
    margin: 0 auto;
    padding: 5mm;
    box-sizing: border-box;
    position: relative;
}

.kwitansi-container {
    border: 1px solid #000;
    padding: 8px;
    height: 100%;
    box-sizing: border-box;
}

.header-section {
    border-bottom: 2px solid #0066cc;
    padding-bottom: 8px;
    margin-bottom: 12px;
    position: relative;
    display: flex;
    align-items: center;
}

.company-logo {
    width: 50px;
    height: 50px;
    margin-right: 10px;
    flex-shrink: 0;
}

.company-info {
    flex: 1;
}

.company-name {
    font-size: 18pt;
    font-weight: bold;
    color: #0066cc;
    letter-spacing: 0.5px;
    margin: 0;
    padding: 0;
    line-height: 1.1;
}

.company-tagline {
    font-size: 8pt;
    color: #666;
    margin: 2px 0 0 0;
}

.qr-code {
    position: absolute;
    top: 0;
    right: 0;
    width: 45px;
    height: 45px;
    border: 1px solid #ddd;
    padding: 3px;
}

.content-section {
    margin: 8px 0;
}

.info-row {
    margin: 3px 0;
    display: flex;
    font-size: 9pt;
}

.info-label {
    width: 120px;
    font-weight: normal;
    flex-shrink: 0;
}

.info-separator {
    width: 10px;
    text-align: center;
    flex-shrink: 0;
}

.info-value {
    flex: 1;
}

.info-value strong {
    font-weight: bold;
}

.terbilang-box {
    border: 1px solid #000;
    padding: 2px;
    margin: 0px 0;
    font-weight: bold;
    font-size: 8pt;
}

.amount-box {
    border: 1px solid #000;
    padding: 2px;
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    background: #f9f9f9;
}

.payment-section {
    margin: 5px 0;
    padding: 2px;
}

.payment-info {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin: 2px 0;
}

.note-box {
    border: 1px solid #000;
    padding: 6px;
    margin: 8px 0;
    font-size: 8pt;
    line-height: 1.4;
}

.signature-section {
    margin-top: 15px;
    text-align: right;
}

.signature-box {
    display: inline-block;
    text-align: center;
    min-width: 150px;
}

.signature-location {
    margin-bottom: 1px;
    font-size: 9pt;
}

.signature-company {
    font-weight: bold;
    margin-bottom: 80px;
    font-size: 9pt;
}

.signature-name {
    font-weight: bold;
    text-decoration: underline;
    margin-top: 5px;
    font-size: 9pt;
}

.signature-title {
    margin-top: 2px;
    font-size: 9pt;
}

.footer-section {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    padding-top: 8px;
    border-top: 1px solid #0066cc;
    text-align: center;
    font-size: 7pt;
    color: #666;
}

.btn-print {
    position: fixed;
    top: 10px;
    right: 10px;
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 9999;
}

.btn-print:hover {
    background: #0056b3;
}

table.detail-table {
    width: 100%;
    margin: 6px 0;
    border-collapse: collapse;
    font-size: 9pt;
}

table.detail-table td {
    font-size: 9pt;
    padding: 2px;
    vertical-align: top;
}

.page-break {
    page-break-after: always;
}
</style>
</head>
<body>

<button class="btn-print no-print" onclick="window.print()">
    🖨️ Cetak Kwitansi
</button>

<!-- KWITANSI 1 - Untuk Perusahaan -->
<div class="kwitansi-wrapper">
    <div class="kwitansi-container">
        <!-- Header -->
            <img src="../assets/images/kwiPKT1.jpg" style="width:100%;" alt="Logo Perusahaan">
        <!-- Content -->
        <div class="content-section">
            <div class="info-row">
                <div class="info-label">Kwitansi No</div>
                <div class="info-separator">:</div>
                <div class="info-value"><strong><?= htmlspecialchars($data['nomor_kwitansi']) ?></strong></div>
            </div>

            <div class="info-row">
                <div class="info-label">Sudah Terima Dari</div>
                <div class="info-separator">:</div>
                <div class="info-value"><strong><?= strtoupper(htmlspecialchars($data['nama_partner'])) ?></strong></div>
            </div>

            <div class="info-row">
                <div class="info-label">Terbilang</div>
                <div class="info-separator">:</div>
                <div class="info-value">
                    <div class="terbilang-box">
                        <?= ucwords(strtolower($terbilang_total)) ?> Rupiah
                    </div>
                </div>
            </div>

            <div class="info-row">
                <table style="width:100%; border-collapse:collapse; font-size:9pt;">
                    <!-- Baris utama: label, titik dua, deskripsi, subtotal -->
                    <tr>
                        <td style="width:120px;">Untuk Pembayaran</td>
                        <td style="width:5px;">:</td>
                        <td style="font-weight:bold;">
                            Pengangkutan <?= strtoupper(htmlspecialchars($data['nama_barang'])) ?> - <?= strtoupper(htmlspecialchars($data['nama_kapal'])) ?>
                        </td>
                        <td style="width:150px; text-align:right;">
                            <?= number_format($subtotal, 2, ',', '.') ?><span style="color:white;">.</span>
                        </td>
                    </tr>

                    <!-- Baris detail: PPN, PO, Invoice -->
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="2">
                            <table class="detail-table" style="width:100%; border-collapse:collapse;">

                        <td width="15%">PPN</td>
                        <td></td>
                        <td align="right" style="width:13%; border-bottom:1px solid #000;">
                            <?= number_format($ppn_nominal, 2, ',', '.') ?>
                        </td>
                    </tr>
                                <tr>
                                    <td>Sesuai PO :</td>
                                    <td width="15"><?= htmlspecialchars($data['nomor_po']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>No Invoice :</td>
                                    <td><?= htmlspecialchars($data['nomor_invoice']) ?></td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Amount + Signature Layout -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:1px;">
                <tr>
                    <!-- Kolom Amount -->
                    <td style="width:60%; vertical-align:top;">
                        <div class="info-row">
                            <div class="info-label">Jumlah Rp.</div>
                            <div class="info-separator">:</div>
                            <div class="info-value">
                                <div class="amount-box">
                                    <?= number_format($total_biaya, 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                        <br>
                        
                        <!-- Payment Info -->
                        <div class="payment-section">
                            <div class="payment-info">NO REKENING <?= strtoupper($nama_bank) ?> GRESIK : <?= $no_rekening ?></div>
                            <div class="payment-info">A/N. <?= strtoupper($nama_rekening) ?></div>
                        </div>

                        <!-- Note -->
                        <div class="note-box">
                            Jika pembayaran dilakukan dengan cheque, giro, wesel dsb.nya maka
                            utang/pembayaran lunas setelah surat-surat berharga tersebut ditunjukan dan
                            diterima baik oleh bank.
                        </div>
                    </td>
                     <td style="width:10%; text-align:right; vertical-align:top;"></td>
                    <!-- Kolom Signature -->
                    <td style="width:30%; text-align:right; vertical-align:top;">
                        <div class="signature-section" style="margin-top:0; text-align:left;">
                            <div class="signature-location">Gresik, <?= $tanggal_cetak ?></div>
                            <div class="signature-company"><?= strtoupper($nama_rekening) ?></div>
                            <div class="signature-name"><?= htmlspecialchars($data['ttd_nama'] ?? 'M.Udror Ardani') ?></div>
                            <div class="signature-title"><?= htmlspecialchars($data['ttd_jabatan'] ?? 'Direktur') ?></div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>

        <!-- Footer -->
        <img src="../assets/images/kwiPKT2.png" style="width:100%;" alt="Footer Perusahaan">
    </div>
</div>

<!-- KWITANSI 1 - Untuk Perusahaan -->
<div class="kwitansi-wrapper">
    <div class="kwitansi-container">
        <!-- Header -->
            <img src="../assets/images/kwiPKT1.jpg" style="width:100%;" alt="Logo Perusahaan">
        <!-- Content -->
        <div class="content-section">
            <div class="info-row">
                <div class="info-label">Kwitansi No</div>
                <div class="info-separator">:</div>
                <div class="info-value"><strong><?= htmlspecialchars($data['nomor_kwitansi']) ?></strong></div>
            </div>

            <div class="info-row">
                <div class="info-label">Sudah Terima Dari</div>
                <div class="info-separator">:</div>
                <div class="info-value"><strong><?= strtoupper(htmlspecialchars($data['nama_partner'])) ?></strong></div>
            </div>

            <div class="info-row">
                <div class="info-label">Terbilang</div>
                <div class="info-separator">:</div>
                <div class="info-value">
                    <div class="terbilang-box">
                        <?= ucwords(strtolower($terbilang_total)) ?> Rupiah
                    </div>
                </div>
            </div>

            <div class="info-row">
                <table style="width:100%; border-collapse:collapse; font-size:9pt;">
                    <!-- Baris utama: label, titik dua, deskripsi, subtotal -->
                    <tr>
                        <td style="width:120px;">Untuk Pembayaran</td>
                        <td style="width:5px;">:</td>
                        <td style="font-weight:bold;">
                            Pengangkutan <?= strtoupper(htmlspecialchars($data['nama_barang'])) ?> - <?= strtoupper(htmlspecialchars($data['nama_kapal'])) ?>
                        </td>
                        <td style="width:150px; text-align:right;">
                            <?= number_format($subtotal, 2, ',', '.') ?><span style="color:white;">.</span>
                        </td>
                    </tr>

                    <!-- Baris detail: PPN, PO, Invoice -->
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="2">
                            <table class="detail-table" style="width:100%; border-collapse:collapse;">

                        <td width="15%">PPN</td>
                        <td></td>
                        <td align="right" style="width:13%; border-bottom:1px solid #000;">
                            <?= number_format($ppn_nominal, 2, ',', '.') ?>
                        </td>
                    </tr>
                                <tr>
                                    <td>Sesuai PO :</td>
                                    <td width="15"><?= htmlspecialchars($data['nomor_po']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>No Invoice :</td>
                                    <td><?= htmlspecialchars($data['nomor_invoice']) ?></td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Amount + Signature Layout -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:1px;">
                <tr>
                    <!-- Kolom Amount -->
                    <td style="width:60%; vertical-align:top;">
                        <div class="info-row">
                            <div class="info-label">Jumlah Rp.</div>
                            <div class="info-separator">:</div>
                            <div class="info-value">
                                <div class="amount-box">
                                    <?= number_format($total_biaya, 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                        <br>
                        
                        <!-- Payment Info -->
                        <div class="payment-section">
                            <div class="payment-info">NO REKENING <?= strtoupper($nama_bank) ?> GRESIK : <?= $no_rekening ?></div>
                            <div class="payment-info">A/N. <?= strtoupper($nama_rekening) ?></div>
                        </div>

                        <!-- Note -->
                        <div class="note-box">
                            Jika pembayaran dilakukan dengan cheque, giro, wesel dsb.nya maka
                            utang/pembayaran lunas setelah surat-surat berharga tersebut ditunjukan dan
                            diterima baik oleh bank.
                        </div>
                    </td>
                     <td style="width:10%; text-align:right; vertical-align:top;"></td>
                    <!-- Kolom Signature -->
                    <td style="width:30%; text-align:right; vertical-align:top;">
                        <div class="signature-section" style="margin-top:0; text-align:left;">
                            <div class="signature-location">Gresik, <?= $tanggal_cetak ?></div>
                            <div class="signature-company"><?= strtoupper($nama_rekening) ?></div>
                            <div class="signature-name"><?= htmlspecialchars($data['ttd_nama'] ?? 'M.Udror Ardani') ?></div>
                            <div class="signature-title"><?= htmlspecialchars($data['ttd_jabatan'] ?? 'Direktur') ?></div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>

        <!-- Footer -->
        <img src="../assets/images/kwiPKT2.png" style="width:100%;" alt="Footer Perusahaan">
    </div>
</div>

<script>
<?php if(isset($_GET['print']) && $_GET['print'] == 1): ?>
window.onload = function() {
    setTimeout(function() {
        window.print();
    }, 500);
};
<?php endif; ?>
</script>

</body>
</html>