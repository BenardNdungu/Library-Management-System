<?php
/**
 * Delete Book Copy
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();

$copyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$copyId) {
    $_SESSION['error'] = 'Copy ID required';
    redirect('index.php');
}

// Verify CSRF token
if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
    $_SESSION['error'] = 'Invalid security token';
    redirect('index.php');
}

try {
    $pdo->beginTransaction();
    
    // Get copy details
    $stmt = $pdo->prepare("SELECT book_id, status FROM book_copies WHERE id = ?");
    $stmt->execute([$copyId]);
    $copy = $stmt->fetch();
    
    if (!$copy) {
        throw new Exception('Copy not found');
    }
    
    if ($copy['status'] === 'Borrowed') {
        throw new Exception('Cannot delete a borrowed copy');
    }
    
    // Delete copy
    $stmt = $pdo->prepare("DELETE FROM book_copies WHERE id = ?");
    $stmt->execute([$copyId]);
    
    // Update book counts
    $stmt = $pdo->prepare("UPDATE books SET total_copies = total_copies - 1 WHERE id = ?");
    $stmt->execute([$copy['book_id']]);
    
    // Update available copies
    $stmt = $pdo->prepare("
        UPDATE books SET available_copies = (
            SELECT COUNT(*) FROM book_copies WHERE book_id = ? AND status = 'Available'
        ) WHERE id = ?
    ");
    $stmt->execute([$copy['book_id'], $copy['book_id']]);
    
    // Audit log
    createAuditLog($pdo, getCurrentUserId(), 'delete_book_copy', 'book_copies', $copyId, 'Deleted book copy');
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Copy deleted successfully.';
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

redirect('copies.php?book_id=' . $copy['book_id']);