<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$conn     = getOLTP();
$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false]);
    exit;
}

$orderRes = $conn->query("
    SELECT o.*, c.name AS customer_name, p.method AS payment_method
    FROM orders o
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN payment p ON o.order_id = p.order_id
    WHERE o.order_id = $order_id
");

if ($orderRes->num_rows === 0) {
    echo json_encode(['success' => false]);
    exit;
}

$order = $orderRes->fetch_assoc();

$itemsRes = $conn->query("
    SELECT oi.quantity, oi.unit_price, m.name
    FROM order_item oi
    JOIN menu_item m ON oi.item_id = m.item_id
    WHERE oi.order_id = $order_id
");

$items = [];
while ($r = $itemsRes->fetch_assoc()) $items[] = $r;

echo json_encode([
    'success' => true,
    'order'   => $order,
    'items'   => $items
]);