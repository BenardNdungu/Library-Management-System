<?php
/**
 * View Loan Details
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Loan Details';

$loanId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$loanId) {
    $_SESSION['error'] = 'Loan ID required';
    redirect('index.php');
}

// Get loan details
$stmt = $pdo->prepare("
    SELECT l.*, 
           m.member_number,
           u.name as member_name,
           u.email as member_email,
           u.phone as member_phone,
           b.title as book_title,
           b.isbn as book_isbn,
           bc.accession_number,
           bc.barcode,
           bc.condition as copy_condition,
           u2.name as issued_by_name,
           u3.name as returned_to_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    JOIN users u2 ON l.issued_by = u2.id
    LEFT JOIN users u3 ON l.returned_to = u3.id
    WHERE l.id = ?
");
$stmt->execute([$loanId]);
$loan = $stmt->fetch();

if (!$loan) {
    $_SESSION['error'] = 'Loan not found';
    redirect('index.php');
}

// Check for fine
$stmt = $pdo->prepare("SELECT * FROM fines WHERE loan_id = ?");
$stmt->execute([$loanId]);
$fine = $stmt->fetch();

// Check for payment
$payment = null;
if ($fine) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE fine_id = ?");
    $stmt->execute([$fine['id']]);
    $payment = $stmt->fetch();
}

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-heart"></i> Loan Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Loans</a> / View
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Loans
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Loan Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Loan Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Loan ID:</strong> #<?php echo $loan['id']; ?></p>
                    <p><strong>Status:</strong> <?php echo getStatusBadge($loan['status'], 'loan'); ?></p>
                    <p><strong>Issue Date:</strong> <?php echo formatDate($loan['issue_date']); ?></p>
                    <p><strong>Due Date:</strong> <?php echo formatDate($loan['due_date']); ?></p>
                    <?php if ($loan['return_date']): ?>
                        <p><strong>Return Date:</strong> <?php echo formatDate($loan['return_date']); ?></p>
                    <?php endif; ?>
                    <p><strong>Renewal Count:</strong> <?php echo $loan['renewal_count']; ?></p>
                    <?php if ($loan['notes']): ?>
                        <p><strong>Notes:</strong> <?php echo $loan['notes']; ?></p>
                    <?php endif; ?>
                    <?php if ($loan['status'] === 'Overdue'): ?>
                        <p class="text-danger">
                            <strong>Overdue Days:</strong> <?php echo calculateOverdueDays($loan['due_date']); ?> days
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Book Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-book"></i> Book Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Title:</strong> <?php echo $loan['book_title']; ?></p>
                    <?php if ($loan['book_isbn']): ?>
                        <p><strong>ISBN:</strong> <?php echo $loan['book_isbn']; ?></p>
                    <?php endif; ?>
                    <p><strong>Accession Number:</strong> <?php echo $loan['accession_number']; ?></p>
                    <?php if ($loan['barcode']): ?>
                        <p><strong>Barcode:</strong> <?php echo $loan['barcode']; ?></p>
                    <?php endif; ?>
                    <p><strong>Condition:</strong> <?php echo $loan['copy_condition']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <!-- Member Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user"></i> Member Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo $loan['member_name']; ?></p>
                    <p><strong>Member Number:</strong> <?php echo $loan['member_number']; ?></p>
                    <p><strong>Email:</strong> <?php echo $loan['member_email']; ?></p>
                    <?php if ($loan['member_phone']): ?>
                        <p><strong>Phone:</strong> <?php echo $loan['member_phone']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Staff Info -->
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-tie"></i> Staff Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Issued By:</strong> <?php echo $loan['issued_by_name']; ?></p>
                    <?php if ($loan['returned_to_name']): ?>
                        <p><strong>Returned To:</strong> <?php echo $loan['returned_to_name']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Fine Information -->
    <?php if ($fine): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Fine Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Amount:</strong> <?php echo formatCurrency($fine['amount']); ?></p>
                        <p><strong>Reason:</strong> <?php echo $fine['reason']; ?></p>
                        <p><strong>Status:</strong> <?php echo getStatusBadge($fine['status'], 'fine'); ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($fine['created_at']); ?></p>
                        
                        <?php if ($payment): ?>
                            <hr>
                            <h6>Payment Details</h6>
                            <p><strong>Amount Paid:</strong> <?php echo formatCurrency($payment['amount']); ?></p>
                            <p><strong>Method:</strong> <?php echo $payment['payment_method']; ?></p>
                            <?php if ($payment['reference_number']): ?>
                                <p><strong>Reference:</strong> <?php echo $payment['reference_number']; ?></p>
                            <?php endif; ?>
                            <p><strong>Date:</strong> <?php echo formatDate($payment['payment_date']); ?></p>
                        <?php elseif ($fine['status'] === 'Unpaid'): ?>
                            <a href="../payments/create.php?fine_id=<?php echo $fine['id']; ?>&member_id=<?php echo $loan['member_id']; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-coins"></i> Record Payment
                            </a>
                            <a href="../fines/index.php?waive=<?php echo $fine['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                               class="btn btn-secondary"
                               data-confirm="Waive this fine?">
                                <i class="fas fa-hand-peace"></i> Waive Fine
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include_once '../../includes/footer.php'; ?>