<?php
/**
 * admin/login.php
 *
 * Single-page handler for Admin Authentication, Password Recovery, and Password Resets.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

// Already logged in? Redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Determine active view mode: 'login', 'forgot', or 'reset'
$action = $_GET['action'] ?? 'login';

$error = '';
$success = '';
$reset_link = '';

// ==========================================
// 1. HANDLE LOGIN SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, full_name, role, password_hash FROM USER WHERE phone = ? AND role = 'admin'");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Incorrect phone number or password.';
    }
}

// ==========================================
// 2. HANDLE FORGOT PASSWORD REQUEST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'forgot') {
    $phone = trim($_POST['phone'] ?? '');

    $stmt = $conn->prepare("SELECT user_id FROM USER WHERE phone = ? AND role = 'admin'");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $raw_token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $raw_token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update = $conn->prepare("UPDATE USER SET reset_token = ?, reset_expires_at = ? WHERE user_id = ?");
        $update->bind_param('ssi', $hashed_token, $expires_at, $user['user_id']);
        $update->execute();

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['PHP_SELF'];
        $reset_link = "$protocol://$host$script?action=reset&token=" . $raw_token;

        $success = 'Reset token generated successfully.';
    } else {
        $success = 'If an account exists with that phone number, a reset link has been generated.';
    }
}

// ==========================================
// 3. HANDLE NEW PASSWORD SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset') {
    $token            = $_GET['token'] ?? '';
    $new_password     = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $hashed_token = hash('sha256', $token);

    $stmt = $conn->prepare("SELECT user_id FROM USER WHERE reset_token = ? AND reset_expires_at > NOW() AND role = 'admin'");
    $stmt->bind_param('s', $hashed_token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = 'Invalid or expired password reset token.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE USER SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE user_id = ?");
        $update->bind_param('si', $new_hash, $user['user_id']);
        $update->execute();

        $success = 'Password updated successfully! You can now log in.';
        $action = 'login'; // Redirect view back to login form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Gorilla Cafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="auth-wrap">
    <h1>Gorilla Cafe Admin</h1>

    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success-text"><?= h($success) ?></p><?php endif; ?>

    <!-- VIEW 1: LOGIN FORM -->
    <?php if ($action === 'login'): ?>
        <form method="post" action="login.php?action=login" class="admin-form">
            <label>Phone number<input type="text" name="phone" required autofocus></label>
            <label>Password<input type="password" name="password" required></label>
            <button type="submit" class="btn-primary">Log In</button>
            <p style="margin-top: 15px; text-align: center;">
                <a href="login.php?action=forgot">Forgot Password?</a>
            </p>
        </form>

    <!-- VIEW 2: FORGOT PASSWORD REQUEST -->
    <?php elseif ($action === 'forgot'): ?>
        <?php if ($reset_link): ?>
            <div class="info-box" style="margin-bottom: 20px; word-break: break-all; background:#f4f4f4; padding:10px;">
                <p><strong>Generated Link:</strong></p>
                <p><a href="<?= h($reset_link) ?>"><?= h($reset_link) ?></a></p>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php?action=forgot" class="admin-form">
            <label>Enter phone number<input type="text" name="phone" required autofocus></label>
            <button type="submit" class="btn-primary">Generate Reset Link</button>
            <p style="margin-top: 15px; text-align: center;">
                <a href="login.php">Back to Login</a>
            </p>
        </form>

    <!-- VIEW 3: RESET PASSWORD FORM -->
    <?php elseif ($action === 'reset'): ?>
        <form method="post" action="login.php?action=reset&token=<?= h($_GET['token'] ?? '') ?>" class="admin-form">
            <label>New Password<input type="password" name="password" required autofocus></label>
            <label>Confirm Password<input type="password" name="confirm_password" required></label>
            <button type="submit" class="btn-primary">Update Password</button>
            <p style="margin-top: 15px; text-align: center;">
                <a href="login.php">Cancel</a>
            </p>
        </form>
    <?php endif; ?>

</div>
</body>
</html>