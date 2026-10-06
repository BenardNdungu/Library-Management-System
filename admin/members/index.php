<?php
/**
 * Member Management - List Members
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Member Management';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$membership = isset($_GET['membership']) ? sanitizeInput($_GET['membership']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR m.member_number LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "m.status = ?";
    $params[] = $status;
}

if ($membership) {
    $where[] = "m.membership_type = ?";
    $params[] = $membership;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalMembers = $stmt->fetch()['total'];
$totalPages = ceil($totalMembers / $limit);

// Get members
$sql = "
    SELECT m.*, u.name, u.email, u.phone, u.username, u.profile_image, u.status as user_status,
           (SELECT COUNT(*) FROM lend WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_lend,
           (SELECT COUNT(*) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines_count,
           (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines_amount
    FROM members m
    JOIN users u ON m.user_id = u.id
    $whereClause
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM members GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $memberId = (int) $_GET['toggle_status'];
    
    $stmt = $pdo->prepare("SELECT status FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if ($member) {
        $newStatus = $member['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $memberId]);
        createAuditLog($pdo, getCurrentUserId(), 'toggle_member_status', 'members', $memberId, 'Changed member status to: ' . $newStatus);
        $_SESSION['success'] = 'Member status updated successfully.';
        redirect('index.php');
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $memberId = (int) $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();
        
        if ($member) {
            // Delete member record first
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            
            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$member['user_id']]);
            
            createAuditLog($pdo, getCurrentUserId(), 'delete_member', 'members', $memberId, 'Deleted member ID: ' . $memberId);
            $_SESSION['success'] = 'Member deleted successfully.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Cannot delete member. They may have associated records.';
    }
    redirect('index.php');
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageScripts = ['members.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Member Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Members
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add Member
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
                <div class="form-group mb-0" style="flex:2; min-width:200px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, member number, email..." value="<?php echo $search; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="membership" class="form-control">
                        <option value="">All Types</option>
                        <option value="Standard" <?php echo $membership === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="Premium" <?php echo $membership === 'Premium' ? 'selected' : ''; ?>>Premium</option>
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
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value"><?php echo $totalMembers; ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
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
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Inactive/Suspended</div>
                    <div class="stat-value"><?php echo ($statusCounts['inactive'] ?? 0) + ($statusCounts['suspended'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Member #</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Current lend</th>
                            <th>Fines</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No members found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-center gap-1">
                                            <?php if ($member['profile_image']): ?>
                                                <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $member['profile_image']; ?>" 
                                                     alt="<?php echo $member['name']; ?>" 
                                                     style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <div class="user-avatar-sm" style="width:32px;height:32px;background:var(--primary);">
                                                    <span class="avatar-initials"><?php echo getInitials($member['name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php echo $member['name']; ?>
                                        </div>
                                    </td>
                                    <td><strong><?php echo $member['member_number']; ?></strong></td>
                                    <td><?php echo $member['email']; ?></td>
                                    <td><?php echo $member['membership_type']; ?></td>
                                    <td><?php echo getStatusBadge($member['status'], 'member'); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $member['current_lend']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($member['outstanding_fines_amount'] > 0): ?>
                                            <span class="badge badge-danger"><?php echo formatCurrency($member['outstanding_fines_amount']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">$0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="view.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="index.php?toggle_status=<?php echo $member['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm <?php echo $member['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>" 
                                               title="<?php echo $member['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $member['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            </a>
                                            <a href="index.php?delete=<?php echo $member['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               data-confirm="Are you sure you want to delete this member? This will also delete their user account.">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&membership=<?php echo $membership; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&membership=<?php echo $membership; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&membership=<?php echo $membership; ?>" class="page-link">
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