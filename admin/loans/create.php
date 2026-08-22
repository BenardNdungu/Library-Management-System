<?php
/**
 * Issue Book (Create Loan)
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Issue Book';

$errors = [];
$formData = [];
$member = null;
$book = null;
$availableCopies = [];

// Get member details if provided
$memberId = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
if ($memberId) {
    $member = getMemberDetails($pdo, $memberId);
    if (!$member) {
        $errors['member'] = 'Member not found';
    } else {
        $canBorrow = canMemberBorrow($pdo, $memberId);
        if (!$canBorrow['canBorrow']) {
            $errors['member'] = $canBorrow['message'];
        }
        $currentLoans = getMemberCurrentLoans($pdo, $memberId);
    }
}

// Get book details if provided
$bookId = isset($_GET['book_id']) ? (int) $_GET['book_id'] : 0;
if ($bookId) {
    $book = getBookDetails($pdo, $bookId);
    if (!$book) {
        $errors['book'] = 'Book not found';
    } else {
        // Get available copies
        $stmt = $pdo->prepare("
            SELECT * FROM book_copies 
            WHERE book_id = ? AND status = 'Available'
            ORDER BY accession_number
        ");
        $stmt->execute([$bookId]);
        $availableCopies = $stmt->fetchAll();
        
        if (empty($availableCopies)) {
            $errors['book'] = 'No available copies for this book';
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $copyId = (int) ($_POST['copy_id'] ?? 0);
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');
    
    // Validate
    if (!$memberId) {
        $errors['member_id'] = 'Please select a member';
    } else {
        $member = getMemberDetails($pdo, $memberId);
        if (!$member) {
            $errors['member_id'] = 'Member not found';
        } else {
            $canBorrow = canMemberBorrow($pdo, $memberId);
            if (!$canBorrow['canBorrow']) {
                $errors['member_id'] = $canBorrow['message'];
            }
        }
    }
    
    if (!$copyId) {
        $errors['copy_id'] = 'Please select a copy';
    } else {
        if (!isCopyAvailable($pdo, $copyId)) {
            $errors['copy_id'] = 'Selected copy is not available';
        }
    }
    
    if (empty($dueDate)) {
        $errors['due_date'] = 'Due date is required';
    } elseif (strtotime($dueDate) <= time()) {
        $errors['due_date'] = 'Due date must be in the future';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get book_id from copy
            $stmt = $pdo->prepare("SELECT book_id FROM book_copies WHERE id = ?");
            $stmt->execute([$copyId]);
            $copy = $stmt->fetch();
            
            // Create loan
            $stmt = $pdo->prepare("
                INSERT INTO loans (member_id, book_copy_id, issued_by, issue_date, due_date, status)
                VALUES (?, ?, ?, CURDATE(), ?, 'Borrowed')
            ");
            $stmt->execute([$memberId, $copyId, getCurrentUserId(), $dueDate]);
            $loanId = $pdo->lastInsertId();
            
            // Update copy status
            $stmt = $pdo->prepare("UPDATE book_copies SET status = 'Borrowed' WHERE id = ?");
            $stmt->execute([$copyId]);
            
            // Update book available copies
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
            $stmt->execute([$copy['book_id']]);
            
            // Get member user_id for notification
            $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            $memberRecord = $stmt->fetch();
            
            // Create notification
            $bookDetails = getBookDetails($pdo, $copy['book_id']);
            createNotification(
                $pdo,
                $memberRecord['user_id'],
                'Book Issued',
                'You have been issued "' . $bookDetails['title'] . '". Due date: ' . formatDate($dueDate),
                'success'
            );
            
            // Audit log
            createAuditLog($pdo, getCurrentUserId(), 'create_loan', 'loans', $loanId, 'Issued book to member ID: ' . $memberId);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Book issued successfully. Loan ID: ' . $loanId;
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Calculate default due date
$defaultDueDate = date('Y-m-d', strtotime('+' . getSetting($pdo, 'default_loan_period', DEFAULT_LOAN_PERIOD) . ' days'));

$pageScripts = ['loans.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-plus-circle"></i> Issue Book</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Loans</a> / Issue
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Loans
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <?php if ($member && isset($errors['member'])): ?>
        <div class="alert alert-danger"><?php echo $errors['member']; ?></div>
    <?php endif; ?>

    <?php if ($book && isset($errors['book'])): ?>
        <div class="alert alert-danger"><?php echo $errors['book']; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Search Member -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user"></i> Select Member</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="create.php" class="d-flex gap-2">
                        <input type="text" name="member_search" class="form-control" placeholder="Search by name or member number..." 
                               value="<?php echo isset($_GET['member_search']) ? sanitizeInput($_GET['member_search']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    
                    <?php if (isset($_GET['member_search']) && $_GET['member_search']): ?>
                        <?php
                        $searchTerm = sanitizeInput($_GET['member_search']);
                        $stmt = $pdo->prepare("
                            SELECT m.*, u.name, u.email, u.phone
                            FROM members m
                            JOIN users u ON m.user_id = u.id
                            WHERE u.name LIKE ? OR m.member_number LIKE ?
                            AND m.status = 'active'
                            LIMIT 10
                        ");
                        $stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
                        $searchResults = $stmt->fetchAll();
                        ?>
                        <?php if ($searchResults): ?>
                            <div class="mt-2">
                                <?php foreach ($searchResults as $result): ?>
                                    <a href="create.php?member_id=<?php echo $result['id']; ?>" 
                                       class="btn btn-outline btn-sm mb-1" 
                                       style="display:block;text-align:left;width:100%;">
                                        <strong><?php echo $result['name']; ?></strong> 
                                        (<?php echo $result['member_number']; ?>)
                                        <br>
                                        <small class="text-muted"><?php echo $result['email']; ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-2">No members found</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($member): ?>
                        <div class="mt-3">
                            <h6>Selected Member</h6>
                            <div class="alert alert-success">
                                <strong><?php echo $member['name']; ?></strong><br>
                                Member #: <?php echo $member['member_number']; ?><br>
                                Email: <?php echo $member['email']; ?><br>
                                <?php if ($member['phone']): ?>
                                    Phone: <?php echo $member['phone']; ?><br>
                                <?php endif; ?>
                                Current Loans: <?php echo $member['current_loans']; ?> / <?php echo getSetting($pdo, 'max_books_per_member', DEFAULT_MAX_BOOKS); ?>
                                <?php if ($member['outstanding_fines_amount'] > 0): ?>
                                    <br>Outstanding Fines: <span class="text-danger"><?php echo formatCurrency($member['outstanding_fines_amount']); ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="create.php" class="btn btn-sm btn-secondary">Change Member</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Search Book -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-book"></i> Select Book</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="create.php" class="d-flex gap-2">
                        <input type="text" name="book_search" class="form-control" placeholder="Search by title, ISBN, accession..." 
                               value="<?php echo isset($_GET['book_search']) ? sanitizeInput($_GET['book_search']) : ''; ?>">
                        <?php if ($memberId): ?>
                            <input type="hidden" name="member_id" value="<?php echo $memberId; ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    
                    <?php if (isset($_GET['book_search']) && $_GET['book_search']): ?>
                        <?php
                        $searchTerm = sanitizeInput($_GET['book_search']);
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT b.*, 
                                   (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'Available') as available_copies
                            FROM books b
                            LEFT JOIN book_copies bc ON b.id = bc.book_id
                            WHERE b.title LIKE ? OR b.isbn LIKE ? OR bc.accession_number LIKE ? OR bc.barcode LIKE ?
                            AND b.available_copies > 0
                            LIMIT 10
                        ");
                        $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
                        $searchResults = $stmt->fetchAll();
                        ?>
                        <?php if ($searchResults): ?>
                            <div class="mt-2">
                                <?php foreach ($searchResults as $result): ?>
                                    <a href="create.php?book_id=<?php echo $result['id']; ?><?php echo $memberId ? '&member_id=' . $memberId : ''; ?>" 
                                       class="btn btn-outline btn-sm mb-1" 
                                       style="display:block;text-align:left;width:100%;">
                                        <strong><?php echo $result['title']; ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Available: <?php echo $result['available_copies']; ?> copies
                                            <?php if ($result['isbn']): ?>
                                                | ISBN: <?php echo $result['isbn']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-2">No available books found</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($book): ?>
                        <div class="mt-3">
                            <h6>Selected Book</h6>
                            <div class="alert alert-success">
                                <strong><?php echo $book['title']; ?></strong><br>
                                <?php if ($book['isbn']): ?>
                                    ISBN: <?php echo $book['isbn']; ?><br>
                                <?php endif; ?>
                                Author: <?php echo $book['author_name'] ?: 'Unknown'; ?><br>
                                Category: <?php echo $book['category_name'] ?: 'Uncategorized'; ?><br>
                                Available Copies: <strong><?php echo count($availableCopies); ?></strong>
                            </div>
                            <a href="create.php<?php echo $memberId ? '?member_id=' . $memberId : ''; ?>" class="btn btn-sm btn-secondary">Change Book</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Form -->
    <?php if ($member && $book && !empty($availableCopies)): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-check-circle"></i> Issue Book</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="create.php" data-validate>
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="copy_id">Select Copy <span class="text-danger">*</span></label>
                                        <select id="copy_id" name="copy_id" class="form-control <?php echo isset($errors['copy_id']) ? 'is-invalid' : ''; ?>" required>
                                            <option value="">-- Select a copy --</option>
                                            <?php foreach ($availableCopies as $copy): ?>
                                                <option value="<?php echo $copy['id']; ?>">
                                                    <?php echo $copy['accession_number']; ?> 
                                                    <?php if ($copy['barcode']): ?>
                                                        (Barcode: <?php echo $copy['barcode']; ?>)
                                                    <?php endif; ?>
                                                    - Condition: <?php echo $copy['condition']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['copy_id'])): ?>
                                            <div class="text-danger"><?php echo $errors['copy_id']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="due_date">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" id="due_date" name="due_date" class="form-control <?php echo isset($errors['due_date']) ? 'is-invalid' : ''; ?>" 
                                               value="<?php echo $dueDate ?? $defaultDueDate; ?>" required>
                                        <?php if (isset($errors['due_date'])): ?>
                                            <div class="text-danger"><?php echo $errors['due_date']; ?></div>
                                        <?php endif; ?>
                                        <div class="form-text">Default loan period: <?php echo getSetting($pdo, 'default_loan_period', DEFAULT_LOAN_PERIOD); ?> days</div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Confirm Details:</strong><br>
                                Member: <?php echo $member['name']; ?> (<?php echo $member['member_number']; ?>)<br>
                                Book: <?php echo $book['title']; ?><br>
                                Current Loans: <?php echo $currentLoans ?? 0; ?> / <?php echo getSetting($pdo, 'max_books_per_member', DEFAULT_MAX_BOOKS); ?>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check"></i> Issue Book
                                </button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include_once '../../includes/footer.php'; ?>