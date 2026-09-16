<?php
/**
 * admin/orders.php
 *
 * Role in system: filterable view of every order - part of Specific
 * Objective 4 (order monitoring). Status can still only ever be
 * *advanced* from kitchen/update_status.php by a logged-in
 * kitchen/waiter account, which is what makes the "In charge: ... at
 * ..." line below each status pill meaningful as an accountability
 * record. The one action admin has here is the reverse: cancelling an
 * order outright (e.g. the client left), which is why it's handled as
 * its own side-state rather than another step in status_sequence().
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id > 0) {
        $admin_id = $_SESSION['user_id'];
        // NOT IN guard is defense-in-depth: a served order shouldn't be
        // cancelled after the fact, and an already-cancelled order can't
        // be re-cancelled (which would also overwrite who cancelled it).
        $stmt = $conn->prepare("UPDATE `ORDER` SET order_status = 'cancelled', last_updated_by = ?, last_updated_at = NOW() WHERE order_id = ? AND order_status NOT IN ('served', 'cancelled')");
        $stmt->bind_param('ii', $admin_id, $order_id);
        $stmt->execute();
    }
    $redirect_params = array_filter([
        'status'    => $_POST['status'] ?? '',
        'date_from' => $_POST['date_from'] ?? '',
        'date_to'   => $_POST['date_to'] ?? '',
    ]);
    header('Location: orders.php' . ($redirect_params ? '?' . http_build_query($redirect_params) : ''));
    exit;
}

$status_filter = $_GET['status'] ?? '';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';

$where = [];
$params = [];
$types = '';

$filterable_statuses = array_merge(status_sequence(), ['cancelled']);
if (in_array($status_filter, $filterable_statuses, true)) {
    $where[] = "o.order_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($date_from !== '') {
    $where[] = "DATE(o.order_time) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $where[] = "DATE(o.order_time) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$sql = "
    SELECT o.order_id, o.order_status, o.order_time, o.total_amount, o.customer_phone, t.table_number,
           o.last_updated_at, o.served_at, u.full_name AS last_updated_by_name,
           TIMESTAMPDIFF(SECOND, o.order_time, NOW()) AS seconds_since_order,
           TIMESTAMPDIFF(SECOND, o.order_time, o.served_at) AS seconds_to_serve
    FROM `ORDER` o
    JOIN CAFE_TABLE t ON t.table_id = o.table_id
    LEFT JOIN USER u ON u.user_id = o.last_updated_by
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY o.order_time DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/kitchen.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Orders</h1>

    <form method="get" class="admin-form admin-form-inline">
        <label>Status
            <select name="status">
                <option value="">All</option>
                <?php foreach ($filterable_statuses as $s): ?>
                    <option value="<?= h($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= h(status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>From<input type="date" name="date_from" value="<?= h($date_from) ?>"></label>
        <label>To<input type="date" name="date_to" value="<?= h($date_to) ?>"></label>
        <button type="submit" class="btn-primary">Filter</button>
        <a href="orders.php" class="btn-small">Clear</a>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th><th>Table</th><th>Status</th><th>Time</th><th>Elapsed</th><th>Phone</th><th>Total (RWF)</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
            <?php
                // Both figures below come from TIMESTAMPDIFF() in the query above,
                // computed entirely on the database server's own clock - this
                // avoids comparing a DB-stored timestamp against PHP's time(),
                // which is what previously made elapsed times read wrong.
                if ($o['order_status'] === 'cancelled') {
                    $elapsed_text = 'Cancelled';
                } elseif ($o['order_status'] === 'served' && $o['seconds_to_serve'] !== null) {
                    $elapsed_text = format_duration((int)$o['seconds_to_serve']) . ' to serve';
                } else {
                    $elapsed_text = format_duration((int)$o['seconds_since_order']) . ' Ago';
                }
                $is_cancellable = !in_array($o['order_status'], ['served', 'cancelled'], true);
            ?>
            <tr>
                <td><?= (int)$o['order_id'] ?></td>
                <td><?= (int)$o['table_number'] ?></td>
                <td>
                    <span class="status-pill status-<?= h($o['order_status']) ?>"><?= h(status_label($o['order_status'])) ?></span>
                    <br>
                    <small class="muted-text"><?php
                        if (!$o['last_updated_by_name']) {
                            echo '&mdash;';
                        } elseif ($o['order_status'] === 'cancelled') {
                            echo 'Cancelled by ' . h($o['last_updated_by_name']) . ' at ' . h($o['last_updated_at']);
                        } else {
                            echo 'Received by: ' . h($o['last_updated_by_name']) . ' at ' . h($o['last_updated_at']);
                        }
                    ?></small>
                </td>
                <td><?= h($o['order_time']) ?></td>
                <td><?= h($elapsed_text) ?></td>
                <td><?= h($o['customer_phone'] ?: '-') ?></td>
                <td><?= format_price($o['total_amount']) ?></td>
                <td class="admin-actions">
                    <?php if ($is_cancellable): ?>
                        <form method="post" onsubmit="return confirm('Cancel order #<?= (int)$o['order_id'] ?>? This cannot be undone.');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                            <input type="hidden" name="status" value="<?= h($status_filter) ?>">
                            <input type="hidden" name="date_from" value="<?= h($date_from) ?>">
                            <input type="hidden" name="date_to" value="<?= h($date_to) ?>">
                            <button type="submit" class="btn-small btn-danger">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</main>

</body>
</html>