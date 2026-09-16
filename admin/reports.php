<?php
/**
 * admin/reports.php
 *
 * Role in system: sales reporting - the "generating sales and order
 * reports" half of Specific Objective 4. Computes total order count,
 * total revenue, and the ten top-selling items by quantity, all
 * aggregated directly from `ORDER`/ORDER_ITEM over an
 * administrator-chosen date range (defaulting to the last 30 days).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Default to the last 30 days if no range was chosen yet.
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to'] ?? date('Y-m-d');
$format    = $_GET['format'] ?? null;

// Fetch Summary Totals
$stmt = $conn->prepare("
    SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue
    FROM `ORDER`
    WHERE DATE(order_time) BETWEEN ? AND ? AND order_status != 'cancelled'
");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

// Fetch Top Selling Items
$stmt = $conn->prepare("
    SELECT mi.item_name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_revenue
    FROM ORDER_ITEM oi
    JOIN `ORDER` o ON o.order_id = oi.order_id
    JOIN MENU_ITEM mi ON mi.item_id = oi.item_id
    WHERE DATE(o.order_time) BETWEEN ? AND ? AND o.order_status != 'cancelled'
    GROUP BY oi.item_id, mi.item_name
    ORDER BY total_qty DESC
    LIMIT 10
");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$top_items_result = $stmt->get_result();
$top_items = $top_items_result->fetch_all(MYSQLI_ASSOC);

// Handle Excel CSV Download
if ($format === 'excel' || $format === 'csv') {
    $filename = "sales_report_{$date_from}_to_{$date_to}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sales Report', "From: $date_from", "To: $date_to"]);
    fputcsv($output, []);
    fputcsv($output, ['Summary Metrics']);
    fputcsv($output, ['Total Orders', 'Total Revenue (RWF)']);
    fputcsv($output, [$totals['order_count'], $totals['revenue']]);
    fputcsv($output, []);
    fputcsv($output, ['Top-Selling Items']);
    fputcsv($output, ['Item Name', 'Quantity Sold', 'Revenue (RWF)']);
    
    foreach ($top_items as $item) {
        fputcsv($output, [$item['item_name'], $item['total_qty'], $item['total_revenue']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .report-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .report-actions label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .export-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.4);
            z-index: 1200;
        }

        .export-modal[aria-hidden="false"] {
            display: flex;
        }

        .export-modal-content {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }

        .export-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .export-actions .btn {
            padding: 8px 14px;
            cursor: pointer;
        }

        .btn-secondary {
            background: #eee;
            border: 1px solid #ccc;
        }

        @media print {
            .admin-form, .btn-primary, .report-actions, nav, .export-modal { display: none !important; }
            .print-only { display: block !important; margin-bottom: 20px; font-weight: bold; }
            body { background: #fff; color: #000; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Sales Report</h1>

    <form method="get" id="filterForm" class="report-actions">
        <label>From <input type="date" name="date_from" value="<?= h($date_from) ?>" onchange="this.form.submit()"></label>
        <label>To <input type="date" name="date_to" value="<?= h($date_to) ?>" onchange="this.form.submit()"></label>
        <button type="button" class="btn-primary" id="openModalBtn">Print report</button>
    </form>

    <div class="print-only">Report Range: <?= h($date_from) ?> through <?= h($date_to) ?></div>

    <div class="stat-cards">
        <div class="stat-card">
            <span class="stat-value"><?= (int)$totals['order_count'] ?></span>
            <span class="stat-label">Orders in range</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= format_price($totals['revenue']) ?></span>
            <span class="stat-label">Total revenue</span>
        </div>
    </div>

    <h2>Top-Selling Items</h2>
    <table class="admin-table">
        <thead>
            <tr><th>Item</th><th>Quantity Sold</th><th>Revenue (RWF)</th></tr>
        </thead>
        <tbody>
        <?php if (count($top_items) === 0): ?>
            <tr><td colspan="3">No sales in this date range.</td></tr>
        <?php else: ?>
            <?php foreach ($top_items as $row): ?>
                <tr>
                    <td><?= h($row['item_name']) ?></td>
                    <td><?= (int)$row['total_qty'] ?></td>
                    <td><?= format_price($row['total_revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</main>

<!-- Format Choice Modal -->
<div id="exportModal" class="export-modal" aria-hidden="true">
    <div class="export-modal-content" role="dialog" aria-modal="true" aria-labelledby="exportTitle">
        <h3 id="exportTitle">Choose Export Format</h3>
        <p>Select how you would like to output the report for <strong><?= h($date_from) ?></strong> through <strong><?= h($date_to) ?></strong>:</p>
        <div class="export-actions">
            <button type="button" class="btn btn-primary" onclick="processExport('pdf')">PDF (Print)</button>
            <button type="button" class="btn btn-primary" onclick="processExport('excel')">Excel (.csv)</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<form id="exportForm" method="get" style="display:none;">
    <input type="hidden" name="date_from" value="<?= h($date_from) ?>">
    <input type="hidden" name="date_to" value="<?= h($date_to) ?>">
    <input type="hidden" name="format" value="excel">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('exportModal');
    const openBtn = document.getElementById('openModalBtn');

    function openModal() {
        if (modal) modal.setAttribute('aria-hidden', 'false');
    }

    window.closeModal = function() {
        if (modal) modal.setAttribute('aria-hidden', 'true');
    };

    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }

    window.processExport = function(type) {
        closeModal();
        if (type === 'pdf') {
            window.print();
        } else if (type === 'excel') {
            const fromInput = document.querySelector('input[name="date_from"]');
            const toInput = document.querySelector('input[name="date_to"]');
            const exportForm = document.getElementById('exportForm');
            
            if (fromInput) exportForm.querySelector('input[name="date_from"]').value = fromInput.value;
            if (toInput) exportForm.querySelector('input[name="date_to"]').value = toInput.value;
            
            exportForm.submit();
        }
    };

    // Keyboard and backdrop dismissal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
});
</script>

</body>
</html>