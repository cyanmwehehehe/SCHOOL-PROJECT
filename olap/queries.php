<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();

$baseUrl = '../';
$olap    = getOLAP();

// ── ROLL-UP ────────────────────────────────────────────────────
$rollup = $olap->query("
    SELECT d.year, d.month,
           SUM(f.total_revenue) AS revenue,
           SUM(f.quantity_sold) AS total_sold
    FROM fact_sales f
    JOIN dim_time d ON f.time_id = d.time_id
    GROUP BY d.year, d.month
    ORDER BY d.year, d.month
");

// ── DRILL-DOWN ─────────────────────────────────────────────────
$drilldown = $olap->query("
    SELECT c.category_name, m.item_name,
           SUM(f.quantity_sold) AS total_sold,
           SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_category c  ON f.category_id = c.category_id
    JOIN dim_menu_item m ON f.item_id      = m.item_id
    GROUP BY c.category_name, m.item_name
    ORDER BY c.category_name, revenue DESC
");

// ── SLICE ──────────────────────────────────────────────────────
$slice = $olap->query("
    SELECT m.item_name,
           SUM(f.quantity_sold) AS total_sold,
           SUM(f.total_revenue) AS weekend_revenue
    FROM fact_sales f
    JOIN dim_time d      ON f.time_id = d.time_id
    JOIN dim_menu_item m ON f.item_id = m.item_id
    WHERE d.is_weekend = 1
    GROUP BY m.item_name
    ORDER BY weekend_revenue DESC
");

// ── DICE ───────────────────────────────────────────────────────
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : 2025;
$dice        = $olap->query("
    SELECT c.category_name, d.month, p.method_name,
           SUM(f.total_revenue) AS revenue
    FROM fact_sales f
    JOIN dim_category c       ON f.category_id       = c.category_id
    JOIN dim_time d            ON f.time_id           = d.time_id
    JOIN dim_payment_method p  ON f.payment_method_id = p.payment_method_id
    WHERE c.category_name = 'Meals'
      AND d.year           = $year_filter
      AND p.method_name    = 'Cash'
    GROUP BY c.category_name, d.month, p.method_name
    ORDER BY d.month
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OLAP Queries - Dhandara's Canteen</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .olap-card { border-left:5px solid; margin-bottom:2rem; }
        .rollup-card    { border-color:#2980b9; }
        .drilldown-card { border-color:#27ae60; }
        .slice-card     { border-color:#f39c12; }
        .dice-card      { border-color:#8e44ad; }
        .olap-label {
            display:inline-block; padding:0.2rem 0.8rem;
            border-radius:20px; font-size:0.75rem;
            font-weight:700; margin-bottom:0.5rem;
        }
        .label-rollup    { background:#d6eaf8; color:#1a5276; }
        .label-drilldown { background:#d5f5e3; color:#1e8449; }
        .label-slice     { background:#fef9e7; color:#d68910; }
        .label-dice      { background:#e8daef; color:#6c3483; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="mb-4">
        <h4 class="mb-0"> OLAP Operations</h4>
        <small class="text-muted">
            Roll-up · Drill-down · Slice · Dice
        </small>
    </div>

    <!-- ROLL-UP -->
    <div class="page-card olap-card rollup-card">
        <span class="olap-label label-rollup">Roll-up</span>
        <h6 class="fw-bold"> Revenue by Month & Year</h6>
        <p class="text-muted small mb-3">
            Aggregates daily sales upward to monthly and yearly totals
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Year</th><th>Month</th>
                        <th>Total Revenue</th><th>Items Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $rollup->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['year'] ?></td>
                        <td><?= $row['month'] ?></td>
                        <td>₱<?= number_format($row['revenue'],2) ?></td>
                        <td><?= $row['total_sold'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DRILL-DOWN -->
    <div class="page-card olap-card drilldown-card">
        <span class="olap-label label-drilldown">Drill-down</span>
        <h6 class="fw-bold"> Category → Item Breakdown</h6>
        <p class="text-muted small mb-3">
            Navigates from category-level summary down to individual items
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Category</th><th>Item</th>
                        <th>Qty Sold</th><th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lastCat = '';
                    while($row = $drilldown->fetch_assoc()):
                        $isNew   = $row['category_name'] !== $lastCat;
                        $lastCat = $row['category_name'];
                    ?>
                    <tr <?= $isNew ? 'class="table-light"' : '' ?>>
                        <td class="fw-bold">
                            <?= $isNew
                                ? htmlspecialchars($row['category_name'])
                                : '' ?>
                        </td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= $row['total_sold'] ?></td>
                        <td>₱<?= number_format($row['revenue'],2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SLICE -->
    <div class="page-card olap-card slice-card">
        <span class="olap-label label-slice">Slice</span>
        <h6 class="fw-bold"> Weekend Sales Only</h6>
        <p class="text-muted small mb-3">
            Filters a single dimension — showing only weekend transactions
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty Sold</th>
                        <th>Weekend Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $slice->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= $row['total_sold'] ?></td>
                        <td>₱<?= number_format($row['weekend_revenue'],2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DICE -->
    <div class="page-card olap-card dice-card">
        <span class="olap-label label-dice">Dice</span>
        <h6 class="fw-bold"> Meals + Cash + Year Filter</h6>
        <p class="text-muted small mb-3">
            Filters multiple dimensions simultaneously —
            category, payment method, and year
        </p>
        <form method="GET" class="d-flex gap-2 align-items-center mb-3">
            <label class="fw-semibold mb-0">Year:</label>
            <select name="year" class="form-select form-select-sm w-auto">
                <?php foreach([2024,2025,2026] as $y): ?>
                <option value="<?= $y ?>"
                    <?= $year_filter==$y?'selected':'' ?>>
                    <?= $y ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary">
                Apply Filter
            </button>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Category</th><th>Month</th>
                        <th>Payment</th><th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $dice->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                        <td><?= $row['month'] ?></td>
                        <td><?= $row['method_name'] ?></td>
                        <td>₱<?= number_format($row['revenue'],2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>