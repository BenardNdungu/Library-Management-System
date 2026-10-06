<?php
/**
 * Change Password
 * Library Management System
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Require authentication
requireAuth();

$pdo = getDBConnection();
$pageTitle = 'Change Password';

$userId = getCurrentUserId();
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required';
    }
    
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        $result = changePassword($pdo, $userId, $currentPassword, $newPassword);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

$userName = $_SESSION['user_name'] ?? 'User';

include_once 'includes/header.php';
include_once 'includes/navbar.php';
include_once 'includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <div class="breadcrumb">
                <a href="<?php echo getDashboardUrl(); ?>">Home</a> / Change Password
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="change-password.php" data-validate>
                        <?php echo csrfField(); ?>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password <span class="text-danger">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (isset($errors['current_password'])): ?>
                                <div class="text-danger"><?php echo $errors['current_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password <span class="text-danger">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="text-danger"><?php echo $errors['new_password']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="text-danger"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Change Password
                            </button>
                            <a href="<?php echo getDashboardUrl(); ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Password Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Password must be at least 8 characters long</li>
                        <li>Use a combination of letters, numbers, and special characters</li>
                        <li>Avoid using common words or personal information</li>
                        <li>Change your password regularly for better security</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once 'includes/footer.php'; ?>