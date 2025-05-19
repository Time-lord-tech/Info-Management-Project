<?php
$page_title = "Dashboard";
require_once 'includes/header.php';
require_once 'includes/db.php'; // Ensure DB connection is available

// Fetching booking stats - Example
$total_bookings = 0;
$available_rooms = 0;
$occupied_rooms = 0;

try {
    // Total Bookings
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM bookings");
    $total_bookings_row = $stmt->fetch();
    $total_bookings = $total_bookings_row ? $total_bookings_row['total'] : 0;

    // Available Rooms
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM rooms WHERE status = 'available'");
    $available_rooms_row = $stmt->fetch();
    $available_rooms = $available_rooms_row ? $available_rooms_row['total'] : 0;

    // Occupied Rooms (can be complex depending on how you define 'occupied')
    // Simplistic: rooms with status 'occupied' OR rooms associated with an active booking
    // For a more accurate 'occupied' count based on current bookings:
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT r.room_id) AS total
        FROM rooms r
        JOIN bookings b ON r.room_id = b.room_id
        WHERE b.check_in_date <= :today AND b.check_out_date >= :today
        AND b.status = 'confirmed'
    ");
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $occupied_rooms_row = $stmt->fetch();
    $occupied_rooms = $occupied_rooms_row ? $occupied_rooms_row['total'] : 0;

} catch (PDOException $e) {
    // Handle errors, e.g., log them or display a user-friendly message
    echo "<div class='alert alert-danger'>Could not retrieve dashboard statistics. " . $e->getMessage() . "</div>";
}

?>

<h1 class="mt-4"><?php echo $page_title; ?></h1>
<p>Welcome back, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</p>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Bookings</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_bookings; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Rooms</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_rooms; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-door-open fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Occupied Rooms (Currently)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $occupied_rooms; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-bed fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

