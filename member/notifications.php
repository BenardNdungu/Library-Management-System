<?php
/**
 * Member Notifications
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'My Notifications';

$userId = getCurrentUserId();

// Get filter
$is_read = isset($_GET['is_read']) ? (int) $_GET['is_read'] : -1;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = ["user_id = ?"];
$params = [$userId];

if ($is_read >= 0) {
    $where[] = "is_read = ?";
    $params[] = $is_read;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get total count
$countSql = "SELECT COUNT(*) as total FROM notifications $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalNotifications = $stmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $limit);

// Get notifications
$sql = "
    SELECT * FROM notifications
    $whereClause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Handle mark as read
if (isset($_GET['mark_read']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $notificationId = (int) $_GET['mark_read'];
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    $_SESSION['success'] = 'Notification marked as read.';
    redirect('notifications.php');
}

// Handle mark all as read
if (isset($_GET['mark_all_read']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    $_SESSION['success'] = 'All notifications marked as read.';
    redirect('notifications.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> My Notifications</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Notifications
            </div>
        </div>
        <div class="page-actions">
            <a href="notifications.php?mark_all_read=1&csrf_token=<?php echo generateCsrfToken(); ?>" 
               class="btn btn-info"
               data-confirm="Mark all notifications as read?">
                <i class="fas fa-check-double"></i> Mark All Read
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
            <form method="GET" action="notifications.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="is_read" class="form-control">
                        <option value="-1">All</option>
                        <option value="0" <?php echo $is_read === 0 ? 'selected' : ''; ?>>Unread</option>
                        <option value="1" <?php echo $is_read === 1 ? 'selected' : ''; ?>>Read</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="notifications.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <p class="text-muted text-center">No notifications found</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="d-flex justify-between align-center mb-3 p-3" 
                         style="background:<?php echo $notification['is_read'] ? 'transparent' : 'var(--gray-100)'; ?>;border-radius:8px;border-left:4px solid <?php 
                            echo $notification['type'] === 'success' ? 'var(--success)' : 
                                ($notification['type'] === 'warning' ? 'var(--warning)' : 
                                ($notification['type'] === 'error' ? 'var(--danger)' : 'var(--info)')); 
                         ?>;">
                        <div style="flex:1;">
                            <div style="font-weight:<?php echo $notification['is_read'] ? 'normal' : 'bold'; ?>;">
                                <?php echo $notification['title']; ?>
                            </div>
                            <div class="text-muted small"><?php echo $notification['message']; ?></div>
                            <div class="text-muted" style="font-size:11px;">
                                <?php echo formatDate($notification['created_at'], 'Y-m-d H:i'); ?>
                            </div>
                        </div>
                        <div class="ml-3 d-flex gap-1 flex-wrap">
                            <?php if (!$notification['is_read']): ?>
                                <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Read
                                </a>
                            <?php endif; ?>
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&is_read=<?php echo $is_read; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&is_read=<?php echo $is_read; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&is_read=<?php echo $is_read; ?>" class="page-link">
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

<?php include_once '../includes/footer.php'; ?>