<!--
    admin/nav.php

    Role in system: shared navigation bar, included by every page
    under admin/ except login.php/setup_admin.php. Also carries the
    back/forward-cache guard script below, so every admin page gets
    the post-logout Back-button protection from a single include.
-->
<nav class="admin-nav">
    <span class="admin-nav-brand">Gorilla Cafe Admin</span>
    <a href="dashboard.php">Dashboard</a>
    <a href="categories.php">Categories</a>
    <a href="items.php">Menu Items</a>
    <a href="orders.php">Orders</a>
    <a href="reports.php">Reports</a>
    <a href="staff.php">Staff</a>
    <a href="tables.php">Tables</a>
    <a href="logout.php" class="admin-nav-logout">Log Out</a>
</nav>
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