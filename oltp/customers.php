<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireLogin();

$conn    = getOLTP();
$baseUrl = '../';
$success = '';
$error   = '';

// HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD CUSTOMER
    if ($action === 'add') {
        $name    = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $stmt    = $conn->prepare(
            "INSERT INTO customer (name, contact)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ss", $name, $contact);
        $stmt->execute()
            ? $success = "Customer '$name' added!"
            : $error   = "Failed to add customer.";
    }

    // EDIT CUSTOMER
    if ($action === 'edit') {
        $customer_id = intval($_POST['customer_id']);
        $name        = trim($_POST['name']);
        $contact     = trim($_POST['contact']);
        $stmt        = $conn->prepare(
            "UPDATE customer SET name=?, contact=?
             WHERE customer_id=?"
        );
        $stmt->bind_param("ssi", $name, $contact, $customer_id);
        $stmt->execute()
            ? $success = "Customer updated!"
            : $error   = "Update failed.";
    }

    // RESET POINTS (admin only)
    if ($action === 'reset_points' && isAdmin()) {
        $customer_id = intval($_POST['customer_id']);
        $conn->query("
            UPDATE customer
            SET loyalty_points = 0
            WHERE customer_id  = $customer_id
        ");
        $success = "Loyalty points reset.";
    }
}

// FETCH ALL CUSTOMERS
$customers = $conn->query("
    SELECT * FROM customer
    ORDER BY loyalty_points DESC
");
$custList = [];
while ($r = $customers->fetch_assoc()) $custList[] = $r;

$totalCustomers = count($custList);
$totalPoints    = array_sum(array_column($custList, 'loyalty_points'));
$topCustomer    = !empty($custList) ? $custList[0] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers - Cantech</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .summary-card {
            border-radius:12px; padding:1.2rem;
            color:white; margin-bottom:1rem;
        }
        .card-purple { background:linear-gradient(135deg,#8e44ad,#6c3483); }
        .card-blue   { background:linear-gradient(135deg,#2980b9,#1a5276); }
        .card-gold   { background:linear-gradient(135deg,#f9ca24,#f0932b); }
        .points-badge {
            background:linear-gradient(135deg,#a29bfe,#6c5ce7);
            color:white; border-radius:20px;
            padding:0.2rem 0.8rem;
            font-size:0.8rem; font-weight:600;
        }
        .tier-vip      { color:#f9ca24; font-weight:700; }
        .tier-regular  { color:#636e72; }
        .tier-new      { color:#b2bec3; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"> Customer Management</h4>
            <small class="text-muted">
                Manage customers and loyalty points
            </small>
        </div>
        <button class="btn btn-danger btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#addModal">
            + Add Customer
        </button>
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
            <div class="summary-card card-blue">
                <div style="font-size:0.85rem;opacity:0.85">
                    Total Customers
                </div>
                <div style="font-size:2rem;font-weight:700">
                    <?= $totalCustomers ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-purple">
                <div style="font-size:0.85rem;opacity:0.85">
                    Total Points Issued
                </div>
                <div style="font-size:2rem;font-weight:700">
                    ⭐ <?= number_format($totalPoints) ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-gold">
                <div style="font-size:0.85rem;opacity:0.85">
                    Top Customer
                </div>
                <div style="font-size:1.2rem;font-weight:700">
                    <?= $topCustomer
                        ? htmlspecialchars($topCustomer['name'])
                        : 'N/A' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CUSTOMER TABLE -->
    <div class="page-card">
        <h6 class="fw-bold mb-3">All Customers</h6>

        <!-- Loyalty Tier Legend -->
        <div class="mb-3 d-flex gap-3">
            <small><span class="tier-vip">⭐ VIP</span>
                — 500+ pts</small>
            <small><span class="tier-regular">🟢 Regular</span>
                — 100–499 pts</small>
            <small><span class="tier-new">⚪ New</span>
                — 0–99 pts</small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Loyalty Points</th>
                        <th>Total Spent</th>
                        <th>Tier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1;
                    foreach ($custList as $c):
                        $pts  = $c['loyalty_points'];
                        $tier = $pts >= 500
                            ? ['⭐ VIP',    'tier-vip']
                            : ($pts >= 100
                                ? ['🟢 Regular','tier-regular']
                                : ['⚪ New',    'tier-new']);
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong>
                            <?= htmlspecialchars($c['name']) ?>
                        </strong></td>
                        <td><?= htmlspecialchars($c['contact']) ?></td>
                        <td>
                            <span class="points-badge">
                                ⭐ <?= number_format($pts) ?> pts
                            </span>
                        </td>
                        <td>₱<?= number_format($c['total_spent'],2) ?></td>
                        <td>
                            <span class="<?= $tier[1] ?>">
                                <?= $tier[0] ?>
                            </span>
                        </td>
                        <td>
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary"
                                    style="font-size:0.78rem;padding:0.2rem 0.6rem"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-id="<?= $c['customer_id'] ?>"
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                    data-contact="<?= htmlspecialchars($c['contact']) ?>">
                                Edit
                            </button>
                            <?php if (isAdmin()): ?>
                            <!-- Reset Points -->
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm(
                                    'Reset loyalty points for this customer?')">
                                <input type="hidden"
                                       name="action" value="reset_points">
                                <input type="hidden"
                                       name="customer_id"
                                       value="<?= $c['customer_id'] ?>">
                                <button class="btn btn-sm btn-outline-warning"
                                        style="font-size:0.78rem;padding:0.2rem 0.6rem">
                                    Reset Pts
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- HOW LOYALTY WORKS -->
    <div class="page-card">
        <h6 class="fw-bold mb-3"> How Loyalty Points Work</h6>
        <div class="row g-3">
            <div class="col-md-3 text-center">
                <div style="font-size:2rem">🛒</div>
                <div class="fw-semibold mt-1">Customer Orders</div>
                <small class="text-muted">
                    Cashier selects customer when placing order
                </small>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem">⭐</div>
                <div class="fw-semibold mt-1">Points Earned</div>
                <small class="text-muted">
                    1 point per ₱10 spent automatically
                </small>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem">🎁</div>
                <div class="fw-semibold mt-1">Redeem Points</div>
                <small class="text-muted">
                    100 pts = ₱10 discount on next order
                </small>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem">👑</div>
                <div class="fw-semibold mt-1">Earn VIP Status</div>
                <small class="text-muted">
                    500+ points = VIP tier customer
                </small>
            </div>
        </div>
    </div>

</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Customer</h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Full Name
                        </label>
                        <input type="text" name="name"
                               class="form-control"
                               placeholder="e.g. Maria Santos"
                               required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Contact Number
                        </label>
                        <input type="text" name="contact"
                               class="form-control"
                               placeholder="e.g. 09171234567">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        Add Customer
                    </button>
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
                <h5 class="modal-title">Edit Customer</h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Full Name
                        </label>
                        <input type="text" name="name"
                               id="edit_name"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Contact Number
                        </label>
                        <input type="text" name="contact"
                               id="edit_contact"
                               class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editModal')
    .addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value      = btn.dataset.id;
    document.getElementById('edit_name').value    = btn.dataset.name;
    document.getElementById('edit_contact').value = btn.dataset.contact;
});
</script>
</body>
</html>