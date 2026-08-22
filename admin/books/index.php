<?php
/**
 * Book Management - List Books
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Book Management';

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$author = isset($_GET['author']) ? (int) $_GET['author'] : 0;
$availability = isset($_GET['availability']) ? sanitizeInput($_GET['availability']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(b.title LIKE ? OR b.isbn LIKE ? OR b.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where[] = "b.category_id = ?";
    $params[] = $category;
}

if ($author) {
    $where[] = "b.author_id = ?";
    $params[] = $author;
}

if ($availability === 'available') {
    $where[] = "b.available_copies > 0";
} elseif ($availability === 'unavailable') {
    $where[] = "b.available_copies = 0";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM books b $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalBooks = $stmt->fetch()['total'];
$totalPages = ceil($totalBooks / $limit);

// Get books
$sql = "
    SELECT b.*, 
           c.name as category_name,
           a.name as author_name,
           p.name as publisher_name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN publishers p ON b.publisher_id = p.id
    $whereClause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get categories for filter
$categories = [];
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $stmt->fetch()) {
    $categories[] = $row;
}

// Get authors for filter
$authors = [];
$stmt = $pdo->query("SELECT id, name FROM authors ORDER BY name");
while ($row = $stmt->fetch()) {
    $authors[] = $row;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $bookId = (int) $_GET['delete'];
    
    try {
        // Check if book has copies
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM book_copies WHERE book_id = ?");
        $stmt->execute([$bookId]);
        $copyCount = $stmt->fetch()['count'];
        
        if ($copyCount > 0) {
            // Only delete if no copies or confirm
            if (isset($_GET['force'])) {
                // Delete copies first
                $stmt = $pdo->prepare("DELETE FROM book_copies WHERE book_id = ?");
                $stmt->execute([$bookId]);
            } else {
                $_SESSION['error'] = 'Cannot delete book with existing copies. Delete copies first or use force delete.';
                redirect('index.php');
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$bookId]);
        createAuditLog($pdo, getCurrentUserId(), 'delete_book', 'books', $bookId, 'Deleted book ID: ' . $bookId);
        $_SESSION['success'] = 'Book deleted successfully.';
        redirect('index.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Cannot delete book. It may have associated records.';
        redirect('index.php');
    }
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageScripts = ['books.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book"></i> Book Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Books
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Book
            </a>
            <a href="copies.php" class="btn btn-info">
                <i class="fas fa-copy"></i> Manage Copies
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
                    <input type="text" name="search" class="form-control" placeholder="Search by title, ISBN, description..." value="<?php echo $search; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="category" class="form-control">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="author" class="form-control">
                        <option value="0">All Authors</option>
                        <?php foreach ($authors as $auth): ?>
                            <option value="<?php echo $auth['id']; ?>" <?php echo $author == $auth['id'] ? 'selected' : ''; ?>>
                                <?php echo $auth['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="availability" class="form-control">
                        <option value="">All Books</option>
                        <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="unavailable" <?php echo $availability === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
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

    <!-- Books Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Author</th>
                            <th>Copies</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No books found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>#<?php echo $book['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-1">
                                            <?php if ($book['cover_image']): ?>
                                                <img src="<?php echo APP_URL; ?>/uploads/books/<?php echo $book['cover_image']; ?>" 
                                                     alt="<?php echo $book['title']; ?>" 
                                                     style="width:40px;height:50px;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <div style="width:40px;height:50px;background:var(--gray-300);border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--gray-500);">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div><?php echo $book['title']; ?></div>
                                                <small class="text-muted"><?php echo $book['publisher_name'] ?? 'N/A'; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $book['isbn'] ?: 'N/A'; ?></td>
                                    <td><?php echo $book['category_name'] ?: 'Uncategorized'; ?></td>
                                    <td><?php echo $book['author_name'] ?: 'Unknown'; ?></td>
                                    <td><?php echo $book['total_copies']; ?></td>
                                    <td>
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $book['available_copies']; ?> available</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="view.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="copies.php?book_id=<?php echo $book['id']; ?>" class="btn btn-sm btn-secondary" title="Manage Copies">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <a href="index.php?delete=<?php echo $book['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               data-confirm="Are you sure you want to delete this book?">
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&category=<?php echo $category; ?>&author=<?php echo $author; ?>&availability=<?php echo $availability; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&category=<?php echo $category; ?>&author=<?php echo $author; ?>&availability=<?php echo $availability; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&category=<?php echo $category; ?>&author=<?php echo $author; ?>&availability=<?php echo $availability; ?>" class="page-link">
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