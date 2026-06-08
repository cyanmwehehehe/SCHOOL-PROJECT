<?php
require_once 'config/auth.php';
require_once 'config/db.php';
requireLogin();

$oltp    = getOLTP();
$olap    = getOLAP();
$baseUrl = '/dhandaras_canteen/';

// ── STATS ──────────────────────────────────────────────────────
$todayRevenue = $oltp->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE DATE(order_date) = CURDATE() AND status = 'completed'
")->fetch_assoc()['total'];

$todayOrders = $oltp->query("
    SELECT COUNT(*) AS total FROM orders
    WHERE DATE(order_date) = CURDATE() AND status = 'completed'
")->fetch_assoc()['total'];

$lowStock = $oltp->query("
    SELECT COUNT(*) AS total FROM ingredient
    WHERE stock_qty <= reorder_level
")->fetch_assoc()['total'];

// ── BEST SELLERS ───────────────────────────────────────────────
$bestSellers = $olap->query("
    SELECT m.item_name, c.category_name,
           SUM(f.quantity_sold) AS total_sold,
           SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_menu_item m ON f.item_id = m.item_id
    JOIN dim_category c ON f.category_id = c.category_id
    GROUP BY m.item_name, c.category_name
    ORDER BY total_sold DESC
    LIMIT 5
");
$bestSellerRows = [];
$rank = 1;
while ($row = $bestSellers->fetch_assoc()) {
    $row['rank'] = $rank++;
    $bestSellerRows[] = $row;
}

// ── LOYALTY LEADERBOARD ────────────────────────────────────────
$loyalCustomers = $oltp->query("
    SELECT name, loyalty_points, total_spent
    FROM customer
    ORDER BY loyalty_points DESC
    LIMIT 5
");

// ── LOW STOCK ALERTS ──────────────────────────────────────────
$lowStockItems = $oltp->query("
    SELECT name, stock_qty, reorder_level, unit
    FROM ingredient
    WHERE stock_qty <= reorder_level
    ORDER BY stock_qty ASC
    LIMIT 5
");

// ── RECENT ORDERS ─────────────────────────────────────────────
$recentOrders = $oltp->query("
    SELECT o.order_id, c.name AS customer,
           o.total_amount, o.status, o.order_date
    FROM orders o
    JOIN customer c ON o.customer_id = c.customer_id
    ORDER BY o.order_date DESC
    LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - CanTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include 'includes/sidebar_style.php'; ?>
    <style>
        .summary-card { border-radius:12px; padding:1.5rem; color:white; margin-bottom:1rem; }
        .summary-card .value { font-size:2rem; font-weight:700; }
        .summary-card .label { font-size:0.85rem; opacity:0.85; }
        .card-red    { background:linear-gradient(135deg,#e94560,#c0392b); }
        .card-blue   { background:linear-gradient(135deg,#2980b9,#1a5276); }
        .card-orange { background:linear-gradient(135deg,#f39c12,#d35400); }
        .card-green  { background:linear-gradient(135deg,#27ae60,#1e8449); }
        .content-card { background:white; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .content-card h6 { font-weight:700; color:#333; margin-bottom:1rem; }
        .rank-badge { width:32px; height:32px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; color:white; }
        .rank-1 { background:linear-gradient(135deg,#f9ca24,#f0932b); }
        .rank-2 { background:linear-gradient(135deg,#b2bec3,#636e72); }
        .rank-3 { background:linear-gradient(135deg,#cd853f,#8b4513); }
        .rank-4, .rank-5 { background:#dfe6e9; color:#636e72; }
        .best-seller-item { display:flex; align-items:center; gap:1rem; padding:0.75rem 0; border-bottom:1px solid #f5f5f5; }
        .best-seller-item:last-child { border-bottom:none; }
        .best-seller-info { flex:1; }
        .best-seller-info .name { font-weight:600; font-size:0.9rem; }
        .best-seller-info .cat { color:#999; font-size:0.78rem; }
        .best-seller-revenue { text-align:right; }
        .best-seller-revenue .amount { font-weight:700; color:#e94560; font-size:0.9rem; }
        .best-seller-revenue .sold { color:#999; font-size:0.75rem; }
        .loyalty-item { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid #f5f5f5; }
        .loyalty-item:last-child { border-bottom:none; }
        .points-badge { background:linear-gradient(135deg,#a29bfe,#6c5ce7); color:white; border-radius:20px; padding:0.2rem 0.8rem; font-size:0.8rem; font-weight:600; }
        .stock-alert { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 0.8rem; border-radius:8px; background:#fff5f5; margin-bottom:0.5rem; border-left:3px solid #e94560; }
        .badge-completed { background:#d4efdf; color:#1e8449; }
        .badge-pending   { background:#fef9e7; color:#d68910; }
        .badge-cancelled { background:#fadbd8; color:#c0392b; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>! </h4>
            <small class="text-muted"><?= date('l, F d, Y') ?></small>
        </div>
        <?php if (isAdmin()): ?>
        <a href="olap/etl.php" class="btn btn-sm btn-outline-danger"> Sync ETL</a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="summary-card card-red"><div class="label">Today's Revenue</div><div class="value">₱<?= number_format($todayRevenue,2) ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-blue"><div class="label">Today's Orders</div><div class="value"><?= $todayOrders ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-orange"><div class="label">Low Stock Alerts</div><div class="value"><?= $lowStock ?></div></div></div>
        <div class="col-md-3"><div class="summary-card card-green"><div class="label">Your Role</div><div class="value" style="font-size:1.2rem"><?= $_SESSION['role']==='admin'?' Admin':' Cashier' ?></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-md-5">
            <div class="content-card">
                <h6> Best Sellers</h6>
                <?php foreach ($bestSellerRows as $item): ?>
                <div class="best-seller-item">
                    <span class="rank-badge rank-<?= $item['rank'] ?>"><?= $item['rank']===1?'🥇':($item['rank']===2?'🥈':($item['rank']===3?'🥉':$item['rank'])) ?></span>
                    <div class="best-seller-info">
                        <div class="name"><?= htmlspecialchars($item['item_name']) ?></div>
                        <div class="cat"><?= htmlspecialchars($item['category_name']) ?></div>
                    </div>
                    <div class="best-seller-revenue">
                        <div class="amount">₱<?= number_format($item['revenue'],2) ?></div>
                        <div class="sold"><?= $item['total_sold'] ?> sold</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($bestSellerRows)): ?>
                <p class="text-muted text-center py-3">No sales data yet. Run ETL first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-7">
            <?php if ($lowStock > 0): ?>
            <div class="content-card">
                <h6> Low Stock Alerts <span class="badge bg-danger ms-2"><?= $lowStock ?> items</span></h6>
                <?php while ($item = $lowStockItems->fetch_assoc()): ?>
                <div class="stock-alert">
                    <div><strong><?= htmlspecialchars($item['name']) ?></strong><small class="text-muted ms-2">Only <?= $item['stock_qty'] ?> <?= $item['unit'] ?> left</small></div>
                    <small class="text-danger fw-bold">Min: <?= $item['reorder_level'] ?> <?= $item['unit'] ?></small>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <h6> Loyalty Points Leaderboard</h6>
                <?php while ($cust = $loyalCustomers->fetch_assoc()): ?>
                <div class="loyalty-item">
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($cust['name']) ?></div>
                        <small class="text-muted">Total spent: ₱<?= number_format($cust['total_spent'],2) ?></small>
                    </div>
                    <span class="points-badge"> <?= number_format($cust['loyalty_points']) ?> pts</span>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"> Recent Orders</h6>
            <a href="oltp/orders.php" class="btn btn-sm btn-outline-danger">View All</a>
        </div>
        <table class="table table-hover table-sm">
            <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date & Time</th></tr></thead>
            <tbody>
                <?php while ($order = $recentOrders->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $order['order_id'] ?></td>
                    <td><?= htmlspecialchars($order['customer']) ?></td>
                    <td>₱<?= number_format($order['total_amount'],2) ?></td>
                    <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                    <td><?= date('M d, Y h:i A', strtotime($order['order_date'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
