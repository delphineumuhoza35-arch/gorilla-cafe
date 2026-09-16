<?php
/**
 * customer/submit_order.php
 *
 * Role in system: JSON endpoint that converts a customer's browser
 * cart into a permanent order (Specific Objective 2). Called by
 * assets/js/cart.js when the customer taps "Place Order".
 *
 * Security-critical behaviour: every item's price and availability
 * is re-read from MENU_ITEM here - the price/quantity values in the
 * request body are never trusted for the final total, only used to
 * identify which items and how many. This is what prevents a
 * customer from tampering with prices via the browser.
 *
 * Expects JSON body: { table_id, customer_phone (optional), items: [{item_id, quantity}, ...] }
 * Responds JSON: { success, order_id } or { success: false, message }
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$table_id = isset($input['table_id']) ? (int)$input['table_id'] : 0;
$phone    = isset($input['customer_phone']) ? trim($input['customer_phone']) : null;
$items    = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

if ($table_id <= 0 || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order data.']);
    exit;
}

// Confirm the table exists.
$stmt = $conn->prepare("SELECT table_id FROM CAFE_TABLE WHERE table_id = ?");
$stmt->bind_param('i', $table_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Unknown table.']);
    exit;
}
$stmt->close();

// Never trust prices sent from the browser - look up the current
// price and availability for every item_id server-side.
$valid_items = []; // item_id => [name, price, quantity]
foreach ($items as $line) {
    $item_id  = isset($line['item_id']) ? (int)$line['item_id'] : 0;
    $quantity = isset($line['quantity']) ? (int)$line['quantity'] : 0;
    if ($item_id <= 0 || $quantity <= 0) continue;

    $stmt = $conn->prepare("SELECT item_id, price FROM MENU_ITEM WHERE item_id = ? AND availability_status = 'available'");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $valid_items[] = ['item_id' => $item_id, 'price' => (float)$row['price'], 'quantity' => $quantity];
    }
}

if (empty($valid_items)) {
    echo json_encode(['success' => false, 'message' => 'None of the items in your cart are currently available.']);
    exit;
}

$total = 0;
foreach ($valid_items as $vi) {
    $total += $vi['price'] * $vi['quantity'];
}

// Insert order + line items as one transaction so we never save a
// half-written order if something fails partway through.
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO `ORDER` (table_id, customer_phone, order_status, total_amount) VALUES (?, ?, 'received', ?)");
    $stmt->bind_param('isd', $table_id, $phone, $total);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO ORDER_ITEM (order_id, item_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
    foreach ($valid_items as $vi) {
        $subtotal = $vi['price'] * $vi['quantity'];
        $stmt->bind_param('iiid', $order_id, $vi['item_id'], $vi['quantity'], $subtotal);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Could not save your order. Please try again.']);
}
