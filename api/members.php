<?php
/**
 * Members API
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
$memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

try {
    switch ($action) {
        case 'list':
            $response = getMembers($pdo, $limit);
            break;
        case 'detail':
            if (!$memberId) {
                throw new Exception('Member ID required');
            }
            $response = getMemberDetail($pdo, $memberId);
            break;
        case 'search':
            $query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
            if (empty($query)) {
                throw new Exception('Search query required');
            }
            $response = searchMembers($pdo, $query, $limit);
            break;
        case 'lend':
            if (!$memberId) {
                throw new Exception('Member ID required');
            }
            $response = getMemberlend($pdo, $memberId);
            break;
        case 'fines':
            if (!$memberId) {
                throw new Exception('Member ID required');
            }
            $response = getMemberFines($pdo, $memberId);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get members list
 */
function getMembers($pdo, $limit): array {
    $stmt = $pdo->prepare("
        SELECT m.id, m.member_number, u.name, u.email, u.phone,
               m.membership_type, m.status,
               (SELECT COUNT(*) FROM lend WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_lend
        FROM members m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get member detail
 */
function getMemberDetail($pdo, $memberId): ?array {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name, u.email, u.phone, u.username, u.profile_image,
               (SELECT COUNT(*) FROM lend WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_lend,
               (SELECT COUNT(*) FROM lend WHERE member_id = m.id AND status = 'Returned') as total_lend,
               (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetch() ?: null;
}

/**
 * Search members
 */
function searchMembers($pdo, $query, $limit): array {
    $stmt = $pdo->prepare("
        SELECT m.id, m.member_number, u.name, u.email
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE u.name LIKE ? OR m.member_number LIKE ? OR u.email LIKE ?
        LIMIT ?
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
    return $stmt->fetchAll();
}

/**
 * Get member lend
 */
function getMemberlend($pdo, $memberId): array {
    $stmt = $pdo->prepare("
        SELECT l.*, b.title as book_title, bc.accession_number
        FROM lend l
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.member_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

/**
 * Get member fines
 */
function getMemberFines($pdo, $memberId): array {
    $stmt = $pdo->prepare("
        SELECT f.*, b.title as book_title
        FROM fines f
        JOIN lend l ON f.loan_id = l.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE f.member_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}