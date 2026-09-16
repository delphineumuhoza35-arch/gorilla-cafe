<?php
/**
 * admin/tables.php
 *
 * Role in system: add or remove café tables - the practical
 * companion to Specific Objective 1 (QR-code menu access), since a
 * table has to exist in CAFE_TABLE before qr/generate.php can print
 * a code for it. Each new table's qr_code_value is built from the
 * current request's own host, so the generated link is automatically
 * correct whether this page is being used on localhost or the live
 * production domain - no manual URL editing required.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $table_number = (int)($_POST['table_number'] ?? 0);

        if ($table_number > 0) {
            // Derive the QR URL from the current request so it's correct
            // whether this admin panel is running on localhost or a live
            // domain - no hardcoded host needed.
            $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
            $scheme = $https ? 'https' : 'http';
            // admin/tables.php -> project root is two directories up from SCRIPT_NAME.
            $base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
            $qr_value  = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/customer/menu.php?table=' . $table_number;

            try {
                $stmt = $conn->prepare("INSERT INTO CAFE_TABLE (table_number, qr_code_value) VALUES (?, ?)");
                $stmt->bind_param('is', $table_number, $qr_value);
                $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                $error = 'A table with that number already exists.';
            }
        } else {
            $error = 'Enter a valid table number.';
        }
    }

    if ($action === 'delete') {
        $table_id = (int)($_POST['table_id'] ?? 0);
        try {
            $stmt = $conn->prepare("DELETE FROM CAFE_TABLE WHERE table_id = ?");
            $stmt->bind_param('i', $table_id);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // FK RESTRICT on ORDER.table_id - table has order history.
            $error = 'Cannot delete this table while it still has orders linked to it.';
        }
    }

    if ($error === '') {
        header('Location: tables.php');
        exit;
    }
}

$tables = $conn->query("SELECT table_id, table_number, qr_code_value FROM CAFE_TABLE ORDER BY table_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tables - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Tables</h1>
    <p class="section-note">Add a table here, then print its QR code from <a href="../qr/generate.php">qr/generate.php</a>.</p>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr><th>Table</th><th>QR Link</th><th></th></tr>
        </thead>
        <tbody>
        <?php while ($t = $tables->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$t['table_number'] ?></td>
                <td class="muted-text"><?= h($t['qr_code_value']) ?></td>
                <td class="admin-actions">
                    <form method="post" class="admin-row-form">
                        <input type="hidden" name="table_id" value="<?= (int)$t['table_id'] ?>">
                        <button type="submit" name="action" value="delete" class="btn-small btn-danger"
                            onclick="return confirm('Delete this table?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Add Table</h2>
    <form method="post" class="admin-form admin-form-inline">
        <input type="hidden" name="action" value="add">
        <input type="number" min="1" name="table_number" placeholder="Table number" required>
        <button type="submit" class="btn-primary">Add</button>
    </form>
</main>

</body>
</html>
