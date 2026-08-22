<?php
/**
 * Edit Author
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Edit Author';

$authorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$authorId) {
    $_SESSION['error'] = 'Author ID required';
    redirect('index.php');
}

// Get author details
$stmt = $pdo->prepare("SELECT * FROM authors WHERE id = ?");
$stmt->execute([$authorId]);
$author = $stmt->fetch();

if (!$author) {
    $_SESSION['error'] = 'Author not found';
    redirect('index.php');
}

$errors = [];
$formData = $author;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'biography' => sanitizeInput($_POST['biography'] ?? '')
    ];
    
    // Validate
    if (empty($formData['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE authors SET name = ?, biography = ? WHERE id = ?");
            $stmt->execute([$formData['name'], $formData['biography'], $authorId]);
            
            createAuditLog($pdo, getCurrentUserId(), 'update_author', 'authors', $authorId, 'Updated author: ' . $formData['name']);
            
            $_SESSION['success'] = 'Author updated successfully.';
            redirect('index.php');
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-edit"></i> Edit Author</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Authors</a> / Edit
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Authors
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="edit.php?id=<?php echo $authorId; ?>" data-validate>
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                           value="<?php echo $formData['name']; ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="text-danger"><?php echo $errors['name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="biography">Biography</label>
                    <textarea id="biography" name="biography" class="form-control" rows="5"><?php echo $formData['biography']; ?></textarea>
                    <div class="form-text">Brief biography of the author</div>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Author
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>