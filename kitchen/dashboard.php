<?php
/**
 * kitchen/dashboard.php
 *
 * Role in system: the shared, login-protected screen kitchen/waiter
 * staff keep open on a tablet or monitor. Renders an empty shell -
 * all order data is fetched and re-rendered client-side by
 * assets/js/kitchen.js, which polls orders_feed.php every 5 seconds
 * and posts advances to update_status.php. This page is the
 * kitchen-facing half of Specific Objective 3.
 */
require_once __DIR__ . '/../includes/auth.php';
require_kitchen();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Dashboard - Gorilla Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/kitchen.css">
</head>
<body class="kitchen-body">

<header class="site-header">
    <h1>Gorilla Café - Kitchen / Waiter</h1>
    <p class="subtitle">
        Orders update automatically every few seconds &middot;
        Logged in as <?= h($_SESSION['full_name']) ?> &middot;
        <a href="logout.php">Log Out</a>
    </p>
</header>

<div class="kitchen-toolbar">
    <label>
        <input type="checkbox" id="showServed">
        Show served orders too
    </label>
</div>

<main id="ordersWrap" class="orders-wrap">
    <p class="loading-text">Loading orders...</p>
</main>

<script src="../assets/js/kitchen.js"></script>
<script>
    // If this page was restored from the browser's back/forward cache
    // (e.g. Back after Log Out), force a fresh request so an expired
    // session actually redirects to login instead of showing a stale copy.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            location.reload();
        }
    });
</script>
</body>
</html>
