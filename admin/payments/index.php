<?php
/**
 * Payment Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Payment Management';

// Get filter parameters
$method = isset($_GET['method']) ? sanitizeInput($_GET['method']) : '';
$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$fine_id = isset($_GET['fine_id']) ? (int) $_GET['fine_id'] : 0;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($method) {
    $where[] = "p.payment_method = ?";
    $params[] = $method;
}

if ($member_id) {
    $where[] = "p.member_id = ?";
    $params[] = $member_id;
}

if ($fine_id) {
    $where[] = "p.fine_id = ?";
    $params[] = $fine_id;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN users u2 ON p.received_by = u2.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalPayments = $stmt->fetch()['total'];
$totalPages = ceil($totalPayments / $limit);

// Get payments
$sql = "
    SELECT p.*,
           m.member_number,
           u.name as member_name,
           u2.name as received_by_name
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN users u2 ON p.received_by = u2.id
    $whereClause
    ORDER BY p.payment_date DESC, p.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get total amounts
$totalAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch()['total'];
$totalCash = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_method = 'Cash'")->fetch()['total'];
$totalCard = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_method = 'Card'")->fetch()['total'];

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
            <h1><i class="fas fa-coins"></i> Payment Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Payments
            </div>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Record Payment
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
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="method" class="form-control">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php echo $method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="Card" <?php echo $method === 'Card' ? 'selected' : ''; ?>>Card</option>
                        <option value="Bank Transfer" <?php echo $method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="Mobile Money" <?php echo $method === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="Other" <?php echo $method === 'Other' ? 'selected' : ''; ?>>Other</option>
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

    <!-- Stats Summary -->
    <div class="row mb-3">
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Collected</div>
                    <div class="stat-value"><?php echo formatCurrency($totalAmount); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Cash</div>
                    <div class="stat-value"><?php echo formatCurrency($totalCash); ?></div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Card</div>
                    <div class="stat-value"><?php echo formatCurrency($totalCard); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No payments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo $payment['member_name']; ?></div>
                                            <small class="text-muted"><?php echo $payment['member_number']; ?></small>
                                        </div>
                                    </td>
                                    <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $payment['payment_method']; ?></span></td>
                                    <td><?php echo $payment['reference_number'] ?: 'N/A'; ?></td>
                                    <td><?php echo $payment['received_by_name']; ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info" title="View Receipt" onclick="printReceipt(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                                            <i class="fas fa-receipt"></i>
                                        </a>
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
                        <a href="?page=<?php echo $page - 1; ?>&method=<?php echo $method; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&method=<?php echo $method; ?>&member_id=<?php echo $member_id; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&method=<?php echo $method; ?>&member_id=<?php echo $member_id; ?>" class="page-link">
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

<!-- Receipt Modal -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h5>Payment Receipt</h5>
            <button class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body" id="receiptContent">
            <div class="text-center mb-3">
                <h4><?php echo getSetting($pdo, 'library_name', APP_NAME); ?></h4>
                <p class="text-muted"><?php echo getSetting($pdo, 'library_address', ''); ?></p>
                <hr>
                <h5>Payment Receipt</h5>
            </div>
            <div id="receiptDetails"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<script>
function printReceipt(payment) {
    const details = document.getElementById('receiptDetails');
    details.innerHTML = `
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:4px 0;"><strong>Receipt #:</strong></td><td style="padding:4px 0;text-align:right;">PAY-${String(payment.id).padStart(6, '0')}</td></tr>
            <tr><td style="padding:4px 0;"><strong>Date:</strong></td><td style="padding:4px 0;text-align:right;">${payment.payment_date}</td></tr>
            <tr><td style="padding:4px 0;"><strong>Member:</strong></td><td style="padding:4px 0;text-align:right;">${payment.member_name} (${payment.member_number})</td></tr>
            <tr><td style="padding:4px 0;"><strong>Amount:</strong></td><td style="padding:4px 0;text-align:right;"><strong>${payment.amount}</strong></td></tr>
            <tr><td style="padding:4px 0;"><strong>Method:</strong></td><td style="padding:4px 0;text-align:right;">${payment.payment_method}</td></tr>
            ${payment.reference_number ? `<tr><td style="padding:4px 0;"><strong>Reference:</strong></td><td style="padding:4px 0;text-align:right;">${payment.reference_number}</td></tr>` : ''}
            <tr><td style="padding:4px 0;"><strong>Received By:</strong></td><td style="padding:4px 0;text-align:right;">${payment.received_by_name}</td></tr>
            ${payment.notes ? `<tr><td style="padding:4px 0;"><strong>Notes:</strong></td><td style="padding:4px 0;text-align:right;">${payment.notes}</td></tr>` : ''}
        </table>
        <hr>
        <p class="text-center text-muted" style="font-size:12px;">Thank you for your payment</p>
    `;
    document.getElementById('receiptModal').classList.add('show');
}
</script>

<?php include_once '../../includes/footer.php'; ?>