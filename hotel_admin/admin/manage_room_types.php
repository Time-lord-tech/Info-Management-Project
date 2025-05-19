<?php
$page_title = "Manage Room Types";
require_once 'includes/header.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Handle Add Room Type Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room_type'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $price_per_night = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);

    if (empty($name) || $price_per_night === false || $price_per_night < 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Room type name and a valid non-negative price per night are required.'];
    } else {
        try {
            $sql = "INSERT INTO room_types (name, description, price_per_night) VALUES (:name, :description, :price_per_night)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price_per_night', $price_per_night);

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Room type added successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add room type. Please try again.'];
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Room type name "' . htmlspecialchars($name) . '" already exists.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred. ' . $e->getMessage()];
            }
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Update Room Type Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room_type'])) {
    $edit_room_type_id = filter_input(INPUT_POST, 'edit_room_type_id', FILTER_VALIDATE_INT);
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $price_per_night = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);

    if (!$edit_room_type_id || empty($name) || $price_per_night === false || $price_per_night < 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Room type ID, name, and a valid non-negative price per night are required for update.'];
    } else {
        try {
            $sql = "UPDATE room_types SET name = :name, description = :description, price_per_night = :price_per_night WHERE room_type_id = :room_type_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price_per_night', $price_per_night);
            $stmt->bindParam(':room_type_id', $edit_room_type_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Room type updated successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'info', 'text' => 'No changes were made to the room type.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update room type. Please try again.'];
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Room type name "' . htmlspecialchars($name) . '" already exists for another type.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred during update. ' . $e->getMessage()];
            }
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Delete Room Type Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_room_type' && isset($_GET['id'])) {
    $room_type_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($room_type_id_to_delete) {
        // Check if any rooms are using this room type
        $check_sql = "SELECT COUNT(*) FROM rooms WHERE room_type_id = :room_type_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':room_type_id', $room_type_id_to_delete, PDO::PARAM_INT);
        $check_stmt->execute();
        $room_count = $check_stmt->fetchColumn();

        $warning_message = "";
        if ($room_count > 0) {
            $warning_message = "Warning: " . $room_count . " room(s) are currently assigned to this type. Deleting this type will set their room type to NULL. ";
        }

        try {
            $sql = "DELETE FROM room_types WHERE room_type_id = :room_type_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':room_type_id', $room_type_id_to_delete, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => $warning_message . 'Room type deleted successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Room type not found or already deleted.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to delete room type.'];
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error occurred during deletion. ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid room type ID for deletion.'];
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Fetch all room types to display
$room_types = [];
try {
    $stmt_types = $pdo->query("SELECT room_type_id, name, description, price_per_night, created_at FROM room_types ORDER BY name ASC");
    $room_types = $stmt_types->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve room types list. " . $e->getMessage() . "</div>";
}
?>

<h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
<?php display_session_message(); ?>

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addRoomTypeModal">
  <i class="fas fa-plus me-1"></i> Add New Room Type
</button>

<div class="modal fade" id="addRoomTypeModal" tabindex="-1" aria-labelledby="addRoomTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRoomTypeModalLabel">Add New Room Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="add_name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add_name" name="name" required>
          </div>
          <div class="mb-3">
            <label for="add_price_per_night" class="form-label">Price Per Night ($) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" class="form-control" id="add_price_per_night" name="price_per_night" required>
          </div>
          <div class="mb-3">
            <label for="add_description" class="form-label">Description</label>
            <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_room_type" class="btn btn-primary">Add Room Type</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editRoomTypeModal" tabindex="-1" aria-labelledby="editRoomTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRoomTypeModalLabel">Edit Room Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="edit_room_type_id" id="edit_room_type_id_field">
        <div class="modal-body">
          <div class="mb-3">
            <label for="edit_name_field" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit_name_field" name="name" required>
          </div>
          <div class="mb-3">
            <label for="edit_price_per_night_field" class="form-label">Price Per Night ($) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" class="form-control" id="edit_price_per_night_field" name="price_per_night" required>
          </div>
          <div class="mb-3">
            <label for="edit_description_field" class="form-label">Description</label>
            <textarea class="form-control" id="edit_description_field" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_room_type" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-primary">Room Types List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="roomTypesTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price/Night</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($room_types)): ?>
                        <tr><td colspan="6" class="text-center">No room types found. Add one to get started!</td></tr>
                    <?php else: ?>
                        <?php foreach ($room_types as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['room_type_id']); ?></td>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($type['price_per_night'], 2)); ?></td>
                            <td><?php echo nl2br(htmlspecialchars(substr($type['description'] ?? '', 0, 100))) . (strlen($type['description'] ?? '') > 100 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars(format_date($type['created_at'], 'Y-m-d H:i')); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-room-type-btn mb-1"
                                        title="Edit Room Type"
                                        data-id="<?php echo htmlspecialchars($type['room_type_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                        data-price_per_night="<?php echo htmlspecialchars($type['price_per_night']); ?>"
                                        data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editRoomTypeModal">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=delete_room_type&id=<?php echo htmlspecialchars($type['room_type_id']); ?>"
                                   class="btn btn-sm btn-danger mb-1" title="Delete Room Type"
                                   onclick="return confirm('Are you sure you want to delete room type \'<?php echo htmlspecialchars(addslashes($type['name'])); ?>\' (ID: <?php echo htmlspecialchars($type['room_type_id']); ?>)? This might affect rooms using this type by setting their type to NULL.');">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editRoomTypeModal = document.getElementById('editRoomTypeModal');
    if (editRoomTypeModal) {
        editRoomTypeModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal

            const roomTypeId = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const pricePerNight = button.getAttribute('data-price_per_night');
            const description = button.getAttribute('data-description');

            editRoomTypeModal.querySelector('.modal-title').textContent = 'Edit Room Type: ' + name;
            editRoomTypeModal.querySelector('#edit_room_type_id_field').value = roomTypeId;
            editRoomTypeModal.querySelector('#edit_name_field').value = name;
            editRoomTypeModal.querySelector('#edit_price_per_night_field').value = parseFloat(pricePerNight).toFixed(2);
            editRoomTypeModal.querySelector('#edit_description_field').value = description;
        });
    }

    // Optional: If you use DataTables library for enhanced table features:
    // $(document).ready(function() {
    //     $('#roomTypesTable').DataTable({"order": [[ 1, "asc" ]] }); // Order by name
    // });
    // Make sure to include jQuery and DataTables JS/CSS files if you use this.
});
</script>

<?php
require_once 'includes/footer.php';
?>