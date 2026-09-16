<?php
/**
 * kitchen/login.php
 *
 * Role in system: authentication entry point for kitchen/waiter
 * staff. Deliberately public (no auth.php gate on this page itself,
 * since a not-yet-logged-in user must be able to reach it). Accepts
 * a staff member's name and secret, checks it against USER (roles
 * 'kitchen' or 'waiter' only), and starts the session that
 * kitchen/dashboard.php, orders_feed.php and update_status.php all
 * require via includes/auth.php's require_kitchen().
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

// Already logged in? skip straight to the dashboard.
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['kitchen', 'waiter'], true)) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, full_name, role, password_hash FROM USER WHERE full_name = ? AND role IN ('kitchen','waiter')");
    $stmt->bind_param('s', $full_name);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Incorrect name or password. Please contact the administrator.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Login - Gorilla Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="auth-wrap">
    <h1>Gorilla Café Kitchen</h1>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
    <form method="post" class="admin-form">
        <label>Username<input type="text" name="full_name" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <button type="submit" class="btn-primary">Log In</button>
    </form>
</div>
</body>
</html>
