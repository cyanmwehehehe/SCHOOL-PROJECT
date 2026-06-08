<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$olap    = getOLAP();
$oltp    = getOLTP();
$baseUrl = '/dhandaras_canteen/';

$totalRevenue = $olap->query("SELECT SUM(total_revenue) AS total FROM fact_sales")->fetch_assoc()['total'] ?? 0;
$totalOrders  = $oltp->query("SELECT COUNT(*) AS total FROM orders WHERE status='completed'")->fetch_assoc()['total'] ?? 0;
$totalItems   = $olap->query("SELECT SUM(quantity_sold) AS total FROM fact_sales")->fetch_assoc()['total'] ?? 0;
$topItem      = $olap->query("
    SELECT m.item_name, SUM(f.quantity_sold) AS total
    FROM fact_sales f
    JOIN dim_menu_item m ON f.item_id = m.item_id
    GROUP BY m.item_name ORDER BY total DESC LIMIT 1
")->fetch_assoc();

$revenueByMonth = $olap->query("
    SELECT d.month, d.year, SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_time d ON f.time_id = d.time_id
    GROUP BY d.year, d.month ORDER BY d.year, d.month
");
$monthLabels = []; $monthData = [];
while($row = $revenueByMonth->fetch_assoc()) {
    $monthLabels[] = $row['month'].' '.$row['year'];
    $monthData[]   = round($row['revenue'],2);
}

$byCategory = $olap->query("
    SELECT c.category_name, SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_category c ON f.category_id = c.category_id
    GROUP BY c.category_name
");
$catLabels = []; $catData = [];
while($row = $byCategory->fetch_assoc()) { $catLabels[] = $row['category_name']; $catData[] = round($row['revenue'],2); }

$topItems = $olap->query("
    SELECT m.item_name, SUM(f.quantity_sold) AS total_sold
    FROM fact_sales f
    JOIN dim_menu_item m ON f.item_id = m.item_id
    GROUP BY m.item_name ORDER BY total_sold DESC LIMIT 5
");
$itemLabels = []; $itemData = [];
while($row = $topItems->fetch_assoc()) { $itemLabels[] = $row['item_name']; $itemData[] = $row['total_sold']; }

$byPayment = $olap->query("
    SELECT p.method_name, SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_payment_method p ON f.payment_method_id = p.payment_method_id
    GROUP BY p.method_name
");
$payLabels = []; $payData = [];
while($row = $byPayment->fetch_assoc()) { $payLabels[] = $row['method_name']; $payData[] = round($row['revenue'],2); }

$filter      = isset($_GET['period']) ? $_GET['period'] : 'all';
$whereClause = '';
if ($filter === 'weekend') $whereClause = 'WHERE d.is_weekend = 1';
if ($filter === 'weekday') $whereClause = 'WHERE d.is_weekend = 0';

$filteredSales = $olap->query("
    SELECT m.item_name, SUM(f.quantity_sold) AS qty, SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_menu_item m ON f.item_id = m.item_id
    JOIN dim_time d ON f.time_id = d.time_id
    $whereClause
    GROUP BY m.item_name ORDER BY revenue DESC LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Canteen Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .summary-card { border-radius:12px; padding:1.5rem; color:white; position:relative; overflow:hidden; }
        .summary-card .value { font-size:2rem; font-weight:700; }
        .summary-card .label { font-size:0.85rem; opacity:0.85; }
        .card-red    { background:linear-gradient(135deg,#e94560,#c0392b); }
        .card-blue   { background:linear-gradient(135deg,#2980b9,#1a5276); }
        .card-green  { background:linear-gradient(135deg,#27ae60,#1e8449); }
        .card-purple { background:linear-gradient(135deg,#8e44ad,#6c3483); }
        .chart-card  { background:white; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .chart-card h6 { font-weight:600; color:#333; margin-bottom:1rem; }
        .filter-pill { display:inline-block; padding:0.35rem 1rem; border-radius:20px; font-size:0.85rem; text-decoration:none; border:1px solid #ddd; color:#555; margin-right:0.4rem; transition:all 0.2s; }
        .filter-pill:hover { background:#e94560; color:white; border-color:#e94560; }
        .filter-pill.active { background:#e94560; color:white; border-color:#e94560; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Analytics Dashboard</h4>
            <small class="text-muted">Last updated: <?= date('F d, Y h:i A') ?></small>
        </div>
        <a href="../olap/etl.php" class="btn btn-sm btn-outline-danger"> Sync ETL</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="summary-card card-red"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue,2) ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-blue"><div class="label">Total Orders</div><div class="value"><?= number_format($totalOrders) ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-green"><div class="label">Items Sold</div><div class="value"><?= number_format($totalItems) ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-purple"><div class="label">Best Seller</div><div class="value" style="font-size:1.1rem"><?= $topItem?$topItem['item_name']:'N/A' ?></div></div></div>
    </div>

    <div class="row g-3 mb-2">
        <div class="col-md-8"><div class="chart-card"><h6>Monthly Revenue (Roll-up)</h6><canvas id="revenueChart" height="120"></canvas></div></div>
        <div class="col-md-4"><div class="chart-card"><h6>Sales by Category (Slice)</h6><canvas id="categoryChart" height="200"></canvas></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="chart-card"><h6>Top 5 Best Selling Items (Drill-down)</h6><canvas id="topItemsChart" height="160"></canvas></div></div>
        <div class="col-md-6"><div class="chart-card"><h6> Revenue by Payment Method (Dice)</h6><canvas id="paymentChart" height="160"></canvas></div></div>
    </div>

    <div class="chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Interactive Slice — Filter by Period</h6>
            <div>
                <a href="?period=all"     class="filter-pill <?= $filter=='all'?'active':'' ?>">All Days</a>
                <a href="?period=weekday" class="filter-pill <?= $filter=='weekday'?'active':'' ?>">Weekdays</a>
                <a href="?period=weekend" class="filter-pill <?= $filter=='weekend'?'active':'' ?>">Weekends</a>
            </div>
        </div>
        <table class="table table-hover table-sm">
            <thead><tr><th>Menu Item</th><th>Qty Sold</th><th>Revenue</th><th>Share</th></tr></thead>
            <tbody>
                <?php
                $rows = []; $grandTotal = 0;
                while($row = $filteredSales->fetch_assoc()) { $rows[] = $row; $grandTotal += $row['revenue']; }
                foreach($rows as $row):
                    $share = $grandTotal > 0 ? round(($row['revenue']/$grandTotal)*100,1) : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                    <td><?= $row['qty'] ?></td>
                    <td>₱<?= number_format($row['revenue'],2) ?></td>
                    <td>
                        <div class="progress" style="height:6px;margin-top:6px;"><div class="progress-bar bg-danger" style="width:<?= $share ?>%"></div></div>
                        <small><?= $share ?>%</small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const colors = ['#e94560','#2980b9','#27ae60','#f39c12','#8e44ad','#16a085','#d35400'];
new Chart(document.getElementById('revenueChart'),{type:'bar',data:{labels:<?= json_encode($monthLabels) ?>,datasets:[{label:'Revenue (₱)',data:<?= json_encode($monthData) ?>,backgroundColor:'#e9456088',borderColor:'#e94560',borderWidth:2,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('categoryChart'),{type:'pie',data:{labels:<?= json_encode($catLabels) ?>,datasets:[{data:<?= json_encode($catData) ?>,backgroundColor:colors}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('topItemsChart'),{type:'bar',data:{labels:<?= json_encode($itemLabels) ?>,datasets:[{label:'Units Sold',data:<?= json_encode($itemData) ?>,backgroundColor:colors,borderRadius:6}]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});
new Chart(document.getElementById('paymentChart'),{type:'doughnut',data:{labels:<?= json_encode($payLabels) ?>,datasets:[{data:<?= json_encode($payData) ?>,backgroundColor:['#2980b9','#27ae60','#f39c12']}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
</script>
</body>
</html>
