<?php
/**
 * Edit Book
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Edit Book';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$bookId) {
    $_SESSION['error'] = 'Book ID required';
    redirect('index.php');
}

// Get book details
$stmt = $pdo->prepare("
    SELECT b.*, 
           c.name as category_name,
           a.name as author_name,
           p.name as publisher_name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN publishers p ON b.publisher_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    $_SESSION['error'] = 'Book not found';
    redirect('index.php');
}

// Get dropdown data
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$authors = $pdo->query("SELECT id, name FROM authors ORDER BY name")->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();

$errors = [];
$formData = $book;

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
        'shelf_location' => sanitizeInput($_POST['shelf_location'] ?? '')
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
    
    // Handle cover image upload
    $coverImage = $book['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadBookCover($_FILES['cover_image']);
        if ($uploadResult['success']) {
            // Delete old cover if exists
            if ($coverImage && file_exists(BOOK_COVER_PATH . $coverImage)) {
                unlink(BOOK_COVER_PATH . $coverImage);
            }
            $coverImage = $uploadResult['filename'];
        } else {
            $errors['cover_image'] = $uploadResult['message'];
        }
    }
    
    // Handle cover removal
    if (isset($_POST['remove_cover']) && $_POST['remove_cover'] == '1') {
        if ($coverImage && file_exists(BOOK_COVER_PATH . $coverImage)) {
            unlink(BOOK_COVER_PATH . $coverImage);
        }
        $coverImage = null;
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE books SET 
                    isbn = ?, title = ?, description = ?, category_id = ?, author_id = ?,
                    publisher_id = ?, publication_year = ?, edition = ?, language = ?,
                    pages = ?, cover_image = ?, shelf_location = ?
                WHERE id = ?
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
                $bookId
            ]);
            
            createAuditLog($pdo, getCurrentUserId(), 'update_book', 'books', $bookId, 'Updated book: ' . $formData['title']);
            
            $_SESSION['success'] = 'Book updated successfully.';
            redirect('index.php');
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
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
            <h1><i class="fas fa-edit"></i> Edit Book</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Books</a> / Edit
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
            <form method="POST" action="edit.php?id=<?php echo $bookId; ?>" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                
                <div class="row">
                    <div class="col-8">
                        <div class="form-group">
                            <label for="title">Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['title']; ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="text-danger"><?php echo $errors['title']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control" 
                                   value="<?php echo $formData['isbn']; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo $formData['description']; ?></textarea>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="category_id">Category <span class="text-danger">*</span></label>
                            <select id="category_id" name="category_id" class="form-control <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo $auth['id']; ?>" <?php echo $formData['author_id'] == $auth['id'] ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo $pub['id']; ?>" <?php echo $formData['publisher_id'] == $pub['id'] ? 'selected' : ''; ?>>
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
                                   value="<?php echo $formData['publication_year']; ?>" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edition">Edition</label>
                            <input type="text" id="edition" name="edition" class="form-control" 
                                   value="<?php echo $formData['edition']; ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-control" 
                                   value="<?php echo $formData['language']; ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="pages">Pages</label>
                            <input type="number" id="pages" name="pages" class="form-control" 
                                   value="<?php echo $formData['pages']; ?>" min="1">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="shelf_location">Shelf Location</label>
                            <input type="text" id="shelf_location" name="shelf_location" class="form-control" 
                                   value="<?php echo $formData['shelf_location']; ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="cover_image">Cover Image</label>
                            <?php if ($book['cover_image']): ?>
                                <div class="mb-2">
                                    <img src="<?php echo APP_URL; ?>/uploads/books/<?php echo $book['cover_image']; ?>" 
                                         alt="Current cover" style="max-width:100px;max-height:120px;border-radius:4px;">
                                    <br>
                                    <label>
                                        <input type="checkbox" name="remove_cover" value="1"> Remove current cover
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                            <?php if (isset($errors['cover_image'])): ?>
                                <div class="text-danger"><?php echo $errors['cover_image']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. Allowed: JPG, PNG, GIF, WebP</div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Book
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>