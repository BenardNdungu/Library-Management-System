<?php
/**
 * Dashboard Stats API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

$pdo = getDBConnection();

try {
    $stats = [];
    
    // Total books
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM books");
    $stats['total_books'] = (int) $stmt->fetch()['count'];
    
    // Total copies
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM book_copies");
    $stats['total_copies'] = (int) $stmt->fetch()['count'];
    
    // Available copies
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM book_copies WHERE status = 'Available'");
    $stats['available_copies'] = (int) $stmt->fetch()['count'];
    
    // Borrowed books
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status IN ('Borrowed', 'Overdue')");
    $stats['borrowed_books'] = (int) $stmt->fetch()['count'];
    
    // Overdue books
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'Overdue'");
    $stats['overdue_books'] = (int) $stmt->fetch()['count'];
    
    // Total members
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
    $stats['total_members'] = (int) $stmt->fetch()['count'];
    
    // Active members
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    $stats['active_members'] = (int) $stmt->fetch()['count'];

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = (int) $stmt->fetch()['count'];
    
    // Pending reservations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'Pending'");
    $stats['pending_reservations'] = (int) $stmt->fetch()['count'];
    
    // Outstanding fines
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE status = 'Unpaid'");
    $stats['outstanding_fines'] = (float) $stmt->fetch()['total'];
    
    // Total fines collected
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments");
    $stats['total_fines_collected'] = (float) $stmt->fetch()['total'];
    
    jsonResponse(['success' => true, 'stats' => $stats]);
    
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}