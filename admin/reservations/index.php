<?php
/**
 * Reservation Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Reservation Management';

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($status) {
    $where[] = "r.status = ?";
    $params[] = $status;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM reservations r
    JOIN members m ON r.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN books b ON r.book_id = b.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalReservations = $stmt->fetch()['total'];
$totalPages = ceil($totalReservations / $limit);

// Get reservations
$sql = "
    SELECT r.*,
           m.member_number,
           u.name as member_name,
           u.email as member_email,
           b.title as book_title,
           b.isbn as book_isbn
    FROM reservations r
    JOIN members m ON r.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN books b ON r.book_id = b.id
    $whereClause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reservations GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Handle status update
if (isset($_GET['update_status']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $reservationId = (int) $_GET['update_status'];
    $newStatus = sanitizeInput($_GET['new_status'] ?? '');
    
    if (in_array($newStatus, ['Pending', 'Ready', 'Completed', 'Cancelled', 'Expired'])) {
        try {
            $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $reservationId]);
            
            // If status is 'Ready', notify member
            if ($newStatus === 'Ready') {
                $stmt = $pdo->prepare("
                    SELECT r.*, m.user_id, b.title
                    FROM reservations r
                    JOIN members m ON r.member_id = m.id
                    JOIN books b ON r.book_id = b.id
                    WHERE r.id = ?
                ");
                $stmt->execute([$reservationId]);
                $res = $stmt->fetch();
                if ($res) {
                    createNotification(
                        $pdo,
                        $res['user_id'],
                        'Reservation Ready',
                        'Your reservation for "' . $res['title'] . '" is now ready for pickup.',
                        'success'
                    );
                }
            }
            
            createAuditLog($pdo, getCurrentUserId(), 'update_reservation', 'reservations', $reservationId, 'Updated reservation status to: ' . $newStatus);
            $_SESSION['success'] = 'Reservation status updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    }
    redirect('index.php');
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-clock"></i> Reservation Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Reservations
            </div>
        </div>
        <div class="page-actions">
            <a href="../loans/create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Issue Book
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="min-width:150px;">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Ready" <?php echo $status === 'Ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="Expired" <?php echo $status === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-3">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $statusCounts['Pending'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Ready</div>
                    <div class="stat-value"><?php echo $statusCounts['Ready'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?php echo $statusCounts['Completed'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Cancelled/Expired</div>
                    <div class="stat-value"><?php echo ($statusCounts['Cancelled'] ?? 0) + ($statusCounts['Expired'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservations Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Reservation Date</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No reservations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>#<?php echo $reservation['id']; ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo $reservation['member_name']; ?></div>
                                            <small class="text-muted"><?php echo $reservation['member_number']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?php echo $reservation['book_title']; ?></div>
                                            <?php if ($reservation['book_isbn']): ?>
                                                <small class="text-muted">ISBN: <?php echo $reservation['book_isbn']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($reservation['reservation_date']); ?></td>
                                    <td><?php echo formatDate($reservation['expiry_date']); ?></td>
                                    <td><?php echo getStatusBadge($reservation['status']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($reservation['status'] === 'Pending'): ?>
                                                <a href="index.php?update_status=<?php echo $reservation['id']; ?>&new_status=Ready&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-success"
                                                   data-confirm="Mark this reservation as ready?">
                                                    <i class="fas fa-check"></i> Ready
                                                </a>
                                                <a href="index.php?update_status=<?php echo $reservation['id']; ?>&new_status=Cancelled&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   data-confirm="Cancel this reservation?">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php elseif ($reservation['status'] === 'Ready'): ?>
                                                <a href="index.php?update_status=<?php echo $reservation['id']; ?>&new_status=Completed&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-info"
                                                   data-confirm="Mark this reservation as completed?">
                                                    <i class="fas fa-check-double"></i> Complete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>