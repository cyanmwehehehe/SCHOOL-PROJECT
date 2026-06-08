<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireLogin();

$conn    = getOLTP();
$success = '';
$error   = '';
$baseUrl = '../';

// ── PLACE ORDER (ACID TRANSACTION) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'place_order') {
        $customer_id    = $_POST['customer_id'] === 'new' ? 0 : intval($_POST['customer_id']);
        $payment_method = trim($_POST['payment_method']);
        $item_ids       = $_POST['item_ids']   ?? [];
        $quantities     = $_POST['quantities'] ?? [];
        $redeem_points  = isset($_POST['redeem_points']) ? 1 : 0;

        $orderItems = [];
        foreach ($item_ids as $i => $item_id) {
            $qty = intval($quantities[$i]);
            if ($qty > 20) {
                $error = "Maximum order per item is 20 pieces. Please adjust your order.";
                break;
            }
            if ($qty > 0) {
                $orderItems[] = ['item_id' => intval($item_id), 'quantity' => $qty];
            }
        }

        if (empty($error) && empty($orderItems)) {
            $error = "Please add at least one item to the order.";
        }

        if (empty($error)) {
            if ($customer_id === 0) {
                $nameType = $_POST['name_type'] ?? 'nickname';
                if ($nameType === 'full') {
                    $surname   = trim($_POST['new_surname']   ?? '');
                    $firstname = trim($_POST['new_firstname'] ?? '');
                    $newName   = ($surname !== '' && $firstname !== '')
                        ? "$surname, $firstname"
                        : ($surname ?: $firstname ?: 'Walk-in Customer');
                } else {
                    $newName = trim($_POST['new_nickname'] ?? '');
                    $newName = $newName !== '' ? $newName : 'Walk-in Customer';
                }
                $stmt = $conn->prepare("INSERT INTO customer (name, contact) VALUES (?, 'N/A')");
                $stmt->bind_param("s", $newName);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }

            $conn->begin_transaction();
            try {
                $total    = 0;
                $discount = 0;
                foreach ($orderItems as &$oi) {
                    $res = $conn->query(
                        "SELECT price FROM menu_item
                         WHERE item_id = {$oi['item_id']} AND is_available = 1"
                    );
                    if ($res->num_rows === 0) throw new Exception("Item not available.");
                    $oi['price'] = $res->fetch_assoc()['price'];
                    $total += $oi['price'] * $oi['quantity'];
                }
                unset($oi);

                if ($redeem_points) {
                    $custRes = $conn->query(
                        "SELECT loyalty_points FROM customer WHERE customer_id = $customer_id"
                    );
                    $custPts = $custRes->fetch_assoc()['loyalty_points'];
                    if ($custPts >= 100) {
                        $discount   = floor($custPts / 100) * 10;
                        $discount   = min($discount, $total);
                        $total      = $total - $discount;
                        $pointsUsed = floor($discount / 10) * 100;
                        $conn->query(
                            "UPDATE customer
                             SET loyalty_points = loyalty_points - $pointsUsed
                             WHERE customer_id = $customer_id"
                        );
                    }
                }

                $stmt = $conn->prepare(
                    "INSERT INTO orders (customer_id, total_amount, status) VALUES (?, ?, 'completed')"
                );
                $stmt->bind_param("id", $customer_id, $total);
                $stmt->execute();
                $order_id = $conn->insert_id;

                foreach ($orderItems as $oi) {
                    $stmt2 = $conn->prepare(
                        "INSERT INTO order_item (order_id, item_id, quantity, unit_price)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt2->bind_param("iiid", $order_id, $oi['item_id'], $oi['quantity'], $oi['price']);
                    $stmt2->execute();
                }

                $stmt3 = $conn->prepare(
                    "INSERT INTO payment (order_id, method, amount_paid) VALUES (?, ?, ?)"
                );
                $stmt3->bind_param("isd", $order_id, $payment_method, $total);
                $stmt3->execute();

                $earnedPoints = floor($total / 10);
                $stmt4        = $conn->prepare(
                    "UPDATE customer
                     SET loyalty_points = loyalty_points + ?,
                         total_spent    = total_spent + ?
                     WHERE customer_id  = ?"
                );
                $stmt4->bind_param("idi", $earnedPoints, $total, $customer_id);
                $stmt4->execute();

                $conn->commit();
                $success = "Order #$order_id placed! Earned $earnedPoints loyalty points."
                    . ($discount > 0 ? " Discount applied: ₱" . number_format($discount, 2) : "");

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Order failed: " . $e->getMessage();
            }
        }
    }

    // CANCEL ORDER
    if ($action === 'cancel') {
        $order_id = intval($_POST['order_id']);
        $conn->begin_transaction();
        try {
            $orderInfo = $conn->query("
                SELECT o.customer_id, o.total_amount
                FROM orders o
                WHERE o.order_id = $order_id AND o.status = 'completed'
            ")->fetch_assoc();

            if ($orderInfo) {
                $pointsToDeduct = floor($orderInfo['total_amount'] / 10);
                $amountToDeduct = $orderInfo['total_amount'];
                $conn->query("
                    UPDATE customer
                    SET loyalty_points = GREATEST(0, loyalty_points - $pointsToDeduct),
                        total_spent    = GREATEST(0, total_spent - $amountToDeduct)
                    WHERE customer_id  = {$orderInfo['customer_id']}
                ");
            }

            $conn->query("UPDATE orders SET status='cancelled' WHERE order_id = $order_id");
            $conn->commit();
            $success = "Order #$order_id cancelled and loyalty points reversed.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Cancel failed: " . $e->getMessage();
        }
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────
$customers = $conn->query("SELECT * FROM customer ORDER BY name");
$custList  = [];
while ($r = $customers->fetch_assoc()) $custList[] = $r;

$menuItems = $conn->query("
    SELECT m.*, c.name AS category_name
    FROM menu_item m
    JOIN category c ON m.category_id = c.category_id
    WHERE m.is_available = 1
    ORDER BY c.name, m.name
");
$menuList = [];
while ($r = $menuItems->fetch_assoc()) $menuList[] = $r;

$menuByCategory = [];
foreach ($menuList as $item) {
    $menuByCategory[$item['category_name']][] = $item;
}

$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$whereStatus  = $filterStatus !== 'all'
    ? "WHERE o.status = '" . $conn->real_escape_string($filterStatus) . "'"
    : '';

$recentOrders = $conn->query("
    SELECT o.*, c.name AS customer_name,
           COUNT(oi.order_item_id) AS item_count
    FROM orders o
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN order_item oi ON o.order_id = oi.order_id
    $whereStatus
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 20
");

$todayStats = $conn->query("
    SELECT COUNT(*) AS total_orders,
           COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM orders
    WHERE DATE(order_date) = CURDATE() AND status = 'completed'
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - CanTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .menu-item-row {
            display:flex; align-items:center; gap:0.5rem;
            padding:0.5rem 0; border-bottom:1px solid #FFE4D0;
        }
        .menu-item-row:last-child { border-bottom:none; }
        .qty-input {
            width:70px; text-align:center;
            border:1.5px solid #FFE4D0; border-radius:8px; padding:0.3rem;
            font-family:'Poppins',sans-serif;
        }
        .qty-input:focus {
            outline:none; border-color:var(--primary);
            box-shadow:0 0 0 3px rgba(255,107,53,0.12);
        }
        .category-header {
            background:#FFF5EE; padding:0.4rem 0.8rem;
            border-radius:6px; font-weight:700;
            font-size:0.75rem; color:var(--primary);
            margin:0.5rem 0 0.3rem;
            text-transform:uppercase; letter-spacing:0.5px;
            border-left:3px solid var(--primary);
        }
        .price-tag {
            color:var(--primary); font-weight:700;
            font-size:0.85rem; min-width:65px; text-align:right;
        }
        .item-name { flex:1; font-size:0.875rem; color:var(--text-main); }
        .order-total {
            background:linear-gradient(135deg, var(--dark), var(--dark-soft));
            color:white; border-radius:12px; padding:1rem 1.5rem; margin-top:1rem;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"> Order Management</h4>
            <small class="text-muted"><?= date('l, F d, Y') ?></small>
        </div>
        <button class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#newOrderModal">
            + New Order
        </button>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success py-2"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= $error ?></div>
    <?php endif; ?>

    <!-- TODAY STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="summary-card card-orange">
                <span class="icon"></span>
                <div class="label">Today's Revenue</div>
                <div class="value">₱<?= number_format($todayStats['total_revenue'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-blue">
                <span class="icon"></span>
                <div class="label">Today's Orders</div>
                <div class="value"><?= $todayStats['total_orders'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card-brown">
                <span class="icon"></span>
                <div class="label">Cashier On Duty</div>
                <div class="value" style="font-size:1rem;margin-top:0.3rem">
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ORDER LIST -->
    <div class="page-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Recent Orders</h6>
            <div>
                <a href="?status=all"
                   class="filter-pill <?= $filterStatus=='all'?'active':'' ?>">All</a>
                <a href="?status=completed"
                   class="filter-pill <?= $filterStatus=='completed'?'active':'' ?>">Completed</a>
                <a href="?status=pending"
                   class="filter-pill <?= $filterStatus=='pending'?'active':'' ?>">Pending</a>
                <a href="?status=cancelled"
                   class="filter-pill <?= $filterStatus=='cancelled'?'active':'' ?>">Cancelled</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $recentOrders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $order['order_id'] ?></strong></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= $order['item_count'] ?> item(s)</td>
                        <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                        <td>
                            <span class="badge-<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, h:i A', strtotime($order['order_date'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary"
                                    style="font-size:0.78rem;padding:0.2rem 0.6rem"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewModal"
                                    onclick="loadOrderDetails(<?= $order['order_id'] ?>)">
                                View
                            </button>
                            <?php if ($order['status'] === 'completed' && isAdmin()): ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Cancel this order?')">
                                <input type="hidden" name="action"   value="cancel">
                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                        style="font-size:0.78rem;padding:0.2rem 0.6rem">
                                    Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- NEW ORDER MODAL -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"> New Order</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="orderForm">
                <input type="hidden" name="action" value="place_order">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>

                            <!-- Search box -->
                            <div style="position:relative;margin-bottom:0.4rem">
                                <input type="text" id="customer_search"
                                       class="form-control"
                                       placeholder=" Type to search customer..."
                                       autocomplete="off"
                                       oninput="filterCustomers(this.value)">
                            </div>

                            <!-- Scrollable select list -->
                            <select name="customer_id" id="customer_select"
                                    class="form-select" required
                                    onchange="updateLoyaltyInfo(this)"
                                    size="5"
                                    style="height:auto;border-radius:10px;">
                                <option value="">-- Select --</option>
                                <option value="new" data-points="0">
                                     New Customer (walk-in)
                                </option>
                                <?php foreach ($custList as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"
                                        data-points="<?= $c['loyalty_points'] ?>"
                                        data-label="<?= htmlspecialchars(strtolower($c['name'])) ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                    (⭐ <?= $c['loyalty_points'] ?> pts)
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Selected display -->
                            <div id="selected_customer_display"
                                 style="display:none;margin-top:0.4rem"
                                 class="alert alert-success py-1 px-2 mb-0">
                                <small>✔ Selected: <strong id="selected_name"></strong>
                                <a href="#" onclick="clearCustomer(event)"
                                   style="float:right;color:#c0392b;text-decoration:none">
                                   ✖ Change</a>
                                </small>
                            </div>

                            <!-- New customer form -->
                            <div id="new_customer_box"
                                 style="display:none;margin-top:0.8rem;
                                        background:#FFF5EE;border-radius:10px;
                                        padding:0.8rem;border:1px solid #FFE4D0">
                                <small class="fw-bold d-block mb-2"
                                       style="color:var(--primary)">
                                    New Customer Details
                                </small>
                                <div class="mb-2">
                                    <div class="btn-group btn-group-sm w-100">
                                        <input type="radio" class="btn-check"
                                               name="name_type" id="type_full"
                                               value="full" checked
                                               onchange="toggleNameType()">
                                        <label class="btn btn-outline-secondary"
                                               for="type_full">
                                            👤 Surname, Firstname
                                        </label>
                                        <input type="radio" class="btn-check"
                                               name="name_type" id="type_nickname"
                                               value="nickname"
                                               onchange="toggleNameType()">
                                        <label class="btn btn-outline-secondary"
                                               for="type_nickname">
                                             Nickname
                                        </label>
                                    </div>
                                </div>
                                <div id="full_name_fields">
                                    <div class="row g-2 mb-1">
                                        <div class="col-6">
                                            <input type="text" name="new_surname"
                                                   class="form-control form-control-sm"
                                                   placeholder="Surname (e.g. Santos)">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="new_firstname"
                                                   class="form-control form-control-sm"
                                                   placeholder="First name (e.g. Maria)">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Saved as "Santos, Maria"
                                    </small>
                                </div>
                                <div id="nickname_field" style="display:none">
                                    <input type="text" name="new_nickname"
                                           class="form-control form-control-sm"
                                           placeholder="Nickname (e.g. Dodong, Ate Mae)">
                                    <small class="text-muted">
                                        Leave blank for "Walk-in Customer"
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option>Cash</option>
                                <option>GCash</option>
                                <option>Card</option>
                            </select>
                        </div>
                    </div>

                    <!-- Loyalty redemption -->
                    <div id="loyalty_section" class="alert alert-warning py-2 mb-3"
                         style="display:none">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="redeem_points" id="redeemCheck"
                                   onchange="updateTotal()">
                            <label class="form-check-label" for="redeemCheck">
                                <strong>Redeem loyalty points</strong>
                                <span id="loyalty_text"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Menu items -->
                    <label class="form-label mb-2">Select Items</label>
                    <?php foreach ($menuByCategory as $catName => $items): ?>
                    <div class="category-header"><?= htmlspecialchars($catName) ?></div>
                    <?php foreach ($items as $item): ?>
                    <div class="menu-item-row">
                        <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                        <span class="price-tag">₱<?= number_format($item['price'], 2) ?></span>
                        <input type="hidden" name="item_ids[]" value="<?= $item['item_id'] ?>">
                        <input type="number" name="quantities[]" class="qty-input"
                               value="0" min="0" max="20"
                               data-price="<?= $item['price'] ?>"
                               onchange="updateTotal()">
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>

                    <!-- Order total -->
                    <div class="order-total">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal</span>
                            <span id="subtotal_display">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1"
                             id="discount_row" style="display:none !important">
                            <span style="color:var(--secondary)">Loyalty Discount</span>
                            <span id="discount_display" style="color:var(--secondary)">-₱0.00</span>
                        </div>
                        <hr style="border-color:rgba(255,255,255,0.2);margin:0.5rem 0">
                        <div class="d-flex justify-content-between">
                            <strong style="font-size:1.1rem">TOTAL</strong>
                            <strong id="total_display"
                                    style="font-size:1.3rem;color:var(--secondary)">
                                ₱0.00
                            </strong>
                        </div>
                        <div style="font-size:0.78rem;opacity:0.7;margin-top:0.3rem">
                            Points to earn: <span id="points_earn">0</span> pts
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        ✔ Place Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW ORDER MODAL -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Order Details</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-3">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── CUSTOMER SEARCH ────────────────────────────────────────────
function filterCustomers(query) {
    const select  = document.getElementById('customer_select');
    const options = select.querySelectorAll('option');
    const q       = query.toLowerCase().trim();

    options.forEach(opt => {
        if (opt.value === '' || opt.value === 'new') {
            opt.style.display = '';
            return;
        }
        const label = opt.dataset.label || opt.textContent.toLowerCase();
        opt.style.display = label.includes(q) ? '' : 'none';
    });

    // Reset selection when typing
    select.value = '';
    document.getElementById('selected_customer_display').style.display = 'none';
    document.getElementById('loyalty_section').style.display = 'none';
    document.getElementById('new_customer_box').style.display = 'none';
    updateTotal();
}

function clearCustomer(e) {
    if (e) e.preventDefault();
    document.getElementById('customer_select').value = '';
    document.getElementById('customer_search').value = '';
    document.getElementById('selected_customer_display').style.display = 'none';
    document.getElementById('loyalty_section').style.display = 'none';
    document.getElementById('new_customer_box').style.display = 'none';
    // Show all options again
    document.querySelectorAll('#customer_select option').forEach(o => o.style.display = '');
    updateTotal();
}

function toggleNameType() {
    const isFull = document.getElementById('type_full').checked;
    document.getElementById('full_name_fields').style.display = isFull ? '' : 'none';
    document.getElementById('nickname_field').style.display   = isFull ? 'none' : '';
}

function updateTotal() {
    document.querySelectorAll('.qty-input').forEach(input => {
        if (parseInt(input.value) > 20) {
            input.value = 20;
            input.style.borderColor = '#FF6B35';
        } else {
            input.style.borderColor = '#FFE4D0';
        }
    });

    let subtotal = 0;
    document.querySelectorAll('.qty-input').forEach(input => {
        const qty   = parseInt(input.value) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        subtotal   += qty * price;
    });

    let discount  = 0;
    const redeemCheck = document.getElementById('redeemCheck');
    const custSelect  = document.getElementById('customer_select');
    if (redeemCheck && redeemCheck.checked && custSelect.value) {
        const pts = parseInt(
            custSelect.options[custSelect.selectedIndex].dataset.points
        ) || 0;
        if (pts >= 100) {
            discount = Math.floor(pts / 100) * 10;
            discount = Math.min(discount, subtotal);
        }
    }

    const total      = subtotal - discount;
    const pointsEarn = Math.floor(total / 10);

    document.getElementById('subtotal_display').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('total_display').textContent    = '₱' + total.toFixed(2);
    document.getElementById('points_earn').textContent      = pointsEarn;

    const discountRow = document.getElementById('discount_row');
    if (discount > 0) {
        discountRow.style.display = 'flex';
        document.getElementById('discount_display').textContent = '-₱' + discount.toFixed(2);
    } else {
        discountRow.style.display = 'none';
    }
}

function updateLoyaltyInfo(select) {
    const val     = select.value;
    const newBox  = document.getElementById('new_customer_box');
    const section = document.getElementById('loyalty_section');
    const display = document.getElementById('selected_customer_display');
    const nameEl  = document.getElementById('selected_name');

    if (!val) {
        display.style.display = 'none';
        newBox.style.display  = 'none';
        section.style.display = 'none';
        updateTotal();
        return;
    }

    if (val === 'new') {
        newBox.style.display  = 'block';
        display.style.display = 'none';
        section.style.display = 'none';
        // Reset name type to full
        document.getElementById('type_full').checked = true;
        toggleNameType();
        const cb = document.getElementById('redeemCheck');
        if (cb) cb.checked = false;
        // Clear search
        document.getElementById('customer_search').value = '';
        document.querySelectorAll('#customer_select option').forEach(o => o.style.display = '');
        updateTotal();
        return;
    }

    // Existing customer selected
    newBox.style.display = 'none';
    const selectedOpt    = select.options[select.selectedIndex];
    if (nameEl && selectedOpt) {
        nameEl.textContent    = selectedOpt.textContent.trim();
        display.style.display = 'block';
    }
    // Clear search box and show all options
    document.getElementById('customer_search').value = '';
    document.querySelectorAll('#customer_select option').forEach(o => o.style.display = '');

    const pts = parseInt(selectedOpt?.dataset.points) || 0;
    if (pts >= 100 && val) {
        const disc = Math.floor(pts / 100) * 10;
        document.getElementById('loyalty_text').textContent =
            ` — ${pts} pts available = ₱${disc.toFixed(2)} discount`;
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        const cb = document.getElementById('redeemCheck');
        if (cb) cb.checked = false;
    }
    updateTotal();
}

document.getElementById('newOrderModal')
    .addEventListener('hidden.bs.modal', function() {
    document.querySelectorAll('.qty-input').forEach(i => i.value = 0);
    document.getElementById('customer_select').value = '';
    document.getElementById('customer_search').value = '';
    document.getElementById('loyalty_section').style.display = 'none';
    document.getElementById('new_customer_box').style.display = 'none';
    document.getElementById('selected_customer_display').style.display = 'none';
    document.querySelectorAll('#customer_select option').forEach(o => o.style.display = '');
    document.getElementById('type_full').checked = true;
    toggleNameType();
    updateTotal();
});

function loadOrderDetails(orderId) {
    const body  = document.getElementById('viewModalBody');
    const title = document.getElementById('viewModalTitle');
    title.textContent = 'Order #' + orderId;
    body.innerHTML    = '<div class="text-center py-3">Loading...</div>';

    fetch('get_order.php?order_id=' + orderId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<p class="text-danger">Could not load order.</p>';
                return;
            }
            const o = data.order;
            let itemRows = '';
            data.items.forEach(item => {
                itemRows += `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>₱${(item.quantity * item.unit_price).toFixed(2)}</td>
                </tr>`;
            });
            body.innerHTML = `
            <table class="table table-sm table-bordered mb-3">
                <tr><th>Customer</th><td>${o.customer_name}</td></tr>
                <tr><th>Date</th><td>${o.order_date}</td></tr>
                <tr><th>Payment</th><td>${o.payment_method}</td></tr>
                <tr><th>Status</th>
                    <td><span class="badge-${o.status}">${o.status}</span></td></tr>
            </table>
            <h6>Items Ordered</h6>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>${itemRows}</tbody>
                <tfoot>
                    <tr class="fw-bold" style="background:#FFF5EE">
                        <td colspan="3" class="text-end">TOTAL</td>
                        <td>₱${parseFloat(o.total_amount).toFixed(2)}</td>
                    </tr>
                </tfoot>
            </table>`;
        })
        .catch(() => {
            body.innerHTML = '<p class="text-danger">Error loading details.</p>';
        });
}
</script>
</body>
</html>
