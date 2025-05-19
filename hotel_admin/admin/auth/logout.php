<?php
require_once __DIR__ . '/../../config/config.php'; // Ensures session_start()

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page
header("location: " . BASE_URL . "admin/auth/login.php");
exit;
?>