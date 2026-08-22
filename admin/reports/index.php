<?php
/**
 * Reports Module
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Reports';

// Get report type and filters
$reportType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'books';
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');

$reportData = [];
$reportTitle = '';

// Generate report based on type
switch ($reportType) {
    case 'books':
        $reportTitle = 'Books Report';
        $reportData = generateBooksReport($pdo);
        break;
    case 'books_by_category':
        $reportTitle = 'Books by Category';
        $reportData = generateBooksByCategoryReport($pdo);
        break;
    case 'books_by_author':
        $reportTitle = 'Books by Author';
        $reportData = generateBooksByAuthorReport($pdo);
        break;
    case 'available_books':
        $reportTitle = 'Available Books';
        $reportData = generateAvailableBooksReport($pdo);
        break;
    case 'borrowed_books':
        $reportTitle = 'Borrowed Books';
        $reportData = generateBorrowedBooksReport($pdo);
        break;
    case 'members':
        $reportTitle = 'Members Report';
        $reportData = generateMembersReport($pdo);
        break;
    case 'members_active':
        $reportTitle = 'Active Members';
        $reportData = generateActiveMembersReport($pdo);
        break;
    case 'members_new':
        $reportTitle = 'New Members';
        $reportData = generateNewMembersReport($pdo, $startDate, $endDate);
        break;
    case 'loans_current':
        $reportTitle = 'Current Loans';
        $reportData = generateCurrentLoansReport($pdo);
        break;
    case 'loans_history':
        $reportTitle = 'Loan History';
        $reportData = generateLoanHistoryReport($pdo, $startDate, $endDate);
        break;
    case 'overdue_books':
        $reportTitle = 'Overdue Books';
        $reportData = generateOverdueBooksReport($pdo);
        break;
    case 'most_borrowed':
        $reportTitle = 'Most Borrowed Books';
        $reportData = generateMostBorrowedReport($pdo, $startDate, $endDate);
        break;
    case 'fines_outstanding':
        $reportTitle = 'Outstanding Fines';
        $reportData = generateOutstandingFinesReport($pdo);
        break;
    case 'fines_paid':
        $reportTitle = 'Paid Fines';
        $reportData = generatePaidFinesReport($pdo, $startDate, $endDate);
        break;
    case 'fines_collection':
        $reportTitle = 'Fine Collection';
        $reportData = generateFineCollectionReport($pdo, $startDate, $endDate);
        break;
    case 'reservations_pending':
        $reportTitle = 'Pending Reservations';
        $reportData = generatePendingReservationsReport($pdo);
        break;
    default:
        $reportTitle = 'Books Report';
        $reportData = generateBooksReport($pdo);
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV($reportData, $reportTitle);
}

// Handle Print
$print = isset($_GET['print']) ? true : false;

/**
 * Generate Books Report
 */
function generateBooksReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.id, b.title, b.isbn, c.name as category, a.name as author, 
               p.name as publisher, b.publication_year, b.total_copies, b.available_copies
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        ORDER BY b.title
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Books by Category Report
 */
function generateBooksByCategoryReport($pdo): array {
    $stmt = $pdo->query("
        SELECT c.name as category, COUNT(b.id) as total_books,
               COALESCE(SUM(b.total_copies), 0) as total_copies,
               COALESCE(SUM(b.available_copies), 0) as available_copies
        FROM categories c
        LEFT JOIN books b ON c.id = b.category_id
        GROUP BY c.id
        ORDER BY total_books DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Books by Author Report
 */
function generateBooksByAuthorReport($pdo): array {
    $stmt = $pdo->query("
        SELECT a.name as author, COUNT(b.id) as total_books
        FROM authors a
        LEFT JOIN books b ON a.id = b.author_id
        GROUP BY a.id
        HAVING total_books > 0
        ORDER BY total_books DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Available Books Report
 */
function generateAvailableBooksReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.title, b.isbn, c.name as category, a.name as author,
               b.available_copies, b.shelf_location
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        WHERE b.available_copies > 0
        ORDER BY b.title
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Borrowed Books Report
 */
function generateBorrowedBooksReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.title, b.isbn, u.name as member_name, m.member_number,
               l.issue_date, l.due_date, l.status
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status IN ('Borrowed', 'Overdue')
        ORDER BY l.due_date
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Members Report
 */
function generateMembersReport($pdo): array {
    $stmt = $pdo->query("
        SELECT m.member_number, u.name, u.email, u.phone, m.membership_type,
               m.status, m.registration_date,
               (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_loans,
               (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE member_id = m.id AND status = 'Unpaid') as outstanding_fines
        FROM members m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.registration_date DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Active Members Report
 */
function generateActiveMembersReport($pdo): array {
    $stmt = $pdo->query("
        SELECT m.member_number, u.name, u.email, u.phone, m.membership_type,
               m.registration_date,
               (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status IN ('Borrowed', 'Overdue')) as current_loans
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE m.status = 'active'
        ORDER BY u.name
    ");
    return $stmt->fetchAll();
}

/**
 * Generate New Members Report
 */
function generateNewMembersReport($pdo, $startDate, $endDate): array {
    $stmt = $pdo->prepare("
        SELECT m.member_number, u.name, u.email, u.phone, m.membership_type,
               m.registration_date
        FROM members m
        JOIN users u ON m.user_id = u.id
        WHERE m.registration_date BETWEEN ? AND ?
        ORDER BY m.registration_date DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Generate Current Loans Report
 */
function generateCurrentLoansReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.title, u.name as member_name, m.member_number,
               l.issue_date, l.due_date, l.status,
               CASE WHEN l.status = 'Overdue' THEN DATEDIFF(CURDATE(), l.due_date) ELSE 0 END as overdue_days
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.status IN ('Borrowed', 'Overdue')
        ORDER BY l.due_date
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Loan History Report
 */
function generateLoanHistoryReport($pdo, $startDate, $endDate): array {
    $stmt = $pdo->prepare("
        SELECT b.title, u.name as member_name, m.member_number,
               l.issue_date, l.due_date, l.return_date, l.status
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE l.created_at BETWEEN ? AND ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    return $stmt->fetchAll();
}

/**
 * Generate Overdue Books Report
 */
function generateOverdueBooksReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.title, u.name as member_name, m.member_number,
               l.due_date, DATEDIFF(CURDATE(), l.due_date) as overdue_days,
               COALESCE(f.amount, 0) as fine_amount
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        LEFT JOIN fines f ON l.id = f.loan_id AND f.status = 'Unpaid'
        WHERE l.status = 'Overdue'
        ORDER BY overdue_days DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Most Borrowed Report
 */
function generateMostBorrowedReport($pdo, $startDate, $endDate): array {
    $stmt = $pdo->prepare("
        SELECT b.title, COUNT(l.id) as borrow_count,
               b.isbn, a.name as author
        FROM loans l
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        LEFT JOIN authors a ON b.author_id = a.id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY b.id
        ORDER BY borrow_count DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    return $stmt->fetchAll();
}

/**
 * Generate Outstanding Fines Report
 */
function generateOutstandingFinesReport($pdo): array {
    $stmt = $pdo->query("
        SELECT u.name as member_name, m.member_number,
               f.amount, f.reason, f.created_at,
               b.title as book_title
        FROM fines f
        JOIN members m ON f.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN loans l ON f.loan_id = l.id
        JOIN book_copies bc ON l.book_copy_id = bc.id
        JOIN books b ON bc.book_id = b.id
        WHERE f.status = 'Unpaid'
        ORDER BY f.amount DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Generate Paid Fines Report
 */
function generatePaidFinesReport($pdo, $startDate, $endDate): array {
    $stmt = $pdo->prepare("
        SELECT u.name as member_name, m.member_number,
               f.amount, f.reason, p.payment_date, p.payment_method
        FROM fines f
        JOIN members m ON f.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN payments p ON f.id = p.fine_id
        WHERE f.status = 'Paid' AND p.payment_date BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Generate Fine Collection Report
 */
function generateFineCollectionReport($pdo, $startDate, $endDate): array {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.payment_date, '%Y-%m') as month,
               COUNT(p.id) as payment_count,
               COALESCE(SUM(p.amount), 0) as total_amount
        FROM payments p
        WHERE p.payment_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Generate Pending Reservations Report
 */
function generatePendingReservationsReport($pdo): array {
    $stmt = $pdo->query("
        SELECT b.title, u.name as member_name, m.member_number,
               r.reservation_date, r.expiry_date, r.status
        FROM reservations r
        JOIN members m ON r.member_id = m.id
        JOIN users u ON m.user_id = u.id
        JOIN books b ON r.book_id = b.id
        WHERE r.status IN ('Pending', 'Ready')
        ORDER BY r.reservation_date
    ");
    return $stmt->fetchAll();
}

/**
 * Export data to CSV
 */
function exportCSV($data, $title): void {
    if (empty($data)) {
        $_SESSION['error'] = 'No data to export';
        redirect('index.php');
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Add data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Get report type options
$reportTypes = [
    'books' => 'All Books',
    'books_by_category' => 'Books by Category',
    'books_by_author' => 'Books by Author',
    'available_books' => 'Available Books',
    'borrowed_books' => 'Borrowed Books',
    'members' => 'All Members',
    'members_active' => 'Active Members',
    'members_new' => 'New Members',
    'loans_current' => 'Current Loans',
    'loans_history' => 'Loan History',
    'overdue_books' => 'Overdue Books',
    'most_borrowed' => 'Most Borrowed Books',
    'fines_outstanding' => 'Outstanding Fines',
    'fines_paid' => 'Paid Fines',
    'fines_collection' => 'Fine Collection',
    'reservations_pending' => 'Pending Reservations'
];

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-bar"></i> Reports</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Reports
            </div>
        </div>
        <div class="page-actions no-print">
            <a href="?type=<?php echo $reportType; ?>&export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a href="?type=<?php echo $reportType; ?>&print=1&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 align-center">
                <div class="form-group mb-0" style="min-width:200px;">
                    <select name="type" class="form-control">
                        <?php foreach ($reportTypes as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $reportType === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <label class="sr-only">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="form-group mb-0" style="min-width:150px;">
                    <label class="sr-only">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Generate
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Report Results -->
    <div class="card">
        <div class="card-header">
            <h5><?php echo $reportTitle; ?></h5>
            <span class="text-muted">
                <?php echo count($reportData); ?> records
                <?php if ($reportType === 'members_new' || $reportType === 'loans_history' || $reportType === 'most_borrowed' || $reportType === 'fines_paid' || $reportType === 'fines_collection'): ?>
                    | <?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($reportData)): ?>
                <p class="text-muted text-center">No data found for this report</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($reportData[0]) as $header): ?>
                                    <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td>
                                            <?php 
                                                if (is_numeric($value) && strpos((string)$value, '.') !== false) {
                                                    echo formatCurrency((float)$value);
                                                } elseif (is_numeric($value) && !strpos((string)$value, '.')) {
                                                    echo number_format($value);
                                                } elseif (strtotime((string)$value) !== false && strpos((string)$value, '-') !== false) {
                                                    echo formatDate($value);
                                                } else {
                                                    echo $value;
                                                }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>