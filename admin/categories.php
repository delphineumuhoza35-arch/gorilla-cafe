<?php
/**
 * admin/categories.php
 *
 * Role in system: admin CRUD screen for menu categories - part of
 * Specific Objective 4 (menu/category management). Single-page
 * pattern: one POST handler branching on $_POST['action']
 * (add/edit/delete), reused with the same shape across items.php,
 * tables.php and staff.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO CATEGORY (category_name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare("UPDATE CATEGORY SET category_name = ? WHERE category_id = ?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        // A category with items still in it can't be deleted (FK RESTRICT) -
        // catch that and show a friendly message instead of a raw SQL error.
        try {
            $stmt = $conn->prepare("DELETE FROM CATEGORY WHERE category_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $error = 'Cannot delete this category while it still has menu items in it. Move or delete those items first.';
        }
    }

    // Redirect after POST (except when we need to show an error from this same request).
    if ($error === '') {
        header('Location: categories.php');
        exit;
    }
}

$categories = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Categories</h1>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr><th>Category</th></tr>
        </thead>
        <tbody>
        <?php while ($cat = $categories->fetch_assoc()): ?>
            <tr>
                <td colspan="2">
                    <form method="post" class="admin-row-form">
                        <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                        <input type="text" name="category_name" value="<?= h($cat['category_name']) ?>">
                        <button type="submit" name="action" value="edit" class="btn-small">Save</button>
                        <button type="submit" name="action" value="delete" class="btn-small btn-danger"
                            onclick="return confirm('Delete this category?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Add Category</h2>
    <form method="post" class="admin-form admin-form-inline">
        <input type="hidden" name="action" value="add">
        <input type="text" name="category_name" placeholder="Category name" required>
        <button type="submit" class="btn-primary">Add</button>
    </form>
</main>

</body>
</html>