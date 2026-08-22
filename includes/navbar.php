<?php
/**
 * Top Navigation Bar
 * Library Management System
 */

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? '';
$profileImage = $_SESSION['profile_image'] ?? '';
$unreadNotifications = isset($pdo) ? getUnreadNotificationsCount($pdo, $_SESSION['user_id']) : 0;
?>

<nav class="navbar">
    <div class="navbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?php echo getDashboardUrl(); ?>" class="navbar-brand">
            <i class="fas fa-book-open"></i>
            <span class="brand-text"><?php echo getSetting($pdo ?? null, 'library_name', APP_NAME); ?></span>
        </a>
    </div>
    
    <div class="navbar-right">
        <!-- Search -->
        <div class="navbar-search">
            <form action="<?php echo APP_URL; ?>/search.php" method="GET" id="navbarSearchForm">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="q" placeholder="Search books, members..." class="search-input" id="navbarSearch">
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </form>
        </div>
        
        <!-- Notifications -->
        <div class="navbar-notifications">
            <a href="<?php echo getNotificationsUrl(); ?>" class="notification-link" id="notificationToggle">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- User Dropdown -->
        <div class="navbar-user dropdown">
            <button class="dropdown-toggle" id="userDropdown" data-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?php if ($profileImage): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $profileImage; ?>" alt="<?php echo $userName; ?>">
                    <?php else: ?>
                        <span class="avatar-initials"><?php echo getInitials($userName); ?></span>
                    <?php endif; ?>
                </div>
                <span class="user-name"><?php echo $userName; ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user-name"><?php echo $userName; ?></div>
                    <div class="dropdown-user-role"><?php echo ucfirst($userRole); ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?php echo getProfileUrl(); ?>" class="dropdown-item">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="<?php echo APP_URL; ?>/change-password.php" class="dropdown-item">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <?php if (isAdmin()): ?>
                    <a href="<?php echo APP_URL; ?>/admin/settings/index.php" class="dropdown-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?php echo APP_URL; ?>/logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<?php
/**
 * Get dashboard URL based on user role
 * @return string
 */
function getDashboardUrl(): string {
    $role = $_SESSION['user_role'] ?? 'member';
    switch ($role) {
        case 'admin':
            return APP_URL . '/admin/dashboard.php';
        case 'librarian':
            return APP_URL . '/librarian/dashboard.php';
        default:
            return APP_URL . '/member/dashboard.php';
    }
}

/**
 * Get profile URL based on user role
 * @return string
 */
function getProfileUrl(): string {
    return isMember() ? APP_URL . '/member/profile.php' : APP_URL . '/admin/users/view.php?id=' . getCurrentUserId();
}

/**
 * Get notifications URL based on user role
 * @return string
 */
function getNotificationsUrl(): string {
    return isMember() ? APP_URL . '/member/notifications.php' : APP_URL . '/admin/notifications/index.php';
}

/**
 * Get user initials
 * @param string $name
 * @return string
 */
function getInitials(string $name): string {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return substr($initials, 0, 2);
}

/**
 * Get unread notifications count
 * @param PDO $pdo
 * @param int $userId
 * @return int
 */
function getUnreadNotificationsCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return (int) ($result['count'] ?? 0);
}
?>