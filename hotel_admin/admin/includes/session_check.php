<?php
require_once __DIR__ . '/../../config/config.php'; // Ensures session_start() is called

// If user is not logged in and not on the login page, redirect to login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Allow access to login page and login processing page
    $allowed_pages = [
        BASE_URL . 'admin/auth/login.php',
        BASE_URL . 'admin/auth/process_login.php'
    ];
    $current_page = BASE_URL . substr($_SERVER['PHP_SELF'], 1); // Construct current page URL

    // A more robust way to check current script name
    $script_name = basename($_SERVER['PHP_SELF']);
    if ($script_name !== 'login.php' && $script_name !== 'process_login.php') {
        header("location: " . BASE_URL . "admin/auth/login.php");
        exit;
    }
}
?>