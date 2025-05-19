<?php
$page_title = "Manage Bookings";
require_once 'includes/header.php'; // Includes session_check.php, config.php
require_once 'includes/db.php';     // PDO connection
require_once 'includes/functions.php'; // Helper functions

// --- PHP Logic for CRUD Operations ---

// Function to calculate number of nights
function calculate_nights($check_in_date_str, $check_out_date_str) {
    try {
        $check_in = new DateTime($check_in_date_str);
        $check_out = new DateTime($check_out_date_str);
        if ($check_out <= $check_in) {
            return 0;
        }
        $interval = $check_in->diff($check_out);
        return $interval->days;
    } catch (Exception $e) {
        return 0;
    }
}

// Function to calculate total price
function calculate_total_price_for_booking($pdo, $room_id, $num_nights) {
    if ($num_nights <= 0 || empty($room_id)) {
        return 0.00;
    }
    try {
        $sql = "SELECT rt.price_per_night
                FROM rooms r
                JOIN room_types rt ON r.room_type_id = rt.room_type_id
                WHERE r.room_id = :room_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
        $stmt->execute();
        $room_type_info = $stmt->fetch();

        if ($room_type_info && isset($room_type_info['price_per_night'])) {
            return (float)$room_type_info['price_per_night'] * $num_nights;
        }
        return 0.00; // Fallback if price cannot be determined
    } catch (PDOException $e) {
        error_log("Error calculating price: " . $e->getMessage());
        return 0.00;
    }
}

// Function to check room availability
function is_room_available_for_booking($pdo, $room_id, $check_in_date, $check_out_date, $exclude_booking_id = null) {
    try {
        $sql = "SELECT COUNT(*) FROM bookings
                WHERE room_id = :room_id
                AND status IN ('pending', 'confirmed') -- Only these statuses block a room
                AND NOT (check_out_date <= :check_in_date OR check_in_date >= :check_out_date)";
        if ($exclude_booking_id) {
            $sql .= " AND booking_id != :exclude_booking_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
        $stmt->bindParam(':check_in_date', $check_in_date);
        $stmt->bindParam(':check_out_date', $check_out_date);
        if ($exclude_booking_id) {
            $stmt->bindParam(':exclude_booking_id', $exclude_booking_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Error checking room availability: " . $e->getMessage());
        return false; 
    }
}

// Handle Add Booking Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    $guest_name = sanitize_input($_POST['guest_name']);
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_SANITIZE_EMAIL);
    $guest_phone = sanitize_input($_POST['guest_phone']);
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in_date = sanitize_input($_POST['check_in_date']);
    $check_out_date = sanitize_input($_POST['check_out_date']);
    $status = sanitize_input($_POST['status']); 
    $notes = sanitize_input($_POST['notes']);
    $total_price_input = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);

    $num_nights = calculate_nights($check_in_date, $check_out_date);
    
    // Use manually entered price if valid and provided, otherwise calculate it.
    // total_price is NOT NULL in your DB.
    if ($total_price_input !== false && $total_price_input >= 0) {
        $final_total_price = $total_price_input;
    } else {
        $final_total_price = calculate_total_price_for_booking($pdo, $room_id, $num_nights);
    }

    if (empty($guest_name) || empty($guest_email) || !$room_id || empty($check_in_date) || empty($check_out_date) || empty($status) || $num_nights <= 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Guest name, email, room, valid check-in/out dates, and status are required. Check-out date must be after check-in date.'];
    } elseif ($final_total_price < 0 && $status !== 'cancelled') { 
        // Allow zero or negative price for cancelled, but generally not for others.
        // Or enforce positive price strictly if that's the business rule.
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Total price must be valid. Please check room price or enter manually.'];
    } elseif (!is_room_available_for_booking($pdo, $room_id, $check_in_date, $check_out_date)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Selected room is not available for the chosen dates.'];
    } else {
        try {
            // user_id is not set from this form, will be NULL or set by DB trigger/default if applicable
            $sql = "INSERT INTO bookings (guest_name, guest_email, guest_phone, room_id, check_in_date, check_out_date, total_price, status, notes)
                    VALUES (:guest_name, :guest_email, :guest_phone, :room_id, :check_in_date, :check_out_date, :total_price, :status, :notes)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':guest_name', $guest_name);
            $stmt->bindParam(':guest_email', $guest_email);
            $stmt->bindParam(':guest_phone', $guest_phone);
            $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
            $stmt->bindParam(':check_in_date', $check_in_date);
            $stmt->bindParam(':check_out_date', $check_out_date);
            $stmt->bindParam(':total_price', $final_total_price);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':notes', $notes);

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Booking added successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add booking. Please try again.'];
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error: ' . $e->getMessage()];
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Update Booking Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $edit_booking_id = filter_input(INPUT_POST, 'edit_booking_id', FILTER_VALIDATE_INT);
    $guest_name = sanitize_input($_POST['guest_name']);
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_SANITIZE_EMAIL);
    $guest_phone = sanitize_input($_POST['guest_phone']);
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in_date = sanitize_input($_POST['check_in_date']);
    $check_out_date = sanitize_input($_POST['check_out_date']);
    $status = sanitize_input($_POST['status']);
    $notes = sanitize_input($_POST['notes']);
    $total_price_input = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);

    $num_nights = calculate_nights($check_in_date, $check_out_date);

    if ($total_price_input !== false && $total_price_input >= 0) {
        $final_total_price = $total_price_input;
    } else {
        $final_total_price = calculate_total_price_for_booking($pdo, $room_id, $num_nights);
    }

    if (!$edit_booking_id || empty($guest_name) || empty($guest_email) || !$room_id || empty($check_in_date) || empty($check_out_date) || empty($status) || $num_nights <= 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Booking ID, guest name, email, room, valid check-in/out dates, and status are required. Check-out date must be after check-in date.'];
    } elseif ($final_total_price < 0 && $status !== 'cancelled') {
         $_SESSION['message'] = ['type' => 'danger', 'text' => 'Total price must be valid.'];
    } elseif (!is_room_available_for_booking($pdo, $room_id, $check_in_date, $check_out_date, $edit_booking_id)) {
         $_SESSION['message'] = ['type' => 'danger', 'text' => 'Selected room is not available for the chosen dates for this update.'];
    } else {
        try {
            $sql = "UPDATE bookings SET
                        guest_name = :guest_name,
                        guest_email = :guest_email,
                        guest_phone = :guest_phone,
                        room_id = :room_id,
                        check_in_date = :check_in_date,
                        check_out_date = :check_out_date,
                        total_price = :total_price,
                        status = :status,
                        notes = :notes
                    WHERE booking_id = :booking_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':guest_name', $guest_name);
            $stmt->bindParam(':guest_email', $guest_email);
            $stmt->bindParam(':guest_phone', $guest_phone);
            $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
            $stmt->bindParam(':check_in_date', $check_in_date);
            $stmt->bindParam(':check_out_date', $check_out_date);
            $stmt->bindParam(':total_price', $final_total_price);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':booking_id', $edit_booking_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Booking updated successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'info', 'text' => 'No changes were made to the booking.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update booking. Please try again.'];
            }
        } catch (PDOException $e) {
             $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error during update: ' . $e->getMessage()];
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Delete Booking Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_booking' && isset($_GET['id'])) {
    $booking_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($booking_id_to_delete) {
        try {
            $sql = "DELETE FROM bookings WHERE booking_id = :booking_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':booking_id', $booking_id_to_delete, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Booking deleted successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Booking not found or already deleted.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to delete booking.'];
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error during deletion: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid booking ID for deletion.'];
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Fetch rooms for dropdowns in modals
$available_rooms_for_form = [];
try {
    $stmt_rooms_form = $pdo->query("SELECT r.room_id, r.room_number, rt.name as room_type_name, rt.price_per_night
                               FROM rooms r
                               JOIN room_types rt ON r.room_type_id = rt.room_type_id
                               WHERE r.status != 'maintenance' 
                               ORDER BY r.room_number ASC");
    $available_rooms_for_form = $stmt_rooms_form->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve rooms list for forms. " . $e->getMessage() . "</div>";
}

// Fetch all bookings to display in the table
$bookings_list = [];
try {
    // Joining with users table to get the username of who made the booking, if user_id is present
    $stmt_bookings_list = $pdo->query("SELECT b.*, r.room_number, rt.name as room_type_name, u.username as booked_by_username
                                 FROM bookings b
                                 LEFT JOIN rooms r ON b.room_id = r.room_id
                                 LEFT JOIN room_types rt ON r.room_type_id = rt.room_type_id
                                 LEFT JOIN users u ON b.user_id = u.user_id 
                                 ORDER BY b.check_in_date DESC, b.booking_id DESC");
    $bookings_list = $stmt_bookings_list->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve bookings list. " . $e->getMessage() . "</div>";
}

// Statuses from your ENUM in bookings table
$booking_statuses_enum = ['pending', 'confirmed', 'cancelled', 'completed'];

?>

<h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
<?php display_session_message(); ?>

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addBookingModal">
  <i class="fas fa-plus me-1"></i> Add New Booking
</button>

<div class="modal fade" id="addBookingModal" tabindex="-1" aria-labelledby="addBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addBookingModalLabel">Add New Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="addBookingForm">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_guest_name" class="form-label">Guest Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add_guest_name" name="guest_name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="add_guest_email" class="form-label">Guest Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="add_guest_email" name="guest_email" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_guest_phone" class="form-label">Guest Phone</label>
              <input type="tel" class="form-control" id="add_guest_phone" name="guest_phone">
            </div>
            <div class="col-md-6 mb-3">
              <label for="add_room_id" class="form-label">Room <span class="text-danger">*</span></label>
              <select class="form-select" id="add_room_id" name="room_id" required>
                <option value="">Select Room</option>
                <?php foreach ($available_rooms_for_form as $room_item): ?>
                  <option value="<?php echo htmlspecialchars($room_item['room_id']); ?>" data-price_per_night="<?php echo htmlspecialchars($room_item['price_per_night']); ?>">
                    <?php echo htmlspecialchars($room_item['room_number'] . ' - ' . $room_item['room_type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_check_in_date" class="form-label">Check-in Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="add_check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="add_check_out_date" class="form-label">Check-out Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="add_check_out_date" name="check_out_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_status" class="form-label">Status <span class="text-danger">*</span></label>
              <select class="form-select" id="add_status" name="status" required>
                <?php foreach ($booking_statuses_enum as $status_val): ?>
                  <option value="<?php echo htmlspecialchars($status_val); ?>" <?php echo ($status_val == 'pending') ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst($status_val)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="add_total_price" class="form-label">Total Price ($) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="add_total_price" name="total_price" required>
                 <small class="form-text text-muted">Auto-calculated. Can be overridden if needed.</small>
            </div>
          </div>
          <div class="mb-3">
            <label for="add_notes" class="form-label">Notes</label>
            <textarea class="form-control" id="add_notes" name="notes" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_booking" class="btn btn-primary">Add Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editBookingModal" tabindex="-1" aria-labelledby="editBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editBookingModalLabel">Edit Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="editBookingForm">
        <input type="hidden" name="edit_booking_id" id="edit_booking_id_field">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_guest_name" class="form-label">Guest Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_guest_name" name="guest_name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_guest_email" class="form-label">Guest Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="edit_guest_email" name="guest_email" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_guest_phone" class="form-label">Guest Phone</label>
              <input type="tel" class="form-control" id="edit_guest_phone" name="guest_phone">
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_room_id" class="form-label">Room <span class="text-danger">*</span></label>
              <select class="form-select" id="edit_room_id" name="room_id" required>
                <option value="">Select Room</option>
                <?php foreach ($available_rooms_for_form as $room_item): ?>
                  <option value="<?php echo htmlspecialchars($room_item['room_id']); ?>" data-price_per_night="<?php echo htmlspecialchars($room_item['price_per_night']); ?>">
                    <?php echo htmlspecialchars($room_item['room_number'] . ' - ' . $room_item['room_type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_check_in_date" class="form-label">Check-in Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="edit_check_in_date" name="check_in_date" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_check_out_date" class="form-label">Check-out Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="edit_check_out_date" name="check_out_date" required>
            </div>
          </div>
           <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
              <select class="form-select" id="edit_status" name="status" required>
                 <?php foreach ($booking_statuses_enum as $status_val): ?>
                  <option value="<?php echo htmlspecialchars($status_val); ?>">
                    <?php echo htmlspecialchars(ucfirst($status_val)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
             <div class="col-md-6 mb-3">
                <label for="edit_total_price" class="form-label">Total Price ($) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="edit_total_price" name="total_price" required>
                <small class="form-text text-muted">Auto-calculated on date/room change. Can be overridden.</small>
            </div>
          </div>
          <div class="mb-3">
            <label for="edit_notes" class="form-label">Notes</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_booking" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-primary">Bookings List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="bookingsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Guest Details</th>
                        <th>Room</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Nights</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Booked By User</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings_list)): ?>
                        <tr><td colspan="11" class="text-center">No bookings found. Add a booking to get started!</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings_list as $booking_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking_item['booking_id']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($booking_item['guest_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($booking_item['guest_email']); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($booking_item['guest_phone'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($booking_item['room_number'] ?? 'N/A') . ($booking_item['room_type_name'] ? ' (' . htmlspecialchars($booking_item['room_type_name']) . ')' : ''); ?></td>
                            <td><?php echo htmlspecialchars(format_date($booking_item['check_in_date'], 'Y-m-d')); ?></td>
                            <td><?php echo htmlspecialchars(format_date($booking_item['check_out_date'], 'Y-m-d')); ?></td>
                            <td><?php echo htmlspecialchars(calculate_nights($booking_item['check_in_date'], $booking_item['check_out_date'])); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($booking_item['total_price'] ?? 0, 2)); ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch ($booking_item['status']) {
                                        case 'confirmed': echo 'success'; break;
                                        case 'pending': echo 'warning'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        case 'completed': echo 'primary'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars(ucfirst($booking_item['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(format_date($booking_item['created_at'], 'Y-m-d H:i')); ?></td>
                            <td><?php echo htmlspecialchars($booking_item['booked_by_username'] ?? 'Guest/Direct'); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-booking-btn mb-1"
                                        title="Edit Booking"
                                        data-id="<?php echo htmlspecialchars($booking_item['booking_id']); ?>"
                                        data-guest_name="<?php echo htmlspecialchars($booking_item['guest_name']); ?>"
                                        data-guest_email="<?php echo htmlspecialchars($booking_item['guest_email']); ?>"
                                        data-guest_phone="<?php echo htmlspecialchars($booking_item['guest_phone'] ?? ''); ?>"
                                        data-room_id="<?php echo htmlspecialchars($booking_item['room_id']); ?>"
                                        data-check_in_date="<?php echo htmlspecialchars($booking_item['check_in_date']); ?>"
                                        data-check_out_date="<?php echo htmlspecialchars($booking_item['check_out_date']); ?>"
                                        data-total_price="<?php echo htmlspecialchars($booking_item['total_price'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($booking_item['status']); ?>"
                                        data-notes="<?php echo htmlspecialchars($booking_item['notes'] ?? ''); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editBookingModal">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=delete_booking&id=<?php echo htmlspecialchars($booking_item['booking_id']); ?>"
                                   class="btn btn-sm btn-danger mb-1" title="Delete Booking"
                                   onclick="return confirm('Are you sure you want to delete booking for \'<?php echo htmlspecialchars(addslashes($booking_item['guest_name'])); ?>\' (ID: <?php echo htmlspecialchars(addslashes($booking_item['booking_id'])); ?>)? This action cannot be undone.');">
                                   <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// JavaScript for populating the edit modal, date validations, and dynamic price calculation
?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Helper function to calculate nights ---
    function calculateNightsJS(checkInStr, checkOutStr) {
        if (!checkInStr || !checkOutStr) return 0;
        const checkInDate = new Date(checkInStr);
        const checkOutDate = new Date(checkOutStr);
        if (checkOutDate <= checkInDate) return 0;
        const diffTime = Math.abs(checkOutDate - checkInDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    }

    // --- Helper function to update total price in a form ---
    function updateTotalPriceDisplay(formIdPrefix) {
        const roomIdField = document.getElementById(formIdPrefix + '_room_id');
        const checkInField = document.getElementById(formIdPrefix + '_check_in_date');
        const checkOutField = document.getElementById(formIdPrefix + '_check_out_date');
        const totalPriceField = document.getElementById(formIdPrefix + '_total_price');

        if (!roomIdField || !checkInField || !checkOutField || !totalPriceField) return;

        const selectedRoomOption = roomIdField.options[roomIdField.selectedIndex];
        const pricePerNight = parseFloat(selectedRoomOption.getAttribute('data-price_per_night'));
        const numNights = calculateNightsJS(checkInField.value, checkOutField.value);

        if (selectedRoomOption.value && pricePerNight >= 0 && numNights > 0) {
            const calculatedPrice = pricePerNight * numNights;
            totalPriceField.value = calculatedPrice.toFixed(2);
             if (formIdPrefix === 'add') { // Only make readonly if auto-calculated for add form
                totalPriceField.readOnly = true;
            }
        } else if (selectedRoomOption.value && pricePerNight >= 0) { // room selected but nights invalid
            totalPriceField.value = "0.00";
             if (formIdPrefix === 'add') {
                totalPriceField.readOnly = true;
            }
        }
         else { // Room not selected or price_per_night not available
            totalPriceField.value = ""; // Clear or set to 0.00 as preferred
            totalPriceField.readOnly = false; // Allow manual input if no price data
        }
    }

    // --- Add Booking Modal: Date Validation and Price Calculation ---
    const addForm = document.getElementById('addBookingForm');
    if (addForm) {
        const addRoomId = addForm.querySelector('#add_room_id');
        const addCheckInDate = addForm.querySelector('#add_check_in_date');
        const addCheckOutDate = addForm.querySelector('#add_check_out_date');

        addCheckInDate.addEventListener('change', function() {
            if (this.value) {
                let minCheckoutDate = new Date(this.value);
                minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                addCheckOutDate.min = minCheckoutDate.toISOString().split("T")[0];
                if (addCheckOutDate.value && addCheckOutDate.value <= this.value) {
                    addCheckOutDate.value = addCheckOutDate.min; 
                }
            }
            updateTotalPriceDisplay('add');
        });
        addCheckOutDate.addEventListener('change', function() {
             if (addCheckInDate.value && this.value && this.value <= addCheckInDate.value) {
                let minCheckoutDate = new Date(addCheckInDate.value);
                minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                this.value = minCheckoutDate.toISOString().split("T")[0];
            }
            updateTotalPriceDisplay('add');
        });
        addRoomId.addEventListener('change', function() {
            updateTotalPriceDisplay('add');
        });
        // Initial call in case dates/room are pre-filled (though not typical for add modal)
        updateTotalPriceDisplay('add');
    }


    // --- Edit Booking Modal: Date Validation, Price Calculation, and Data Population ---
    const editBookingModal = document.getElementById('editBookingModal');
    if (editBookingModal) {
        const editRoomId = editBookingModal.querySelector('#edit_room_id');
        const editCheckInDate = editBookingModal.querySelector('#edit_check_in_date');
        const editCheckOutDate = editBookingModal.querySelector('#edit_check_out_date');

        editCheckInDate.addEventListener('change', function() {
            if (this.value) {
                let minCheckoutDate = new Date(this.value);
                minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                editCheckOutDate.min = minCheckoutDate.toISOString().split("T")[0];
                 if (editCheckOutDate.value && editCheckOutDate.value <= this.value) {
                    editCheckOutDate.value = editCheckOutDate.min;
                }
            }
            updateTotalPriceDisplay('edit');
        });
         editCheckOutDate.addEventListener('change', function() {
            if (editCheckInDate.value && this.value && this.value <= editCheckInDate.value) {
                let minCheckoutDate = new Date(editCheckInDate.value);
                minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                this.value = minCheckoutDate.toISOString().split("T")[0];
            }
            updateTotalPriceDisplay('edit');
        });
        editRoomId.addEventListener('change', function() {
            updateTotalPriceDisplay('edit');
        });
        
        editBookingModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 

            const bookingId = button.getAttribute('data-id');
            const guestName = button.getAttribute('data-guest_name');
            const guestEmail = button.getAttribute('data-guest_email');
            const guestPhone = button.getAttribute('data-guest_phone');
            const roomId = button.getAttribute('data-room_id');
            const checkInDate = button.getAttribute('data-check_in_date');
            const checkOutDate = button.getAttribute('data-check_out_date');
            const totalPrice = button.getAttribute('data-total_price');
            const status = button.getAttribute('data-status');
            const notes = button.getAttribute('data-notes');

            editBookingModal.querySelector('.modal-title').textContent = 'Edit Booking ID: ' + bookingId;
            editBookingModal.querySelector('#edit_booking_id_field').value = bookingId;
            editBookingModal.querySelector('#edit_guest_name').value = guestName;
            editBookingModal.querySelector('#edit_guest_email').value = guestEmail;
            editBookingModal.querySelector('#edit_guest_phone').value = guestPhone;
            editBookingModal.querySelector('#edit_room_id').value = roomId;
            editBookingModal.querySelector('#edit_check_in_date').value = checkInDate;
            editBookingModal.querySelector('#edit_check_out_date').value = checkOutDate;
            editBookingModal.querySelector('#edit_total_price').value = parseFloat(totalPrice).toFixed(2);
            editBookingModal.querySelector('#edit_status').value = status;
            editBookingModal.querySelector('#edit_notes').value = notes;
            
            // Set min date for check-in on edit modal
            // (Allow past dates for editing historical records if necessary, or set to today)
            // editBookingModal.querySelector('#edit_check_in_date').min = new Date().toISOString().split("T")[0];
            // Set min for checkout based on current check-in
             if (checkInDate) {
                let minCheckoutDate = new Date(checkInDate);
                minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                editBookingModal.querySelector('#edit_check_out_date').min = minCheckoutDate.toISOString().split("T")[0];
            }
            // Initial call to set price or ensure it's correct if room changes
            updateTotalPriceDisplay('edit'); 
        });
    }

    // Optional: Initialize DataTables
    // if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
    //     $('#bookingsTable').DataTable({
    //         "order": [[ 3, "desc" ]] // Example: order by check-in date descending
    //     });
    // }
});
</script>

<?php
require_once 'includes/footer.php'; 
?>