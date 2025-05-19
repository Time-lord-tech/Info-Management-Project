<?php
$page_title = "Manage Rooms";
require_once 'includes/header.php'; // Includes session_check.php, config.php
require_once 'includes/db.php';     // PDO connection
require_once 'includes/functions.php'; // Helper functions like sanitize_input, display_session_message

// --- PHP Logic for CRUD Operations ---

// Handle Add Room Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $room_number = sanitize_input($_POST['room_number']);
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
    $status = sanitize_input($_POST['status']);
    $description = sanitize_input($_POST['description']);

    if (empty($room_number) || $room_type_id === false || empty($status)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Room number, type, and status are required.'];
    } else {
        try {
            $sql = "INSERT INTO rooms (room_number, room_type_id, status, description) VALUES (:room_number, :room_type_id, :status, :description)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':room_number', $room_number);
            $stmt->bindParam(':room_type_id', $room_type_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Room added successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add room. Please try again.'];
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // MySQL error code for duplicate entry
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Room number "' . htmlspecialchars($room_number) . '" already exists.'];
            } else {
                // In production, log this error instead of showing details to the user
                // error_log("PDO Error: " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred. Please try again later.'];
            }
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"])); // Prevent form resubmission
    exit;
}

// Handle Update Room Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $edit_room_id = filter_input(INPUT_POST, 'edit_room_id', FILTER_VALIDATE_INT);
    $room_number = sanitize_input($_POST['room_number']);
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
    $status = sanitize_input($_POST['status']);
    $description = sanitize_input($_POST['description']);

    if (empty($edit_room_id) || empty($room_number) || $room_type_id === false || empty($status)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Room ID, number, type, and status are required for update.'];
    } else {
        try {
            $sql = "UPDATE rooms SET room_number = :room_number, room_type_id = :room_type_id, status = :status, description = :description WHERE room_id = :room_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':room_number', $room_number);
            $stmt->bindParam(':room_type_id', $room_type_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':room_id', $edit_room_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Room updated successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'info', 'text' => 'No changes were made to the room.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update room. Please try again.'];
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Room number "' . htmlspecialchars($room_number) . '" already exists for another room.'];
            } else {
                // error_log("PDO Error: " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred during update. Please try again later.'];
            }
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}


// Handle Delete Room Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_room' && isset($_GET['id'])) {
    $room_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($room_id_to_delete) {
        // Optional: Add a check here if the room has current/future confirmed bookings before deleting,
        // even if ON DELETE SET NULL is used, to provide a better UX warning.
        // For example:
        // $checkSql = "SELECT COUNT(*) FROM bookings WHERE room_id = :room_id AND status = 'confirmed' AND check_out_date >= CURDATE()";
        // $checkStmt = $pdo->prepare($checkSql);
        // $checkStmt->bindParam(':room_id', $room_id_to_delete, PDO::PARAM_INT);
        // $checkStmt->execute();
        // if ($checkStmt->fetchColumn() > 0) {
        //     $_SESSION['message'] = ['type' => 'warning', 'text' => 'Cannot delete room. It has active or upcoming confirmed bookings. Please cancel or reassign bookings first.'];
        // } else {
            try {
                $sql = "DELETE FROM rooms WHERE room_id = :room_id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':room_id', $room_id_to_delete, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Room deleted successfully!'];
                    } else {
                        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Room not found or already deleted.'];
                    }
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to delete room.'];
                }
            } catch (PDOException $e) {
                // If foreign key constraints prevent deletion (e.g., if ON DELETE RESTRICT was used and bookings exist),
                // this catch block would handle it. With ON DELETE SET NULL, this specific error is less likely for bookings.
                // error_log("PDO Error: " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred during deletion. The room might be in use.'];
            }
        // } // End of optional check block
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid room ID for deletion.'];
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Fetch room types for dropdowns
$room_types = [];
try {
    $stmt_types = $pdo->query("SELECT room_type_id, name FROM room_types ORDER BY name ASC");
    $room_types = $stmt_types->fetchAll();
} catch (PDOException $e) {
    // error_log("PDO Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve room types. Please contact support.</div>";
    // Potentially die() or include an error template if this is critical for page function
}

// Fetch all rooms to display
$rooms = [];
try {
    $stmt_rooms = $pdo->query("SELECT r.room_id, r.room_number, r.status, r.description, r.room_type_id,
                                      rt.name as room_type_name, rt.price_per_night
                              FROM rooms r
                              LEFT JOIN room_types rt ON r.room_type_id = rt.room_type_id
                              ORDER BY r.room_number ASC");
    $rooms = $stmt_rooms->fetchAll();
} catch (PDOException $e) {
    // error_log("PDO Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve rooms list. Please contact support.</div>";
    // Potentially die() or include an error template
}

?>

<h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>

<?php display_session_message(); // Display success/error messages from session ?>

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addRoomModal">
  <i class="fas fa-plus me-1"></i> Add New Room
</button>

<div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRoomModalLabel">Add New Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="add_room_number" class="form-label">Room Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add_room_number" name="room_number" required>
          </div>
          <div class="mb-3">
            <label for="add_room_type_id" class="form-label">Room Type <span class="text-danger">*</span></label>
            <select class="form-select" id="add_room_type_id" name="room_type_id" required>
              <option value="">Select Room Type</option>
              <?php foreach ($room_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type['room_type_id']); ?>">
                  <?php echo htmlspecialchars($type['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="add_status" class="form-label">Status <span class="text-danger">*</span></label>
            <select class="form-select" id="add_status" name="status" required>
              <option value="available">Available</option>
              <option value="occupied">Occupied (Manual Override)</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="add_description" class="form-label">Description</label>
            <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRoomModalLabel">Edit Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="edit_room_id" id="edit_room_id_field"> <div class="modal-body">
          <div class="mb-3">
            <label for="edit_room_number_field" class="form-label">Room Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit_room_number_field" name="room_number" required>
          </div>
          <div class="mb-3">
            <label for="edit_room_type_id_field" class="form-label">Room Type <span class="text-danger">*</span></label>
            <select class="form-select" id="edit_room_type_id_field" name="room_type_id" required>
              <option value="">Select Room Type</option>
              <?php foreach ($room_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type['room_type_id']); ?>">
                  <?php echo htmlspecialchars($type['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="edit_status_field" class="form-label">Status <span class="text-danger">*</span></label>
            <select class="form-select" id="edit_status_field" name="status" required>
              <option value="available">Available</option>
              <option value="occupied">Occupied</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
           <div class="mb-3">
            <label for="edit_description_field" class="form-label">Description</label>
            <textarea class="form-control" id="edit_description_field" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_room" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-primary">Room List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="roomsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Room Number</th>
                        <th>Type</th>
                        <th>Price/Night</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr><td colspan="6" class="text-center">No rooms found. Add a room to get started!</td></tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($room['room_type_name'] ?? 'N/A'); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($room['price_per_night'] ?? 0, 2)); ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch ($room['status']) {
                                        case 'available': echo 'success'; break;
                                        case 'occupied': echo 'warning'; break;
                                        case 'maintenance': echo 'secondary'; break;
                                        default: echo 'light text-dark'; // Ensure text is visible on light bg
                                    }
                                ?>">
                                    <?php echo htmlspecialchars(ucfirst($room['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(substr($room['description'] ?? '', 0, 50)) . (strlen($room['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-room-btn mb-1"
                                        title="Edit Room"
                                        data-id="<?php echo htmlspecialchars($room['room_id']); ?>"
                                        data-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                        data-type_id="<?php echo htmlspecialchars($room['room_type_id'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($room['status']); ?>"
                                        data-description="<?php echo htmlspecialchars($room['description'] ?? ''); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editRoomModal">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=delete_room&id=<?php echo htmlspecialchars($room['room_id']); ?>"
                                   class="btn btn-sm btn-danger mb-1" title="Delete Room"
                                   onclick="return confirm('Are you sure you want to delete room \'<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>\'? This action cannot be undone and might affect bookings if ON DELETE SET NULL is used.');">
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
// JavaScript for populating the edit modal
// This should ideally be in your admin/assets/js/script.js and included via footer.php
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editRoomModal = document.getElementById('editRoomModal');
    if (editRoomModal) {
        editRoomModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal

            // Extract info from data-* attributes
            const roomId = button.getAttribute('data-id');
            const roomNumber = button.getAttribute('data-number');
            const roomTypeId = button.getAttribute('data-type_id');
            const status = button.getAttribute('data-status');
            const description = button.getAttribute('data-description');

            // Update the modal's content using the new field IDs
            const modalTitle = editRoomModal.querySelector('.modal-title');
            const roomIdInput = editRoomModal.querySelector('#edit_room_id_field'); // Updated ID
            const roomNumberInput = editRoomModal.querySelector('#edit_room_number_field'); // Updated ID
            const roomTypeIdSelect = editRoomModal.querySelector('#edit_room_type_id_field'); // Updated ID
            const statusSelect = editRoomModal.querySelector('#edit_status_field'); // Updated ID
            const descriptionTextarea = editRoomModal.querySelector('#edit_description_field'); // Updated ID

            modalTitle.textContent = 'Edit Room: ' + roomNumber;
            if(roomIdInput) roomIdInput.value = roomId;
            if(roomNumberInput) roomNumberInput.value = roomNumber;
            if(roomTypeIdSelect) roomTypeIdSelect.value = roomTypeId;
            if(statusSelect) statusSelect.value = status;
            if(descriptionTextarea) descriptionTextarea.value = description;
        });
    }

    // Optional: If you use DataTables library for enhanced table features:
    // $(document).ready(function() {
    //     $('#roomsTable').DataTable();
    // });
    // Make sure to include jQuery and DataTables JS/CSS files if you use this.
});
</script>

<?php
require_once 'includes/footer.php';
?>