<?php
/**
 * Loan Management - List Loans
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Loan Management';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR m.member_number LIKE ? OR b.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "l.status = ?";
    $params[] = $status;
}

if ($member_id) {
    $where[] = "l.member_id = ?";
    $params[] = $member_id;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalLoans = $stmt->fetch()['total'];
$totalPages = ceil($totalLoans / $limit);

// Get loans
$sql = "
    SELECT l.*, 
           m.member_number,
           u.name as member_name,
           u.email as member_email,
           b.title as book_title,
           bc.accession_number,
           bc.barcode,
           u2.name as issued_by_name,
           u3.name as returned_to_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    JOIN users u2 ON l.issued_by = u2.id
    LEFT JOIN users u3 ON l.returned_to = u3.id
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM loans GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageScripts = ['loans.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-heart"></i> Loan Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Loans
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Issue Book
            </a>
            <a href="../returns/index.php" class="btn btn-success">
                <i class="fas fa-undo-alt"></i> Return Book
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
                    <input type="text" name="search" class="form-control" placeholder="Search by member, book, accession..." value="<?php echo $search; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Borrowed" <?php echo $status === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="Returned" <?php echo $status === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="Lost" <?php echo $status === 'Lost' ? 'selected' : ''; ?>>Lost</option>
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
                <div class="stat-icon warning">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Borrowed</div>
                    <div class="stat-value"><?php echo $statusCounts['Borrowed'] ?? 0; ?></div>
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
                    <div class="stat-value"><?php echo $statusCounts['Overdue'] ?? 0; ?></div>
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
                    <div class="stat-value"><?php echo $statusCounts['Returned'] ?? 0; ?></div>
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
                    <div class="stat-value"><?php echo $statusCounts['Lost'] ?? 0; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loans Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Copy</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No loans found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td>#<?php echo $loan['id']; ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo $loan['member_name']; ?></div>
                                            <small class="text-muted"><?php echo $loan['member_number']; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo $loan['book_title']; ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo $loan['accession_number']; ?></div>
                                            <small class="text-muted"><?php echo $loan['barcode'] ?: 'No barcode'; ?></small>
                                        </div>
                                    </td>
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
                                            <a href="view.php?id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($loan['status'] === 'Borrowed' || $loan['status'] === 'Overdue'): ?>
                                                <a href="renew.php?id=<?php echo $loan['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-warning" 
                                                   title="Renew"
                                                   data-confirm="Are you sure you want to renew this loan?">
                                                    <i class="fas fa-redo"></i>
                                                </a>
                                                <a href="../returns/index.php?loan_id=<?php echo $loan['id']; ?>" 
                                                   class="btn btn-sm btn-success" 
                                                   title="Return">
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
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