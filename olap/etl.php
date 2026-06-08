<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();

$baseUrl = '../';
$log     = [];
$ran     = false;

function runETL() {
    $oltp = getOLTP();
    $olap = getOLAP();
    $log  = [];

    // STEP 1: dim_category
    $cats = $oltp->query("SELECT category_id, name FROM category");
    while ($row = $cats->fetch_assoc()) {
        $stmt = $olap->prepare(
            "INSERT INTO dim_category (category_id, category_name)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)"
        );
        $stmt->bind_param("is", $row['category_id'], $row['name']);
        $stmt->execute();
    }
    $log[] = ['step'=>1,'label'=>'Categories','status'=>'success',
              'msg'=>'dim_category synced'];

    // STEP 2: dim_menu_item
    $items = $oltp->query("SELECT item_id, name, price FROM menu_item");
    $count = 0;
    while ($row = $items->fetch_assoc()) {
        $cost = $row['price'] * 0.6;
        $stmt = $olap->prepare(
            "INSERT INTO dim_menu_item
             (item_id, item_name, unit_price, cost_price)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE item_name = VALUES(item_name)"
        );
        $stmt->bind_param("isdd",
            $row['item_id'], $row['name'],
            $row['price'], $cost
        );
        $stmt->execute();
        $count++;
    }
    $log[] = ['step'=>2,'label'=>'Menu Items','status'=>'success',
              'msg'=>"$count items synced to dim_menu_item"];

    // STEP 3: dim_customer
    $custs = $oltp->query("SELECT customer_id, name FROM customer");
    $count = 0;
    while ($row = $custs->fetch_assoc()) {
        $type = 'walk-in';
        $stmt = $olap->prepare(
            "INSERT INTO dim_customer
             (customer_id, customer_name, type)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)"
        );
        $stmt->bind_param("iss",
            $row['customer_id'], $row['name'], $type
        );
        $stmt->execute();
        $count++;
    }
    $log[] = ['step'=>3,'label'=>'Customers','status'=>'success',
              'msg'=>"$count customers synced to dim_customer"];

    // STEP 4: dim_payment_method
    $methods = ['Cash','GCash','Card'];
    foreach ($methods as $m) {
        $stmt = $olap->prepare(
            "INSERT IGNORE INTO dim_payment_method (method_name)
             VALUES (?)"
        );
        $stmt->bind_param("s", $m);
        $stmt->execute();
    }
    $log[] = ['step'=>4,'label'=>'Payment Methods','status'=>'success',
              'msg'=>'dim_payment_method synced'];

    // STEP 5: dim_time
    $dates = $oltp->query(
        "SELECT DISTINCT DATE(order_date) AS d FROM orders"
    );
    $count = 0;
    while ($row = $dates->fetch_assoc()) {
        $d         = $row['d'];
        $ts        = strtotime($d);
        $day       = date('d', $ts);
        $week      = date('W', $ts);
        $month     = date('F', $ts);
        $quarter   = ceil(date('n', $ts) / 3);
        $year      = date('Y', $ts);
        $dayOfWeek = date('l', $ts);
        $isWeekend = in_array(date('N', $ts), [6,7]) ? 1 : 0;
        $stmt      = $olap->prepare(
            "INSERT IGNORE INTO dim_time
             (full_date,day,week,month,quarter,year,day_of_week,is_weekend)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param("siisisis",
            $d,$day,$week,$month,$quarter,$year,$dayOfWeek,$isWeekend
        );
        $stmt->execute();
        $count++;
    }
    $log[] = ['step'=>5,'label'=>'Time Dimension','status'=>'success',
              'msg'=>"$count dates loaded into dim_time"];

    // STEP 6: fact_sales
    $olap->query("TRUNCATE TABLE fact_sales");
    $facts = $oltp->query("
        SELECT oi.order_item_id, o.order_date,
               oi.item_id, mi.category_id,
               o.customer_id, p.method,
               oi.quantity, (oi.quantity * oi.unit_price) AS total_revenue,
               oi.unit_price
        FROM order_item oi
        JOIN orders     o  ON oi.order_id    = o.order_id
        JOIN menu_item  mi ON oi.item_id     = mi.item_id
        JOIN payment    p  ON o.order_id     = p.order_id
        WHERE o.status = 'completed'
    ");
    $count = 0;
    while ($row = $facts->fetch_assoc()) {
        $dateOnly = date('Y-m-d', strtotime($row['order_date']));
        $tRes     = $olap->query(
            "SELECT time_id FROM dim_time
             WHERE full_date = '$dateOnly'"
        );
        $tRow     = $tRes->fetch_assoc();
        if (!$tRow) continue;
        $time_id  = $tRow['time_id'];

        $pRes     = $olap->query(
            "SELECT payment_method_id FROM dim_payment_method
             WHERE method_name = '{$row['method']}'"
        );
        $pRow     = $pRes->fetch_assoc();
        if (!$pRow) continue;
        $pm_id    = $pRow['payment_method_id'];
        $cost     = $row['total_revenue'] * 0.6;

        $stmt     = $olap->prepare(
            "INSERT INTO fact_sales
             (time_id,item_id,category_id,customer_id,
              payment_method_id,quantity_sold,
              total_revenue,discount_amount,cost_of_goods)
             VALUES (?,?,?,?,?,?,?,0,?)"
        );
        $stmt->bind_param(
            "iiiiiddd",
            $time_id, $row['item_id'], $row['category_id'],
            $row['customer_id'], $pm_id,
            $row['quantity'], $row['total_revenue'], $cost
        );
        $stmt->execute();
        $count++;
    }
    $log[] = ['step'=>6,'label'=>'Fact Sales','status'=>'success',
              'msg'=>"$count sales records loaded into fact_sales"];

    return $log;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['run'])) {
    $log = runETL();
    $ran = true;
}

// Quick stats
$oltp          = getOLTP();
$olap          = getOLAP();
$totalOrders   = $oltp->query(
    "SELECT COUNT(*) AS c FROM orders
     WHERE status='completed'"
)->fetch_assoc()['c'];
$totalFacts    = $olap->query(
    "SELECT COUNT(*) AS c FROM fact_sales"
)->fetch_assoc()['c'];
$lastSync      = $olap->query(
    "SELECT MAX(d.full_date) AS last
     FROM fact_sales f
     JOIN dim_time d ON f.time_id = d.time_id"
)->fetch_assoc()['last'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Run ETL - Dhandara's Canteen</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .etl-step {
            display:flex; align-items:center; gap:1rem;
            padding:0.8rem 1rem; border-radius:10px;
            margin-bottom:0.5rem; background:#f8f9fa;
            border-left:4px solid #27ae60;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from{opacity:0;transform:translateX(-10px)}
                            to{opacity:1;transform:translateX(0)} }
        .step-number {
            width:32px; height:32px; border-radius:50%;
            background:#27ae60; color:white;
            display:flex; align-items:center;
            justify-content:center; font-weight:700;
            font-size:0.85rem; flex-shrink:0;
        }
        .step-label { font-weight:600; font-size:0.9rem; }
        .step-msg   { color:#666; font-size:0.82rem; }
        .stat-card  {
            background:white; border-radius:12px; padding:1.2rem;
            box-shadow:0 2px 8px rgba(0,0,0,0.06);
            text-align:center;
        }
        .stat-value { font-size:1.8rem; font-weight:700; color:#e94560; }
        .stat-label { color:#999; font-size:0.82rem; margin-top:0.2rem; }
        .btn-etl {
            background:linear-gradient(135deg,#e94560,#c0392b);
            color:white; border:none; border-radius:12px;
            padding:0.8rem 2.5rem; font-size:1rem;
            font-weight:600; transition:all 0.3s; width:100%;
        }
        .btn-etl:hover {
            transform:translateY(-2px);
            box-shadow:0 4px 15px rgba(233,69,96,0.4);
            color:white;
        }
        .flow-arrow {
            text-align:center; color:#ccc;
            font-size:1.5rem; margin:0.3rem 0;
        }
        .db-box {
            border-radius:10px; padding:1rem;
            text-align:center; font-weight:600;
        }
        .db-oltp { background:#d6eaf8; color:#1a5276; }
        .db-olap { background:#d5f5e3; color:#1e8449; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">

    <div class="mb-4">
        <h4 class="mb-0"> ETL Sync</h4>
        <small class="text-muted">
            Extract → Transform → Load (OLTP to OLAP)
        </small>
    </div>

    <!-- STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalOrders) ?></div>
                <div class="stat-label">Completed Orders in OLTP</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalFacts) ?></div>
                <div class="stat-label">Records in fact_sales (OLAP)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="font-size:1.2rem">
                    <?= $lastSync ?? 'Never' ?>
                </div>
                <div class="stat-label">Last Synced Date</div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- ETL FLOW DIAGRAM + BUTTON -->
        <div class="col-md-4">
            <div class="page-card text-center">
                <h6 class="fw-bold mb-3">ETL Pipeline</h6>

                <div class="db-box db-oltp mb-2">
                     Canteen_oltp<br>
                    <small>Transactional Database</small>
                </div>
                <div class="flow-arrow">↓ Extract</div>
                <div class="page-card py-2 px-3 mb-0"
                     style="background:#fff8e1;border-radius:8px">
                     <strong>Transform</strong><br>
                    <small class="text-muted">
                        Clean · Map · Aggregate
                    </small>
                </div>
                <div class="flow-arrow">↓ Load</div>
                <div class="db-box db-olap mb-3">
                     Canteen_olap<br>
                    <small>Star Schema</small>
                </div>

                <form method="POST">
                    <button name="run" class="btn-etl">
                        ▶ Run ETL Now
                    </button>
                </form>
                <small class="text-muted d-block mt-2">
                    This will sync all completed orders
                    from OLTP into the Star Schema
                </small>
            </div>
        </div>

        <!-- ETL LOG -->
        <div class="col-md-8">
            <div class="page-card">
                <h6 class="fw-bold mb-3">
                    <?= $ran ? '✅ ETL Run Log' : ' ETL Steps' ?>
                </h6>

                <?php if ($ran): ?>
                <!-- Show actual results -->
                <?php foreach($log as $step): ?>
                <div class="etl-step">
                    <div class="step-number"><?= $step['step'] ?></div>
                    <div>
                        <div class="step-label">
                            <?= $step['label'] ?>
                        </div>
                        <div class="step-msg">
                            ✔ <?= $step['msg'] ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="alert alert-success mt-3 mb-0">
                    <strong>ETL completed successfully!</strong><br>
                    <small>
                        Timestamp: <?= date('F d, Y h:i:s A') ?>
                    </small>
                </div>

                <?php else: ?>
                <!-- Show preview of what will happen -->
                <?php
                $steps = [
                    [1,'Load dim_category',
                     'Syncs all food categories from OLTP'],
                    [2,'Load dim_menu_item',
                     'Syncs menu items with pricing data'],
                    [3,'Load dim_customer',
                     'Syncs customer records'],
                    [4,'Load dim_payment_method',
                     'Loads Cash, GCash, Card methods'],
                    [5,'Load dim_time',
                     'Generates date hierarchy from orders'],
                    [6,'Load fact_sales',
                     'Builds the main fact table from completed orders'],
                ];
                foreach($steps as [$n,$label,$desc]):
                ?>
                <div class="etl-step" style="border-color:#bdc3c7">
                    <div class="step-number"
                         style="background:#bdc3c7"><?= $n ?></div>
                    <div>
                        <div class="step-label"><?= $label ?></div>
                        <div class="step-msg"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="alert alert-info mt-3 mb-0">
                    Click <strong>Run ETL Now</strong> to start the sync.
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>