<?php
/**
 * Cron Job: Process Overdue Books
 * Run this script daily to update overdue statuses and send notifications
 * 
 * Setup: Add to crontab - 0 0 * * * php /path/to/library-management-system/cron/process-overdue.php
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Set time limit for long running process
set_time_limit(300);

// Log file
$logFile = APP_ROOT . '/logs/cron.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Starting overdue processing ===");

try {
    $pdo = getDBConnection();
    
    // 1. Update overdue loans
    logMessage("Updating overdue loans...");
    $stmt = $pdo->prepare("
        UPDATE loans 
        SET status = 'Overdue' 
        WHERE status = 'Borrowed' AND due_date < CURDATE()
    ");
    $stmt->execute();
    $overdueCount = $stmt->rowCount();
    logMessage("Updated $overdueCount loans to overdue status");
    
    // 2. Process overdue notifications
    logMessage("Processing overdue notifications...");
    $stmt = $pdo->prepare("
        SELECT l.id, l.due_date, l.member_id, 
               m.user_id, b.title as book_title,
               DATEDIFF(CURDATE(), l.due_date) as overdue_days
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status = 'Overdue' 
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = m.user_id 
            AND n.title = 'Book Overdue'
            AND DATE(n.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $overdueLoans = $stmt->fetchAll();
    
    $notificationCount = 0;
    foreach ($overdueLoans as $loan) {
        createNotification(
            $pdo,
            $loan['user_id'],
            'Book Overdue',
            'Your book "' . $loan['book_title'] . '" is ' . $loan['overdue_days'] . ' days overdue. Please return it immediately to avoid additional fines.',
            'error'
        );
        $notificationCount++;
        logMessage("Sent overdue notification for loan #{$loan['id']} to user #{$loan['user_id']}");
    }
    logMessage("Sent $notificationCount overdue notifications");
    
    // 3. Process expiring reservations
    logMessage("Processing expiring reservations...");
    $stmt = $pdo->prepare("
        SELECT r.id, r.member_id, m.user_id, b.title as book_title
        FROM reservations r
        JOIN members m ON r.member_id = m.id
        JOIN books b ON r.book_id = b.id
        WHERE r.status IN ('Pending', 'Ready') 
        AND r.expiry_date <= CURDATE()
    ");
    $stmt->execute();
    $expiringReservations = $stmt->fetchAll();
    
    $expiredCount = 0;
    foreach ($expiringReservations as $reservation) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'Expired' WHERE id = ?");
        $stmt->execute([$reservation['id']]);
        $expiredCount++;
        
        createNotification(
            $pdo,
            $reservation['user_id'],
            'Reservation Expired',
            'Your reservation for "' . $reservation['book_title'] . '" has expired.',
            'warning'
        );
        logMessage("Expired reservation #{$reservation['id']}");
    }
    logMessage("Expired $expiredCount reservations");
    
    // 4. Process due date reminders (2 days before due)
    logMessage("Processing due date reminders...");
    $stmt = $pdo->prepare("
        SELECT l.id, l.due_date, l.member_id,
               m.user_id, b.title as book_title
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status = 'Borrowed' 
        AND l.due_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = m.user_id 
            AND n.title LIKE 'Book Due Soon%'
            AND DATE(n.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $dueReminders = $stmt->fetchAll();
    
    $reminderCount = 0;
    foreach ($dueReminders as $loan) {
        createNotification(
            $pdo,
            $loan['user_id'],
            'Book Due Soon',
            'Your book "' . $loan['book_title'] . '" is due in 2 days on ' . formatDate($loan['due_date']) . '. Please return it on time.',
            'warning'
        );
        $reminderCount++;
        logMessage("Sent due reminder for loan #{$loan['id']}");
    }
    logMessage("Sent $reminderCount due date reminders");
    
    // 5. Process ready reservations (notify when book becomes available)
    logMessage("Processing ready reservations...");
    $stmt = $pdo->prepare("
        SELECT r.id, r.member_id, m.user_id, b.title as book_title
        FROM reservations r
        JOIN members m ON r.member_id = m.id
        JOIN books b ON r.book_id = b.id
        WHERE r.status = 'Ready'
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = m.user_id 
            AND n.title = 'Reservation Ready'
            AND DATE(n.created_at) = CURDATE()
        )
    ");
    $stmt->execute();
    $readyReservations = $stmt->fetchAll();
    
    $readyCount = 0;
    foreach ($readyReservations as $reservation) {
        createNotification(
            $pdo,
            $reservation['user_id'],
            'Reservation Ready',
            'Your reservation for "' . $reservation['book_title'] . '" is now ready for pickup.',
            'success'
        );
        $readyCount++;
        logMessage("Sent ready notification for reservation #{$reservation['id']}");
    }
    logMessage("Sent $readyCount ready reservation notifications");
    
    // 6. Clean up old notifications (keep last 30 days)
    logMessage("Cleaning up old notifications...");
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read = 1");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    logMessage("Deleted $deletedCount old notifications");
    
    logMessage("=== Overdue processing completed ===");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
}