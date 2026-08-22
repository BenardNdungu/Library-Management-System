<?php
/**
 * Notification Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Notification Management';

// Get filter parameters
$is_read = isset($_GET['is_read']) ? (int) $_GET['is_read'] : -1;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($is_read >= 0) {
    $where[] = "is_read = ?";
    $params[] = $is_read;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM notifications $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalNotifications = $stmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $limit);

// Get notifications
$sql = "
    SELECT n.*, u.name as user_name
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    $whereClause
    ORDER BY n.created_at DESC
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
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notificationId]);
    $_SESSION['success'] = 'Notification marked as read.';
    redirect('index.php');
}

// Handle mark all as read
if (isset($_GET['mark_all_read']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1");
    $stmt->execute();
    $_SESSION['success'] = 'All notifications marked as read.';
    redirect('index.php');
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $notificationId = (int) $_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$notificationId]);
    createAuditLog($pdo, getCurrentUserId(), 'delete_notification', 'notifications', $notificationId, 'Deleted notification');
    $_SESSION['success'] = 'Notification deleted.';
    redirect('index.php');
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> Notification Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Notifications
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php?mark_all_read=1&csrf_token=<?php echo generateCsrfToken(); ?>" 
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
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
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
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Notifications Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No notifications found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <tr <?php echo !$notification['is_read'] ? 'style="font-weight:bold;"' : ''; ?>>
                                    <td>#<?php echo $notification['id']; ?></td>
                                    <td><?php echo $notification['user_name']; ?></td>
                                    <td><?php echo $notification['title']; ?></td>
                                    <td><?php echo substr($notification['message'], 0, 50) . (strlen($notification['message']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $notification['type'] === 'success' ? 'success' : 
                                                ($notification['type'] === 'warning' ? 'warning' : 
                                                ($notification['type'] === 'error' ? 'danger' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($notification['is_read']): ?>
                                            <span class="badge badge-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($notification['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if (!$notification['is_read']): ?>
                                                <a href="index.php?mark_read=<?php echo $notification['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-success" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="index.php?delete=<?php echo $notification['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               data-confirm="Delete this notification?">
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

<?php include_once '../../includes/footer.php'; ?>