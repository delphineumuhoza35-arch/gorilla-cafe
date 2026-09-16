<?php
/**
 * customer/status_check.php
 *
 * Role in system: lightweight JSON polling endpoint used only by
 * order_status.php's own JavaScript (assets/js/order_status.js),
 * which calls this every 5 seconds. Returns just the current
 * order_status so the customer's status badge can update without a
 * full page reload - the polling half of Specific Objective 3.
 *
 * Responds JSON: { success: true, order_status } or { success: false }
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$stmt = $conn->prepare("SELECT order_status FROM `ORDER` WHERE order_id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode(['success' => true, 'order_status' => $row['order_status']]);
