<?php
/**
 * Member Borrowed Books
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'My Borrowed Books';

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
$where = ["l.member_id = ?"];
$params = [$memberId];

if ($status) {
    $where[] = "l.status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM lend l
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totallend = $stmt->fetch()['total'];
$totalPages = ceil($totallend / $limit);

// Get lend
$sql = "
    SELECT l.*, b.title as book_title, b.isbn, bc.accession_number, bc.barcode
    FROM lend l
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    $whereClause
    ORDER BY l.due_date ASC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lend = $stmt->fetchAll();

// Get status counts
$statusCounts = [
    'Borrowed' => 0,
    'Overdue' => 0,
    'Returned' => 0,
    'Lost' => 0
];
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM lend WHERE member_id = ? GROUP BY status");
$stmt->execute([$memberId]);
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-heart"></i> My Borrowed Books</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Borrowed Books
            </div>
        </div>
        <div class="page-actions">
            <a href="catalog.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Browse Catalog
            </a>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-3">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Borrowed</div>
                    <div class="stat-value"><?php echo $statusCounts['Borrowed']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value"><?php echo $statusCounts['Overdue']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Returned</div>
                    <div class="stat-value"><?php echo $statusCounts['Returned']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Lost</div>
                    <div class="stat-value"><?php echo $statusCounts['Lost']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="borrowed-books.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Borrowed" <?php echo $status === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="Returned" <?php echo $status === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="Lost" <?php echo $status === 'Lost' ? 'selected' : ''; ?>>Lost</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="borrowed-books.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- lend Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Accession</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lend)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No lend found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lend as $loan): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div><?php echo $loan['book_title']; ?></div>
                                            <?php if ($loan['isbn']): ?>
                                                <small class="text-muted">ISBN: <?php echo $loan['isbn']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $loan['accession_number']; ?></td>
                                    <td><?php echo formatDate($loan['issue_date']); ?></td>
                                    <td>
                                        <?php echo formatDate($loan['due_date']); ?>
                                        <?php if ($loan['status'] === 'Overdue'): ?>
                                            <span class="badge badge-danger">
                                                <?php echo calculateOverdueDays($loan['due_date']); ?> days overdue
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo getStatusBadge($loan['status'], 'loan'); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="../admin/books/view.php?id=<?php echo $loan['book_id'] ?? 0; ?>" class="btn btn-sm btn-info" title="View Book">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($loan['status'] === 'Borrowed' || $loan['status'] === 'Overdue'): ?>
                                                <a href="../admin/returns/index.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success" title="Return">
                                                    <i class="fas fa-undo-alt"></i>
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