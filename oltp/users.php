<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();

$conn    = getOLTP();
$success = '';
$error   = '';
$baseUrl = '../';

// ── ADD USER ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $password  = $_POST['password'];
        $role      = $_POST['role'];

        if (empty($full_name) || empty($username) || empty($password)) {
            $error = 'All fields are required.';
        } else {
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Username \"$username\" is already taken.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $conn->prepare(
                    "INSERT INTO users (full_name, username, password, role)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("ssss", $full_name, $username, $hashed, $role);
                $stmt->execute()
                    ? $success = "User \"$username\" added successfully."
                    : $error   = "Database error: " . $conn->error;
            }
        }
    }

    // ── DELETE USER ───────────────────────────────────────────
    if ($_POST['action'] === 'delete') {
        $uid = (int) $_POST['user_id'];
        if ($uid === $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $success = "User deleted successfully.";
        }
    }

    // ── RESET PASSWORD ────────────────────────────────────────
    if ($_POST['action'] === 'reset_password') {
        $uid      = (int) $_POST['user_id'];
        $new_pass = $_POST['new_password'];
        if (empty($new_pass)) {
            $error = "New password cannot be empty.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt   = $conn->prepare(
                "UPDATE users SET password = ? WHERE user_id = ?"
            );
            $stmt->bind_param("si", $hashed, $uid);
            $stmt->execute();
            $success = "Password reset successfully.";
        }
    }
}

// ── FETCH ALL USERS ───────────────────────────────────────────
$users = $conn->query(
    "SELECT user_id, full_name, username, role, created_at
     FROM users ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - CanTech</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php include '../includes/sidebar_style.php'; ?>
    <style>
        .badge-admin {
            background: #FFF3CD; color: #856404;
            padding: 0.25rem 0.75rem; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .badge-cashier {
            background: #FFE4D0; color: #E85A25;
            padding: 0.25rem 0.75rem; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .btn-reset {
            background: #FEF9E7; color: #D68910;
            border: 1px solid #F39C12; border-radius: 8px;
            padding: 0.25rem 0.7rem; font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-reset:hover { background: #F39C12; color: white; }
        .btn-del {
            background: #FADBD8; color: #C0392B;
            border: 1px solid #E74C3C; border-radius: 8px;
            padding: 0.25rem 0.7rem; font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-del:hover { background: #C0392B; color: white; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"> User Management</h4>
            <small class="text-muted">Add, remove, and manage system accounts</small>
        </div>
        <button class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#addUserModal">
            + Add New User
        </button>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ❌ <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- USERS TABLE -->
    <div class="page-card">
        <h6>System Accounts</h6>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['user_id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td>
                            <span class="badge-<?= $u['role'] ?>">
                                <?= $u['role'] === 'admin' ? '👑 Admin' : '🧾 Cashier' ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <button class="btn-reset me-1"
                                onclick="openReset(<?= $u['user_id'] ?>,
                                '<?= htmlspecialchars($u['username']) ?>')">
                                🔑 Reset
                            </button>
                            <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" class="btn-del">🗑 Delete</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:0.78rem">— current user</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"> Add New User</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control"
                               placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               placeholder="e.g. cashier2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Min. 6 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="cashier"> Cashier</option>
                            <option value="admin"> Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔑 Reset Password</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"  value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Resetting password for:
                        <strong id="reset_username"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password"
                               class="form-control"
                               placeholder="Enter new password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openReset(uid, username) {
    document.getElementById('reset_user_id').value        = uid;
    document.getElementById('reset_username').textContent = username;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>
</body>
</html>
