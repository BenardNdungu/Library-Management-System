<?php
/**
 * Renew Loan
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();

$loanId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$loanId) {
    $_SESSION['error'] = 'Loan ID required';
    redirect('index.php');
}

// Verify CSRF token
if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
    $_SESSION['error'] = 'Invalid security token';
    redirect('index.php');
}

try {
    $pdo->beginTransaction();
    
    // Get loan details
    $stmt = $pdo->prepare("
        SELECT l.*, m.member_id, bc.book_id, b.title
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.id = ? AND l.status IN ('Borrowed', 'Overdue')
    ");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        throw new Exception('Loan not found or already returned');
    }
    
    // Check renewal count
    $maxRenewals = getSetting($pdo, 'max_renewal_count', DEFAULT_MAX_RENEWALS);
    if ($loan['renewal_count'] >= $maxRenewals) {
        throw new Exception('Maximum renewals (' . $maxRenewals . ') reached for this loan');
    }
    
    // Calculate new due date
    $defaultPeriod = getSetting($pdo, 'default_loan_period', DEFAULT_LOAN_PERIOD);
    $newDueDate = date('Y-m-d', strtotime('+' . $defaultPeriod . ' days', strtotime($loan['due_date'])));
    
    // Update loan
    $stmt = $pdo->prepare("
        UPDATE loans SET 
            due_date = ?, 
            renewal_count = renewal_count + 1,
            status = 'Borrowed'
        WHERE id = ?
    ");
    $stmt->execute([$newDueDate, $loanId]);
    
    // Update copy status if it was overdue
    if ($loan['status'] === 'Overdue') {
        $stmt = $pdo->prepare("UPDATE book_copies SET status = 'Borrowed' WHERE id = ?");
        $stmt->execute([$loan['book_copy_id']]);
    }
    
    // Create notification for member
    $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
    $stmt->execute([$loan['member_id']]);
    $member = $stmt->fetch();
    
    if ($member) {
        createNotification(
            $pdo,
            $member['user_id'],
            'Loan Renewed',
            'Your loan for "' . $loan['title'] . '" has been renewed. New due date: ' . formatDate($newDueDate),
            'success'
        );
    }
    
    // Audit log
    createAuditLog($pdo, getCurrentUserId(), 'renew_loan', 'loans', $loanId, 'Renewed loan. New due date: ' . $newDueDate);
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Loan renewed successfully. New due date: ' . formatDate($newDueDate);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

redirect('index.php');