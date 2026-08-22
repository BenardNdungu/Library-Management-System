<?php
/**
 * Main Configuration File
 * Library Management System
 */

// Error reporting - disable in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour

// Application paths
define('APP_ROOT', dirname(__DIR__));
define('APP_URL', 'http://localhost/librarymanagementsystem');

// Upload paths
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('BOOK_COVER_PATH', UPLOAD_PATH . 'books/');
define('MEMBER_PHOTO_PATH', UPLOAD_PATH . 'members/');

// Application settings
define('APP_NAME', 'Library Management System');
define('APP_VERSION', '1.0.0');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// CSRF token time to live (1 hour)
define('CSRF_TTL', 3600);

// Pagination
define('ITEMS_PER_PAGE', 20);

// File upload limits
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include functions
require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/csrf.php';