<?php
/**
 * Search API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication for API
requireAuth();

$pdo = getDBConnection();
$response = [];

// Get search parameters
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'books';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

if (empty($query)) {
    jsonResponse(['success' => false, 'message' => 'Search query is required']);
}

try {
    switch ($type) {
        case 'books':
            $response = searchBooks($pdo, $query, $limit);
            break;
        case 'members':
            $response = searchMembers($pdo, $query, $limit);
            break;
        case 'loans':
            $response = searchLoans($pdo, $query, $limit);
            break;
        case 'all':
            $response = searchAll($pdo, $query, $limit);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid search type']);
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Search books
 */
function searchBooks($pdo, $query, $limit): array {
    $stmt = $pdo->prepare("
        SELECT b.id, b.title, b.isbn, b.available_copies,
               a.name as author_name, c.name as category_name
        FROM books b
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.title LIKE ? OR b.isbn LIKE ? OR b.description LIKE ?
        LIMIT ?
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
    return $stmt->fetchAll();
}

/**
 * Search members
 */
function searchMembers($pdo, $query, $limit): array {
    $stmt = $pdo->prepare("
        SELECT m.id, m.member_number, u.name, u.email, u.phone
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE u.name LIKE ? OR m.member_number LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
        LIMIT ?
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    return $stmt->fetchAll();
}

/**
 * Search loans
 */
function searchLoans($pdo, $query, $limit): array {
    $stmt = $pdo->prepare("
        SELECT l.id, l.status, l.due_date,
               u.name as member_name, m.member_number,
               b.title as book_title,
               bc.accession_number
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE u.name LIKE ? OR m.member_number LIKE ? OR b.title LIKE ? OR bc.accession_number LIKE ?
        LIMIT ?
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    return $stmt->fetchAll();
}

/**
 * Search all
 */
function searchAll($pdo, $query, $limit): array {
    return [
        'books' => searchBooks($pdo, $query, $limit),
        'members' => searchMembers($pdo, $query, $limit),
        'loans' => searchLoans($pdo, $query, $limit)
    ];
}