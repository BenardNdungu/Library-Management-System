<?php
/**
 * View Member Details
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Member Details';

$memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$memberId) {
    $_SESSION['error'] = 'Member ID required';
    redirect('index.php');
}

// Get member details
$member = getMemberDetails($pdo, $memberId);
if (!$member) {
    $_SESSION['error'] = 'Member not found';
    redirect('index.php');
}

// Get current lend
$stmt = $pdo->prepare("
    SELECT l.*, 
           b.title as book_title,
           bc.accession_number,
           u.name as issued_by_name
    FROM lend l
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    JOIN users u ON l.issued_by = u.id
    WHERE l.member_id = ? AND l.status IN ('Borrowed', 'Overdue')
    ORDER BY l.due_date ASC
");
$stmt->execute([$memberId]);
$currentlend = $stmt->fetchAll();

// Get loan history
$stmt = $pdo->prepare("
    SELECT l.*, 
           b.title as book_title,
           bc.accession_number,
           u.name as issued_by_name,
           u2.name as returned_to_name
    FROM lend l
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    JOIN users u ON l.issued_by = u.id
    LEFT JOIN users u2 ON l.returned_to = u2.id
    WHERE l.member_id = ? AND l.status = 'Returned'
    ORDER BY l.return_date DESC
    LIMIT 20
");
$stmt->execute([$memberId]);
$loanHistory = $stmt->fetchAll();

// Get fines
$stmt = $pdo->prepare("
    SELECT f.*, l.id as loan_id, b.title as book_title
    FROM fines f
    JOIN lend l ON f.loan_id = l.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    WHERE f.member_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$memberId]);
$fines = $stmt->fetchAll();

// Get payments
$stmt = $pdo->prepare("
    SELECT p.*, u.name as received_by_name
    FROM payments p
    JOIN users u ON p.received_by = u.id
    WHERE p.member_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 20
");
$stmt->execute([$memberId]);
$payments = $stmt->fetchAll();

// Get reservations
$stmt = $pdo->prepare("
    SELECT r.*, b.title as book_title
    FROM reservations r
    JOIN books b ON r.book_id = b.id
    WHERE r.member_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$memberId]);
$reservations = $stmt->fetchAll();

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user"></i> Member Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Members</a> / View
            </div>
        </div>
        <div class="page-actions">
            <a href="edit.php?id=<?php echo $memberId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Member
            </a>
            <a href="../lend/create.php?member_id=<?php echo $memberId; ?>" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Issue Book
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Profile -->
        <div class="col-4">
            <div class="card">
                <div class="card-body text-center">
                    <?php if ($member['profile_image']): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $member['profile_image']; ?>" 
                             alt="<?php echo $member['name']; ?>" 
                             style="width:150px;height:150px;border-radius:50%;object-fit:cover;margin-bottom:16px;">
                    <?php else: ?>
                        <div style="width:150px;height:150px;border-radius:50%;background:var(--primary);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:64px;color:white;">
                            <?php echo getInitials($member['name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4><?php echo $member['name']; ?></h4>
                    <p class="text-muted"><?php echo $member['member_number']; ?></p>
                    <p>
                        <?php echo getStatusBadge($member['status'], 'member'); ?>
                        <?php echo getStatusBadge($member['user_status']); ?>
                    </p>
                    <p><strong>Membership:</strong> <?php echo $member['membership_type']; ?></p>
                    <p><strong>Registered:</strong> <?php echo formatDate($member['registration_date']); ?></p>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Contact Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?php echo $member['email']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $member['phone'] ?: 'N/A'; ?></p>
                    <p><strong>Username:</strong> <?php echo $member['username']; ?></p>
                    <?php if ($member['date_of_birth']): ?>
                        <p><strong>Date of Birth:</strong> <?php echo formatDate($member['date_of_birth']); ?></p>
                    <?php endif; ?>
                    <?php if ($member['gender']): ?>
                        <p><strong>Gender:</strong> <?php echo $member['gender']; ?></p>
                    <?php endif; ?>
                    <?php if ($member['address']): ?>
                        <p><strong>Address:</strong> <?php echo nl2br($member['address']); ?></p>
                    <?php endif; ?>
                    <?php if ($member['emergency_contact']): ?>
                        <p><strong>Emergency Contact:</strong> <?php echo $member['emergency_contact']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="stat-card mb-2">
                        <div class="stat-icon info">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Total Books Borrowed</div>
                            <div class="stat-value"><?php echo $member['total_lend'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="stat-card mb-2">
                        <div class="stat-icon warning">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Current lend</div>
                            <div class="stat-value"><?php echo $member['current_lend']; ?></div>
                        </div>
                    </div>
                    <div class="stat-card mb-2">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Outstanding Fines</div>
                            <div class="stat-value"><?php echo formatCurrency($member['outstanding_fines_amount']); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Total Payments Made</div>
                            <div class="stat-value"><?php 
                                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE member_id = ?");
                                $stmt->execute([$memberId]);
                                echo formatCurrency($stmt->fetch()['total']);
                            ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- lend and History -->
        <div class="col-8">
            <!-- Current lend -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-hand-holding-heart"></i> Current lend</h5>
                    <a href="../lend/create.php?member_id=<?php echo $memberId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Issue Book
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($currentlend)): ?>
                        <p class="text-muted text-center">No current lend</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Accession</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentlend as $loan): ?>
                                        <tr>
                                            <td><?php echo $loan['book_title']; ?></td>
                                            <td><?php echo $loan['accession_number']; ?></td>
                                            <td><?php echo formatDate($loan['issue_date']); ?></td>
                                            <td>
                                                <?php echo formatDate($loan['due_date']); ?>
                                                <?php if ($loan['status'] === 'Overdue'): ?>
                                                    <span class="badge badge-danger">
                                                        <?php echo calculateOverdueDays($loan['due_date']); ?> days overdue
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($loan['status'], 'loan'); ?></td>
                                            <td>
                                                <a href="../returns/index.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-undo-alt"></i> Return
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Loan History -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Loan History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($loanHistory)): ?>
                        <p class="text-muted text-center">No loan history</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Accession</th>
                                        <th>Issue Date</th>
                                        <th>Return Date</th>
                                        <th>Returned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loanHistory as $loan): ?>
                                        <tr>
                                            <td><?php echo $loan['book_title']; ?></td>
                                            <td><?php echo $loan['accession_number']; ?></td>
                                            <td><?php echo formatDate($loan['issue_date']); ?></td>
                                            <td><?php echo formatDate($loan['return_date']); ?></td>
                                            <td><?php echo $loan['returned_to_name']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fines -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle"></i> Fines</h5>
                    <a href="../fines/index.php?member_id=<?php echo $memberId; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($fines)): ?>
                        <p class="text-muted text-center">No fines</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($fines, 0, 5) as $fine): ?>
                                        <tr>
                                            <td><?php echo $fine['book_title']; ?></td>
                                            <td><?php echo formatCurrency($fine['amount']); ?></td>
                                            <td><?php echo getStatusBadge($fine['status'], 'fine'); ?></td>
                                            <td><?php echo formatDate($fine['created_at']); ?></td>
                                            <td>
                                                <?php if ($fine['status'] === 'Unpaid'): ?>
                                                    <a href="../payments/create.php?fine_id=<?php echo $fine['id']; ?>&member_id=<?php echo $memberId; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-coins"></i> Pay
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reservations -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-clock"></i> Reservations</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservations)): ?>
                        <p class="text-muted text-center">No reservations</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Reservation Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo $reservation['book_title']; ?></td>
                                            <td><?php echo formatDate($reservation['reservation_date']); ?></td>
                                            <td><?php echo formatDate($reservation['expiry_date']); ?></td>
                                            <td><?php echo getStatusBadge($reservation['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>