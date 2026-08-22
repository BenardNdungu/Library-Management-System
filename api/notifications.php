<?php
/**
 * Notifications API
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Get parameters
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    switch ($action) {
        case 'list':
            $response = getNotifications($pdo, $userId);
            break;
        case 'unread':
            $response = getUnreadNotifications($pdo, $userId);
            break;
        case 'mark_read':
            if (!$notificationId) {
                throw new Exception('Notification ID required');
            }
            $response = markAsRead($pdo, $userId, $notificationId);
            break;
        case 'mark_all_read':
            $response = markAllAsRead($pdo, $userId);
            break;
        case 'count':
            $response = ['count' => getUnreadCount($pdo, $userId)];
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    jsonResponse(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get user notifications
 */
function getNotifications($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get unread notifications
 */
function getUnreadNotifications($pdo, $userId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get unread count
 */
function getUnreadCount($pdo, $userId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    return (int) $stmt->fetch()['count'];
}

/**
 * Mark notification as read
 */
function markAsRead($pdo, $userId, $notificationId): array {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Notification marked as read'];
    }
    return ['success' => false, 'message' => 'Notification not found'];
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($pdo, $userId): array {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    return ['success' => true, 'message' => 'All notifications marked as read', 'count' => $stmt->rowCount()];
}