<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireLogin();

$conn    = getOLTP();
$success = '';
$error   = '';
$baseUrl = '../';

// ── HANDLE FORM ACTIONS ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD MENU ITEM
    if ($action === 'add') {
        $name        = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $price       = floatval($_POST['price']);

        $stmt = $conn->prepare(
            "INSERT INTO menu_item (name, category_id, price, is_available)
             VALUES (?, ?, ?, 1)"
        );
        $stmt->bind_param("sid", $name, $category_id, $price);
        $stmt->execute()
            ? $success = "Menu item '$name' added successfully!"
            : $error   = "Failed to add item.";
    }

    // EDIT MENU ITEM
    if ($action === 'edit') {
        $item_id     = intval($_POST['item_id']);
        $name        = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $price       = floatval($_POST['price']);

        $stmt = $conn->prepare(
            "UPDATE menu_item SET name=?, category_id=?, price=?
             WHERE item_id=?"
        );
        $stmt->bind_param("sdii", $name, $category_id, $price, $item_id);
        $stmt->execute()
            ? $success = "Item updated successfully!"
            : $error   = "Failed to update item.";
    }

    // TOGGLE AVAILABILITY
    if ($action === 'toggle') {
        $item_id = intval($_POST['item_id']);
        $conn->query(
            "UPDATE menu_item
             SET is_available = IF(is_available=1, 0, 1)
             WHERE item_id = $item_id"
        );
        $success = "Item availability updated!";
    }

    // DELETE (admin only)
    if ($action === 'delete' && isAdmin()) {
        $item_id = intval($_POST['item_id']);
        $stmt = $conn->prepare("DELETE FROM menu_item WHERE item_id=?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute()
            ? $success = "Item deleted."
            : $error   = "Cannot delete — item may have existing orders.";
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────
$categories = $conn->query("SELECT * FROM category ORDER BY name");
$catList    = [];
while ($r = $categories->fetch_assoc()) $catList[] = $r;

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterCat = isset($_GET['cat'])    ? intval($_GET['cat'])  : 0;

$where = "WHERE 1=1";
if ($search)    $where .= " AND m.name LIKE '%" . $conn->real_escape_string($search) . "%'";
if ($filterCat) $where .= " AND m.category_id = $filterCat";

$items = $conn->query("
    SELECT m.*, c.name AS category_name
    FROM menu_item m
    JOIN category c ON m.category_id = c.category_id
    $where
    ORDER BY c.name, m.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Menu - CanTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .badge-available   { background:#D5F5E3; color:#1E8449; border-radius:20px; padding:0.2rem 0.8rem; font-size:0.75rem; font-weight:600; }
        .badge-unavailable { background:#FADBD8; color:#C0392B; border-radius:20px; padding:0.2rem 0.8rem; font-size:0.75rem; font-weight:600; }
        .btn-action { font-size:0.78rem; padding:0.2rem 0.6rem; border-radius:6px; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"> Menu Management</h4>
            <small class="text-muted">Manage all available menu items</small>
        </div>
        <?php if (isAdmin()): ?>
        <button class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#addModal">
            + Add Item
        </button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success py-2"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= $error ?></div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="page-card py-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="text" name="search" class="form-control form-control-sm w-auto"
                   placeholder="Search item..." value="<?= htmlspecialchars($search) ?>">
            <select name="cat" class="form-select form-select-sm w-auto">
                <option value="0">All Categories</option>
                <?php foreach ($catList as $cat): ?>
                <option value="<?= $cat['category_id'] ?>"
                    <?= $filterCat == $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
            <a href="menu.php" class="btn btn-sm btn-outline-danger">Clear</a>
        </form>
    </div>

    <!-- MENU TABLE -->
    <div class="page-card">
        <table class="table table-hover table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td>₱<?= number_format($item['price'], 2) ?></td>
                    <td>
                        <span class="<?= $item['is_available'] ? 'badge-available' : 'badge-unavailable' ?>">
                            <?= $item['is_available'] ? '✔ Available' : '✖ Unavailable' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action"  value="toggle">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                            <button class="btn btn-sm btn-outline-warning btn-action">
                                <?= $item['is_available'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <?php if (isAdmin()): ?>
                        <button class="btn btn-sm btn-outline-primary btn-action"
                            data-bs-toggle="modal"
                            data-bs-target="#editModal"
                            data-id="<?= $item['item_id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-cat="<?= $item['category_id'] ?>"
                            data-price="<?= $item['price'] ?>">
                            Edit
                        </button>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="action"  value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger btn-action">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($catList as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" class="form-control"
                               step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"  value="edit">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="edit_cat" class="form-select">
                            <?php foreach ($catList as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" id="edit_price"
                               class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_item_id').value = btn.dataset.id;
    document.getElementById('edit_name').value    = btn.dataset.name;
    document.getElementById('edit_price').value   = btn.dataset.price;
    const catSelect = document.getElementById('edit_cat');
    for (let opt of catSelect.options) {
        opt.selected = opt.value == btn.dataset.cat;
    }
});
</script>
</body>
</html>
