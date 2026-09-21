<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/includes/db.php';
$conn->query("SELECT 1");
echo "OK " . date('Y-m-d H:i:s') . " UTC+2\n";