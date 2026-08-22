<?php
/**
 * View User Details
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'User Details';

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$userId) {
    $_SESSION['error'] = 'User ID required';
    redirect('index.php');
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM loans WHERE issued_by = u.id) as loans_issued,
           (SELECT COUNT(*) FROM loans WHERE returned_to = u.id) as loans_returned
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    redirect('index.php');
}

// Check if user is a member
$isMember = false;
$member = null;
$stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = ?");
$stmt->execute([$userId]);
$member = $stmt->fetch();
if ($member) {
    $isMember = true;
}

// Get audit logs for this user
$stmt = $pdo->prepare("
    SELECT * FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$userId]);
$auditLogs = $stmt->fetchAll();

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user"></i> User Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Users</a> / View
            </div>
        </div>
        <div class="page-actions">
            <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- User Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>ID:</strong> #<?php echo $user['id']; ?></p>
                    <p><strong>Name:</strong> <?php echo $user['name']; ?></p>
                    <p><strong>Username:</strong> <?php echo $user['username']; ?></p>
                    <p><strong>Email:</strong> <?php echo $user['email']; ?></p>
                    <?php if ($user['phone']): ?>
                        <p><strong>Phone:</strong> <?php echo $user['phone']; ?></p>
                    <?php endif; ?>
                    <p><strong>Role:</strong> <?php echo getRoleDisplayName($user['role']); ?></p>
                    <p><strong>Status:</strong> <?php echo getStatusBadge($user['status']); ?></p>
                    <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?></p>
                    <p><strong>Created:</strong> <?php echo formatDate($user['created_at']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-id-card"></i> Member Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($isMember && $member): ?>
                        <p><strong>Member Number:</strong> <?php echo $member['member_number']; ?></p>
                        <p><strong>Membership Type:</strong> <?php echo $member['membership_type']; ?></p>
                        <p><strong>Member Status:</strong> <?php echo getStatusBadge($member['status'], 'member'); ?></p>
                        <p><strong>Registration Date:</strong> <?php echo formatDate($member['registration_date']); ?></p>
                        <?php if ($member['date_of_birth']): ?>
                            <p><strong>Date of Birth:</strong> <?php echo formatDate($member['date_of_birth']); ?></p>
                        <?php endif; ?>
                        <?php if ($member['gender']): ?>
                            <p><strong>Gender:</strong> <?php echo $member['gender']; ?></p>
                        <?php endif; ?>
                        <?php if ($member['address']): ?>
                            <p><strong>Address:</strong> <?php echo nl2br($member['address']); ?></p>
                        <?php endif; ?>
                        <?php if ($member['emergency_contact']): ?>
                            <p><strong>Emergency Contact:</strong> <?php echo $member['emergency_contact']; ?></p>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="../members/view.php?id=<?php echo $member['id']; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> View Full Member Profile
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">This user is not registered as a member.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mt-3">
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Loans Issued</div>
                    <div class="stat-value"><?php echo $user['loans_issued']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Loans Returned</div>
                    <div class="stat-value"><?php echo $user['loans_returned']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Audit Log Entries</div>
                    <div class="stat-value"><?php echo count($auditLogs); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    <a href="../audit-logs/index.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-info">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($auditLogs)): ?>
                        <p class="text-muted text-center">No audit logs found for this user</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><span class="badge badge-info"><?php echo ucwords(str_replace('_', ' ', $log['action'])); ?></span></td>
                                            <td><?php echo $log['table_name'] ?: 'N/A'; ?></td>
                                            <td><?php echo $log['description'] ?: 'N/A'; ?></td>
                                            <td><?php echo $log['ip_address'] ?: 'N/A'; ?></td>
                                            <td><?php echo formatDate($log['created_at'], 'Y-m-d H:i:s'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>