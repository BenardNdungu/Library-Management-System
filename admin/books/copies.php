<?php
/**
 * Book Copy Management
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Book Copies';

$bookId = isset($_GET['book_id']) ? (int) $_GET['book_id'] : 0;
if (!$bookId) {
    $_SESSION['error'] = 'Book ID required';
    redirect('index.php');
}

// Get book details
$stmt = $pdo->prepare("SELECT id, title FROM books WHERE id = ?");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    $_SESSION['error'] = 'Book not found';
    redirect('index.php');
}

// Get copies
$stmt = $pdo->prepare("SELECT * FROM book_copies WHERE book_id = ? ORDER BY accession_number");
$stmt->execute([$bookId]);
$copies = $stmt->fetchAll();

// Handle add copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    requireCsrfToken();
    
    $accessionNumber = generateAccessionNumber($pdo);
    $barcode = sanitizeInput($_POST['barcode'] ?? '');
    $condition = sanitizeInput($_POST['condition'] ?? 'Good');
    $status = sanitizeInput($_POST['status'] ?? 'Available');
    $purchaseDate = sanitizeInput($_POST['purchase_date'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $location = sanitizeInput($_POST['location'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO book_copies (book_id, accession_number, barcode, `condition`, status, purchase_date, price, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bookId, $accessionNumber, $barcode, $condition, $status, $purchaseDate ?: null, $price ?: null, $location ?: null]);
        
        // Update book total copies
        $stmt = $pdo->prepare("UPDATE books SET total_copies = total_copies + 1 WHERE id = ?");
        $stmt->execute([$bookId]);
        
        if ($status === 'Available') {
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
            $stmt->execute([$bookId]);
        }
        
        createAuditLog($pdo, getCurrentUserId(), 'add_book_copy', 'book_copies', $pdo->lastInsertId(), 'Added copy to book: ' . $book['title']);
        $_SESSION['success'] = 'Copy added successfully. Accession: ' . $accessionNumber;
        redirect('copies.php?book_id=' . $bookId);
    } catch (PDOException $e) {
        $errors['general'] = 'Database error: ' . $e->getMessage();
    }
}

// Handle edit copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    requireCsrfToken();
    
    $copyId = (int) ($_POST['copy_id'] ?? 0);
    $barcode = sanitizeInput($_POST['barcode'] ?? '');
    $condition = sanitizeInput($_POST['condition'] ?? 'Good');
    $status = sanitizeInput($_POST['status'] ?? 'Available');
    $purchaseDate = sanitizeInput($_POST['purchase_date'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $location = sanitizeInput($_POST['location'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE book_copies SET 
                barcode = ?, `condition` = ?, status = ?,
                purchase_date = ?, price = ?, location = ?
            WHERE id = ? AND book_id = ?
        ");
        $stmt->execute([$barcode, $condition, $status, $purchaseDate ?: null, $price ?: null, $location, $copyId, $bookId]);
        
        // Update book available copies count
        updateBookAvailabilityCounts($pdo, $bookId);
        
        createAuditLog($pdo, getCurrentUserId(), 'update_book_copy', 'book_copies', $copyId, 'Updated copy for book: ' . $book['title']);
        $_SESSION['success'] = 'Copy updated successfully.';
        redirect('copies.php?book_id=' . $bookId);
    } catch (PDOException $e) {
        $errors['general'] = 'Database error: ' . $e->getMessage();
    }
}

// Handle delete copy
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    requireCsrfToken($_GET['csrf_token']);
    $copyId = (int) $_GET['delete'];
    
    try {
        // Check if copy is borrowed
        $stmt = $pdo->prepare("SELECT status FROM book_copies WHERE id = ? AND book_id = ?");
        $stmt->execute([$copyId, $bookId]);
        $copy = $stmt->fetch();
        
        if ($copy && $copy['status'] === 'Borrowed') {
            $_SESSION['error'] = 'Cannot delete a borrowed copy. Please return it first.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM book_copies WHERE id = ? AND book_id = ?");
            $stmt->execute([$copyId, $bookId]);
            
            // Update book total copies
            $stmt = $pdo->prepare("UPDATE books SET total_copies = total_copies - 1 WHERE id = ?");
            $stmt->execute([$bookId]);
            
            updateBookAvailabilityCounts($pdo, $bookId);
            
            createAuditLog($pdo, getCurrentUserId(), 'delete_book_copy', 'book_copies', $copyId, 'Deleted copy from book: ' . $book['title']);
            $_SESSION['success'] = 'Copy deleted successfully.';
        }
        redirect('copies.php?book_id=' . $bookId);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Cannot delete copy. It may have associated records.';
        redirect('copies.php?book_id=' . $bookId);
    }
}

/**
 * Update book availability counts
 */
function updateBookAvailabilityCounts($pdo, $bookId): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM book_copies WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as available FROM book_copies WHERE book_id = ? AND status = 'Available'");
    $stmt->execute([$bookId]);
    $available = $stmt->fetch()['available'];
    
    $stmt = $pdo->prepare("UPDATE books SET total_copies = ?, available_copies = ? WHERE id = ?");
    $stmt->execute([$total, $available, $bookId]);
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageScripts = ['books.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-copy"></i> Manage Copies - <?php echo $book['title']; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Books</a> / <a href="view.php?id=<?php echo $bookId; ?>">View</a> / Copies
            </div>
        </div>
        <div class="page-actions">
            <a href="view.php?id=<?php echo $bookId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View Book
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Add Copy Form -->
    <div class="card mb-3">
        <div class="card-header">
            <h5><i class="fas fa-plus"></i> Add Copy</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="copies.php?book_id=<?php echo $bookId; ?>" class="row g-3">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="col-2">
                    <div class="form-group">
                        <label for="barcode">Barcode</label>
                        <input type="text" id="barcode" name="barcode" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="col-2">
                    <div class="form-group">
                        <label for="condition">Condition</label>
                        <select id="condition" name="condition" class="form-control">
                            <option value="New">New</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                </div>
                <div class="col-2">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="Available">Available</option>
                            <option value="Reserved">Reserved</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="col-2">
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="col-2">
                    <div class="form-group">
                        <label for="purchase_date">Purchase Date</label>
                        <input type="date" id="purchase_date" name="purchase_date" class="form-control">
                    </div>
                </div>
                <div class="col-2">
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="Shelf location">
                    </div>
                </div>
                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Copy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Copies Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Accession</th>
                            <th>Barcode</th>
                            <th>Status</th>
                            <th>Condition</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Purchase Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($copies)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No copies found for this book</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($copies as $copy): ?>
                                <tr>
                                    <td><strong><?php echo $copy['accession_number']; ?></strong></td>
                                    <td><?php echo $copy['barcode'] ?: 'N/A'; ?></td>
                                    <td><?php echo getStatusBadge($copy['status'], 'copy'); ?></td>
                                    <td><?php echo $copy['condition']; ?></td>
                                    <td><?php echo $copy['location'] ?: 'N/A'; ?></td>
                                    <td><?php echo $copy['price'] ? formatCurrency($copy['price']) : 'N/A'; ?></td>
                                    <td><?php echo $copy['purchase_date'] ? formatDate($copy['purchase_date']) : 'N/A'; ?></td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <button class="btn btn-sm btn-warning" data-modal="editCopyModal" 
                                                    onclick="editCopy(<?php echo htmlspecialchars(json_encode($copy)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($copy['status'] !== 'Borrowed'): ?>
                                                <a href="copies.php?book_id=<?php echo $bookId; ?>&delete=<?php echo $copy['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   data-confirm="Are you sure you want to delete this copy?">
                                                    <i class="fas fa-trash"></i>
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
        </div>
    </div>
</main>

<!-- Edit Copy Modal -->
<div class="modal-backdrop" id="editCopyModal">
    <div class="modal">
        <div class="modal-header">
            <h5>Edit Copy</h5>
            <button class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="copies.php?book_id=<?php echo $bookId; ?>" id="editCopyForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="copy_id" id="edit_copy_id">
                
                <div class="form-group">
                    <label for="edit_barcode">Barcode</label>
                    <input type="text" id="edit_barcode" name="barcode" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label for="edit_condition">Condition</label>
                    <select id="edit_condition" name="condition" class="form-control">
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                        <option value="Damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" class="form-control">
                        <option value="Available">Available</option>
                        <option value="Reserved">Reserved</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Lost">Lost</option>
                        <option value="Damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_price">Price</label>
                    <input type="number" id="edit_price" name="price" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="edit_purchase_date">Purchase Date</label>
                    <input type="date" id="edit_purchase_date" name="purchase_date" class="form-control">
                </div>
                <div class="form-group">
                    <label for="edit_location">Location</label>
                    <input type="text" id="edit_location" name="location" class="form-control" placeholder="Shelf location">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('editCopyForm').submit()">
                <i class="fas fa-save"></i> Update Copy
            </button>
        </div>
    </div>
</div>

<script>
function editCopy(copy) {
    document.getElementById('edit_copy_id').value = copy.id;
    document.getElementById('edit_barcode').value = copy.barcode || '';
    document.getElementById('edit_condition').value = copy.condition || 'Good';
    document.getElementById('edit_status').value = copy.status || 'Available';
    document.getElementById('edit_price').value = copy.price || '';
    document.getElementById('edit_purchase_date').value = copy.purchase_date || '';
    document.getElementById('edit_location').value = copy.location || '';
    document.getElementById('editCopyModal').classList.add('show');
}
</script>

<?php include_once '../../includes/footer.php'; ?>