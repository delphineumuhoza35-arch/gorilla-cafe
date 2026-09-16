<?php
/**
 * customer/menu.php
 *
 * Role in system: the page a customer lands on after scanning a
 * table's QR code (Specific Objective 1). Resolves the table number
 * from the URL, then renders every available menu item grouped by
 * category (Specific Objective 2). No login, no session - this page
 * is reachable by anyone with the link.
 *
 * The cart itself is built client-side in assets/js/cart.js; this
 * script only supplies the initial menu data and the table identity.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// The table number comes from the QR code URL: menu.php?table=3
$table_number = isset($_GET['table']) ? (int)$_GET['table'] : 0;

$table = null;
if ($table_number > 0) {
    $stmt = $conn->prepare("SELECT table_id, table_number FROM CAFE_TABLE WHERE table_number = ?");
    $stmt->bind_param('i', $table_number);
    $stmt->execute();
    $table = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Without a valid table we can't attach an order to anything - stop here.
if (!$table) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gorilla Café</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div class="empty-state">
            <h1>Gorilla Café</h1>
            <p>We couldn't find your table. Please scan the QR code on your table again.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Pull categories and their available items in one go.
$categories = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");

$items_by_category = [];
$items_result = $conn->query("
    SELECT item_id, category_id, item_name, description, price
    FROM MENU_ITEM
    WHERE availability_status = 'available'
    ORDER BY item_name
");
while ($row = $items_result->fetch_assoc()) {
    $items_by_category[$row['category_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gorilla Café - Menu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
    <h1>Gorilla Café</h1>
    <p class="subtitle">by Food &amp; Stuff - Table <?= h($table['table_number']) ?></p>
</header>

<main class="menu-wrap">
    <?php while ($cat = $categories->fetch_assoc()):
        $cat_items = $items_by_category[$cat['category_id']] ?? [];
        if (empty($cat_items)) continue; // skip empty categories
    ?>
    <section class="category">
        <h2><?= h($cat['category_name']) ?></h2>
        <div class="item-grid">
            <?php foreach ($cat_items as $item): ?>
            <div class="item-card">
                <div class="item-photo-placeholder">IMG</div>
                <div class="item-info">
                    <h3><?= h($item['item_name']) ?></h3>
                    <p class="item-desc"><?= h($item['description']) ?></p>
                    <p class="item-price"><?= format_price($item['price']) ?></p>
                </div>
                <div class="item-controls" data-item-id="<?= (int)$item['item_id'] ?>">
                    <button class="btn-add"
                        data-id="<?= (int)$item['item_id'] ?>"
                        data-name="<?= h($item['item_name']) ?>"
                        data-price="<?= (float)$item['price'] ?>">
                        Add
                    </button>
                    <div class="qty-stepper" hidden>
                        <button class="qty-minus" data-id="<?= (int)$item['item_id'] ?>">-</button>
                        <span class="qty-value">0</span>
                        <button class="qty-plus" data-id="<?= (int)$item['item_id'] ?>">+</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endwhile; ?>
</main>

<!-- Sticky cart summary bar - opens the full cart panel -->
<div class="cart-bar" id="cartBar" hidden>
    <span id="cartBarCount">0 items</span>
    <span id="cartBarTotal">0 RWF</span>
    <button id="cartBarOpen">View Cart</button>
</div>

<!-- Cart panel -->
<div class="cart-panel" id="cartPanel" hidden>
    <div class="cart-panel-header">
        <h2>Your Order</h2>
        <button id="cartClose">X</button>
    </div>
    <div id="cartItems" class="cart-items"></div>
    <div class="cart-total-row">
        <span>Total</span>
        <span id="cartTotal">0 RWF</span>
    </div>
    <label class="phone-label">
        Phone number (optional, for order updates)
        <input type="tel" id="customerPhone" placeholder="e.g. 07XXXXXXXX">
    </label>
    <button id="placeOrderBtn" class="btn-primary">Place Order</button>
    <p id="orderError" class="error-text" hidden></p>
</div>

<script>
    const TABLE_ID = <?= (int)$table['table_id'] ?>;
    const TABLE_NUMBER = <?= (int)$table['table_number'] ?>;
</script>
<script src="../assets/js/cart.js"></script>
</body>
</html>