<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireLogin();

$conn    = getOLTP();
$success = '';
$error   = '';
$baseUrl = '../';

// ── HANDLE ACTIONS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD INGREDIENT
    if ($action === 'add' && isAdmin()) {
        $name          = trim($_POST['name']);
        $unit          = trim($_POST['unit']);
        $stock_qty     = floatval($_POST['stock_qty']);
        $reorder_level = floatval($_POST['reorder_level']);

        $stmt = $conn->prepare(
            "INSERT INTO ingredient (name, unit, stock_qty, reorder_level)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssdd", $name, $unit, $stock_qty, $reorder_level);
        $stmt->execute()
            ? $success = "Ingredient '$name' added!"
            : $error   = "Failed to add ingredient.";
    }

    // RESTOCK
    if ($action === 'restock') {
        $ingredient_id = intval($_POST['ingredient_id']);
        $supplier_id   = intval($_POST['supplier_id']);
        $qty_added     = floatval($_POST['qty_added']);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "UPDATE ingredient SET stock_qty = stock_qty + ? WHERE ingredient_id = ?"
            );
            $stmt->bind_param("di", $qty_added, $ingredient_id);
            $stmt->execute();

            $stmt2 = $conn->prepare(
                "INSERT INTO restock_log (ingredient_id, supplier_id, qty_added) VALUES (?, ?, ?)"
            );
            $stmt2->bind_param("iid", $ingredient_id, $supplier_id, $qty_added);
            $stmt2->execute();

            $conn->commit();
            $success = "Restock recorded successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Restock failed: " . $e->getMessage();
        }
    }

    // EDIT INGREDIENT
    if ($action === 'edit' && isAdmin()) {
        $ingredient_id = intval($_POST['ingredient_id']);
        $name          = trim($_POST['name']);
        $unit          = trim($_POST['unit']);
        $reorder_level = floatval($_POST['reorder_level']);

        $stmt = $conn->prepare(
            "UPDATE ingredient SET name=?, unit=?, reorder_level=? WHERE ingredient_id=?"
        );
        $stmt->bind_param("ssdi", $name, $unit, $reorder_level, $ingredient_id);
        $stmt->execute()
            ? $success = "Ingredient updated!"
            : $error   = "Update failed.";
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────
$ingredients = $conn->query("SELECT * FROM ingredient ORDER BY stock_qty ASC");
$ingList     = [];
while ($r = $ingredients->fetch_assoc()) $ingList[] = $r;

$suppliers = $conn->query("SELECT * FROM supplier ORDER BY name");
$supList   = [];
while ($r = $suppliers->fetch_assoc()) $supList[] = $r;

$restockLogs = $conn->query("
    SELECT rl.*, i.name AS ingredient_name,
           s.name AS supplier_name, i.unit
    FROM restock_log rl
    JOIN ingredient i ON rl.ingredient_id = i.ingredient_id
    JOIN supplier   s ON rl.supplier_id   = s.supplier_id
    ORDER BY rl.restock_date DESC
    LIMIT 10
");

$lowCount  = $conn->query(
    "SELECT COUNT(*) AS c FROM ingredient WHERE stock_qty <= reorder_level"
)->fetch_assoc()['c'];
$totalIngs = count($ingList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - CanTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .stock-bar  { height:6px; border-radius:3px; background:#FFE4D0; margin-top:4px; }
        .stock-fill { height:100%; border-radius:3px; transition:width 0.3s; }
        .low-row    { background:#FFF5EE !important; }
        .badge-low  { background:#FADBD8; color:#C0392B; border-radius:20px; padding:0.2rem 0.7rem; font-size:0.75rem; font-weight:600; }
        .badge-ok   { background:#D5F5E3; color:#1E8449; border-radius:20px; padding:0.2rem 0.7rem; font-size:0.75rem; font-weight:600; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"> Inventory Management</h4>
            <small class="text-muted">Track ingredients and stock levels</small>
        </div>
        <?php if (isAdmin()): ?>
        <button class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#addIngModal">
            + Add Ingredient
        </button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success py-2"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= $error ?></div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="summary-card card-green">
                <span class="icon"></span>
                <div class="label">Total Ingredients</div>
                <div class="value"><?= $totalIngs ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-red">
                <span class="icon"></span>
                <div class="label">Low Stock Alerts</div>
                <div class="value"><?= $lowCount ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-purple">
                <span class="icon"></span>
                <div class="label">Suppliers</div>
                <div class="value"><?= count($supList) ?></div>
            </div>
        </div>
    </div>

    <!-- INGREDIENTS TABLE -->
    <div class="page-card">
        <h6> Ingredients Stock</h6>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ingredient</th>
                        <th>Stock Level</th>
                        <th>Unit</th>
                        <th>Reorder At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    foreach ($ingList as $ing):
                        $isLow    = $ing['stock_qty'] <= $ing['reorder_level'];
                        $pct      = $ing['reorder_level'] > 0
                            ? min(100, round(($ing['stock_qty'] / ($ing['reorder_level'] * 3)) * 100))
                            : 100;
                        $barColor = $isLow ? '#FF6B35' : '#2ECC71';
                    ?>
                    <tr class="<?= $isLow ? 'low-row' : '' ?>">
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($ing['name']) ?></strong></td>
                        <td style="min-width:130px">
                            <?= number_format($ing['stock_qty'], 2) ?>
                            <div class="stock-bar">
                                <div class="stock-fill"
                                     style="width:<?= $pct ?>%;background:<?= $barColor ?>">
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($ing['unit']) ?></td>
                        <td><?= number_format($ing['reorder_level'], 2) ?></td>
                        <td>
                            <span class="<?= $isLow ? 'badge-low' : 'badge-ok' ?>">
                                <?= $isLow ? '⚠ Low Stock' : '✔ OK' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-success"
                                    style="font-size:0.78rem;padding:0.2rem 0.6rem"
                                    data-bs-toggle="modal"
                                    data-bs-target="#restockModal"
                                    data-id="<?= $ing['ingredient_id'] ?>"
                                    data-name="<?= htmlspecialchars($ing['name']) ?>">
                                Restock
                            </button>
                            <?php if (isAdmin()): ?>
                            <button class="btn btn-sm btn-outline-primary"
                                    style="font-size:0.78rem;padding:0.2rem 0.6rem"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editIngModal"
                                    data-id="<?= $ing['ingredient_id'] ?>"
                                    data-name="<?= htmlspecialchars($ing['name']) ?>"
                                    data-unit="<?= htmlspecialchars($ing['unit']) ?>"
                                    data-reorder="<?= $ing['reorder_level'] ?>">
                                Edit
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RESTOCK HISTORY -->
    <div class="page-card">
        <h6> Recent Restock History</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ingredient</th>
                        <th>Qty Added</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $restockLogs->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M d, Y h:i A', strtotime($log['restock_date'])) ?></td>
                        <td><?= htmlspecialchars($log['ingredient_name']) ?></td>
                        <td>+<?= number_format($log['qty_added'], 2) ?>
                            <?= htmlspecialchars($log['unit']) ?></td>
                        <td><?= htmlspecialchars($log['supplier_name']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD INGREDIENT MODAL -->
<div class="modal fade" id="addIngModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Ingredient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ingredient Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-select">
                            <option>kg</option><option>g</option>
                            <option>L</option><option>ml</option>
                            <option>pcs</option><option>pack</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" name="stock_qty" class="form-control"
                               step="0.01" min="0" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control"
                               step="0.01" min="0" value="5" required>
                        <small class="text-muted">Alert shows when stock drops below this number</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RESTOCK MODAL -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restock — <span id="restock_name"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="ingredient_id" id="restock_ing_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <?php foreach ($supList as $sup): ?>
                            <option value="<?= $sup['supplier_id'] ?>">
                                <?= htmlspecialchars($sup['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity to Add</label>
                        <input type="number" name="qty_added" class="form-control"
                               step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT INGREDIENT MODAL -->
<div class="modal fade" id="editIngModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"        value="edit">
                <input type="hidden" name="ingredient_id" id="edit_ing_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit_ing_name"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit</label>
                        <select name="unit" id="edit_ing_unit" class="form-select">
                            <option>kg</option><option>g</option>
                            <option>L</option><option>ml</option>
                            <option>pcs</option><option>pack</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" id="edit_ing_reorder"
                               class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('restockModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('restock_ing_id').value     = btn.dataset.id;
    document.getElementById('restock_name').textContent = btn.dataset.name;
});
document.getElementById('editIngModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_ing_id').value      = btn.dataset.id;
    document.getElementById('edit_ing_name').value    = btn.dataset.name;
    document.getElementById('edit_ing_reorder').value = btn.dataset.reorder;
    const unitSelect = document.getElementById('edit_ing_unit');
    for (let opt of unitSelect.options) {
        opt.selected = opt.value === btn.dataset.unit;
    }
});
</script>
</body>
</html>
