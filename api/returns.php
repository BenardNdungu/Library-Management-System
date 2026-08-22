<?php
/**
 * Returns API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();

// Get parameters
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'search';
$loanId = isset($_GET['loan_id']) ? (int) $_GET['loan_id'] : 0;
$barcode = isset($_GET['barcode']) ? sanitizeInput($_GET['barcode']) : '';

try {
    switch ($action) {
        case 'search':
            $response = searchReturns($pdo, $barcode, $loanId);
            break;
        case 'process':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            $response = processReturn($pdo, $_POST);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Search for loans to return
 */
function searchReturns($pdo, $barcode, $loanId): array {
    if ($loanId) {
        // Get specific loan
        $stmt = $pdo->prepare("
            SELECT l.id, l.due_date, l.status,
                   m.member_number, u.name as member_name,
                   b.title as book_title, bc.accession_number, bc.barcode
            FROM loans l
            JOIN members m ON l.member_id = m.id
            JOIN users u ON m.user_id = u.id
            JOIN book_copies bc ON l.book_copy_id = bc.id
            JOIN books b ON bc.book_id = b.id
            WHERE l.id = ? AND l.status IN ('Borrowed', 'Overdue')
        ");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();
        
        if ($loan) {
            $loan['overdue_days'] = calculateOverdueDays($loan['due_date']);
            $dailyRate = getSetting($pdo, 'daily_fine_rate', DEFAULT_DAILY_FINE);
            $loan['fine_amount'] = calculateFine($loan['overdue_days'], $dailyRate);
        }
        return $loan ?: [];
    }
    
    if ($barcode) {
        // Search by barcode or accession number
        $stmt = $pdo->prepare("
            SELECT l.id, l.due_date, l.status,
                   m.member_number, u.name as member_name,
                   b.title as book_title, bc.accession_number, bc.barcode
            FROM loans l
            JOIN members m ON l.member_id = m.id
            JOIN users u ON m.user_id = u.id
            JOIN book_copies bc ON l.book_copy_id = bc.id
            JOIN books b ON bc.book_id = b.id
            WHERE (bc.barcode = ? OR bc.accession_number = ?)
            AND l.status IN ('Borrowed', 'Overdue')
        ");
        $stmt->execute([$barcode, $barcode]);
        $loan = $stmt->fetch();
        
        if ($loan) {
            $loan['overdue_days'] = calculateOverdueDays($loan['due_date']);
            $dailyRate = getSetting($pdo, 'daily_fine_rate', DEFAULT_DAILY_FINE);
            $loan['fine_amount'] = calculateFine($loan['overdue_days'], $dailyRate);
        }
        return $loan ?: [];
    }
    
    throw new Exception('No search criteria provided');
}

/**
 * Process return
 */
function processReturn($pdo, $data): array {
    $loanId = (int) ($data['loan_id'] ?? 0);
    $fineAmount = (float) ($data['fine_amount'] ?? 0);
    $fineStatus = sanitizeInput($data['fine_status'] ?? '');
    $notes = sanitizeInput($data['notes'] ?? '');
    
    if (!$loanId) {
        throw new Exception('Loan ID required');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get loan details
        $stmt = $pdo->prepare("
            SELECT l.*, bc.book_id, b.title
            FROM loans l
            JOIN book_copies bc ON l.book_copy_id = bc.id
            JOIN books b ON bc.book_id = b.id
            WHERE l.id = ? AND l.status IN ('Borrowed', 'Overdue')
        ");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            throw new Exception('Loan not found or already returned');
        }
        
        // Update loan
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET status = 'Returned', return_date = CURDATE(), returned_to = ?, notes = CONCAT(IFNULL(notes, ''), ' ', ?)
            WHERE id = ?
        ");
        $stmt->execute([getCurrentUserId(), $notes, $loanId]);
        
        // Update copy status
        $stmt = $pdo->prepare("UPDATE book_copies SET status = 'Available' WHERE id = ?");
        $stmt->execute([$loan['book_copy_id']]);
        
        // Update book available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
        $stmt->execute([$loan['book_id']]);
        
        $fineId = null;
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
        $message = 'Your book "' . $loan['title'] . '" has been returned.';
        if ($fineAmount > 0) {
            $message .= ' A fine of ' . formatCurrency($fineAmount) . ' has been applied.';
            if ($fineStatus === 'paid') {
                $message .= ' The fine has been paid.';
            }
        }
        createNotification($pdo, $memberRecord['user_id'], 'Book Returned', $message, 'success');
        
        // Audit log
        createAuditLog($pdo, getCurrentUserId(), 'return_book', 'loans', $loanId, 'Returned book from loan ID: ' . $loanId);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Book returned successfully' . ($fineAmount > 0 ? '. Fine: ' . formatCurrency($fineAmount) : ''),
            'fine_id' => $fineId,
            'fine_amount' => $fineAmount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}