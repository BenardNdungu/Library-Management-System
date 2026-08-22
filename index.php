<?php
/**
 * Index Page - Redirect to appropriate dashboard based on role
 * Library Management System
 */

require_once 'config/config.php';
require_once 'config/database.php';

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    $dashboardMap = [
        'admin' => 'admin/dashboard.php',
        'librarian' => 'librarian/dashboard.php',
        'member' => 'member/dashboard.php'
    ];
    redirect($dashboardMap[$role] ?? 'member/dashboard.php');
}

// Not logged in - redirect to login
redirect('login.php');