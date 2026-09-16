<?php
/**
 * kitchen/orders_feed.php
 *
 * Role in system: JSON endpoint polled every 5 seconds by
 * assets/js/kitchen.js to keep the kitchen dashboard's order queue
 * current - the core mechanism behind Specific Objective 3 (order
 * transmission to kitchen/service staff). Requires an active
 * kitchen/waiter session.
 *
 * Responds JSON: { success: true, orders: [{ order_id, table_number,
 * order_status, order_time, total_amount, customer_phone, items }] }
 */
require_once __DIR__ . '/../includes/auth.php';
require_kitchen(true);
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// By default only show orders still in progress (not yet served).
// ?show_served=1 includes today's served orders too, for staff who
// want to double-check something that already went out.
$show_served = isset($_GET['show_served']) && $_GET['show_served'] === '1';

$sql = "
    SELECT o.order_id, o.order_status, o.order_time, o.total_amount, o.customer_phone,
           t.table_number
    FROM `ORDER` o
    JOIN CAFE_TABLE t ON t.table_id = o.table_id
";
$sql .= $show_served
    ? " WHERE DATE(o.order_time) = CURDATE()"
    : " WHERE o.order_status NOT IN ('served', 'cancelled')";
$sql .= " ORDER BY o.order_time ASC";

$orders_result = $conn->query($sql);

$orders = [];
while ($order = $orders_result->fetch_assoc()) {
    $order_id = (int)$order['order_id'];

    $stmt = $conn->prepare("
        SELECT mi.item_name, oi.quantity
        FROM ORDER_ITEM oi
        JOIN MENU_ITEM mi ON mi.item_id = oi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = ['name' => $item['item_name'], 'quantity' => (int)$item['quantity']];
    }
    $stmt->close();

    $orders[] = [
        'order_id'       => $order_id,
        'table_number'   => (int)$order['table_number'],
        'order_status'   => $order['order_status'],
        'order_time'     => $order['order_time'],
        'total_amount'   => (float)$order['total_amount'],
        'customer_phone' => $order['customer_phone'],
        'items'          => $items,
    ];
}

echo json_encode(['success' => true, 'orders' => $orders]);
