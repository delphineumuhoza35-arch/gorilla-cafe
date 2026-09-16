<?php
/**
 * customer/order_status.php
 *
 * Role in system: the confirmation/tracking page a customer is sent
 * to immediately after placing an order (?order_id=N). Renders the
 * order's current status and line items once on load, then hands off
 * to assets/js/order_status.js, which polls status_check.php every
 * 5 seconds so the badge updates on its own as kitchen staff advance
 * the order - this is the customer-facing half of Specific
 * Objective 3 (order transmission/tracking).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$stmt = $conn->prepare("
    SELECT o.order_id, o.order_status, o.order_time, o.total_amount, t.table_number
    FROM `ORDER` o
    JOIN CAFE_TABLE t ON t.table_id = o.table_id
    WHERE o.order_id = ?
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die('Order not found.');
}

$stmt = $conn->prepare("
    SELECT mi.item_name, oi.quantity, oi.subtotal
    FROM ORDER_ITEM oi
    JOIN MENU_ITEM mi ON mi.item_id = oi.item_id
    WHERE oi.order_id = ?
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$lines = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= (int)$order['order_id'] ?> - Gorilla Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
    <h1>Gorilla Café</h1>
    <p class="subtitle">Table <?= h($order['table_number']) ?> · Order #<?= (int)$order['order_id'] ?></p>
</header>

<main class="status-wrap">
    <div class="status-badge-wrap">
        <span id="statusBadge" class="status-badge status-<?= h($order['order_status']) ?>">
            <?= h(status_label($order['order_status'])) ?>
        </span>
    </div>

    <div class="status-steps" id="statusSteps">
        <?php foreach (status_sequence() as $step): ?>
            <div class="step" data-step="<?= h($step) ?>"><?= h(status_label($step)) ?></div>
        <?php endforeach; ?>
    </div>

    <section class="order-lines">
        <?php while ($line = $lines->fetch_assoc()): ?>
            <div class="order-line">
                <span><?= h($line['quantity']) ?> × <?= h($line['item_name']) ?></span>
                <span><?= format_price($line['subtotal']) ?></span>
            </div>
        <?php endwhile; ?>
        <div class="order-line order-line-total">
            <span>Total</span>
            <span><?= format_price($order['total_amount']) ?></span>
        </div>
    </section>

    <p class="hint-text">This page updates automatically - no need to refresh.</p>
      <p class="hint-text"> OUR MOMO PAY CODE:182*8*1*12026#</p>
</main>

<script>
    const ORDER_ID = <?= (int)$order['order_id'] ?>;
</script>
<script src="../assets/js/order_status.js"></script>
</body>
</html>
