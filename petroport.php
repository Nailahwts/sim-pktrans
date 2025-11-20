<?php
session_start();
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$data = $_SESSION['data'] ?? [];
$header = $_SESSION['header'] ?? [];

if(isset($_POST['import_excel'])){
    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $header = $rows[0];
    unset($rows[0]);
    $data = [];
    foreach($rows as $row){
        $data[] = array_combine($header, $row);
    }

    $_SESSION['data'] = $data;
    $_SESSION['header'] = $header;
}

// Ambil filter
$filters = [
    'Cargo' => $_POST['cargo_filter'] ?? '',
    'Date' => $_POST['date_filter'] ?? '',
    'Shift' => $_POST['shift_filter'] ?? '',
    'Warehouse' => $_POST['warehouse_filter'] ?? '',
    'EMKL' => $_POST['emkl_filter'] ?? ''
];

// Filter data
$filtered = array_filter($data, function($d) use ($filters){
    if($filters['Cargo'] && $d['Cargo'] != $filters['Cargo']) return false;
    if($filters['Date'] && $d['Date'] != $filters['Date']) return false;
    if($filters['Shift'] && $d['Shift'] != $filters['Shift']) return false;
    if($filters['Warehouse'] && $d['Warehouse'] != $filters['Warehouse']) return false;
    if($filters['EMKL'] && $d['EMKL'] != $filters['EMKL']) return false;
    return true;
});

if(is_numeric($cell)){
    echo (intval($cell) == $cell) ? intval($cell) : number_format($cell,2);
} else {
    echo $cell;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Petroport Data</title>
</head>
<body>
<h2>Import Excel Petroport</h2>

<!-- Form Upload -->
<form method="post" enctype="multipart/form-data">
    <input type="file" name="excel_file" required>
    <button type="submit" name="import_excel">Import</button>
</form>

<!-- Form Filter -->
<form method="post">
    <label>Cargo:</label>
    <input type="text" name="cargo" value="<?= htmlspecialchars($filters['Cargo']) ?>">

    <label>Date:</label>
    <input type="date" name="date" value="<?= htmlspecialchars($filters['Date']) ?>">

    <label>Shift:</label>
    <select name="shift">
        <option value="">All</option>
        <option value="1" <?= $filters['Shift']=='1'?'selected':'' ?>>1</option>
        <option value="2" <?= $filters['Shift']=='2'?'selected':'' ?>>2</option>
        <option value="3" <?= $filters['Shift']=='3'?'selected':'' ?>>3</option>
    </select>

    <label>Warehouse:</label>
    <input type="text" name="warehouse" value="<?= htmlspecialchars($filters['Warehouse']) ?>">

    <label>EMKL:</label>
    <input type="text" name="emkl" value="<?= htmlspecialchars($filters['EMKL']) ?>">

    <button type="submit">Filter</button>
</form>

<!-- Table -->
<table border="1" cellpadding="5">
    <thead>
        <tr>
            <?php if(!empty($header)) foreach($header as $h): ?>
                <th><?= $h ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if(!empty($filtered)): ?>
            <?php foreach($filtered as $row): ?>
                <tr>
                    <?php foreach($row as $cell): ?>
                        <td><?= is_numeric($cell)?number_format($cell,2):$cell ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="<?= count($header) ?: 1 ?>">Belum ada data. Upload Excel dulu!</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
