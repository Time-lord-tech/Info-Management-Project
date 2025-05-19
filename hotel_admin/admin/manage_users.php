<?php
$page_title = "Manage Users";
require_once 'includes/header.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Recommended: Ensure only users with 'admin' role can access this page.
// You would need a function like has_role() and ensure $_SESSION['role'] is set during login.
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'You do not have permission to access this page.'];
    header("Location: dashboard.php"); // Or an appropriate "access denied" page
    exit;
}
*/

// Handle Add User Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = sanitize_input($_POST['username']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $full_name = sanitize_input($_POST['full_name']);
    $password = $_POST['password']; // Plain text, as per current system
    $confirm_password = $_POST['confirm_password'];
    // $role = sanitize_input($_POST['role']); // Role is no longer taken from form for admin creation
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // ***** MODIFICATION: Admin creating user can only create 'staff' *****
    $role = 'staff';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Username, email, and password are required.'];
    } elseif ($password !== $confirm_password) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Passwords do not match.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid email format.'];
    } else {
        try {
            $check_sql = "SELECT user_id FROM users WHERE username = :username OR email = :email";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            if ($check_stmt->fetch()) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Username or email already exists.'];
            } else {
                // SECURITY WARNING: Storing plain text password. Implement hashing!
                $sql = "INSERT INTO users (username, email, full_name, password, role, is_active)
                        VALUES (:username, :email, :full_name, :password, :role, :is_active)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':role', $role); // Will be 'staff'
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Staff user added successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add user. Please try again.'];
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error: ' . $e->getMessage()];
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Update User Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $edit_user_id = filter_input(INPUT_POST, 'edit_user_id', FILTER_VALIDATE_INT);
    $username = sanitize_input($_POST['username']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $full_name = sanitize_input($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    // ***** MODIFICATION: Role is taken from a hidden field carrying the original role *****
    $role = sanitize_input($_POST['original_role']); // Use original role, admin cannot change it via UI
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$edit_user_id || empty($username) || empty($email)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'User ID, username, and email are required.'];
    } elseif (!empty($password) && ($password !== $confirm_password)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'New passwords do not match.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid email format.'];
    } elseif (!in_array($role, ['admin', 'staff'])) { // Validate the original role just in case
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid original role detected. Contact support.'];
    } else {
        try {
            $check_sql = "SELECT user_id FROM users WHERE (username = :username OR email = :email) AND user_id != :user_id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
            $check_stmt->execute();

            if ($check_stmt->fetch()) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Username or email already exists for another user.'];
            } else {
                $sql_parts = [
                    "username = :username",
                    "email = :email",
                    "full_name = :full_name",
                    "role = :role", // Role will be set to its original value
                    "is_active = :is_active"
                ];
                if (!empty($password)) {
                    $sql_parts[] = "password = :password";
                }
                $sql_update_fields = implode(", ", $sql_parts);
                $sql = "UPDATE users SET " . $sql_update_fields . " WHERE user_id = :user_id";

                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':role', $role); // Sets role to its original value
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $edit_user_id, PDO::PARAM_INT);
                if (!empty($password)) {
                    $stmt->bindParam(':password', $password);
                }

                if ($stmt->execute()) {
                    // Update session details if the logged-in user is editing their own profile
                    if ($edit_user_id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $username; // Update username in session if changed
                        // Note: Role cannot be changed by admin via UI, so no need to update session role here
                        // unless a different mechanism allows self-role change.
                    }
                     if ($stmt->rowCount() > 0) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'User updated successfully!'];
                    } else {
                        $_SESSION['message'] = ['type' => 'info', 'text' => 'No changes were made to the user.'];
                    }
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update user. Please try again.'];
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error: ' . $e->getMessage()];
        }
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

// Handle Delete User Request (logic for self-delete and last admin delete remains important)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    $user_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($user_id_to_delete) {
        if ($user_id_to_delete == $_SESSION['user_id']) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'You cannot delete your own account through this interface.'];
        } else {
            $role_check_sql = "SELECT role FROM users WHERE user_id = :user_id";
            $role_stmt = $pdo->prepare($role_check_sql);
            $role_stmt->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
            $role_stmt->execute();
            $user_to_delete_role = $role_stmt->fetchColumn();

            $can_delete = true;
            if ($user_to_delete_role === 'admin') {
                $admin_count_sql = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
                $admin_count_stmt = $pdo->query($admin_count_sql);
                $admin_count = $admin_count_stmt->fetchColumn();
                if ($admin_count <= 1) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cannot delete the last admin user.'];
                    $can_delete = false;
                }
            }

            if ($can_delete) {
                try {
                    $sql = "DELETE FROM users WHERE user_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'User deleted successfully!'];
                    } else {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to delete user.'];
                    }
                } catch (PDOException $e) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error during deletion.'];
                }
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid user ID for deletion.'];
    }
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}


// Fetch all users
$users = [];
try {
    $stmt_users = $pdo->query("SELECT user_id, username, email, full_name, role, is_active, created_at FROM users ORDER BY username ASC");
    $users = $stmt_users->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve users list. " . $e->getMessage() . "</div>";
}
?>

<h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
<?php display_session_message(); ?>
<p class="text-danger"><strong>Security Warning:</strong> Passwords are currently managed in plain text. Implement password hashing for improved security.</p>

<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
  <i class="fas fa-user-plus me-1"></i> Add New Staff User
</button>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserModalLabel">Add New Staff User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_username" class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add_username" name="username" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="add_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="add_email" name="email" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="add_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="add_full_name" name="full_name">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="add_password" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="add_password" name="password" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="add_confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Role</label>
              <input type="text" class="form-control" value="Staff" readonly>
              <input type="hidden" name="role" value="staff">
            </div>
            <div class="col-md-6 mb-3 align-self-center">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="add_is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="add_is_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_user" class="btn btn-primary">Add Staff User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="edit_user_id" id="edit_user_id_field">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_username" class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_username" name="username" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="edit_email" name="email" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="edit_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="edit_full_name" name="full_name">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
              <input type="password" class="form-control" id="edit_password" name="password">
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_confirm_password" class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
            </div>
          </div>
           <div class="row">
            <div class="col-md-6 mb-3">
              <label for="edit_role_display" class="form-label">Role</label>
              <input type="text" class="form-control" id="edit_role_display" readonly> <input type="hidden" id="edit_original_role_field" name="original_role"> </div>
            <div class="col-md-6 mb-3 align-self-center">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="is_active" value="1">
                <label class="form-check-label" for="edit_is_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-primary">Users List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user_item['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                            <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                            <td><?php echo htmlspecialchars($user_item['full_name'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-<?php echo ($user_item['role'] == 'admin') ? 'primary' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($user_item['role'])); ?></span></td>
                            <td>
                                <span class="badge bg-<?php echo ($user_item['is_active']) ? 'success' : 'danger'; ?>">
                                    <?php echo ($user_item['is_active']) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(format_date($user_item['created_at'], 'Y-m-d H:i')); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-user-btn mb-1"
                                        title="Edit User"
                                        data-id="<?php echo htmlspecialchars($user_item['user_id']); ?>"
                                        data-username="<?php echo htmlspecialchars($user_item['username']); ?>"
                                        data-email="<?php echo htmlspecialchars($user_item['email']); ?>"
                                        data-full_name="<?php echo htmlspecialchars($user_item['full_name'] ?? ''); ?>"
                                        data-role="<?php echo htmlspecialchars($user_item['role']); ?>"
                                        data-is_active="<?php echo htmlspecialchars($user_item['is_active']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($user_item['user_id'] != $_SESSION['user_id']): // Prevent deleting own account ?>
                                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=delete_user&id=<?php echo htmlspecialchars($user_item['user_id']); ?>"
                                   class="btn btn-sm btn-danger mb-1" title="Delete User"
                                   onclick="return confirm('Are you sure you want to delete user \'<?php echo htmlspecialchars(addslashes($user_item['username'])); ?>\' (ID: <?php echo htmlspecialchars($user_item['user_id']); ?>)? This action cannot be undone.');">
                                   <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php endif; ?>
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
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 

            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const email = button.getAttribute('data-email');
            const fullName = button.getAttribute('data-full_name');
            const role = button.getAttribute('data-role');
            const isActive = button.getAttribute('data-is_active');

            editUserModal.querySelector('.modal-title').textContent = 'Edit User: ' + username;
            editUserModal.querySelector('#edit_user_id_field').value = userId;
            editUserModal.querySelector('#edit_username').value = username;
            editUserModal.querySelector('#edit_email').value = email;
            editUserModal.querySelector('#edit_full_name').value = fullName;
            
            // Display role as readonly text, pass original role in hidden field
            editUserModal.querySelector('#edit_role_display').value = role.charAt(0).toUpperCase() + role.slice(1); // Capitalized for display
            editUserModal.querySelector('#edit_original_role_field').value = role; // Actual value for submission (original role)
            
            editUserModal.querySelector('#edit_is_active').checked = (isActive == '1');
            
            editUserModal.querySelector('#edit_password').value = '';
            editUserModal.querySelector('#edit_confirm_password').value = '';
        });
    }
});
</script>

<?php
require_once 'includes/footer.php'; 
?>