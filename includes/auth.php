<?php
/**
 * includes/auth.php
 *
 * Role in system: session-based access control for the two protected
 * areas of the application (admin/, kitchen/). Customer-facing pages
 * never include this file - ordering intentionally requires no account.
 *
 * Every protected page (admin or kitchen) starts by requiring this
 * file, which guarantees a logged-in session of the right role or
 * redirects/rejects.
 */
session_start();

/**
 * Send response headers that stop the browser from serving a cached
 * copy of a protected page after logout (e.g. via the Back button).
 * Called by both require_admin() and require_kitchen() on every
 * protected request, regardless of whether the session is valid.
 *
 * @return void
 */
function no_cache_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/**
 * Gate for admin/*.php pages. Redirects to login.php (relative to the
 * calling script's own directory) unless the current session belongs
 * to a logged-in user with role 'admin'.
 *
 * @return void
 */
function require_admin() {
    no_cache_headers();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

/**
 * Gate for kitchen/*.php pages - both 'kitchen' and 'waiter' roles are
 * accepted, since they share one dashboard screen.
 *
 * @param bool $json Set true by the JSON AJAX endpoints
 *                    (orders_feed.php, update_status.php): an HTML
 *                    redirect there would be silently followed by
 *                    fetch() and choke on non-JSON, so those respond
 *                    with a 401 JSON body instead of a redirect.
 * @return void
 */
function require_kitchen($json = false) {
    no_cache_headers();
    $role = $_SESSION['role'] ?? '';
    if (!isset($_SESSION['user_id']) || !in_array($role, ['kitchen', 'waiter'], true)) {
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        } else {
            header('Location: login.php');
        }
        exit;
    }
}
