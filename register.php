<?php
/**
 * Member Registration Page
 * Library Management System
 */

require_once 'config/config.php';
require_once 'config/database.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    $dashboardMap = [
        'admin' => 'admin/dashboard.php',
        'librarian' => 'librarian/dashboard.php',
        'member' => 'member/dashboard.php'
    ];
    redirect($dashboardMap[$role] ?? 'member/dashboard.php');
}

$error = '';
$success = '';
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
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'membership_type' => sanitizeInput($_POST['membership_type'] ?? 'Standard'),
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'emergency_contact' => sanitizeInput($_POST['emergency_contact'] ?? '')
    ];
    
    // Validate
    $errors = [];
    
    if (empty($formData['name'])) {
        $errors['name'] = 'Full name is required';
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'Invalid email address';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already exists';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
    
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($formData['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$formData['username']]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Username already exists';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
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
    
    if (empty($errors) && empty($error)) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Create user
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, username, password, role, status, profile_image)
                VALUES (?, ?, ?, ?, ?, 'member', 'active', ?)
            ");
            $stmt->execute([
                $formData['name'],
                $formData['email'],
                $formData['phone'],
                $formData['username'],
                $hashedPassword,
                $profileImage
            ]);
            $userId = $pdo->lastInsertId();
            
            // Create member
            $memberNumber = generateMemberNumber($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO members (
                    member_number, user_id, registration_date, membership_type,
                    date_of_birth, gender, address, emergency_contact, status
                ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $memberNumber,
                $userId,
                $formData['membership_type'],
                $formData['date_of_birth'] ?: null,
                $formData['gender'] ?: null,
                $formData['address'] ?: null,
                $formData['emergency_contact'] ?: null
            ]);
            
            // Create welcome notification
            createNotification(
                $pdo,
                $userId,
                'Welcome to the Library!',
                'Welcome ' . $formData['name'] . '! Your member number is ' . $memberNumber . '. You can now borrow books from the library.',
                'success'
            );
            
            // Audit log
            createAuditLog($pdo, $userId, 'register', 'users', $userId, 'New member registered: ' . $formData['name']);
            
            $pdo->commit();
            
            // Auto-login after registration
            $loginResult = loginUser($pdo, $formData['username'], $formData['password']);
            if ($loginResult['success']) {
                redirect('member/dashboard.php');
            } else {
                $success = 'Registration successful! You can now login with your credentials.';
                $formData = [];
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
            
            // Delete uploaded image if error
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

$pageTitle = 'Register';
$settingsPdo = getDBConnection();
$libraryName = getSetting($settingsPdo, 'library_name', APP_NAME);
$logoFilename = getSetting($settingsPdo, 'logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: url('assets/images/image1.png') center center / cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 560px;
            padding: 40px 36px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .header .logo {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 4px;
        }

        .header .logo-image {
            display: block;
            width: min(220px, 100%);
            height: 88px;
            margin: 0 auto 4px;
            object-fit: contain;
        }
        
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .header p {
            color: #6c7a8a;
            font-size: 14px;
        }
        
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fde8e8;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .form-group {
            margin-bottom: 14px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group .input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        
        .form-group textarea {
            padding-left: 12px;
            resize: vertical;
            min-height: 60px;
        }
        
        .form-group select {
            padding-left: 12px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236c7a8a' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input.is-invalid,
        .form-group select.is-invalid {
            border-color: #fc8181;
        }
        
        .form-group input.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(252, 129, 129, 0.1);
        }
        
        .form-group .text-danger {
            font-size: 12px;
            color: #fc8181;
            margin-top: 2px;
        }
        
        .form-group .form-text {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 2px;
        }
        
        .form-group input[type="file"] {
            padding: 8px 12px;
            background: #f7fafc;
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6c7a8a;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 24px 16px;
            }
            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if ($logoFilename): ?>
                <img class="logo-image" src="<?php echo htmlspecialchars(rtrim(APP_URL, '/') . '/uploads/' . $logoFilename, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($libraryName, ENT_QUOTES, 'UTF-8'); ?> logo">
            <?php else: ?>
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($libraryName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Join the library and start borrowing books</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            
            <div class="form-group">
                <label for="name">Full Name <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="name" name="name" class="<?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                           value="<?php echo $formData['name'] ?? ''; ?>" placeholder="Enter your full name" required>
                </div>
                <?php if (isset($errors['name'])): ?>
                    <div class="text-danger"><?php echo $errors['name']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label for="email">Email <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo $formData['email'] ?? ''; ?>" placeholder="Enter your email" required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="text-danger"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo $formData['phone'] ?? ''; ?>" placeholder="Enter phone number">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label for="username">Username <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tag"></i>
                        <input type="text" id="username" name="username" class="<?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo $formData['username'] ?? ''; ?>" placeholder="Choose a username" required>
                    </div>
                    <?php if (isset($errors['username'])): ?>
                        <div class="text-danger"><?php echo $errors['username']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">3-50 characters, unique</div>
                </div>
                <div class="form-group">
                    <label for="membership_type">Membership Type</label>
                    <select id="membership_type" name="membership_type">
                        <option value="Standard" <?php echo ($formData['membership_type'] ?? '') === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="Premium" <?php echo ($formData['membership_type'] ?? '') === 'Premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label for="password">Password <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="<?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               placeholder="Create a password" required>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="text-danger"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Minimum 8 characters</div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="<?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                               placeholder="Confirm your password" required>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="text-danger"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo $formData['date_of_birth'] ?? ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select</option>
                        <option value="Male" <?php echo ($formData['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($formData['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($formData['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-home" style="top:12px;transform:none;"></i>
                    <textarea id="address" name="address" placeholder="Enter your address"><?php echo $formData['address'] ?? ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label for="emergency_contact">Emergency Contact</label>
                <div class="input-wrapper">
                    <i class="fas fa-phone-alt"></i>
                    <input type="text" id="emergency_contact" name="emergency_contact" 
                           value="<?php echo $formData['emergency_contact'] ?? ''; ?>" placeholder="Name - Phone Number">
                </div>
            </div>
            
            <div class="form-group">
                <label for="profile_image">Profile Image</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*">
                <?php if (isset($errors['profile_image'])): ?>
                    <div class="text-danger"><?php echo $errors['profile_image']; ?></div>
                <?php endif; ?>
                <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. Allowed: JPG, PNG, GIF, WebP</div>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        
        <div class="footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>