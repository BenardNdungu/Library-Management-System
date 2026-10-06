<?php
/**
 * lend API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

$pdo = getDBConnection();
$response = [];

// Get parameters
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$loanId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

try {
    switch ($action) {
        case 'list':
            $response = getlend($pdo, $limit);
            break;
        case 'detail':
            if (!$loanId) {
                throw new Exception('Loan ID required');
            }
            $response = getLoanDetail($pdo, $loanId);
            break;
        case 'current':
            $response = getCurrentlend($pdo, $limit);
            break;
        case 'overdue':
            $response = getOverduelend($pdo, $limit);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get lend list
 */
function getlend($pdo, $limit): array {
    $stmt = $pdo->prepare("
        SELECT l.id, l.status, l.issue_date, l.due_date,
               u.name as member_name, m.member_number,
               b.title as book_title, bc.accession_number
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        ORDER BY l.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get loan detail
 */
function getLoanDetail($pdo, $loanId): ?array {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               u.name as member_name, m.member_number,
               b.title as book_title, bc.accession_number,
               u2.name as issued_by_name,
               u3.name as returned_to_name
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        JOIN users u2 ON l.issued_by = u2.id
        LEFT JOIN users u3 ON l.returned_to = u3.id
        WHERE l.id = ?
    ");
    $stmt->execute([$loanId]);
    return $stmt->fetch() ?: null;
}

/**
 * Get current lend (borrowed and overdue)
 */
function getCurrentlend($pdo, $limit): array {
    $stmt = $pdo->prepare("
        SELECT l.id, l.status, l.issue_date, l.due_date,
               u.name as member_name, m.member_number,
               b.title as book_title, bc.accession_number
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status IN ('Borrowed', 'Overdue')
        ORDER BY l.due_date
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get overdue lend
 */
function getOverduelend($pdo, $limit): array {
    $stmt = $pdo->prepare("
        SELECT l.id, l.due_date,
               u.name as member_name, m.member_number,
               b.title as book_title, bc.accession_number,
               DATEDIFF(CURDATE(), l.due_date) as overdue_days
        FROM lend l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status = 'Overdue'
        ORDER BY overdue_days DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}