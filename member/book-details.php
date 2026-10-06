<?php
/**
 * Book Details for Members
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'Book Details';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$bookId) {
    $_SESSION['error'] = 'Book ID required';
    redirect('catalog.php');
}

// Get book details
$stmt = $pdo->prepare("
    SELECT b.*, 
           c.name as category_name,
           c.description as category_description,
           a.name as author_name,
           a.biography as author_biography,
           p.name as publisher_name,
           p.email as publisher_email,
           p.website as publisher_website
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
    redirect('catalog.php');
}

// Get member info
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([getCurrentUserId()]);
$member = $stmt->fetch();
$memberId = $member['id'] ?? 0;

// Check if member has reservation
$hasReservation = false;
if ($memberId) {
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE member_id = ? AND book_id = ? AND status IN ('Pending', 'Ready') AND expiry_date >= CURDATE()");
    $stmt->execute([$memberId, $bookId]);
    $hasReservation = (bool) $stmt->fetch();
}

// Get available copies
$stmt = $pdo->prepare("SELECT * FROM book_copies WHERE book_id = ? AND status = 'Available'");
$stmt->execute([$bookId]);
$availableCopies = $stmt->fetchAll();

// Get all copies
$stmt = $pdo->prepare("SELECT * FROM book_copies WHERE book_id = ?");
$stmt->execute([$bookId]);
$allCopies = $stmt->fetchAll();

// Get member's loan status for this book
$hasLoan = false;
if ($memberId) {
    $stmt = $pdo->prepare("
        SELECT l.id FROM lend l
        JOIN book_copies bc ON l.book_copy_id = bc.id
        WHERE l.member_id = ? AND bc.book_id = ? AND l.status IN ('Borrowed', 'Overdue')
    ");
    $stmt->execute([$memberId, $bookId]);
    $hasLoan = (bool) $stmt->fetch();
}

// Handle reservation
if (isset($_GET['reserve']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);

    $result = createPickupReservation($pdo, $memberId, $bookId);
    $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    redirect('book-details.php?id=' . $bookId);
}

// Handle cancel reservation
if (isset($_GET['cancel_reservation']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    
    if ($memberId) {
        $stmt = $pdo->prepare("
            UPDATE reservations SET status = 'Cancelled' 
            WHERE member_id = ? AND book_id = ? AND status IN ('Pending', 'Ready')
        ");
        $stmt->execute([$memberId, $bookId]);
        $_SESSION['success'] = 'Reservation cancelled successfully.';
    }
    redirect('book-details.php?id=' . $bookId);
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
            <h1><i class="fas fa-book"></i> Book Details</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / <a href="catalog.php">Catalog</a> / Details
            </div>
        </div>
        <div class="page-actions">
            <a href="catalog.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Catalog
            </a>
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
                                <div style="width:100%;height:250px;background:var(--gray-300);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:64px;color:var(--gray-500);">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-9">
                            <h2><?php echo $book['title']; ?></h2>
                            <p class="text-muted">
                                <?php if ($book['author_name']): ?>
                                    <strong>Author:</strong> <?php echo $book['author_name']; ?><br>
                                <?php endif; ?>
                                <?php if ($book['category_name']): ?>
                                    <strong>Category:</strong> <?php echo $book['category_name']; ?><br>
                                <?php endif; ?>
                                <?php if ($book['isbn']): ?>
                                    <strong>ISBN:</strong> <?php echo $book['isbn']; ?><br>
                                <?php endif; ?>
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
                                    <strong>Shelf Location:</strong> <?php echo $book['shelf_location']; ?>
                                <?php endif; ?>
                            </p>
                            <div class="mt-2">
                                <?php if ($book['available_copies'] > 0): ?>
                                    <span class="badge badge-success" style="font-size:14px;padding:8px 16px;">
                                        <i class="fas fa-check-circle"></i> Available (<?php echo $book['available_copies']; ?> copies)
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size:14px;padding:8px 16px;">
                                        <i class="fas fa-times-circle"></i> Unavailable
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <?php if ($hasLoan): ?>
                                    <span class="badge badge-info" style="font-size:14px;padding:8px 16px;">
                                        <i class="fas fa-check"></i> You have borrowed this book
                                    </span>
                                <?php elseif (!$hasReservation): ?>
                                    <a href="book-details.php?id=<?php echo $bookId; ?>&reserve=<?php echo $bookId; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                       class="btn btn-success"
                                       data-confirm="Request this book for pickup at the library?">
                                        <i class="fas fa-hand-holding-heart"></i> Reserve for Pickup
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-info" style="font-size:14px;padding:8px 16px;">
                                        <i class="fas fa-clock"></i> Pickup request active
                                    </span>
                                    <a href="book-details.php?id=<?php echo $bookId; ?>&cancel_reservation=<?php echo $bookId; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                       class="btn btn-danger btn-sm"
                                       data-confirm="Cancel your pickup request?">
                                        <i class="fas fa-times"></i> Cancel Request
                                    </a>
                                <?php endif; ?>
                            </div>
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
                            <?php if ($book['author_name'] && $book['author_name']): ?>
                                <a href="catalog.php?author=<?php echo $book['author_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-search"></i> More books by this author
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-4">
            <!-- Availability -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-copy"></i> Availability</h5>
                </div>
                <div class="card-body">
                    <p><strong>Total Copies:</strong> <?php echo count($allCopies); ?></p>
                    <p><strong>Available:</strong> <?php echo count($availableCopies); ?></p>
                    <p><strong>Borrowed:</strong> <?php 
                        $borrowed = array_filter($allCopies, function($c) { return $c['status'] === 'Borrowed'; });
                        echo count($borrowed);
                    ?></p>
                    <p><strong>Reserved:</strong> <?php 
                        $reserved = array_filter($allCopies, function($c) { return $c['status'] === 'Reserved'; });
                        echo count($reserved);
                    ?></p>
                    <?php if (!empty($availableCopies)): ?>
                        <div class="mt-2">
                            <h6>Available Copies:</h6>
                            <?php foreach ($availableCopies as $copy): ?>
                                <span class="badge badge-success"><?php echo $copy['accession_number']; ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Publisher Info -->
            <?php if ($book['publisher_name']): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> Publisher</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo $book['publisher_name']; ?></p>
                        <?php if ($book['publisher_email']): ?>
                            <p><strong>Email:</strong> <?php echo $book['publisher_email']; ?></p>
                        <?php endif; ?>
                        <?php if ($book['publisher_website']): ?>
                            <p><strong>Website:</strong> <a href="<?php echo $book['publisher_website']; ?>" target="_blank"><?php echo $book['publisher_website']; ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include_once '../includes/footer.php'; ?>