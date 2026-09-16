<?php
/**
 * admin/setup_admin.php
 *
 * Role in system: one-time bootstrap that creates the very first
 * admin account, since an admin session is required to reach every
 * other admin/*.php page (a chicken-and-egg problem this page
 * exists solely to solve). Permanently self-locks the moment one
 * admin account exists - subsequent kitchen/waiter accounts are
 * created through admin/staff.php instead, which requires being
 * logged in as admin already.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Safety gate: once at least one admin account exists, this page
// refuses to create more - use the (future) admin user management
// screen instead, not this bootstrap script.
$existing = $conn->query("SELECT COUNT(*) AS c FROM USER WHERE role = 'admin'")->fetch_assoc();
$admin_exists = $existing['c'] > 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$admin_exists) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($full_name === '' || $phone === '' || $password === '') {
        $error = 'Full name, phone number and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO USER (full_name, role, phone, password_hash) VALUES (?, 'admin', ?, ?)");
        $stmt->bind_param('sss', $full_name, $phone, $hash);
        $stmt->execute();
        $success = 'Admin account created. You can now log in.';
        $admin_exists = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Gorilla Cafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="auth-wrap">
    <h1>Create Admin Account</h1>

    <?php if ($admin_exists && !$success): ?>
        <p class="notice">An admin account already exists. This setup page is now locked.</p>
        <p><a href="login.php">Go to login</a></p>
    <?php elseif ($success): ?>
        <p class="success-text"><?= h($success) ?></p>
        <p><a href="login.php">Go to login</a></p>
    <?php else: ?>
        <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
        <form method="post" class="admin-form">
            <label>Full name<input type="text" name="full_name" required></label>
            <label>Phone number<input type="text" name="phone" required></label>
            <label>Password (min 6 characters)<input type="password" name="password" required></label>
            <button type="submit" class="btn-primary">Create Admin Account</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>