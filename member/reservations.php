<?php
/**
 * Member Reservations
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'My Reservations';

// Get member id
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([getCurrentUserId()]);
$member = $stmt->fetch();
$memberId = $member['id'] ?? 0;

// Get filter
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where = ["r.member_id = ?"];
$params = [$memberId];

if ($status) {
    $where[] = "r.status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM reservations r
    JOIN books b ON r.book_id = b.id
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalReservations = $stmt->fetch()['total'];
$totalPages = ceil($totalReservations / $limit);

// Get reservations
$sql = "
    SELECT r.*, b.title as book_title, b.isbn, b.cover_image
    FROM reservations r
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

// Handle cancel
if (isset($_GET['cancel']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $reservationId = (int) $_GET['cancel'];
    
    $stmt = $pdo->prepare("
        UPDATE reservations SET status = 'Cancelled' 
        WHERE id = ? AND member_id = ? AND status IN ('Pending', 'Ready')
    ");
    $stmt->execute([$reservationId, $memberId]);
    $_SESSION['success'] = 'Reservation cancelled successfully.';
    redirect('reservations.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
include_once '../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-clock"></i> My Reservations</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Reservations
            </div>
        </div>
        <div class="page-actions">
            <a href="catalog.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Browse Catalog
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
            <form method="GET" action="reservations.php" class="d-flex flex-wrap gap-2 align-center">
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
                <a href="reservations.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Reservations Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
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
                                <td colspan="5" class="text-center text-muted">No reservations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-center gap-2">
                                            <?php if ($reservation['cover_image']): ?>
                                                <img src="<?php echo APP_URL; ?>/uploads/books/<?php echo $reservation['cover_image']; ?>" 
                                                     alt="<?php echo $reservation['book_title']; ?>" 
                                                     style="width:40px;height:50px;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <div style="width:40px;height:50px;background:var(--gray-300);border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--gray-500);">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div><?php echo $reservation['book_title']; ?></div>
                                                <?php if ($reservation['isbn']): ?>
                                                    <small class="text-muted">ISBN: <?php echo $reservation['isbn']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($reservation['reservation_date']); ?></td>
                                    <td>
                                        <?php echo formatDate($reservation['expiry_date']); ?>
                                        <?php if ($reservation['status'] === 'Ready' && strtotime($reservation['expiry_date']) < time()): ?>
                                            <span class="badge badge-danger">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo getStatusBadge($reservation['status']); ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($reservation['status'] === 'Pending' || $reservation['status'] === 'Ready'): ?>
                                                <a href="reservations.php?cancel=<?php echo $reservation['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   data-confirm="Cancel this reservation?">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] === 'Ready'): ?>
                                                <span class="badge badge-info">Ready for pickup at the library</span>
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

<?php include_once '../includes/footer.php'; ?>