<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../libs/fpdf/fpdf.php';
requireAdmin();

$baseUrl = '/dhandaras_canteen/';
$olap    = getOLAP();
$action  = isset($_GET['action']) ? $_GET['action'] : '';
$type    = isset($_GET['type'])   ? $_GET['type']   : 'revenue';

function getReportData($olap, $type) {
    switch($type) {
        case 'revenue':
            $result  = $olap->query("SELECT d.year, d.month, SUM(f.total_revenue) AS total_revenue, SUM(f.quantity_sold) AS total_sold, SUM(f.cost_of_goods) AS total_cost, SUM(f.total_revenue)-SUM(f.cost_of_goods) AS profit FROM fact_sales f JOIN dim_time d ON f.time_id=d.time_id GROUP BY d.year,d.month ORDER BY d.year,d.month");
            $headers = ['Year','Month','Revenue (P)','Items Sold','Cost (P)','Profit (P)'];
            $keys    = ['year','month','total_revenue','total_sold','total_cost','profit'];
            $title   = 'Monthly Revenue Report'; break;
        case 'items':
            $result  = $olap->query("SELECT m.item_name, c.category_name, SUM(f.quantity_sold) AS total_sold, SUM(f.total_revenue) AS revenue FROM fact_sales f JOIN dim_menu_item m ON f.item_id=m.item_id JOIN dim_category c ON f.category_id=c.category_id GROUP BY m.item_name,c.category_name ORDER BY revenue DESC");
            $headers = ['Item Name','Category','Qty Sold','Revenue (P)'];
            $keys    = ['item_name','category_name','total_sold','revenue'];
            $title   = 'Menu Item Sales Report'; break;
        case 'payment':
            $result  = $olap->query("SELECT p.method_name, COUNT(*) AS transactions, SUM(f.total_revenue) AS revenue FROM fact_sales f JOIN dim_payment_method p ON f.payment_method_id=p.payment_method_id GROUP BY p.method_name ORDER BY revenue DESC");
            $headers = ['Payment Method','Transactions','Revenue (P)'];
            $keys    = ['method_name','transactions','revenue'];
            $title   = 'Payment Method Report'; break;
        default: return null;
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return compact('headers','keys','title','rows');
}

$data = getReportData($olap, $type);

if ($action === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$type.'_report_'.date('Ymd').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ["CanTech Canteen Management System"]);
    fputcsv($out, [$data['title']]);
    fputcsv($out, ['Generated:', date('F d, Y h:i A')]);
    fputcsv($out, []);
    fputcsv($out, $data['headers']);
    foreach ($data['rows'] as $row) {
        $line = [];
        foreach ($data['keys'] as $key) {
            $line[] = in_array($key,['total_revenue','revenue','total_cost','profit']) ? number_format($row[$key],2) : $row[$key];
        }
        fputcsv($out, $line);
    }
    fclose($out); exit;
}

if ($action === 'pdf') {
    $pdf = new FPDF('L','mm','A4');
    $pdf->AddPage(); $pdf->SetMargins(15,15,15);
    $pdf->SetFont('Arial','B',16); $pdf->SetTextColor(233,69,96);
    $pdf->Cell(0,10,"CanTech Canteen Management System",0,1,'C');
    $pdf->SetFont('Arial','B',12); $pdf->SetTextColor(40,40,40);
    $pdf->Cell(0,8,$data['title'],0,1,'C');
    $pdf->SetFont('Arial','',9); $pdf->SetTextColor(120,120,120);
    $pdf->Cell(0,6,'Generated: '.date('F d, Y h:i A'),0,1,'C'); $pdf->Ln(4);
    $pdf->SetFont('Arial','B',9); $pdf->SetFillColor(26,26,46); $pdf->SetTextColor(255,255,255);
    $colWidth = 267/count($data['headers']);
    foreach ($data['headers'] as $h) $pdf->Cell($colWidth,8,$h,1,0,'C',true);
    $pdf->Ln();
    $pdf->SetFont('Arial','',8); $pdf->SetTextColor(40,40,40); $alt = false;
    foreach ($data['rows'] as $row) {
        $pdf->SetFillColor($alt?240:255,$alt?242:255,$alt?245:255);
        foreach ($data['keys'] as $key) {
            $val = in_array($key,['total_revenue','revenue','total_cost','profit']) ? 'P'.number_format($row[$key],2) : $row[$key];
            $pdf->Cell($colWidth,7,$val,1,0,'C',true);
        }
        $pdf->Ln(); $alt = !$alt;
    }
    $pdf->Output('D','CanTech_canteen_'.$type.'_'.date('Ymd').'.pdf'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Reports - CanTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .report-card { background:white; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .btn-csv { background:#27ae60; color:white; border:none; }
        .btn-csv:hover { background:#1e8449; color:white; }
        .btn-pdf { background:#e94560; color:white; border:none; }
        .btn-pdf:hover { background:#c0392b; color:white; }
        .type-btn { border-radius:20px; margin-right:0.4rem; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <h4 class="mb-1"> Export Reports</h4>
    <p class="text-muted mb-4">Download analytical reports as PDF or CSV</p>

    <div class="report-card">
        <h6 class="mb-3">Select Report Type</h6>
        <a href="?type=revenue" class="btn type-btn <?= $type=='revenue'?'btn-danger':'btn-outline-secondary' ?>"> Monthly Revenue</a>
        <a href="?type=items"   class="btn type-btn <?= $type=='items'  ?'btn-danger':'btn-outline-secondary' ?>"> Item Sales</a>
        <a href="?type=payment" class="btn type-btn <?= $type=='payment'?'btn-danger':'btn-outline-secondary' ?>"> Payment Methods</a>
    </div>

    <div class="report-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"> Preview — <?= $data['title'] ?></h6>
            <div>
                <a href="?type=<?= $type ?>&action=csv" class="btn btn-csv btn-sm me-2">⬇ Download CSV</a>
                <a href="?type=<?= $type ?>&action=pdf" class="btn btn-pdf btn-sm">⬇ Download PDF</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead><tr><?php foreach($data['headers'] as $h): ?><th><?= $h ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php foreach($data['rows'] as $row): ?>
                    <tr><?php foreach($data['keys'] as $key): ?><td><?= in_array($key,['total_revenue','revenue','total_cost','profit']) ? 'P'.number_format($row[$key],2) : htmlspecialchars($row[$key]) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if($type==='revenue' && !empty($data['rows'])): ?>
                    <tr class="table-danger fw-bold">
                        <td colspan="2" class="text-end">TOTAL</td>
                        <td>P<?= number_format(array_sum(array_column($data['rows'],'total_revenue')),2) ?></td>
                        <td><?= array_sum(array_column($data['rows'],'total_sold')) ?></td>
                        <td>P<?= number_format(array_sum(array_column($data['rows'],'total_cost')),2) ?></td>
                        <td>P<?= number_format(array_sum(array_column($data['rows'],'total_revenue'))-array_sum(array_column($data['rows'],'total_cost')),2) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted">Generated: <?= date('F d, Y h:i A') ?> &nbsp;|&nbsp; <?= count($data['rows']) ?> records found</small>
    </div>
</div>
</body>
</html>
