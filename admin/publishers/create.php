<?php
/**
 * Create Publisher
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Add Publisher';

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'website' => sanitizeInput($_POST['website'] ?? '')
    ];
    
    // Validate
    if (empty($formData['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if ($formData['email'] && !validateEmail($formData['email'])) {
        $errors['email'] = 'Invalid email address';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO publishers (name, email, phone, address, website) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$formData['name'], $formData['email'], $formData['phone'], $formData['address'], $formData['website']]);
            $publisherId = $pdo->lastInsertId();
            
            createAuditLog($pdo, getCurrentUserId(), 'create_publisher', 'publishers', $publisherId, 'Created publisher: ' . $formData['name']);
            
            $_SESSION['success'] = 'Publisher created successfully.';
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
            <h1><i class="fas fa-building"></i> Add Publisher</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Publishers</a> / Add
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Publishers
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="create.php" data-validate>
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                           value="<?php echo $formData['name'] ?? ''; ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="text-danger"><?php echo $errors['name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['email'] ?? ''; ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="text-danger"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo $formData['phone'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo $formData['address'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" class="form-control" 
                           value="<?php echo $formData['website'] ?? ''; ?>" placeholder="https://example.com">
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Publisher
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>