<?php
/**
 * Create Book
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Add Book';

$errors = [];
$formData = [];

// Get dropdown data
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$authors = $pdo->query("SELECT id, name FROM authors ORDER BY name")->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'isbn' => sanitizeInput($_POST['isbn'] ?? ''),
        'title' => sanitizeInput($_POST['title'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'author_id' => (int) ($_POST['author_id'] ?? 0),
        'publisher_id' => (int) ($_POST['publisher_id'] ?? 0),
        'publication_year' => (int) ($_POST['publication_year'] ?? 0),
        'edition' => sanitizeInput($_POST['edition'] ?? ''),
        'language' => sanitizeInput($_POST['language'] ?? 'English'),
        'pages' => (int) ($_POST['pages'] ?? 0),
        'shelf_location' => sanitizeInput($_POST['shelf_location'] ?? ''),
        'total_copies' => (int) ($_POST['total_copies'] ?? 1),
        'copy_condition' => sanitizeInput($_POST['copy_condition'] ?? 'Good'),
        'copy_price' => (float) ($_POST['copy_price'] ?? 0),
        'purchase_date' => sanitizeInput($_POST['purchase_date'] ?? '')
    ];
    
    // Validate
    if (empty($formData['title'])) {
        $errors['title'] = 'Title is required';
    }
    
    if (empty($formData['category_id'])) {
        $errors['category_id'] = 'Category is required';
    }
    
    if (empty($formData['author_id'])) {
        $errors['author_id'] = 'Author is required';
    }
    
    if (empty($formData['total_copies']) || $formData['total_copies'] < 1) {
        $errors['total_copies'] = 'At least 1 copy is required';
    }
    
    // Handle cover image upload
    $coverImage = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadBookCover($_FILES['cover_image']);
        if ($uploadResult['success']) {
            $coverImage = $uploadResult['filename'];
        } else {
            $errors['cover_image'] = $uploadResult['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert book
            $stmt = $pdo->prepare("
                INSERT INTO books (
                    isbn, title, description, category_id, author_id, publisher_id,
                    publication_year, edition, language, pages, cover_image,
                    shelf_location, total_copies, available_copies
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formData['isbn'],
                $formData['title'],
                $formData['description'],
                $formData['category_id'] ?: null,
                $formData['author_id'] ?: null,
                $formData['publisher_id'] ?: null,
                $formData['publication_year'] ?: null,
                $formData['edition'] ?: null,
                $formData['language'],
                $formData['pages'] ?: null,
                $coverImage,
                $formData['shelf_location'] ?: null,
                $formData['total_copies'],
                $formData['total_copies'] // Initially all copies are available
            ]);
            
            $bookId = $pdo->lastInsertId();
            
            // Create book copies
            for ($i = 1; $i <= $formData['total_copies']; $i++) {
                $accessionNumber = generateAccessionNumber($pdo);
                $barcode = 'BAR-' . str_pad($bookId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO book_copies (
                        book_id, accession_number, barcode, `condition`, status,
                        purchase_date, price, location
                    ) VALUES (?, ?, ?, ?, 'Available', ?, ?, ?)
                ");
                $stmt->execute([
                    $bookId,
                    $accessionNumber,
                    $barcode,
                    $formData['copy_condition'],
                    $formData['purchase_date'] ?: null,
                    $formData['copy_price'] ?: null,
                    $formData['shelf_location'] ?: null
                ]);
            }
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'create_book', 'books', $bookId, 'Created book: ' . $formData['title']);
            
            $_SESSION['success'] = 'Book created successfully with ' . $formData['total_copies'] . ' copies.';
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
            
            // Delete uploaded cover if error
            if ($coverImage && file_exists(BOOK_COVER_PATH . $coverImage)) {
                unlink(BOOK_COVER_PATH . $coverImage);
            }
        }
    }
}

/**
 * Upload book cover image
 */
function uploadBookCover($file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'book_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = BOOK_COVER_PATH . $filename;
    
    if (!is_dir(BOOK_COVER_PATH)) {
        mkdir(BOOK_COVER_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

$pageScripts = ['books.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-plus-circle"></i> Add Book</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Books</a> / Add
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Books
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="create.php" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                
                <div class="row">
                    <div class="col-8">
                        <div class="form-group">
                            <label for="title">Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['title'] ?? ''; ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="text-danger"><?php echo $errors['title']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control" 
                                   value="<?php echo $formData['isbn'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo $formData['description'] ?? ''; ?></textarea>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="category_id">Category <span class="text-danger">*</span></label>
                            <select id="category_id" name="category_id" class="form-control <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($formData['category_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category_id'])): ?>
                                <div class="text-danger"><?php echo $errors['category_id']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="author_id">Author <span class="text-danger">*</span></label>
                            <select id="author_id" name="author_id" class="form-control <?php echo isset($errors['author_id']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Select Author</option>
                                <?php foreach ($authors as $auth): ?>
                                    <option value="<?php echo $auth['id']; ?>" <?php echo ($formData['author_id'] ?? 0) == $auth['id'] ? 'selected' : ''; ?>>
                                        <?php echo $auth['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['author_id'])): ?>
                                <div class="text-danger"><?php echo $errors['author_id']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="publisher_id">Publisher</label>
                            <select id="publisher_id" name="publisher_id" class="form-control">
                                <option value="">Select Publisher</option>
                                <?php foreach ($publishers as $pub): ?>
                                    <option value="<?php echo $pub['id']; ?>" <?php echo ($formData['publisher_id'] ?? 0) == $pub['id'] ? 'selected' : ''; ?>>
                                        <?php echo $pub['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" class="form-control" 
                                   value="<?php echo $formData['publication_year'] ?? ''; ?>" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edition">Edition</label>
                            <input type="text" id="edition" name="edition" class="form-control" 
                                   value="<?php echo $formData['edition'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-control" 
                                   value="<?php echo $formData['language'] ?? 'English'; ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="pages">Pages</label>
                            <input type="number" id="pages" name="pages" class="form-control" 
                                   value="<?php echo $formData['pages'] ?? ''; ?>" min="1">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="shelf_location">Shelf Location</label>
                            <input type="text" id="shelf_location" name="shelf_location" class="form-control" 
                                   value="<?php echo $formData['shelf_location'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="cover_image">Cover Image</label>
                            <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                            <?php if (isset($errors['cover_image'])): ?>
                                <div class="text-danger"><?php echo $errors['cover_image']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. Allowed: JPG, PNG, GIF, WebP</div>
                        </div>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3">Initial Copy Details</h5>
                
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="total_copies">Number of Copies <span class="text-danger">*</span></label>
                            <input type="number" id="total_copies" name="total_copies" class="form-control <?php echo isset($errors['total_copies']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['total_copies'] ?? 1; ?>" min="1" required>
                            <?php if (isset($errors['total_copies'])): ?>
                                <div class="text-danger"><?php echo $errors['total_copies']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="copy_condition">Condition</label>
                            <select id="copy_condition" name="copy_condition" class="form-control">
                                <option value="New" <?php echo ($formData['copy_condition'] ?? '') === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="Good" <?php echo ($formData['copy_condition'] ?? '') === 'Good' ? 'selected' : ''; ?>>Good</option>
                                <option value="Fair" <?php echo ($formData['copy_condition'] ?? '') === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                <option value="Poor" <?php echo ($formData['copy_condition'] ?? '') === 'Poor' ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="copy_price">Price</label>
                            <input type="number" id="copy_price" name="copy_price" class="form-control" 
                                   value="<?php echo $formData['copy_price'] ?? ''; ?>" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                                   value="<?php echo $formData['purchase_date'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Book
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>
