<?php
/**
 * Logout Handler
 */

require_once '../../config/database.php';

// Destroy session
session_unset();
session_destroy();

// Clear remember cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login
header('Location: ../../login.php');
exit;
