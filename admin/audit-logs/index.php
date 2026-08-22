<?php
/**
 * Audit Logs
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'Audit Logs';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR al.action LIKE ? OR al.description LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action) {
    $where[] = "al.action = ?";
    $params[] = $action;
}

if ($user_id) {
    $where[] = "al.user_id = ?";
    $params[] = $user_id;
}

if ($start_date) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $end_date;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalLogs = $stmt->fetch()['total'];
$totalPages = ceil($totalLogs / $limit);

// Get audit logs
$sql = "
    SELECT al.*, u.name as user_name, u.username
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = [];
$stmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
while ($row = $stmt->fetch()) {
    $actions[] = $row['action'];
}

// Get users for filter
$users = [];
$stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
while ($row = $stmt->fetch()) {
    $users[] = $row;
}

// Handle clear logs
if (isset($_GET['clear']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $days = (int) ($_GET['days'] ?? 30);
    
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $_SESSION['success'] = 'Audit logs older than ' . $days . ' days cleared.';
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
            <h1><i class="fas fa-history"></i> Audit Logs</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Audit Logs
            </div>
        </div>
        <div class="page-actions no-print">
            <div class="dropdown">
                <button class="btn btn-danger dropdown-toggle" data-toggle="dropdown">
                    <i class="fas fa-trash"></i> Clear Logs
                </button>
                <div class="dropdown-menu">
                    <a href="index.php?clear=1&days=30&csrf_token=<?php echo generateCsrfToken(); ?>" 
                       class="dropdown-item" data-confirm="Delete logs older than 30 days?">
                        <i class="fas fa-calendar"></i> Older than 30 days
                    </a>
                    <a href="index.php?clear=1&days=90&csrf_token=<?php echo generateCsrfToken(); ?>" 
                       class="dropdown-item" data-confirm="Delete logs older than 90 days?">
                        <i class="fas fa-calendar"></i> Older than 90 days
                    </a>
                    <a href="index.php?clear=1&days=180&csrf_token=<?php echo generateCsrfToken(); ?>" 
                       class="dropdown-item" data-confirm="Delete logs older than 180 days?">
                        <i class="fas fa-calendar"></i> Older than 180 days
                    </a>
                    <a href="index.php?clear=1&days=365&csrf_token=<?php echo generateCsrfToken(); ?>" 
                       class="dropdown-item" data-confirm="Delete logs older than 1 year?">
                        <i class="fas fa-calendar"></i> Older than 1 year
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="flex:2; min-width:200px;">
                    <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo $search; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="action" class="form-control">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act): ?>
                            <option value="<?php echo $act; ?>" <?php echo $action === $act ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $act)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="user_id" class="form-control">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id === $user['id'] ? 'selected' : ''; ?>>
                                <?php echo $user['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <input type="date" name="start_date" class="form-control" placeholder="Start Date" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <input type="date" name="end_date" class="form-control" placeholder="End Date" value="<?php echo $end_date; ?>">
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

    <!-- Logs Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No audit logs found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>#<?php echo $log['id']; ?></td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <?php echo $log['user_name']; ?>
                                            <small class="text-muted d-block"><?php echo $log['username']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">System/Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo ucwords(str_replace('_', ' ', $log['action'])); ?></span>
                                    </td>
                                    <td><?php echo $log['table_name'] ?: 'N/A'; ?></td>
                                    <td><?php echo $log['record_id'] ?: 'N/A'; ?></td>
                                    <td><?php echo $log['description'] ?: 'N/A'; ?></td>
                                    <td><?php echo $log['ip_address'] ?: 'N/A'; ?></td>
                                    <td><?php echo formatDate($log['created_at'], 'Y-m-d H:i:s'); ?></td>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&action=<?php echo $action; ?>&user_id=<?php echo $user_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&action=<?php echo $action; ?>&user_id=<?php echo $user_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&action=<?php echo $action; ?>&user_id=<?php echo $user_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="page-link">
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