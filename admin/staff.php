<?php

/**
 * admin/staff.php
 *
 * Role in system: create and manage kitchen/waiter accounts - not
 * one of the four literal specific objectives, but implemented
 * because Chapter Two's conceptual framework already anticipated
 * "Manages user accounts" under Manager & Admin, and because
 * kitchen/login.php needs somewhere for those accounts to come from.
 * Deliberately cannot create or touch admin accounts (the role
 * dropdown only offers kitchen/waiter, and reset_secret's UPDATE is
 * scoped with "AND role IN ('kitchen','waiter')" as defense-in-depth).
 * Account deletion is intentionally out of scope - only creation and
 * secret reset.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = $_POST['role'] ?? '';
        $password  = $_POST['password'] ?? '';

        if ($full_name === '' || !in_array($role, ['kitchen', 'waiter'], true) || strlen($password) < 6) {
            $error = 'Please provide a name, a valid role, and a secret of at least 6 characters.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO USER (full_name, role, password_hash) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $full_name, $role, $hash);
                $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                // Duplicate hit on uq_user_full_name - staff names must be unique
                // because kitchen login looks accounts up by name.
                $error = 'A staff member with that name already exists. Choose a different name.';
            }
        }
    }

    if ($action === 'delete_staff') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            $error = 'Invalid staff account.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "DELETE FROM USER
                     WHERE user_id = ?
                     AND role IN ('kitchen', 'waiter')"
                );

                $stmt->bind_param('i', $user_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    // deletion succeeded
                } else {
                    $error = 'Staff account not found.';
                }

                $stmt->close();

            } catch (mysqli_sql_exception $e) {
                $error = 'Unable to delete staff account. It may be linked to existing orders.';
            }
        }
    }

    if ($action === 'reset_secret') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($user_id > 0 && strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // role clause is defense-in-depth so this action can never touch the admin row.
            $stmt = $conn->prepare("UPDATE USER SET password_hash = ? WHERE user_id = ? AND role IN ('kitchen','waiter')");
            $stmt->bind_param('si', $hash, $user_id);
            $stmt->execute();
        } else {
            $error = 'Please provide a new secret of at least 6 characters.';
        }
    }

    // Redirect after POST (except when we need to show an error from this same request).
    if ($error === '') {
        header('Location: staff.php');
        exit;
    }
}

$staff = $conn->query("SELECT user_id, full_name, role FROM USER WHERE role IN ('kitchen','waiter') ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Kitchen &amp; Waiter Staff</h1>
    <p class="section-note">Staff use these names and password to log in at <code>kitchen/login.php</code>. Forgotten passwords can be reset below - staff cannot reset their own.</p>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr><th>Name</th><th>Role</th><th>New Password</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($staff->num_rows === 0): ?>
            <tr><td colspan="4">No kitchen or waiter accounts yet - add one below.</td></tr>
        <?php endif; ?>
        <?php while ($member = $staff->fetch_assoc()): ?>
            <tr>
                <form method="post" class="admin-row-form">
                    <input type="hidden" name="user_id" value="<?= (int)$member['user_id'] ?>">
                    <td><?= h($member['full_name']) ?></td>
                    <td><?= h(ucfirst($member['role'])) ?></td>
                    <td><input type="password" name="password" placeholder="Min 6 characters" minlength="6"></td>
                    <td class="admin-actions">
    <button type="submit"
            name="action"
            value="reset_secret"
            class="btn-small">
        Reset Password
    </button>
,
    <button type="submit"
            name="action"
            value="delete_staff"
            class="btn-small"
            onclick="return confirm('Are you sure you want to delete this staff account?');">
        Delete Staff
    </button>
</td>
                </form>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Add Staff Account</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="action" value="add">
        <label>Name<input type="text" name="full_name" required></label>
        <label>Role
            <select name="role" required>
                <option value="kitchen">Kitchen</option>
                <option value="waiter">Waiter</option>
            </select>
        </label>
        <label>Initial password (min 6 characters)<input type="password" name="password" required minlength="6"></label>
        <button type="submit" class="btn-primary">Add Staff Account</button>
    </form>
</main>

</body>
</html>
