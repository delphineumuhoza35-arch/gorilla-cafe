<?php
/**
 * kitchen/logout.php
 *
 * Role in system: destroys the current kitchen/waiter session and
 * returns to the login screen. Paired with the no-cache headers in
 * includes/auth.php and the pageshow/persisted guard script in
 * kitchen/dashboard.php so a subsequent browser Back button press
 * can't display a stale authenticated page.
 */
session_start();
session_unset();
session_destroy();
header('Location: ../index.php');
exit;
