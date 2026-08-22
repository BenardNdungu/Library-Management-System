<?php
/**
 * Logout
 * Library Management System
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Log the logout action if user is logged in
if (isLoggedIn()) {
    try {
        $pdo = getDBConnection();
        createAuditLog($pdo, getCurrentUserId(), 'logout', 'users', getCurrentUserId(), 'User logged out');
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log('Logout audit error: ' . $e->getMessage());
    }
}

// Perform logout
logoutUser();

// Redirect to login page with success message
header('Location: login.php?success=1');
exit;