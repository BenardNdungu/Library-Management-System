<?php
/**
 * User Management - List Users
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'User Management';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $where[] = "u.role = ?";
    $params[] = $role;
}

if ($status) {
    $where[] = "u.status = ?";
    $params[] = $status;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM members WHERE user_id = u.id) as has_member_record
    FROM users u
    $whereClause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get counts for filters
$roleCounts = [];
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $stmt->fetch()) {
    $roleCounts[$row['role']] = $row['count'];
}

$statusCounts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $userId = (int) $_GET['delete'];
    
    // Prevent deleting self
    if ($userId == getCurrentUserId()) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            createAuditLog($pdo, getCurrentUserId(), 'delete_user', 'users', $userId, 'Deleted user ID: ' . $userId);
            $_SESSION['success'] = 'User deleted successfully.';
            redirect('index.php');
        } catch (PDOException $e) {
            $error = 'Cannot delete user. They may have associated records.';
        }
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $userId = (int) $_GET['toggle_status'];
    
    if ($userId == getCurrentUserId()) {
        $error = 'You cannot change your own status.';
    } else {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            createAuditLog($pdo, getCurrentUserId(), 'toggle_user_status', 'users', $userId, 'Changed user status to: ' . $newStatus);
            $_SESSION['success'] = 'User status updated successfully.';
            redirect('index.php');
        }
    }
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageScripts = ['users.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Users
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="flex:1; min-width:200px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, username..." value="<?php echo $search; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="librarian" <?php echo $role === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                        <option value="member" <?php echo $role === 'member' ? 'selected' : ''; ?>>Member</option>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-3">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $totalUsers; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?php echo $statusCounts['active'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Inactive</div>
                    <div class="stat-value"><?php echo $statusCounts['inactive'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Administrators</div>
                    <div class="stat-value"><?php echo $roleCounts['admin'] ?? 0; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-1">
                                            <?php if ($user['profile_image']): ?>
                                                <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $user['profile_image']; ?>" alt="<?php echo $user['name']; ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <div class="user-avatar-sm" style="width:32px;height:32px;background:var(--primary);">
                                                    <span class="avatar-initials"><?php echo getInitials($user['name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php echo $user['name']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo getRoleDisplayName($user['role']); ?></td>
                                    <td><?php echo getStatusBadge($user['status']); ?></td>
                                    <td><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != getCurrentUserId()): ?>
                                                <a href="index.php?toggle_status=<?php echo $user['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>" 
                                                   title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                                   data-confirm="Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?">
                                                    <i class="fas <?php echo $user['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                </a>
                                                <a href="index.php?delete=<?php echo $user['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   title="Delete"
                                                   data-confirm="Are you sure you want to delete this user? This action cannot be undone.">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>