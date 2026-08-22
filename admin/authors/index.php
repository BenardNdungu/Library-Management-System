<?php
/**
 * Author Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Author Management';

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
$countSql = "SELECT COUNT(*) as total FROM authors $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalAuthors = $stmt->fetch()['total'];
$totalPages = ceil($totalAuthors / $limit);

// Get authors
$sql = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM books WHERE author_id = a.id) as book_count
    FROM authors a
    $whereClause
    ORDER BY a.name ASC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$authors = $stmt->fetchAll();

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $authorId = (int) $_GET['delete'];
    
    // Check if author has books
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE author_id = ?");
    $stmt->execute([$authorId]);
    $bookCount = $stmt->fetch()['count'];
    
    if ($bookCount > 0) {
        $_SESSION['error'] = 'Cannot delete author with ' . $bookCount . ' associated book(s). Remove or reassign books first.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM authors WHERE id = ?");
        $stmt->execute([$authorId]);
        createAuditLog($pdo, getCurrentUserId(), 'delete_author', 'authors', $authorId, 'Deleted author ID: ' . $authorId);
        $_SESSION['success'] = 'Author deleted successfully.';
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
            <h1><i class="fas fa-user-edit"></i> Author Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Authors
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Author
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
                    <input type="text" name="search" class="form-control" placeholder="Search authors..." value="<?php echo $search; ?>">
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

    <!-- Authors Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Biography</th>
                            <th>Books</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($authors)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No authors found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($authors as $author): ?>
                                <tr>
                                    <td>#<?php echo $author['id']; ?></td>
                                    <td><strong><?php echo $author['name']; ?></strong></td>
                                    <td><?php echo substr($author['biography'] ?? '', 0, 100) . (strlen($author['biography'] ?? '') > 100 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $author['book_count']; ?> books</span>
                                    </td>
                                    <td><?php echo formatDate($author['created_at']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="view.php?id=<?php echo $author['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $author['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="index.php?delete=<?php echo $author['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               data-confirm="Are you sure you want to delete this author?<?php echo $author['book_count'] > 0 ? ' It has ' . $author['book_count'] . ' associated book(s).' : ''; ?>">
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