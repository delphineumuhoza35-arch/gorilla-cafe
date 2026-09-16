<?php
/**
 * admin/dashboard.php
 *
 * Role in system: landing page after admin login. Purely a
 * read-only summary (today's order count and revenue) - all actual
 * management happens on the other admin/*.php pages linked from
 * admin/nav.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$today = $conn->query("
    SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue
    FROM `ORDER`
    WHERE DATE(order_time) = CURDATE() AND order_status != 'cancelled'
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gorilla Cafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Welcome, <?= h($_SESSION['full_name']) ?></h1>

    <div class="stat-cards">
        <div class="stat-card">
            <span class="stat-value"><?= (int)$today['order_count'] ?></span>
            <span class="stat-label">Orders today</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= format_price($today['revenue']) ?></span>
            <span class="stat-label">Revenue today</span>
        </div>
    </div>

    <p>Use the menu above to manage categories and items, review orders, or view sales reports.</p>
</main>

</body>
</html>