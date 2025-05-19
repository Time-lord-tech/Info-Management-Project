<?php
// Ensure session is started (if not already by config.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitizes input data.
 *
 * @param string $data The input data.
 * @return string Sanitized data.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Displays session messages (e.g., success, error alerts).
 */
function display_session_message() {
    if (isset($_SESSION['message'])) {
        $type = isset($_SESSION['message']['type']) ? $_SESSION['message']['type'] : 'info'; // default to info
        $text = isset($_SESSION['message']['text']) ? $_SESSION['message']['text'] : '';

        // Ensure type is one of the valid Bootstrap alert types
        $valid_types = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
        if (!in_array($type, $valid_types)) {
            $type = 'info'; // Default to 'info' if type is invalid
        }

        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($text);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['message']); // Clear the message after displaying
    }
}

/**
 * Checks if the current user has a specific role.
 * Assumes $_SESSION['role'] is set upon login.
 *
 * @param string|array $required_role The role(s) required.
 * @return bool True if the user has the required role, false otherwise.
 */
function has_role($required_role) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role'])) {
        return false; // Not logged in or role not set
    }

    $user_role = $_SESSION['role'];

    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    } else {
        return $user_role === $required_role;
    }
}

/**
 * Redirects to a given URL.
 *
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Formats a date string.
 *
 * @param string $date_string The date string to format.
 * @param string $format The desired output format.
 * @return string|false The formatted date string, or false on failure.
 */
function format_date($date_string, $format = 'M d, Y') {
    if (empty($date_string) || $date_string === '0000-00-00' || $date_string === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $date = date_create($date_string);
    if ($date) {
        return date_format($date, $format);
    }
    return 'Invalid Date';
}

// Add more helper functions as needed (e.g., for pagination, generating CSRF tokens)

?>