<?php
/**
 * includes/functions.php
 *
 * Role in system: small, stateless helper functions shared by
 * customer, kitchen and admin pages. No database access here -
 * purely formatting/escaping/lookup utilities.
 */

/**
 * Format a decimal amount as Rwandan Francs for display.
 *
 * @param float|string $amount Raw numeric amount, e.g. 1500.00
 * @return string Formatted string, e.g. "1,500 RWF"
 */
function format_price($amount) {
    return number_format((float)$amount, 0) . ' RWF';
}

/**
 * Escape a value for safe HTML output. Used on every piece of
 * dynamic data printed into a page to prevent cross-site scripting.
 *
 * @param string|null $value Raw value (null-safe)
 * @return string HTML-escaped string
 */
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Human-friendly label for an order_status ENUM value.
 *
 * @param string $status One of status_sequence()
 * @return string Display label, e.g. "Order Received"
 */
function status_label($status) {
    $labels = [
        'received'  => 'Order Received',
        'preparing' => 'Preparing',
        'ready'     => 'Ready',
        'served'    => 'Served',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}

/**
 * The fixed, ordered pipeline an order's status moves through.
 * Used by kitchen/update_status.php to enforce forward-only
 * transitions, and by the kitchen dashboard / customer status page
 * to render step indicators in the correct order.
 *
 * @return string[] Ordered list of valid status values
 */
function status_sequence() {
    return ['received', 'preparing', 'ready', 'served'];
}

/**
 * Render a number of seconds as a short human-readable duration, used to
 * show how long an order has been waiting or took to be served.
 *
 * @param int $seconds Elapsed time in seconds (negative values clamp to 0)
 * @return string e.g. "45m" or "1h 5m"
 */
function format_duration($seconds) {
    $seconds = max(0, (int)$seconds);
    $hours   = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
}