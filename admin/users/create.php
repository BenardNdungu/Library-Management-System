<?php
/**
 * Create User
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'Add User';

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'role' => sanitizeInput($_POST['role'] ?? 'member'),
        'status' => sanitizeInput($_POST['status'] ?? 'active')
    ];
    
    // Validate
    if (empty($formData['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'Invalid email address';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($formData['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already exists';
        }
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($formData['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, username, password, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formData['name'],
                $formData['email'],
                $formData['phone'],
                $formData['username'],
                $hashedPassword,
                $formData['role'],
                $formData['status']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // If role is member, create member record
            if ($formData['role'] === 'member') {
                $memberNumber = generateMemberNumber($pdo);
                $stmt = $pdo->prepare("
                    INSERT INTO members (member_number, user_id, registration_date, status)
                    VALUES (?, ?, CURDATE(), ?)
                ");
                $stmt->execute([$memberNumber, $userId, $formData['status']]);
            }
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'create_user', 'users', $userId, 'Created user: ' . $formData['username']);
            
            $_SESSION['success'] = 'User created successfully.';
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageScripts = ['users.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-plus"></i> Add User</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Users</a> / Add
            </div>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
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
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['name'] ?? ''; ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="text-danger"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['email'] ?? ''; ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="text-danger"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo $formData['phone'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['username'] ?? ''; ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="text-danger"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">3-50 characters, unique</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input type="password" id="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="text-danger"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="member" <?php echo ($formData['role'] ?? '') === 'member' ? 'selected' : ''; ?>>Member</option>
                                <option value="librarian" <?php echo ($formData['role'] ?? '') === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                <option value="admin" <?php echo ($formData['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo ($formData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($formData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>
