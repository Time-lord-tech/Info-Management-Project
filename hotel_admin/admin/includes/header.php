<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/assets/css/style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right vh-100" id="sidebar-wrapper">
            <div class="sidebar-heading text-white p-3"><?php echo SITE_NAME; ?></div>
            <div class="list-group list-group-flush">
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>admin/manage_bookings.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_bookings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check me-2"></i>Manage Bookings
                </a>
                <a href="<?php echo BASE_URL; ?>admin/manage_rooms.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_rooms.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bed me-2"></i>Manage Rooms
                </a>
                <a href="<?php echo BASE_URL; ?>admin/manage_room_types.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_room_types.php') ? 'active' : ''; ?>">
                    <i class="fas fa-door-open me-2"></i>Manage Room Types
                </a>
                <a href="<?php echo BASE_URL; ?>admin/manage_users.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
                <a href="<?php echo BASE_URL; ?>admin/auth/logout.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
        <div id="page-content-wrapper" class="flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="collapse navbar-collapse">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i><?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : 'Admin'; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="#">Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/auth/logout.php">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container-fluid p-4">