<?php
/**
 * Edit Member
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Edit Member';

$memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$memberId) {
    $_SESSION['error'] = 'Member ID required';
    redirect('index.php');
}

// Get member details
$stmt = $pdo->prepare("
    SELECT m.*, u.id as user_id, u.name, u.email, u.phone, u.username, u.profile_image, u.status as user_status
    FROM members m
    JOIN users u ON m.user_id = u.id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['error'] = 'Member not found';
    redirect('index.php');
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
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'membership_type' => sanitizeInput($_POST['membership_type'] ?? 'Standard'),
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'emergency_contact' => sanitizeInput($_POST['emergency_contact'] ?? ''),
        'status' => sanitizeInput($_POST['status'] ?? 'active'),
        'user_status' => sanitizeInput($_POST['user_status'] ?? 'active')
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
        $stmt->execute([$formData['email'], $member['user_id']]);
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
        $stmt->execute([$formData['username'], $member['user_id']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already exists';
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
    
    // Handle password change
    $newPassword = $_POST['new_password'] ?? '';
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors['new_password'] = 'Password must be at least 6 characters';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update user
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?, username = ?, status = ?, profile_image = ?";
            $params = [$formData['name'], $formData['email'], $formData['phone'], $formData['username'], $formData['user_status'], $profileImage];
            
            if (isset($hashedPassword)) {
                $sql .= ", password = ?";
                $params[] = $hashedPassword;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $member['user_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Update member
            $stmt = $pdo->prepare("
                UPDATE members SET 
                    membership_type = ?, date_of_birth = ?, gender = ?,
                    address = ?, emergency_contact = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $formData['membership_type'],
                $formData['date_of_birth'] ?: null,
                $formData['gender'] ?: null,
                $formData['address'] ?: null,
                $formData['emergency_contact'] ?: null,
                $formData['status'],
                $memberId
            ]);
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'update_member', 'members', $memberId, 'Updated member: ' . $formData['name']);
            
            $_SESSION['success'] = 'Member updated successfully.';
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
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

$pageScripts = ['members.js'];
include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-edit"></i> Edit Member</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Members</a> / Edit
            </div>
        </div>
        <div class="page-actions">
            <a href="view.php?id=<?php echo $memberId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View Member
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Members
            </a>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="edit.php?id=<?php echo $memberId; ?>" enctype="multipart/form-data" data-validate>
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
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['username']; ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="text-danger"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo $formData['phone']; ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="membership_type">Membership Type</label>
                            <select id="membership_type" name="membership_type" class="form-control">
                                <option value="Standard" <?php echo $formData['membership_type'] === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="Premium" <?php echo $formData['membership_type'] === 'Premium' ? 'selected' : ''; ?>>Premium</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                   value="<?php echo $formData['date_of_birth']; ?>">
                        </div>
                    </div>
                    <div class="col-4">
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

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="status">Member Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo $formData['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="user_status">User Account Status</label>
                            <select id="user_status" name="user_status" class="form-control">
                                <option value="active" <?php echo $formData['user_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $formData['user_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                        <i class="fas fa-save"></i> Update Member
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>