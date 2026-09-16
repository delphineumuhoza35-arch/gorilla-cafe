<?php
/**
 * kitchen/update_status.php
 *
 * Role in system: the single place an order's status is ever changed.
 * Called by assets/js/kitchen.js when staff tap the "advance" button
 * on an order card. Enforces two invariants central to Specific
 * Objective 3: (1) status may only move forward one step at a time
 * through status_sequence() - no skipping or reversing - and (2)
 * every change is attributed to the logged-in staff member
 * (last_updated_by) and timestamped (last_updated_at), which is what
 * lets the admin Orders page show who handled each order.
 *
 * Expects JSON body: { order_id, new_status }
 * Responds JSON: { success, message? }
 */
require_once __DIR__ . '/../includes/auth.php';
require_kitchen(true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$order_id   = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$new_status = isset($input['new_status']) ? $input['new_status'] : '';

// Only these four values are ever valid - reject anything else outright.
$valid_statuses = status_sequence();
if ($order_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare("SELECT order_status FROM `ORDER` WHERE order_id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

// Statuses only ever move forward one step at a time (received -> preparing
// -> ready -> served) - reject skips or backward moves so a stray/replayed
// request can't put an order in an inconsistent state.
$current_index = array_search($order['order_status'], $valid_statuses, true);
$new_index     = array_search($new_status, $valid_statuses, true);
if ($new_index !== $current_index + 1) {
    echo json_encode(['success' => false, 'message' => 'Orders can only move to the next status in sequence.']);
    exit;
}

$staff_id = $_SESSION['user_id'];
if ($new_status === 'served') {
    // Also record the dedicated served_at timestamp, so admin can show
    // received-to-served duration precisely (last_updated_at alone isn't
    // enough once cancellation exists as a separate side-state).
    $stmt = $conn->prepare("UPDATE `ORDER` SET order_status = ?, last_updated_by = ?, last_updated_at = NOW(), served_at = NOW() WHERE order_id = ?");
} else {
    $stmt = $conn->prepare("UPDATE `ORDER` SET order_status = ?, last_updated_by = ?, last_updated_at = NOW() WHERE order_id = ?");
}
$stmt->bind_param('sii', $new_status, $staff_id, $order_id);
$stmt->execute();

echo json_encode(['success' => true]);
