<?php
/**
 * View Book Details
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Book Details';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$bookId) {
    $_SESSION['error'] = 'Book ID required';
    redirect('index.php');
}

// Get book details
$stmt = $pdo->prepare("
    SELECT b.*, 
           c.name as category_name,
           a.name as author_name,
           a.biography as author_biography,
           p.name as publisher_name,
           p.email as publisher_email,
           p.phone as publisher_phone
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN publishers p ON b.publisher_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    $_SESSION['error'] = 'Book not found';
    redirect('index.php');
}

// Get book copies
$stmt = $pdo->prepare("
    SELECT * FROM book_copies WHERE book_id = ? ORDER BY accession_number
");
$stmt->execute([$bookId]);
$copies = $stmt->fetchAll();

// Get copy counts by status
$statusCounts = [];
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count FROM book_copies WHERE book_id = ? GROUP BY status
");
$stmt->execute([$bookId]);
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Get borrowing history
$stmt = $pdo->prepare("
    SELECT l.*, 
           m.member_number, 
           u.name as member_name,
           u2.name as issued_by_name,
           u3.name as returned_to_name
    FROM lend l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN users u2 ON l.issued_by = u2.id
    LEFT JOIN users u3 ON l.returned_to = u3.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    WHERE bc.book_id = ?
    ORDER BY l.created_at DESC
    LIMIT 20
");
$stmt->execute([$bookId]);
$loanHistory = $stmt->fetchAll();

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book"></i> Book Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Books</a> / View
            </div>
        </div>
        <div class="page-actions">
            <a href="edit.php?id=<?php echo $bookId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Book
            </a>
            <a href="copies.php?book_id=<?php echo $bookId; ?>" class="btn btn-info">
                <i class="fas fa-copy"></i> Manage Copies
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Book Info -->
        <div class="col-8">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-3">
                            <?php if ($book['cover_image']): ?>
                                <img src="<?php echo APP_URL; ?>/uploads/books/<?php echo $book['cover_image']; ?>" 
                                     alt="<?php echo $book['title']; ?>" 
                                     style="width:100%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div style="width:100%;height:200px;background:var(--gray-300);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:48px;color:var(--gray-500);">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-9">
                            <h2><?php echo $book['title']; ?></h2>
                            <p class="text-muted">
                                <?php if ($book['isbn']): ?>
                                    <strong>ISBN:</strong> <?php echo $book['isbn']; ?><br>
                                <?php endif; ?>
                                <strong>Category:</strong> <?php echo $book['category_name'] ?: 'Uncategorized'; ?><br>
                                <strong>Author:</strong> <?php echo $book['author_name'] ?: 'Unknown'; ?><br>
                                <?php if ($book['publisher_name']): ?>
                                    <strong>Publisher:</strong> <?php echo $book['publisher_name']; ?><br>
                                <?php endif; ?>
                                <?php if ($book['publication_year']): ?>
                                    <strong>Year:</strong> <?php echo $book['publication_year']; ?><br>
                                <?php endif; ?>
                                <?php if ($book['edition']): ?>
                                    <strong>Edition:</strong> <?php echo $book['edition']; ?><br>
                                <?php endif; ?>
                                <strong>Language:</strong> <?php echo $book['language']; ?><br>
                                <?php if ($book['pages']): ?>
                                    <strong>Pages:</strong> <?php echo $book['pages']; ?><br>
                                <?php endif; ?>
                                <?php if ($book['shelf_location']): ?>
                                    <strong>Shelf:</strong> <?php echo $book['shelf_location']; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($book['description']): ?>
                        <div class="mt-3">
                            <h5>Description</h5>
                            <p><?php echo nl2br($book['description']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($book['author_biography']): ?>
                        <div class="mt-3">
                            <h5>About the Author</h5>
                            <p><?php echo nl2br($book['author_biography']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5>Copy Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="stat-card mb-2">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Available</div>
                            <div class="stat-value"><?php echo $statusCounts['Available'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="stat-card mb-2">
                        <div class="stat-icon warning">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Borrowed</div>
                            <div class="stat-value"><?php echo $statusCounts['Borrowed'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="stat-card mb-2">
                        <div class="stat-icon info">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Reserved</div>
                            <div class="stat-value"><?php echo $statusCounts['Reserved'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="stat-card mb-2">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Lost/Damaged</div>
                            <div class="stat-value"><?php echo ($statusCounts['Lost'] ?? 0) + ($statusCounts['Damaged'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon secondary">
                            <i class="fas fa-copy"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Total Copies</div>
                            <div class="stat-value"><?php echo $book['total_copies']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Copies -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-copy"></i> Book Copies</h5>
                    <a href="copies.php?book_id=<?php echo $bookId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Copy
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Accession</th>
                                    <th>Barcode</th>
                                    <th>Status</th>
                                    <th>Condition</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($copies)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No copies found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($copies as $copy): ?>
                                        <tr>
                                            <td><?php echo $copy['accession_number']; ?></td>
                                            <td><?php echo $copy['barcode'] ?: 'N/A'; ?></td>
                                            <td><?php echo getStatusBadge($copy['status'], 'copy'); ?></td>
                                            <td><?php echo $copy['condition']; ?></td>
                                            <td><?php echo $copy['location'] ?: 'N/A'; ?></td>
                                            <td>
                                                <a href="copy-edit.php?id=<?php echo $copy['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="copy-delete.php?id=<?php echo $copy['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   data-confirm="Are you sure you want to delete this copy?">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan History -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Borrowing History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Copy</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Issued By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($loanHistory)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No borrowing history</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($loanHistory as $loan): ?>
                                        <tr>
                                            <td><?php echo $loan['member_name'] . ' (' . $loan['member_number'] . ')'; ?></td>
                                            <td><?php echo $loan['book_copy_id']; ?></td>
                                            <td><?php echo formatDate($loan['issue_date']); ?></td>
                                            <td><?php echo formatDate($loan['due_date']); ?></td>
                                            <td><?php echo $loan['return_date'] ? formatDate($loan['return_date']) : '-'; ?></td>
                                            <td><?php echo getStatusBadge($loan['status'], 'loan'); ?></td>
                                            <td><?php echo $loan['issued_by_name']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>