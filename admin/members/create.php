<?php
/**
 * Create Member
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require librarian or admin authentication
requireLibrarian();

$pdo = getDBConnection();
$pageTitle = 'Add Member';

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
        'membership_type' => sanitizeInput($_POST['membership_type'] ?? 'Standard'),
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'emergency_contact' => sanitizeInput($_POST['emergency_contact'] ?? ''),
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
    
    // Handle profile image upload
    $profileImage = '';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadProfileImage($_FILES['profile_image']);
        if ($uploadResult['success']) {
            $profileImage = $uploadResult['filename'];
        } else {
            $errors['profile_image'] = $uploadResult['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create user
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, username, password, role, status, profile_image)
                VALUES (?, ?, ?, ?, ?, 'member', ?, ?)
            ");
            $stmt->execute([
                $formData['name'],
                $formData['email'],
                $formData['phone'],
                $formData['username'],
                $hashedPassword,
                $formData['status'],
                $profileImage
            ]);
            $userId = $pdo->lastInsertId();
            
            // Create member
            $memberNumber = generateMemberNumber($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO members (
                    member_number, user_id, registration_date, membership_type,
                    date_of_birth, gender, address, emergency_contact, status
                ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $memberNumber,
                $userId,
                $formData['membership_type'],
                $formData['date_of_birth'] ?: null,
                $formData['gender'] ?: null,
                $formData['address'] ?: null,
                $formData['emergency_contact'] ?: null,
                $formData['status']
            ]);
            $memberId = $pdo->lastInsertId();
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'create_member', 'members', $memberId, 'Created member: ' . $formData['name'] . ' (' . $memberNumber . ')');
            
            $_SESSION['success'] = 'Member created successfully. Member Number: ' . $memberNumber;
            redirect('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
            
            if ($profileImage && file_exists(MEMBER_PHOTO_PATH . $profileImage)) {
                unlink(MEMBER_PHOTO_PATH . $profileImage);
            }
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
            <h1><i class="fas fa-user-plus"></i> Add Member</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / <a href="index.php">Members</a> / Add
            </div>
        </div>
        <div class="page-actions">
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
            <form method="POST" action="create.php" enctype="multipart/form-data" data-validate>
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
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $formData['username'] ?? ''; ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="text-danger"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">3-50 characters, unique</div>
                        </div>
                    </div>
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
                            <label for="membership_type">Membership Type</label>
                            <select id="membership_type" name="membership_type" class="form-control">
                                <option value="Standard" <?php echo ($formData['membership_type'] ?? '') === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="Premium" <?php echo ($formData['membership_type'] ?? '') === 'Premium' ? 'selected' : ''; ?>>Premium</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                   value="<?php echo $formData['date_of_birth'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($formData['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($formData['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($formData['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?php echo ($formData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($formData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($formData['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo $formData['address'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="emergency_contact">Emergency Contact</label>
                    <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" 
                           value="<?php echo $formData['emergency_contact'] ?? ''; ?>" placeholder="Name - Phone Number">
                </div>

                <div class="form-group">
                    <label for="profile_image">Profile Image</label>
                    <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                    <?php if (isset($errors['profile_image'])): ?>
                        <div class="text-danger"><?php echo $errors['profile_image']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. Allowed: JPG, PNG, GIF, WebP</div>
                </div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Member
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>