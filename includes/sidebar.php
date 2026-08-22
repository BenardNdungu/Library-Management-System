<?php
/**
 * Sidebar Navigation
 * Library Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($path, $currentPage, $currentDir = null): string {
    if ($currentDir === null) {
        $currentDir = $currentPage;
    }

    if (strpos($path, '.php') !== false) {
        return basename($path) === $currentPage ? 'active' : '';
    } else {
        return $currentDir === $path ? 'active' : '';
    }
}

function isActiveParent($path, $currentDir): string {
    return $currentDir === $path ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo getDashboardUrl(); ?>" class="sidebar-brand">
            <i class="fas fa-book-open"></i>
            <span class="brand-text"><?php echo getSetting($pdo ?? null, 'library_name', APP_NAME); ?></span>
        </a>
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-section-label">Overview</li>
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?php echo getDashboardUrl(); ?>" class="nav-link <?php echo isActive('dashboard.php', $currentPage, $currentDir); ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <?php if (isMember()): ?>
            <li class="nav-section-label">My Library</li>
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/member/catalog.php" class="nav-link <?php echo isActive('catalog.php', $currentPage, $currentDir); ?>">
                    <i class="fas fa-book"></i>
                    <span>Catalog</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/member/borrowed-books.php" class="nav-link <?php echo isActive('borrowed-books.php', $currentPage, $currentDir); ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>My Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/member/reservations.php" class="nav-link <?php echo isActive('reservations.php', $currentPage, $currentDir); ?>">
                    <i class="fas fa-clock"></i>
                    <span>My Reservations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/member/fines.php" class="nav-link <?php echo isActive('fines.php', $currentPage, $currentDir); ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>My Fines</span>
                </a>
            </li>
            <?php else: ?>
            
            <?php if (isAdmin()): ?>
            <li class="nav-section-label">Administration</li>
            <!-- Users -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/users/index.php" class="nav-link <?php echo isActive('users', $currentDir); ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Users</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-section-label">Library</li>
            <!-- Books -->
            <li class="nav-item <?php echo isActiveParent('books', $currentDir) || isActiveParent('authors', $currentDir) || isActiveParent('categories', $currentDir) || isActiveParent('publishers', $currentDir) ? 'active-parent' : ''; ?>">
                <a href="#books-menu" class="nav-link nav-link-toggle" data-toggle="collapse" aria-expanded="<?php echo isActiveParent('books', $currentDir) || isActiveParent('authors', $currentDir) || isActiveParent('categories', $currentDir) || isActiveParent('publishers', $currentDir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-book"></i>
                    <span>Books</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="nav-submenu <?php echo isActiveParent('books', $currentDir) || isActiveParent('authors', $currentDir) || isActiveParent('categories', $currentDir) || isActiveParent('publishers', $currentDir) ? 'show' : ''; ?>" id="books-menu">
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/books/index.php" class="nav-link <?php echo isActive('index.php', $currentPage, $currentDir); ?>">
                            <i class="fas fa-list"></i>
                            <span>All Books</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/books/create.php" class="nav-link <?php echo isActive('create.php', $currentPage, $currentDir); ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Book</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/authors/index.php" class="nav-link <?php echo isActive('authors', $currentDir); ?>">
                            <i class="fas fa-user-edit"></i>
                            <span>Authors</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/categories/index.php" class="nav-link <?php echo isActive('categories', $currentDir); ?>">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/publishers/index.php" class="nav-link <?php echo isActive('publishers', $currentDir); ?>">
                            <i class="fas fa-building"></i>
                            <span>Publishers</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- Members -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/members/index.php" class="nav-link <?php echo isActive('members', $currentDir); ?>">
                    <i class="fas fa-users"></i>
                    <span>Members</span>
                </a>
            </li>
            
            <li class="nav-section-label">Circulation</li>
            <!-- Loans -->
            <li class="nav-item <?php echo isActiveParent('loans', $currentDir) || isActiveParent('returns', $currentDir) ? 'active-parent' : ''; ?>">
                <a href="#loans-menu" class="nav-link nav-link-toggle" data-toggle="collapse" aria-expanded="<?php echo isActiveParent('loans', $currentDir) || isActiveParent('returns', $currentDir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Loans</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="nav-submenu <?php echo isActiveParent('loans', $currentDir) || isActiveParent('returns', $currentDir) ? 'show' : ''; ?>" id="loans-menu">
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/loans/index.php" class="nav-link <?php echo isActive('index.php', $currentPage, $currentDir); ?>">
                            <i class="fas fa-list"></i>
                            <span>All Loans</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/loans/create.php" class="nav-link <?php echo isActive('create.php', $currentPage, $currentDir); ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span>Issue Book</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/returns/index.php" class="nav-link <?php echo isActive('returns', $currentDir); ?>">
                            <i class="fas fa-undo-alt"></i>
                            <span>Return Book</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- Reservations -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/reservations/index.php" class="nav-link <?php echo isActive('reservations', $currentDir); ?>">
                    <i class="fas fa-clock"></i>
                    <span>Reservations</span>
                </a>
            </li>
            
            <!-- Fines & Payments -->
            <li class="nav-item <?php echo isActiveParent('fines', $currentDir) || isActiveParent('payments', $currentDir) ? 'active-parent' : ''; ?>">
                <a href="#fines-menu" class="nav-link nav-link-toggle" data-toggle="collapse" aria-expanded="<?php echo isActiveParent('fines', $currentDir) || isActiveParent('payments', $currentDir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Fines & Payments</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="nav-submenu <?php echo isActiveParent('fines', $currentDir) || isActiveParent('payments', $currentDir) ? 'show' : ''; ?>" id="fines-menu">
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/fines/index.php" class="nav-link <?php echo isActive('fines', $currentDir); ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Fines</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/admin/payments/index.php" class="nav-link <?php echo isActive('payments', $currentDir); ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="nav-section-label">Insights</li>
            <!-- Reports -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/reports/index.php" class="nav-link <?php echo isActive('reports', $currentDir); ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <!-- Notifications -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/notifications/index.php" class="nav-link <?php echo isActive('notifications', $currentDir); ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="nav-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if (isAdmin()): ?>
            <li class="nav-section-label">System</li>
            <!-- Settings -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/settings/index.php" class="nav-link <?php echo isActive('settings', $currentDir); ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <!-- Audit Logs -->
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/audit-logs/index.php" class="nav-link <?php echo isActive('audit-logs', $currentDir); ?>">
                    <i class="fas fa-history"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-sm">
                <?php if ($profileImage): ?>
                    <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $profileImage; ?>" alt="<?php echo $userName; ?>">
                <?php else: ?>
                    <span class="avatar-initials"><?php echo getInitials($userName); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo $userName; ?></span>
                <span class="user-role"><?php echo ucfirst($userRole); ?></span>
            </div>
            <a href="<?php echo APP_URL; ?>/logout.php" class="logout-link" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>