<?php
/**
 * Books API
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
$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

try {
    switch ($action) {
        case 'list':
            $response = getBooks($pdo, $limit);
            break;
        case 'detail':
            if (!$bookId) {
                throw new Exception('Book ID required');
            }
            $response = getBookDetail($pdo, $bookId);
            break;
        case 'search':
            $query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
            if (empty($query)) {
                throw new Exception('Search query required');
            }
            $response = searchBooks($pdo, $query, $limit);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get list of books
 */
function getBooks($pdo, $limit): array {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               c.name as category_name,
               a.name as author_name,
               p.name as publisher_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        ORDER BY b.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get book detail
 */
function getBookDetail($pdo, $bookId): ?array {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               c.name as category_name,
               c.description as category_description,
               a.name as author_name,
               a.biography as author_biography,
               p.name as publisher_name,
               p.email as publisher_email,
               p.website as publisher_website,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'Available') as available_copies,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id) as total_copies
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookId]);
    return $stmt->fetch() ?: null;
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