<?php
/**
 * Create Payment
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Record Payment';

$errors = [];
$formData = [];
$fine = null;
$member = null;

// Get fine details if provided
$fineId = isset($_GET['fine_id']) ? (int) $_GET['fine_id'] : 0;
$memberId = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;

if ($fineId) {
    $stmt = $pdo->prepare("
        SELECT f.*, m.member_number, u.name as member_name, b.title as book_title
        FROM fines f
        JOIN members m ON f.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN lend l ON f.loan_id = l.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE f.id = ? AND f.status = 'Unpaid'
    ");
    $stmt->execute([$fineId]);
    $fine = $stmt->fetch();
    
    if ($fine) {
        $memberId = $fine['member_id'];
        $formData['amount'] = $fine['amount'];
    }
}

if ($memberId && !$fine) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name, u.email
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
}

// Get all unpaid fines for member if no specific fine selected
$unpaidFines = [];
if ($memberId) {
    $stmt = $pdo->prepare("
        SELECT f.*, b.title as book_title
        FROM fines f
        JOIN lend l ON f.loan_id = l.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE f.member_id = ? AND f.status = 'Unpaid'
    ");
    $stmt->execute([$memberId]);
    $unpaidFines = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'member_id' => (int) ($_POST['member_id'] ?? 0),
        'fine_id' => (int) ($_POST['fine_id'] ?? 0),
        'amount' => (float) ($_POST['amount'] ?? 0),
        'payment_method' => sanitizeInput($_POST['payment_method'] ?? 'Cash'),
        'reference_number' => sanitizeInput($_POST['reference_number'] ?? ''),
        'payment_date' => sanitizeInput($_POST['payment_date'] ?? date('Y-m-d')),
        'notes' => sanitizeInput($_POST['notes'] ?? '')
    ];
    
    // Validate
    if (!$formData['member_id']) {
        $errors['member_id'] = 'Member is required';
    }
    
    if ($formData['amount'] <= 0) {
        $errors['amount'] = 'Amount must be greater than 0';
    }
    
    if (empty($formData['payment_method'])) {
        $errors['payment_method'] = 'Payment method is required';
    }
    
    if (empty($formData['payment_date'])) {
        $errors['payment_date'] = 'Payment date is required';
    }
    
    // If paying a specific fine, validate amount doesn't exceed fine
    if ($formData['fine_id']) {
        $stmt = $pdo->prepare("SELECT amount FROM fines WHERE id = ? AND status = 'Unpaid'");
        $stmt->execute([$formData['fine_id']]);
        $fineCheck = $stmt->fetch();
        if ($fineCheck && $formData['amount'] > $fineCheck['amount']) {
            $errors['amount'] = 'Amount cannot exceed the fine amount of ' . formatCurrency($fineCheck['amount']);
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert payment
            $stmt = $pdo->prepare("
                INSERT INTO payments (member_id, fine_id, amount, payment_method, reference_number, received_by, payment_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formData['member_id'],
                $formData['fine_id'] ?: null,
                $formData['amount'],
                $formData['payment_method'],
                $formData['reference_number'] ?: null,
                getCurrentUserId(),
                $formData['payment_date'],
                $formData['notes'] ?: null
            ]);
            $paymentId = $pdo->lastInsertId();
            
            // Update fine status if paying a specific fine
            if ($formData['fine_id']) {
                $stmt = $pdo->prepare("UPDATE fines SET status = 'Paid' WHERE id = ?");
                $stmt->execute([$formData['fine_id']]);
            }
            
            // Get member user_id for notification
            $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
            $stmt->execute([$formData['member_id']]);
            $memberRecord = $stmt->fetch();
            
            // Create notification
            createNotification(
                $pdo,
                $memberRecord['user_id'],
                'Payment Recorded',
                'A payment of ' . formatCurrency($formData['amount']) . ' has been recorded.',
                'success'
            );
            
            // Audit log
            createAuditLog($pdo, getCurrentUserId(), 'create_payment', 'payments', $paymentId, 'Recorded payment of ' . $formData['amount']);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Payment recorded successfully. Receipt #: PAY-' . str_pad($paymentId, 6, '0', STR_PAD_LEFT);
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageScripts = ['payments.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-plus-circle"></i> Record Payment</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Payments</a> / Record
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <!-- Member Search -->
    <div class="card mb-3">
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
                    SELECT m.*, u.name, u.email
                    FROM members m
                    JOIN users u ON m.user_id = u.id
                    WHERE u.name LIKE ? OR m.member_number LIKE ?
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
                    <div class="alert alert-success">
                        <strong><?php echo $member['name']; ?></strong><br>
                        Member #: <?php echo $member['member_number']; ?><br>
                        Email: <?php echo $member['email']; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Form -->
    <?php if ($memberId): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-coins"></i> Payment Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="create.php" data-validate>
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="member_id" value="<?php echo $memberId; ?>">
                    
                    <?php if (!empty($unpaidFines)): ?>
                        <div class="form-group">
                            <label for="fine_id">Select Fine to Pay</label>
                            <select id="fine_id" name="fine_id" class="form-control" onchange="updateAmount(this.value)">
                                <option value="0">-- Select a fine --</option>
                                <?php foreach ($unpaidFines as $uf): ?>
                                    <option value="<?php echo $uf['id']; ?>" 
                                            data-amount="<?php echo $uf['amount']; ?>"
                                            <?php echo ($fineId && $fineId == $uf['id']) ? 'selected' : ''; ?>>
                                        <?php echo $uf['book_title']; ?> - <?php echo formatCurrency($uf['amount']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">Custom Amount</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control <?php echo isset($errors['amount']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo $formData['amount'] ?? ''; ?>" step="0.01" min="0.01" required>
                        <?php if (isset($errors['amount'])): ?>
                            <div class="text-danger"><?php echo $errors['amount']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select id="payment_method" name="payment_method" class="form-control <?php echo isset($errors['payment_method']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="Cash" <?php echo ($formData['payment_method'] ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Card" <?php echo ($formData['payment_method'] ?? '') === 'Card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="Bank Transfer" <?php echo ($formData['payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Mobile Money" <?php echo ($formData['payment_method'] ?? '') === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                    <option value="Other" <?php echo ($formData['payment_method'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['payment_method'])): ?>
                                    <div class="text-danger"><?php echo $errors['payment_method']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" id="payment_date" name="payment_date" class="form-control <?php echo isset($errors['payment_date']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $formData['payment_date'] ?? date('Y-m-d'); ?>" required>
                                <?php if (isset($errors['payment_date'])): ?>
                                    <div class="text-danger"><?php echo $errors['payment_date']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number" class="form-control" 
                               value="<?php echo $formData['reference_number'] ?? ''; ?>" placeholder="Check number, transaction ID, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"><?php echo $formData['notes'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Record Payment
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function updateAmount(fineId) {
    const select = document.getElementById('fine_id');
    const amountInput = document.getElementById('amount');
    
    if (fineId && fineId !== '0' && fineId !== 'custom') {
        const option = select.querySelector(`option[value="${fineId}"]`);
        if (option) {
            amountInput.value = option.dataset.amount || '';
            amountInput.readOnly = true;
        }
    } else if (fineId === 'custom') {
        amountInput.readOnly = false;
        amountInput.value = '';
        amountInput.focus();
    } else {
        amountInput.readOnly = false;
        amountInput.value = '';
    }
}

// Initialize with selected fine
<?php if ($fineId): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('fine_id');
        if (select) {
            select.value = '<?php echo $fineId; ?>';
            updateAmount('<?php echo $fineId; ?>');
        }
    });
<?php endif; ?>
</script>

<?php include_once '../../includes/footer.php'; ?>