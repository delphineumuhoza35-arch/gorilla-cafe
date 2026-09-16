<?php
/**
 * admin/items.php
 *
 * Role in system: admin CRUD screen for menu items - the core of
 * Specific Objective 4 (managing menu items, prices and categories).
 * The 'availability' checkbox is a soft-hide: setting an item to
 * 'unavailable' removes it from customer/menu.php's listing without
 * deleting the row, which is what keeps past ORDER_ITEM references
 * to it intact (a hard delete is blocked below with a friendly
 * message if the item has ever been ordered).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $item_id     = (int)($_POST['item_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name        = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        // Checkbox: present in POST only when checked.
        $availability = isset($_POST['availability']) ? 'available' : 'unavailable';

        if ($category_id > 0 && $name !== '' && $price >= 0) {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO MENU_ITEM (category_id, item_name, description, price, availability_status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('issds', $category_id, $name, $description, $price, $availability);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("UPDATE MENU_ITEM SET category_id = ?, item_name = ?, description = ?, price = ?, availability_status = ? WHERE item_id = ?");
                $stmt->bind_param('issdsi', $category_id, $name, $description, $price, $availability, $item_id);
                $stmt->execute();
            }
        } else {
            $error = 'Please provide a category, name and a valid price.';
        }
    }

    if ($action === 'delete') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        try {
            $stmt = $conn->prepare("DELETE FROM MENU_ITEM WHERE item_id = ?");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // FK RESTRICT on ORDER_ITEM - item was already ordered by someone.
            $error = 'Cannot delete this item because it appears in past orders. Mark it unavailable instead.';
        }
    }

    if ($error === '') {
        header('Location: items.php');
        exit;
    }
}

$categories = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
$category_list = [];
while ($c = $categories->fetch_assoc()) { $category_list[] = $c; }

$items = $conn->query("
    SELECT mi.item_id, mi.item_name, mi.description, mi.price, mi.availability_status, mi.category_id, c.category_name
    FROM MENU_ITEM mi
    JOIN CATEGORY c ON c.category_id = mi.category_id
    ORDER BY c.category_name, mi.item_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Items - Gorilla Cafe Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/nav.php'; ?>

<main class="admin-main">
    <h1>Menu Items</h1>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>

    <?php if (empty($category_list)): ?>
        <p class="notice">Add a category first before adding menu items.</p>
    <?php else: ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Item</th><th>Category</th><th>Price (RWF)</th><th>Available</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <form method="post" class="admin-row-form">
                    <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                    <td>
                        <input type="text" name="item_name" value="<?= h($item['item_name']) ?>" required>
                        <input type="text" name="description" value="<?= h($item['description']) ?>" placeholder="Description" class="desc-input">
                    </td>
                    <td>
                        <select name="category_id">
                            <?php foreach ($category_list as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>" <?= $c['category_id'] == $item['category_id'] ? 'selected' : '' ?>>
                                    <?= h($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0" name="price" value="<?= h($item['price']) ?>" class="price-input"></td>
                    <td class="center-cell">
                        <input type="checkbox" name="availability" <?= $item['availability_status'] === 'available' ? 'checked' : '' ?>>
                    </td>
                    <td class="admin-actions">
                        <button type="submit" name="action" value="edit" class="btn-small">Save</button>
                        <button type="submit" name="action" value="delete" class="btn-small btn-danger"
                            onclick="return confirm('Delete this item?');">Delete</button>
                    </td>
                </form>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Add Menu Item</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="action" value="add">
        <label>Category
            <select name="category_id" required>
                <?php foreach ($category_list as $c): ?>
                    <option value="<?= (int)$c['category_id'] ?>"><?= h($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Name<input type="text" name="item_name" required></label>
        <label>Description<input type="text" name="description"></label>
        <label>Price (RWF)<input type="number" step="0.01" min="0" name="price" required></label>
        <label class="checkbox-label"><input type="checkbox" name="availability" checked> Available</label>
        <button type="submit" class="btn-primary">Add Item</button>
    </form>

    <?php endif; ?>
</main>

</body>
</html>