<?php
/**
 * Publisher Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Publisher Management';

// Get filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "name LIKE ?";
    $params[] = "%$search%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM publishers $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalPublishers = $stmt->fetch()['total'];
$totalPages = ceil($totalPublishers / $limit);

// Get publishers
$sql = "
    SELECT p.*, 
           (SELECT COUNT(*) FROM books WHERE publisher_id = p.id) as book_count
    FROM publishers p
    $whereClause
    ORDER BY p.name ASC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$publishers = $stmt->fetchAll();

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $publisherId = (int) $_GET['delete'];
    
    // Check if publisher has books
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE publisher_id = ?");
    $stmt->execute([$publisherId]);
    $bookCount = $stmt->fetch()['count'];
    
    if ($bookCount > 0) {
        $_SESSION['error'] = 'Cannot delete publisher with ' . $bookCount . ' associated book(s). Remove or reassign books first.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM publishers WHERE id = ?");
        $stmt->execute([$publisherId]);
        createAuditLog($pdo, getCurrentUserId(), 'delete_publisher', 'publishers', $publisherId, 'Deleted publisher ID: ' . $publisherId);
        $_SESSION['success'] = 'Publisher deleted successfully.';
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
            <h1><i class="fas fa-building"></i> Publisher Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Publishers
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Publisher
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
                    <input type="text" name="search" class="form-control" placeholder="Search publishers..." value="<?php echo $search; ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Publishers Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Books</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($publishers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No publishers found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($publishers as $publisher): ?>
                                <tr>
                                    <td>#<?php echo $publisher['id']; ?></td>
                                    <td><strong><?php echo $publisher['name']; ?></strong></td>
                                    <td><?php echo $publisher['email'] ?: 'N/A'; ?></td>
                                    <td><?php echo $publisher['phone'] ?: 'N/A'; ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $publisher['book_count']; ?> books</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="edit.php?id=<?php echo $publisher['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="index.php?delete=<?php echo $publisher['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               data-confirm="Are you sure you want to delete this publisher?<?php echo $publisher['book_count'] > 0 ? ' It has ' . $publisher['book_count'] . ' associated book(s).' : ''; ?>">
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>" class="page-link">
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