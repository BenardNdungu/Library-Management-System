<?php
/**
 * Recent Activity API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

$pdo = getDBConnection();
$activities = [];

try {
    // Recent loans
    $stmt = $pdo->query("
        SELECT l.*, m.member_number, u.name as member_name, b.title as book_title
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        ORDER BY l.created_at DESC
        LIMIT 3
    ");
    while ($row = $stmt->fetch()) {
        $activities[] = [
            'date' => formatDate($row['created_at'], 'Y-m-d H:i'),
            'type' => 'Loan',
            'description' => $row['member_name'] . ' borrowed "' . $row['book_title'] . '"',
            'user' => $row['member_number']
        ];
    }
    
    // Recent returns
    $stmt = $pdo->query("
        SELECT l.*, m.member_number, u.name as member_name, b.title as book_title
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status = 'Returned'
        ORDER BY l.return_date DESC
        LIMIT 3
    ");
    while ($row = $stmt->fetch()) {
        $activities[] = [
            'date' => formatDate($row['return_date'], 'Y-m-d H:i'),
            'type' => 'Return',
            'description' => $row['member_name'] . ' returned "' . $row['book_title'] . '"',
            'user' => $row['member_number']
        ];
    }
    
    // Recent members
    $stmt = $pdo->query("
        SELECT m.*, u.name as member_name
        FROM members m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.created_at DESC
        LIMIT 2
    ");
    while ($row = $stmt->fetch()) {
        $activities[] = [
            'date' => formatDate($row['created_at'], 'Y-m-d H:i'),
            'type' => 'Member',
            'description' => 'New member registered: ' . $row['member_name'] . ' (' . $row['member_number'] . ')',
            'user' => $row['member_number']
        ];
    }
    
    // Recent payments
    $stmt = $pdo->query("
        SELECT p.*, m.member_number, u.name as member_name
        FROM payments p
        JOIN members m ON p.member_id = m.id
        JOIN users u ON m.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 2
    ");
    while ($row = $stmt->fetch()) {
        $activities[] = [
            'date' => formatDate($row['payment_date'], 'Y-m-d H:i'),
            'type' => 'Payment',
            'description' => 'Payment of ' . formatCurrency($row['amount']) . ' from ' . $row['member_name'],
            'user' => $row['member_number']
        ];
    }
    
    // Sort by date (most recent first)
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    $activities = array_slice($activities, 0, 10);
    
    jsonResponse(['success' => true, 'activities' => $activities]);
    
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}