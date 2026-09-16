<?php
/**
 * qr/generate.php
 *
 * Role in system: printable sheet of QR codes, one per row currently
 * in CAFE_TABLE - the physical output half of Specific Objective 1.
 * No admin login required (a member of staff standing at a printer
 * needs to reach this quickly) and no hardcoded table list: every
 * table added via admin/tables.php appears here automatically on the
 * next page load, with no code change needed.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$tables = $conn->query("SELECT table_id, table_number, qr_code_value FROM CAFE_TABLE ORDER BY table_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table QR Codes - Gorilla Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Kept inline since this page is print-focused and only used here. */
        .qr-grid { display: flex; flex-wrap: wrap; gap: 24px; padding: 24px; }
        .qr-card {
            width: 260px; text-align: center; border: 1px dashed #ccc;
            border-radius: 12px; padding: 20px; page-break-inside: avoid;
        }
        .qr-card img { width: 200px; height: 200px; }
        .qr-card h3 { margin: 10px 0 4px; }
        .qr-card small { color: #666; word-break: break-all; }
        .print-bar { padding: 16px 24px; }
        @media print {
            .print-bar { display: none; }
            .qr-card { border: 1px solid #999; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <button onclick="window.print()">Print all QR codes</button>
</div>

<div class="qr-grid">
    <?php while ($t = $tables->fetch_assoc()):
        // Free QR image API - encodes the exact URL stored in qr_code_value.
        // Needs an internet connection when the page is loaded/printed.
        $qr_img_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($t['qr_code_value']);
    ?>
    <div class="qr-card">
        <img src="<?= h($qr_img_url) ?>" alt="QR code for table <?= h($t['table_number']) ?>">
        <h3>Table <?= h($t['table_number']) ?></h3>
        <small><?= h($t['qr_code_value']) ?></small>
    </div>
    <?php endwhile; ?>
</div>

</body>
</html>
