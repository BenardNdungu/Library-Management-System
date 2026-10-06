<?php
/**
 * Member Fines
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'My Fines';

// Get member id
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([getCurrentUserId()]);
$member = $stmt->fetch();
$memberId = $member['id'] ?? 0;

// Get filter
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = ["f.member_id = ?"];
$params = [$memberId];

if ($status) {
    $where[] = "f.status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM fines f
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
    SELECT f.*, b.title as book_title, l.due_date,
           DATEDIFF(l.return_date, l.due_date) as overdue_days
    FROM fines f
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

// Get totals
$totalUnpaid = 0;
$totalPaid = 0;
$totalWaived = 0;

$stmt = $pdo->prepare("SELECT status, COALESCE(SUM(amount), 0) as total FROM fines WHERE member_id = ? GROUP BY status");
$stmt->execute([$memberId]);
while ($row = $stmt->fetch()) {
    if ($row['status'] === 'Unpaid') $totalUnpaid = $row['total'];
    if ($row['status'] === 'Paid') $totalPaid = $row['total'];
    if ($row['status'] === 'Waived') $totalWaived = $row['total'];
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-exclamation-triangle"></i> My Fines</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Fines
            </div>
        </div>
        <div class="page-actions">
            <a href="../admin/payments/create.php?member_id=<?php echo $memberId; ?>" class="btn btn-success">
                <i class="fas fa-coins"></i> Make Payment
            </a>
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
                    <div class="stat-label">Unpaid</div>
                    <div class="stat-value"><?php echo formatCurrency($totalUnpaid); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Paid</div>
                    <div class="stat-value"><?php echo formatCurrency($totalPaid); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-hand-peace"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Waived</div>
                    <div class="stat-value"><?php echo formatCurrency($totalWaived); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="fines.php" class="d-flex flex-wrap gap-2 align-center">
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
                <a href="fines.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Fines Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
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
                                <td colspan="6" class="text-center text-muted">No fines found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fines as $fine): ?>
                                <tr>
                                    <td><?php echo $fine['book_title']; ?></td>
                                    <td><strong><?php echo formatCurrency($fine['amount']); ?></strong></td>
                                    <td><?php echo $fine['reason']; ?></td>
                                    <td><?php echo getStatusBadge($fine['status'], 'fine'); ?></td>
                                    <td><?php echo formatDate($fine['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($fine['status'] === 'Unpaid'): ?>
                                                <a href="../admin/payments/create.php?fine_id=<?php echo $fine['id']; ?>&member_id=<?php echo $memberId; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-coins"></i> Pay
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
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>" class="page-link">
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