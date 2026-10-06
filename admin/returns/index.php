<?php
/**
 * Return Book
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Return Book';

$errors = [];
$success = '';
$loan = null;
$fine = null;
$fineAmount = 0;

// Get loan details if loan_id provided
$loanId = isset($_GET['loan_id']) ? (int) $_GET['loan_id'] : 0;
if ($loanId) {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               m.member_number,
               u.name as member_name,
               u.email as member_email,
               b.title as book_title,
               bc.accession_number,
               bc.barcode,
               u2.name as issued_by_name
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        JOIN users u2 ON l.issued_by = u2.id
        WHERE l.id = ? AND l.status IN ('Borrowed', 'Overdue')
    ");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    
    if ($loan) {
        // Calculate overdue days and fine
        $overdueDays = calculateOverdueDays($loan['due_date']);
        if ($overdueDays > 0) {
            $dailyRate = getSetting($pdo, 'daily_fine_rate', DEFAULT_DAILY_FINE);
            $fineAmount = calculateFine($overdueDays, $dailyRate);
        }
    } else {
        $errors['loan'] = 'Loan not found or already returned';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $fineAmount = (float) ($_POST['fine_amount'] ?? 0);
    $fineStatus = sanitizeInput($_POST['fine_status'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (!$loanId) {
        $errors['general'] = 'Loan ID required';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get loan details
            $stmt = $pdo->prepare("
                SELECT l.*, bc.book_id
                FROM lend l
                JOIN book_copies bc ON l.book_copy_id = bc.id
                WHERE l.id = ? AND l.status IN ('Borrowed', 'Overdue')
            ");
            $stmt->execute([$loanId]);
            $loan = $stmt->fetch();
            
            if (!$loan) {
                throw new Exception('Loan not found or already returned');
            }
            
            // Update loan
            $stmt = $pdo->prepare("
                UPDATE lend 
                SET status = 'Returned', return_date = CURDATE(), returned_to = ? 
                WHERE id = ?
            ");
            $stmt->execute([getCurrentUserId(), $loanId]);
            
            // Update copy status
            $stmt = $pdo->prepare("UPDATE book_copies SET status = 'Available' WHERE id = ?");
            $stmt->execute([$loan['book_copy_id']]);
            
            // Update book available copies
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
            $stmt->execute([$loan['book_id']]);
            
            // Create fine if applicable
            if ($fineAmount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO fines (loan_id, member_id, amount, reason, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $loanId,
                    $loan['member_id'],
                    $fineAmount,
                    'Late return - ' . calculateOverdueDays($loan['due_date']) . ' days overdue',
                    $fineStatus === 'paid' ? 'Paid' : 'Unpaid'
                ]);
                $fineId = $pdo->lastInsertId();
                
                // If fine is paid, create payment record
                if ($fineStatus === 'paid') {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (member_id, fine_id, amount, payment_method, received_by, payment_date, notes)
                        VALUES (?, ?, ?, 'Cash', ?, CURDATE(), ?)
                    ");
                    $stmt->execute([
                        $loan['member_id'],
                        $fineId,
                        $fineAmount,
                        getCurrentUserId(),
                        'Fine paid during return'
                    ]);
                }
            }
            
            // Get member user_id for notification
            $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
            $stmt->execute([$loan['member_id']]);
            $memberRecord = $stmt->fetch();
            
            // Create notification
            $message = 'Your book "' . $loan['book_title'] . '" has been returned.';
            if ($fineAmount > 0) {
                $message .= ' A fine of ' . formatCurrency($fineAmount) . ' has been applied.';
                if ($fineStatus === 'paid') {
                    $message .= ' The fine has been paid.';
                }
            }
            createNotification($pdo, $memberRecord['user_id'], 'Book Returned', $message, 'success');
            
            // Audit log
            createAuditLog($pdo, getCurrentUserId(), 'return_book', 'lend', $loanId, 'Returned book from loan ID: ' . $loanId);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Book returned successfully.' . ($fineAmount > 0 ? ' Fine: ' . formatCurrency($fineAmount) : '');
            redirect('../lend/index.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = $e->getMessage();
        }
    }
}

// Handle search
$searchResults = [];
if (isset($_GET['search']) && $_GET['search']) {
    $searchTerm = sanitizeInput($_GET['search']);
    $stmt = $pdo->prepare("
        SELECT l.id, l.due_date, l.status,
               m.member_number,
               u.name as member_name,
               b.title as book_title,
               bc.accession_number,
               bc.barcode
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE (u.name LIKE ? OR m.member_number LIKE ? OR bc.accession_number LIKE ? OR bc.barcode LIKE ? OR b.title LIKE ?)
        AND l.status IN ('Borrowed', 'Overdue')
        LIMIT 10
    ");
    $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
    $searchResults = $stmt->fetchAll();
}

// Handle success/error messages
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$pageScripts = ['lend.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-undo-alt"></i> Return Book</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="../lend/index.php">lend</a> / Return
            </div>
        </div>
        <div class="page-actions">
            <a href="../lend/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to lend
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <?php if (isset($errors['loan'])): ?>
        <div class="alert alert-danger"><?php echo $errors['loan']; ?></div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="flex:3; min-width:250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by member name, member number, accession, barcode, or book title..." 
                           value="<?php echo isset($_GET['search']) ? sanitizeInput($_GET['search']) : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
            
            <?php if (!empty($searchResults)): ?>
                <div class="mt-3">
                    <h6>Search Results</h6>
                    <?php foreach ($searchResults as $result): ?>
                        <a href="index.php?loan_id=<?php echo $result['id']; ?>" 
                           class="btn btn-outline btn-sm mb-1" 
                           style="display:block;text-align:left;width:100%;">
                            <div class="d-flex justify-between">
                                <div>
                                    <strong><?php echo $result['member_name']; ?></strong>
                                    <span class="text-muted">(<?php echo $result['member_number']; ?>)</span>
                                </div>
                                <div>
                                    <span class="badge badge-<?php echo $result['status'] === 'Overdue' ? 'danger' : 'info'; ?>">
                                        <?php echo $result['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-muted small">
                                Book: <?php echo $result['book_title']; ?> | 
                                Accession: <?php echo $result['accession_number']; ?> | 
                                Due: <?php echo formatDate($result['due_date']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif (isset($_GET['search']) && $_GET['search']): ?>
                <div class="alert alert-info mt-2">No active lend found matching your search</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Return Form -->
    <?php if ($loan): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-check-circle"></i> Return Book</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h6>Member Details</h6>
                        <p>
                            <strong>Name:</strong> <?php echo $loan['member_name']; ?><br>
                            <strong>Member #:</strong> <?php echo $loan['member_number']; ?><br>
                            <strong>Email:</strong> <?php echo $loan['member_email']; ?>
                        </p>
                    </div>
                    <div class="col-6">
                        <h6>Book Details</h6>
                        <p>
                            <strong>Title:</strong> <?php echo $loan['book_title']; ?><br>
                            <strong>Accession #:</strong> <?php echo $loan['accession_number']; ?><br>
                            <strong>Barcode:</strong> <?php echo $loan['barcode'] ?: 'N/A'; ?><br>
                            <strong>Issued By:</strong> <?php echo $loan['issued_by_name']; ?>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-4">
                        <div class="alert alert-info">
                            <strong>Issue Date:</strong><br>
                            <?php echo formatDate($loan['issue_date']); ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="alert <?php echo $loan['status'] === 'Overdue' ? 'alert-danger' : 'alert-info'; ?>">
                            <strong>Due Date:</strong><br>
                            <?php echo formatDate($loan['due_date']); ?>
                            <?php if ($loan['status'] === 'Overdue'): ?>
                                <br>
                                <span class="text-danger"><?php echo calculateOverdueDays($loan['due_date']); ?> days overdue</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="alert <?php echo $fineAmount > 0 ? 'alert-danger' : 'alert-success'; ?>">
                            <strong>Fine Amount:</strong><br>
                            <?php if ($fineAmount > 0): ?>
                                <span class="text-danger"><?php echo formatCurrency($fineAmount); ?></span>
                            <?php else: ?>
                                <span class="text-success">No fine</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="index.php" data-validate>
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                    <input type="hidden" name="fine_amount" value="<?php echo $fineAmount; ?>">
                    
                    <?php if ($fineAmount > 0): ?>
                        <div class="form-group">
                            <label for="fine_status">Fine Status</label>
                            <select id="fine_status" name="fine_status" class="form-control">
                                <option value="unpaid">Unpaid (Member will be billed)</option>
                                <option value="paid">Paid Now</option>
                                <option value="waived">Waive Fine</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="fine_status" value="none">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Confirm Return
                        </button>
                        <a href="../lend/index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include_once '../../includes/footer.php'; ?>