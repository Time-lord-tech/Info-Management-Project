<?php
require_once __DIR__ . '/../../config/config.php'; // Ensures session_start()
require_once __DIR__ . '/../includes/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . BASE_URL . "admin/dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"]; // The password from the form
    $error_message = "";

    if (empty($username) || empty($password)) {
        $error_message = "Please enter username and password.";
    } else {
        // Prepare a select statement
        // Allow login with either username or email
        $sql = "SELECT user_id, username, password, role FROM users WHERE (username = :username OR email = :username) AND is_active = TRUE";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Check if username exists, if yes then verify password
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        // --- MODIFICATION FOR PLAIN TEXT PASSWORD ---
                        $plain_password_from_db = $row["password"]; // Get the plain text password from DB

                        if ($password === $plain_password_from_db) { // Direct string comparison
                            // Password is correct, so start a new session
                            // session_start(); // Already started in config.php

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $row["user_id"];
                            $_SESSION["username"] = $row["username"];
                            $_SESSION["role"] = $row["role"]; // Store user role

                            // Redirect user to dashboard
                            header("location: " . BASE_URL . "admin/dashboard.php");
                            exit;
                        } else {
                            // Password is not valid
                            $error_message = "Invalid username or password.";
                        }
                        // --- END OF MODIFICATION ---
                    }
                } else {
                    // Username doesn't exist
                    $error_message = "Invalid username or password.";
                }
            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            // Close statement
            unset($stmt);
        }
    }
    // Close connection
    unset($pdo);

    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
        header("location: login.php");
        exit;
    }
} else {
    // If not a POST request, redirect to login page
    header("location: login.php");
    exit;
}
?>