<?php
/**
 * Authentication System
 * Library Management System
 */

/**
 * Login user
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array [success => bool, message => string, user => array|null]
 */
function loginUser(PDO $pdo, string $username, string $password): array {
    // Get user by username or email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account is inactive. Please contact administrator.', 'user' => null];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
    }
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Store user data in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_status'] = $user['status'];
    $_SESSION['profile_image'] = $user['profile_image'];
    $_SESSION['last_activity'] = time();
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Log the login action
    createAuditLog($pdo, $user['id'], 'login', 'users', $user['id'], 'User logged in');
    
    return ['success' => true, 'message' => 'Login successful', 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser(): void {
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 * @param string $role
 * @return bool
 */
function hasRole(string $role): bool {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin(): bool {
    return hasRole('admin');
}

/**
 * Check if user is librarian
 * @return bool
 */
function isLibrarian(): bool {
    return hasRole('librarian') || isAdmin();
}

/**
 * Check if user is member
 * @return bool
 */
function isMember(): bool {
    return hasRole('member');
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user name
 * @return string|null
 */
function getCurrentUserName(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Check if session has expired
 * @return bool
 */
function isSessionExpired(): bool {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    return (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT;
}

/**
 * Update session activity
 */
function updateSessionActivity(): void {
    $_SESSION['last_activity'] = time();
}

/**
 * Require authentication for a page
 * @param string|null $requiredRole
 */
function requireAuth(?string $requiredRole = null): void {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/login.php');
        exit;
    }
    
    if (isSessionExpired()) {
        logoutUser();
        redirect(APP_URL . '/login.php?expired=1');
        exit;
    }
    
    updateSessionActivity();
    
    if ($requiredRole !== null && !hasRole($requiredRole)) {
        // Check if user has a higher role
        $allowedRoles = ['admin', 'librarian', 'member'];
        $roleHierarchy = [
            'admin' => 3,
            'librarian' => 2,
            'member' => 1
        ];
        
        $currentRole = getCurrentUserRole();
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        $currentLevel = $roleHierarchy[$currentRole] ?? 0;
        
        if ($currentLevel < $requiredLevel) {
            // Insufficient permissions - redirect to appropriate dashboard
            $dashboardMap = [
                'admin' => 'admin/dashboard.php',
                'librarian' => 'librarian/dashboard.php',
                'member' => 'member/dashboard.php'
            ];
            
            $redirect = $dashboardMap[$currentRole] ?? 'login.php';
            redirect(APP_URL . '/' . $redirect);
            exit;
        }
    }
}

/**
 * Require admin role
 */
function requireAdmin(): void {
    requireAuth('admin');
}

/**
 * Require librarian or admin role
 */
function requireLibrarian(): void {
    requireAuth('librarian');
}

/**
 * Require member role
 */
function requireMember(): void {
    requireAuth('member');
}

/**
 * Get user details by ID
 * @param PDO $pdo
 * @param int $userId
 * @return array|null
 */
function getUserDetails(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Change user password
 * @param PDO $pdo
 * @param int $userId
 * @param string $currentPassword
 * @param string $newPassword
 * @return array [success => bool, message => string]
 */
function changePassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword): array {
    $user = getUserDetails($pdo, $userId);
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Validate new password strength
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters'];
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $userId])) {
        createAuditLog($pdo, $userId, 'password_change', 'users', $userId, 'Password changed');
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to change password'];
}

/**
 * Reset user password (admin function)
 * @param PDO $pdo
 * @param int $userId
 * @param int $adminId
 * @return array [success => bool, message => string, newPassword => string|null]
 */
function resetUserPassword(PDO $pdo, int $userId, int $adminId): array {
    $newPassword = generateToken(6);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $userId])) {
        createAuditLog($pdo, $adminId, 'password_reset', 'users', $userId, 'Password reset');
        return ['success' => true, 'message' => 'Password reset successfully', 'newPassword' => $newPassword];
    }
    
    return ['success' => false, 'message' => 'Failed to reset password', 'newPassword' => null];
}