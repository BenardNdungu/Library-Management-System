<?php
/**
 * Member Dashboard
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'Member Dashboard';

$userId = getCurrentUserId();

// Get member details
$stmt = $pdo->prepare("
    SELECT m.*, u.name, u.email, u.phone, u.profile_image
    FROM members m
    JOIN users u ON m.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['error'] = 'Member record not found. Please contact administrator.';
    redirect('../logout.php');
}

$memberId = $member['id'];

// Get statistics
$stats = [];

// Current loans
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')");
$stmt->execute([$memberId]);
$stats['current_loans'] = $stmt->fetch()['count'];

// Total loans
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status = 'Returned'");
$stmt->execute([$memberId]);
$stats['total_loans'] = $stmt->fetch()['count'];

// Overdue books
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status = 'Overdue'");
$stmt->execute([$memberId]);
$stats['overdue_books'] = $stmt->fetch()['count'];

// Outstanding fines
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE member_id = ? AND status = 'Unpaid'");
$stmt->execute([$memberId]);
$stats['outstanding_fines'] = $stmt->fetch()['total'];

// Pending reservations
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservations WHERE member_id = ? AND status IN ('Pending', 'Ready')");
$stmt->execute([$memberId]);
$stats['pending_reservations'] = $stmt->fetch()['count'];

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$stats['unread_notifications'] = $stmt->fetch()['count'];

// Get current loans details
$stmt = $pdo->prepare("
    SELECT l.*, b.title as book_title, bc.accession_number
    FROM loans l
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    WHERE l.member_id = ? AND l.status IN ('Borrowed', 'Overdue')
    ORDER BY l.due_date ASC
    LIMIT 5
");
$stmt->execute([$memberId]);
$currentLoans = $stmt->fetchAll();

// Get recent notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentNotifications = $stmt->fetchAll();

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user"></i> My Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Dashboard
            </div>
        </div>
        <div class="page-actions">
            <span class="text-muted">Welcome back, <?php echo $member['name']; ?>!</span>
        </div>
    </div>

    <!-- Member Profile Summary -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-center gap-3 member-profile-summary">
                        <?php if ($member['profile_image']): ?>
                            <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $member['profile_image']; ?>" 
                                 alt="<?php echo $member['name']; ?>" 
                                 style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:32px;color:white;">
                                <?php echo getInitials($member['name']); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h4><?php echo $member['name']; ?></h4>
                            <p class="text-muted">
                                Member #: <?php echo $member['member_number']; ?> | 
                                Type: <?php echo $member['membership_type']; ?> | 
                                Status: <?php echo getStatusBadge($member['status'], 'member'); ?>
                            </p>
                            <p class="text-muted">
                                <?php echo $member['email']; ?> | 
                                <?php echo $member['phone'] ?: 'No phone'; ?>
                            </p>
                        </div>
                        <div class="ml-auto">
                            <a href="profile.php" class="btn btn-primary">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Current Loans</div>
                    <div class="stat-value"><?php echo $stats['current_loans']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Books Read</div>
                    <div class="stat-value"><?php echo $stats['total_loans']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Overdue Books</div>
                    <div class="stat-value"><?php echo $stats['overdue_books']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Outstanding Fines</div>
                    <div class="stat-value"><?php echo formatCurrency($stats['outstanding_fines']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Pending Reservations</div>
                    <div class="stat-value"><?php echo $stats['pending_reservations']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Unread Notifications</div>
                    <div class="stat-value"><?php echo $stats['unread_notifications']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Loans -->
    <div class="row mt-3">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-hand-holding-heart"></i> Current Loans</h5>
                    <a href="borrowed-books.php" class="btn btn-sm btn-info">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($currentLoans)): ?>
                        <p class="text-muted text-center">No current loans</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentLoans as $loan): ?>
                                        <tr>
                                            <td><?php echo $loan['book_title']; ?></td>
                                            <td>
                                                <?php echo formatDate($loan['due_date']); ?>
                                                <?php if ($loan['status'] === 'Overdue'): ?>
                                                    <span class="badge badge-danger">
                                                        <?php echo calculateOverdueDays($loan['due_date']); ?> days overdue
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($loan['status'], 'loan'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Notifications -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bell"></i> Recent Notifications</h5>
                    <a href="notifications.php" class="btn btn-sm btn-info">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentNotifications)): ?>
                        <p class="text-muted text-center">No notifications</p>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                            <div class="d-flex justify-between align-center mb-2 p-2" style="background:<?php echo $notification['is_read'] ? 'transparent' : 'var(--gray-100)'; ?>;border-radius:8px;">
                                <div>
                                    <div style="font-weight:<?php echo $notification['is_read'] ? 'normal' : 'bold'; ?>;">
                                        <?php echo $notification['title']; ?>
                                    </div>
                                    <small class="text-muted"><?php echo substr($notification['message'], 0, 60) . (strlen($notification['message']) > 60 ? '...' : ''); ?></small>
                                </div>
                                <div>
                                    <span class="badge badge-<?php 
                                        echo $notification['type'] === 'success' ? 'success' : 
                                            ($notification['type'] === 'warning' ? 'warning' : 
                                            ($notification['type'] === 'error' ? 'danger' : 'info')); 
                                    ?>">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-rocket"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="catalog.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search Catalog
                        </a>
                        <a href="borrowed-books.php" class="btn btn-info">
                            <i class="fas fa-hand-holding-heart"></i> My Books
                        </a>
                        <a href="reservations.php" class="btn btn-warning">
                            <i class="fas fa-clock"></i> My Reservations
                        </a>
                        <a href="fines.php" class="btn btn-danger">
                            <i class="fas fa-exclamation-triangle"></i> My Fines
                        </a>
                        <a href="profile.php" class="btn btn-secondary">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="../change-password.php" class="btn btn-secondary">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../includes/footer.php'; ?>