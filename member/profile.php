<?php
/**
 * Member Profile
 * Library Management System
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Require member authentication
requireMember();

$pdo = getDBConnection();
$pageTitle = 'My Profile';

$userId = getCurrentUserId();

// Get member details
$stmt = $pdo->prepare("
    SELECT m.*, u.name, u.email, u.phone, u.username, u.profile_image, u.status as user_status
    FROM members m
    JOIN users u ON m.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['error'] = 'Member record not found. Please contact administrator.';
    redirect('dashboard.php');
}

$errors = [];
$formData = $member;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'emergency_contact' => sanitizeInput($_POST['emergency_contact'] ?? ''),
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? '')
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
    
    // Handle profile image upload
    $profileImage = $member['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadProfileImage($_FILES['profile_image']);
        if ($uploadResult['success']) {
            if ($profileImage && file_exists(MEMBER_PHOTO_PATH . $profileImage)) {
                unlink(MEMBER_PHOTO_PATH . $profileImage);
            }
            $profileImage = $uploadResult['filename'];
        } else {
            $errors['profile_image'] = $uploadResult['message'];
        }
    }
    
    // Handle image removal
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($profileImage && file_exists(MEMBER_PHOTO_PATH . $profileImage)) {
            unlink(MEMBER_PHOTO_PATH . $profileImage);
        }
        $profileImage = null;
    }
    
    if (empty($errors)) {
        try {
            // Update user
            $stmt = $pdo->prepare("
                UPDATE users SET name = ?, email = ?, phone = ?, profile_image = ?
                WHERE id = ?
            ");
            $stmt->execute([$formData['name'], $formData['email'], $formData['phone'], $profileImage, $userId]);
            
            // Update member
            $stmt = $pdo->prepare("
                UPDATE members SET 
                    address = ?, emergency_contact = ?, date_of_birth = ?, gender = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $formData['address'] ?: null,
                $formData['emergency_contact'] ?: null,
                $formData['date_of_birth'] ?: null,
                $formData['gender'] ?: null,
                $userId
            ]);
            
            // Update session
            $_SESSION['user_name'] = $formData['name'];
            $_SESSION['user_email'] = $formData['email'];
            if ($profileImage) {
                $_SESSION['profile_image'] = $profileImage;
            }
            
            createAuditLog($pdo, $userId, 'update_profile', 'users', $userId, 'Member updated profile');
            $_SESSION['success'] = 'Profile updated successfully.';
            redirect('profile.php');
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

/**
 * Upload profile image
 */
function uploadProfileImage($file): array {
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
    $filename = 'member_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = MEMBER_PHOTO_PATH . $filename;
    
    if (!is_dir(MEMBER_PHOTO_PATH)) {
        mkdir(MEMBER_PHOTO_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
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
            <h1><i class="fas fa-user"></i> My Profile</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a> / Profile
            </div>
        </div>
        <div class="page-actions">
            <a href="../change-password.php" class="btn btn-warning">
                <i class="fas fa-key"></i> Change Password
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Form -->
        <div class="col-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="profile.php" enctype="multipart/form-data" data-validate>
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
                                    <label for="username">Username</label>
                                    <input type="text" id="username" class="form-control" 
                                           value="<?php echo $formData['username']; ?>" disabled>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                           value="<?php echo $formData['date_of_birth']; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="">Select</option>
                                        <option value="Male" <?php echo $formData['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $formData['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $formData['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2"><?php echo $formData['address']; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" 
                                   value="<?php echo $formData['emergency_contact']; ?>" placeholder="Name - Phone Number">
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_image">Profile Image</label>
                            <?php if ($member['profile_image']): ?>
                                <div class="mb-2">
                                    <img src="<?php echo APP_URL; ?>/uploads/members/<?php echo $member['profile_image']; ?>" 
                                         alt="Current profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                    <br>
                                    <label>
                                        <input type="checkbox" name="remove_image" value="1"> Remove current image
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                            <?php if (isset($errors['profile_image'])): ?>
                                <div class="text-danger"><?php echo $errors['profile_image']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. Allowed: JPG, PNG, GIF, WebP</div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Profile Info -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Account Info</h5>
                </div>
                <div class="card-body">
                    <p><strong>Member #:</strong> <?php echo $member['member_number']; ?></p>
                    <p><strong>Membership:</strong> <?php echo $member['membership_type']; ?></p>
                    <p><strong>Status:</strong> <?php echo getStatusBadge($member['status'], 'member'); ?></p>
                    <p><strong>Registered:</strong> <?php echo formatDate($member['registration_date']); ?></p>
                    <p><strong>User Status:</strong> <?php echo getStatusBadge($member['user_status']); ?></p>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')");
                    $stmt->execute([$member['id']]);
                    $current = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status = 'Returned'");
                    $stmt->execute([$member['id']]);
                    $total = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE member_id = ? AND status = 'Unpaid'");
                    $stmt->execute([$member['id']]);
                    $fines = $stmt->fetch()['total'];
                    ?>
                    <p><strong>Current Loans:</strong> <?php echo $current; ?></p>
                    <p><strong>Total Books Read:</strong> <?php echo $total; ?></p>
                    <p><strong>Outstanding Fines:</strong> <?php echo formatCurrency($fines); ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../includes/footer.php'; ?>