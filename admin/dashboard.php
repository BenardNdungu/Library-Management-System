<?php
/**
 * Admin Dashboard
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();

// Get statistics
$stats = [];

// Total books
$stmt = $pdo->query("SELECT COUNT(*) as count FROM books");
$stats['total_books'] = $stmt->fetch()['count'];

// Total book copies
$stmt = $pdo->query("SELECT COUNT(*) as count FROM book_copies");
$stats['total_copies'] = $stmt->fetch()['count'];

// Available copies
$stmt = $pdo->query("SELECT COUNT(*) as count FROM book_copies WHERE status = 'Available'");
$stats['available_copies'] = $stmt->fetch()['count'];

// Borrowed books
$stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status IN ('Borrowed', 'Overdue')");
$stats['borrowed_books'] = $stmt->fetch()['count'];

// Overdue books
$stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'Overdue'");
$stats['overdue_books'] = $stmt->fetch()['count'];

// Total members
$stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
$stats['total_members'] = $stmt->fetch()['count'];

// Active members
$stmt = $pdo->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
$stats['active_members'] = $stmt->fetch()['count'];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch()['count'];

// Pending reservations
$stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'Pending'");
$stats['pending_reservations'] = $stmt->fetch()['count'];

// Outstanding fines
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE status = 'Unpaid'");
$stats['outstanding_fines'] = $stmt->fetch()['total'];

// Total fines collected
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments");
$stats['total_fines_collected'] = $stmt->fetch()['total'];

// Books borrowed by month (last 12 months)
$monthlyBorrowed = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE DATE_FORMAT(issue_date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthlyBorrowed['labels'][] = $monthLabel;
    $monthlyBorrowed['values'][] = (int) $stmt->fetch()['count'];
}

// New members by month (last 12 months)
$monthlyMembers = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members WHERE DATE_FORMAT(registration_date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthlyMembers['labels'][] = $monthLabel;
    $monthlyMembers['values'][] = (int) $stmt->fetch()['count'];
}

// Books by category
$stmt = $pdo->query("
    SELECT c.name, COUNT(b.id) as count 
    FROM categories c 
    LEFT JOIN books b ON c.id = b.category_id 
    GROUP BY c.id 
    ORDER BY count DESC 
    LIMIT 10
");
$booksByCategory = ['labels' => [], 'values' => []];
while ($row = $stmt->fetch()) {
    $booksByCategory['labels'][] = $row['name'];
    $booksByCategory['values'][] = (int) $row['count'];
}

// Most borrowed books
$stmt = $pdo->query("
    SELECT b.title, COUNT(l.id) as count 
    FROM loans l 
    JOIN book_copies bc ON l.book_copy_id = bc.id 
    JOIN books b ON bc.book_id = b.id 
    GROUP BY b.id 
    ORDER BY count DESC 
    LIMIT 5
");
$mostBorrowed = ['labels' => [], 'values' => []];
while ($row = $stmt->fetch()) {
    $mostBorrowed['labels'][] = strlen($row['title']) > 20 ? substr($row['title'], 0, 20) . '...' : $row['title'];
    $mostBorrowed['values'][] = (int) $row['count'];
}

// Fine collection by month
$monthlyFines = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthlyFines['labels'][] = $monthLabel;
    $monthlyFines['values'][] = (float) $stmt->fetch()['total'];
}

// Recent activities
$recentActivities = [];

// Recent loans
$stmt = $pdo->query("
    SELECT l.*, m.member_number, u.name as member_name, b.title as book_title
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN book_copies bc ON l.book_copy_id = bc.id
    JOIN books b ON bc.book_id = b.id
    ORDER BY l.created_at DESC
    LIMIT 5
");
while ($row = $stmt->fetch()) {
    $recentActivities[] = [
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
    LIMIT 5
");
while ($row = $stmt->fetch()) {
    $recentActivities[] = [
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
    LIMIT 5
");
while ($row = $stmt->fetch()) {
    $recentActivities[] = [
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
    LIMIT 5
");
while ($row = $stmt->fetch()) {
    $recentActivities[] = [
        'date' => formatDate($row['payment_date'], 'Y-m-d H:i'),
        'type' => 'Payment',
        'description' => 'Payment of ' . formatCurrency($row['amount']) . ' from ' . $row['member_name'],
        'user' => $row['member_number']
    ];
}

// Sort activities by date (most recent first)
usort($recentActivities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentActivities = array_slice($recentActivities, 0, 10);

$pageTitle = 'Dashboard';
$pageScripts = ['dashboard.js'];
include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-pie"></i> Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Dashboard
            </div>
        </div>
        <div class="page-actions">
            <span class="text-muted">Last updated: <?php echo date('Y-m-d H:i'); ?></span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row dashboard-stats">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Books</div>
                    <div class="stat-value" id="total-books"><?php echo $stats['total_books']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-copy"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Copies</div>
                    <div class="stat-value" id="total-copies"><?php echo $stats['total_copies']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Available Copies</div>
                    <div class="stat-value" id="available-copies"><?php echo $stats['available_copies']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Borrowed Books</div>
                    <div class="stat-value" id="borrowed-books"><?php echo $stats['borrowed_books']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Overdue Books</div>
                    <div class="stat-value" id="overdue-books"><?php echo $stats['overdue_books']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value" id="total-members"><?php echo $stats['total_members']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value" id="total-users"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Active Members</div>
                    <div class="stat-value" id="active-members"><?php echo $stats['active_members']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Pending Reservations</div>
                    <div class="stat-value" id="pending-reservations"><?php echo $stats['pending_reservations']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Outstanding Fines</div>
                    <div class="stat-value" id="outstanding-fines"><?php echo formatCurrency($stats['outstanding_fines']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Fines Collected</div>
                    <div class="stat-value" id="total-fines-collected"><?php echo formatCurrency($stats['total_fines_collected']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-3">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Books Borrowed by Month</h5>
                </div>
                <div class="card-body">
                    <canvas id="borrowedByMonthChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-plus"></i> New Members by Month</h5>
                </div>
                <div class="card-body">
                    <canvas id="newMembersChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tags"></i> Books by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="booksByCategoryChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-trophy"></i> Most Borrowed Books</h5>
                </div>
                <div class="card-body">
                    <canvas id="mostBorrowedChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-coins"></i> Fine Collection</h5>
                </div>
                <div class="card-body">
                    <canvas id="fineCollectionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    <span class="text-muted">Last 10 activities</span>
                </div>
                <div class="card-body recent-activity">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td><?php echo $activity['date']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $activity['type'] === 'Loan' ? 'info' : 
                                                    ($activity['type'] === 'Return' ? 'success' : 
                                                    ($activity['type'] === 'Payment' ? 'primary' : 'secondary')); 
                                            ?>">
                                                <?php echo $activity['type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $activity['description']; ?></td>
                                        <td><?php echo $activity['user']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Hidden data for charts -->
<script id="chartData" type="application/json">
{
    "borrowedByMonth": <?php echo json_encode($monthlyBorrowed); ?>,
    "newMembersByMonth": <?php echo json_encode($monthlyMembers); ?>,
    "booksByCategory": <?php echo json_encode($booksByCategory); ?>,
    "mostBorrowedBooks": <?php echo json_encode($mostBorrowed); ?>,
    "fineCollection": <?php echo json_encode($monthlyFines); ?>
}
</script>

<?php include_once '../includes/footer.php'; ?>