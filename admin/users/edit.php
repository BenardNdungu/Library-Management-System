<?php
/**
 * Edit User
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'Edit User';

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$userId) {
    $_SESSION['error'] = 'User ID required';
    redirect('index.php');
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    redirect('index.php');
}

$errors = [];
$formData = $user;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'username' => sanitizeInput($_POST['username'] ?? ''),
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $userId]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($formData['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$formData['username'], $userId]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already exists';
        }
    }
    
    // Handle password change
    $newPassword = $_POST['new_password'] ?? '';
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors['new_password'] = 'Password must be at least 6 characters';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }
    
    // Prevent changing own role/status to deactivate self
    if ($userId == getCurrentUserId()) {
        if ($formData['status'] === 'inactive') {
            $errors['status'] = 'You cannot deactivate your own account';
        }
        if ($formData['role'] !== $user['role']) {
            $errors['role'] = 'You cannot change your own role';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update user
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?, username = ?, role = ?, status = ?";
            $params = [$formData['name'], $formData['email'], $formData['phone'], $formData['username'], $formData['role'], $formData['status']];
            
            if (isset($hashedPassword)) {
                $sql .= ", password = ?";
                $params[] = $hashedPassword;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // If role changed to member, ensure member record exists
            if ($formData['role'] === 'member') {
                $stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
                $stmt->execute([$userId]);
                if (!$stmt->fetch()) {
                    $memberNumber = generateMemberNumber($pdo);
                    $stmt = $pdo->prepare("
                        INSERT INTO members (member_number, user_id, registration_date, status)
                        VALUES (?, ?, CURDATE(), ?)
                    ");
                    $stmt->execute([$memberNumber, $userId, $formData['status']]);
                }
            }
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'update_user', 'users', $userId, 'Updated user: ' . $formData['username']);
            
            $_SESSION['success'] = 'User updated successfully.';
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
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
            <h1><i class="fas fa-user-edit"></i> Edit User</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Users</a> / Edit
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
            <form method="POST" action="edit.php?id=<?php echo $userId; ?>" data-validate>
                <?php echo csrfField(); ?>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['name']; ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="text-danger"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['email']; ?>" required>
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
                                   value="<?php echo $formData['phone']; ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['username']; ?>" required>
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
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select id="role" name="role" class="form-control <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" required>
                                <option value="member" <?php echo $formData['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                <option value="librarian" <?php echo $formData['role'] === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                <option value="admin" <?php echo $formData['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                            <?php if (isset($errors['role'])): ?>
                                <div class="text-danger"><?php echo $errors['role']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>">
                                <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="text-danger"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <label for="new_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                           placeholder="Enter new password">
                    <?php if (isset($errors['new_password'])): ?>
                        <div class="text-danger"><?php echo $errors['new_password']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Minimum 6 characters</div>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>