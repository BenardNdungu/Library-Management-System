<?php
/**
 * Member Catalog - Search and Browse Books
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'Library Catalog';

$stmt = $pdo->prepare('SELECT id FROM members WHERE user_id = ?');
$stmt->execute([getCurrentUserId()]);
$member = $stmt->fetch();
$memberId = (int) ($member['id'] ?? 0);

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
           p.name as publisher_name,
           (SELECT COUNT(*) FROM reservations WHERE book_id = b.id AND member_id = ? AND status IN ('Pending', 'Ready') AND expiry_date >= CURDATE()) as has_reservation,
           (SELECT COUNT(*) FROM lend l JOIN book_copies bc ON l.book_copy_id = bc.id WHERE l.member_id = ? AND bc.book_id = b.id AND l.status IN ('Borrowed', 'Overdue')) as has_loan
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN publishers p ON b.publisher_id = p.id
    $whereClause
    ORDER BY b.title ASC
    LIMIT ? OFFSET ?
";
$paramsWithMember = array_merge([$memberId, $memberId], $params, [$limit, $offset]);

$stmt = $pdo->prepare($sql);
$stmt->execute($paramsWithMember);
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

// Handle reservation
if (isset($_GET['reserve']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $bookId = (int) $_GET['reserve'];
    
    $memberId = $member['id'] ?? 0;
    if (!$memberId) {
        $stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
        $stmt->execute([getCurrentUserId()]);
        $member = $stmt->fetch();
        $memberId = $member['id'] ?? 0;
    }
    
    $result = createPickupReservation($pdo, $memberId, $bookId);
    $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    redirect('catalog.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$info = $_SESSION['info'] ?? '';
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['info']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-search"></i> Library Catalog</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Catalog
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($info): ?>
        <div class="alert alert-info"><?php echo $info; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="catalog.php" class="d-flex flex-wrap gap-2 align-center">
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
                        <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available Now</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="catalog.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="row">
        <?php if (empty($books)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted">
                        <i class="fas fa-book" style="font-size:48px;display:block;margin-bottom:16px;"></i>
                        <p>No books found matching your criteria.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($books as $book): ?>
                <div class="col-4">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex gap-2">
                                <?php if ($book['cover_image']): ?>
                                    <img src="<?php echo APP_URL; ?>/uploads/books/<?php echo $book['cover_image']; ?>" 
                                         alt="<?php echo $book['title']; ?>" 
                                         style="width:80px;height:100px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                                <?php else: ?>
                                    <div style="width:80px;height:100px;background:var(--gray-300);border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--gray-500);">
                                        <i class="fas fa-book" style="font-size:32px;"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;">
                                    <h6 class="mb-1"><?php echo $book['title']; ?></h6>
                                    <p class="text-muted small mb-1">
                                        <?php echo $book['author_name'] ?: 'Unknown Author'; ?>
                                    </p>
                                    <p class="text-muted small mb-1">
                                        <?php echo $book['category_name'] ?: 'Uncategorized'; ?>
                                        <?php if ($book['isbn']): ?>
                                            | ISBN: <?php echo $book['isbn']; ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-1">
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge badge-success">Available (<?php echo $book['available_copies']; ?> copies)</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Unavailable</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="book-details.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        <?php if ($book['has_loan']): ?>
                                            <span class="badge badge-info">Already borrowed</span>
                                        <?php elseif ($book['has_reservation']): ?>
                                            <span class="badge badge-info">Pickup request active</span>
                                        <?php else: ?>
                                            <a href="catalog.php?reserve=<?php echo $book['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                               class="btn btn-sm btn-success"
                                               data-confirm="Request this book for pickup at the library?">
                                                <i class="fas fa-hand-holding-heart"></i> Reserve for Pickup
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
</main>

<?php include_once '../includes/footer.php'; ?>