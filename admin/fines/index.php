<?php
/**
 * Fine Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Fine Management';

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($status) {
    $where[] = "f.status = ?";
    $params[] = $status;
}

if ($member_id) {
    $where[] = "f.member_id = ?";
    $params[] = $member_id;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM fines f
    JOIN members m ON f.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN lend l ON f.loan_id = l.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalFines = $stmt->fetch()['total'];
$totalPages = ceil($totalFines / $limit);

// Get fines
$sql = "
    SELECT f.*,
           m.member_number,
           u.name as member_name,
           b.title as book_title,
           l.id as loan_id,
           l.due_date
    FROM fines f
    JOIN members m ON f.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN lend l ON f.loan_id = l.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    $whereClause
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fines = $stmt->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM fines GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Get total amounts
$totalUnpaid = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE status = 'Unpaid'")->fetch()['total'];
$totalPaid = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE status = 'Paid'")->fetch()['total'];

// Handle waive fine
if (isset($_GET['waive']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $fineId = (int) $_GET['waive'];
    
    try {
        $stmt = $pdo->prepare("UPDATE fines SET status = 'Waived' WHERE id = ?");
        $stmt->execute([$fineId]);
        createAuditLog($pdo, getCurrentUserId(), 'waive_fine', 'fines', $fineId, 'Waived fine ID: ' . $fineId);
        $_SESSION['success'] = 'Fine waived successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
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
            <h1><i class="fas fa-exclamation-triangle"></i> Fine Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Fines
            </div>
        </div>
        <div class="page-actions">
            <a href="../payments/index.php" class="btn btn-success">
                <i class="fas fa-coins"></i> Record Payment
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
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Unpaid" <?php echo $status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Waived" <?php echo $status === 'Waived' ? 'selected' : ''; ?>>Waived</option>
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
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Unpaid Fines</div>
                    <div class="stat-value"><?php echo $statusCounts['Unpaid'] ?? 0; ?></div>
                    <div class="stat-label">Total: <?php echo formatCurrency($totalUnpaid); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Paid Fines</div>
                    <div class="stat-value"><?php echo $statusCounts['Paid'] ?? 0; ?></div>
                    <div class="stat-label">Total: <?php echo formatCurrency($totalPaid); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-hand-peace"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Waived Fines</div>
                    <div class="stat-value"><?php echo $statusCounts['Waived'] ?? 0; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fines Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fines)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No fines found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fines as $fine): ?>
                                <tr>
                                    <td>#<?php echo $fine['id']; ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo $fine['member_name']; ?></div>
                                            <small class="text-muted"><?php echo $fine['member_number']; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo $fine['book_title']; ?></td>
                                    <td><strong><?php echo formatCurrency($fine['amount']); ?></strong></td>
                                    <td><?php echo $fine['reason']; ?></td>
                                    <td><?php echo getStatusBadge($fine['status'], 'fine'); ?></td>
                                    <td><?php echo formatDate($fine['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($fine['status'] === 'Unpaid'): ?>
                                                <a href="../payments/create.php?fine_id=<?php echo $fine['id']; ?>&member_id=<?php echo $fine['member_id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-coins"></i> Pay
                                                </a>
                                                <a href="index.php?waive=<?php echo $fine['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-secondary"
                                                   data-confirm="Are you sure you want to waive this fine?">
                                                    <i class="fas fa-hand-peace"></i> Waive
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($fine['status'] === 'Paid'): ?>
                                                <a href="../payments/index.php?fine_id=<?php echo $fine['id']; ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-receipt"></i> View Payment
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
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
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