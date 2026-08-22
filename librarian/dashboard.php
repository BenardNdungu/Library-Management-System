<?php
/**
 * Librarian Dashboard
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require librarian authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Librarian Dashboard';

// Get statistics
$stats = [];

// Total books
$stmt = $pdo->query("SELECT COUNT(*) as count FROM books");
$stats['total_books'] = $stmt->fetch()['count'];

// Total members
$stmt = $pdo->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
$stats['active_members'] = $stmt->fetch()['count'];

// Current loans
$stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status IN ('Borrowed', 'Overdue')");
$stats['current_loans'] = $stmt->fetch()['count'];

// Overdue books
$stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'Overdue'");
$stats['overdue_books'] = $stmt->fetch()['count'];

// Available copies
$stmt = $pdo->query("SELECT COUNT(*) as count FROM book_copies WHERE status = 'Available'");
$stats['available_copies'] = $stmt->fetch()['count'];

// Pending reservations
$stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'Pending'");
$stats['pending_reservations'] = $stmt->fetch()['count'];

// Outstanding fines
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE status = 'Unpaid'");
$stats['outstanding_fines'] = $stmt->fetch()['total'];

// Books borrowed by month (last 6 months)
$monthlyBorrowed = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE DATE_FORMAT(issue_date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthlyBorrowed['labels'][] = $monthLabel;
    $monthlyBorrowed['values'][] = (int) $stmt->fetch()['count'];
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

// Sort activities by date (most recent first)
usort($recentActivities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentActivities = array_slice($recentActivities, 0, 10);

$pageScripts = ['dashboard.js'];
include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-pie"></i> Librarian Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Dashboard
            </div>
        </div>
        <div class="page-actions">
            <span class="text-muted">Welcome, <?php echo $_SESSION['user_name']; ?>!</span>
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
                    <div class="stat-value"><?php echo $stats['total_books']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Active Members</div>
                    <div class="stat-value"><?php echo $stats['active_members']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Current Loans</div>
                    <div class="stat-value"><?php echo $stats['current_loans']; ?></div>
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
                    <div class="stat-value"><?php echo $stats['overdue_books']; ?></div>
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
                    <div class="stat-value"><?php echo $stats['available_copies']; ?></div>
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
                    <div class="stat-value"><?php echo $stats['pending_reservations']; ?></div>
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
                    <div class="stat-value"><?php echo formatCurrency($stats['outstanding_fines']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Monthly Loans</div>
                    <div class="stat-value"><?php echo array_sum($monthlyBorrowed['values']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-3">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Books Borrowed (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="borrowedByMonthChart" height="250"></canvas>
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
                                                    ($activity['type'] === 'Return' ? 'success' : 'secondary'); 
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
    "borrowedByMonth": <?php echo json_encode($monthlyBorrowed); ?>
}
</script>

<?php include_once '../includes/footer.php'; ?>