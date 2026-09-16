<?php
/**
 * admin/logout.php
 *
 * Role in system: destroys the current admin session. Paired with
 * the no-cache headers in includes/auth.php and the pageshow/
 * persisted guard script in admin/nav.php (included on every admin
 * page) so a subsequent browser Back button press can't display a
 * stale authenticated page.
 */
session_start();
session_unset();
session_destroy();
header('Location: ../index.php');
exit;