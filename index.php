<?php
/**
 * index.php
 *
 * Role in system: root landing page, staff entry points only.
 * Customers never land here - they always arrive via a table's QR
 * code straight at customer/menu.php?table=N, so no customer link is
 * offered below.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gorilla Café</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing-page">

<header class="site-header">
    <h1>Gorilla Café</h1>
    <p class="subtitle">by Food &amp; Stuff - Staff Access</p>
</header>

<main class="landing-wrap">
    <div class="landing-links">
        <a class="landing-link" href="kitchen/dashboard.php">
            <strong>Kitchen Dashboard</strong>
            <span>Live order queue - keep this open on a tablet or screen in the kitchen.</span>
        </a>
        <a class="landing-link" href="admin/login.php">
            <strong>Admin Login</strong>
            <span>Manage categories, items, orders and sales reports.</span>
        </a>
    </div>

    <p class="landing-note">
        Looking for the customer menu? It's only reachable by scanning a table's
        QR code - print or reprint them from <a href="qr/generate.php">qr/generate.php</a>.
    </p>
</main>
</body>
</html>
