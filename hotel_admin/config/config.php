<?php
// Database Credentials

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');             // Use 'root'
define('DB_PASSWORD', '');                 // Empty string for no password
define('DB_NAME', 'hotel_booking_db');   // Or whatever you named your database
// Site Settings
define('SITE_NAME', 'Hotel Admin Panel');
define('BASE_URL', 'http://localhost/hotel_admin/'); // Adjust as per your setup

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>