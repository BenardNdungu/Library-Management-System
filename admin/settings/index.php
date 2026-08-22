<?php
/**
 * System Settings
 * Library Management System
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Require admin authentication
requireAdmin();

$pdo = getDBConnection();
$pageTitle = 'System Settings';

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $settings = [
        'library_name' => sanitizeInput($_POST['library_name'] ?? ''),
        'library_address' => sanitizeInput($_POST['library_address'] ?? ''),
        'library_phone' => sanitizeInput($_POST['library_phone'] ?? ''),
        'library_email' => sanitizeInput($_POST['library_email'] ?? ''),
        'max_books_per_member' => (int) ($_POST['max_books_per_member'] ?? 0),
        'default_loan_period' => (int) ($_POST['default_loan_period'] ?? 0),
        'max_renewal_count' => (int) ($_POST['max_renewal_count'] ?? 0),
        'daily_fine_rate' => (float) ($_POST['daily_fine_rate'] ?? 0),
        'reservation_period' => (int) ($_POST['reservation_period'] ?? 0),
        'currency' => sanitizeInput($_POST['currency'] ?? '$'),
        'date_format' => sanitizeInput($_POST['date_format'] ?? 'Y-m-d')
    ];
    
    // Validate
    if (empty($settings['library_name'])) {
        $errors['library_name'] = 'Library name is required';
    }
    
    if ($settings['max_books_per_member'] < 1) {
        $errors['max_books_per_member'] = 'Must be at least 1';
    }
    
    if ($settings['default_loan_period'] < 1) {
        $errors['default_loan_period'] = 'Must be at least 1';
    }
    
    if ($settings['daily_fine_rate'] < 0) {
        $errors['daily_fine_rate'] = 'Must be 0 or greater';
    }
    
    if ($settings['reservation_period'] < 1) {
        $errors['reservation_period'] = 'Must be at least 1';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                updateSetting($pdo, $key, $value);
            }
            
            $pdo->commit();
            
            createAuditLog($pdo, getCurrentUserId(), 'update_settings', 'settings', null, 'Updated system settings');
            $success = 'Settings updated successfully.';
            
            // Refresh constants
            foreach ($settings as $key => $value) {
                define('SETTING_' . strtoupper($key), $value);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set default values if not set
$defaults = [
    'library_name' => APP_NAME,
    'library_address' => '',
    'library_phone' => '',
    'library_email' => '',
    'max_books_per_member' => DEFAULT_MAX_BOOKS,
    'default_loan_period' => DEFAULT_LOAN_PERIOD,
    'max_renewal_count' => DEFAULT_MAX_RENEWALS,
    'daily_fine_rate' => DEFAULT_DAILY_FINE,
    'reservation_period' => DEFAULT_RESERVATION_PERIOD,
    'currency' => DEFAULT_CURRENCY,
    'date_format' => DEFAULT_DATE_FORMAT
];

foreach ($defaults as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}

// Handle logo upload
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploadResult = uploadLogo($_FILES['logo']);
    if ($uploadResult['success']) {
        updateSetting($pdo, 'logo', $uploadResult['filename']);
        $settings['logo'] = $uploadResult['filename'];
        $success = 'Logo uploaded successfully.';
    } else {
        $errors['logo'] = $uploadResult['message'];
    }
}

// Handle logo removal
if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
    if (isset($settings['logo']) && file_exists(UPLOAD_PATH . $settings['logo'])) {
        unlink(UPLOAD_PATH . $settings['logo']);
    }
    updateSetting($pdo, 'logo', '');
    $settings['logo'] = '';
    $success = 'Logo removed successfully.';
}

/**
 * Upload logo
 */
function uploadLogo($file): array {
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
    $filename = 'logo_' . time() . '.' . $extension;
    $destination = UPLOAD_PATH . $filename;
    
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

include_once '../../includes/header.php';
include_once '../../includes/navbar.php';
include_once '../../includes/sidebar.php';
?>

<main class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Home</a> / Settings
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
        <!-- General Settings -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-sliders-h"></i> General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php" data-validate>
                        <?php echo csrfField(); ?>
                        
                        <div class="form-group">
                            <label for="library_name">Library Name <span class="text-danger">*</span></label>
                            <input type="text" id="library_name" name="library_name" class="form-control <?php echo isset($errors['library_name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $settings['library_name']; ?>" required>
                            <?php if (isset($errors['library_name'])): ?>
                                <div class="text-danger"><?php echo $errors['library_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="library_address">Library Address</label>
                            <textarea id="library_address" name="library_address" class="form-control" rows="2"><?php echo $settings['library_address']; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="library_phone">Phone Number</label>
                                    <input type="text" id="library_phone" name="library_phone" class="form-control" 
                                           value="<?php echo $settings['library_phone']; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="library_email">Email Address</label>
                                    <input type="email" id="library_email" name="library_email" class="form-control" 
                                           value="<?php echo $settings['library_email']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="currency">Currency Symbol</label>
                                    <input type="text" id="currency" name="currency" class="form-control" 
                                           value="<?php echo $settings['currency']; ?>" maxlength="5">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="date_format">Date Format</label>
                                    <input type="text" id="date_format" name="date_format" class="form-control" 
                                           value="<?php echo $settings['date_format']; ?>" placeholder="Y-m-d">
                                    <div class="form-text">PHP date format (Y-m-d, d/m/Y, etc.)</div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Loan Settings</h6>
                        
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="max_books_per_member">Max Books Per Member <span class="text-danger">*</span></label>
                                    <input type="number" id="max_books_per_member" name="max_books_per_member" class="form-control <?php echo isset($errors['max_books_per_member']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $settings['max_books_per_member']; ?>" min="1" required>
                                    <?php if (isset($errors['max_books_per_member'])): ?>
                                        <div class="text-danger"><?php echo $errors['max_books_per_member']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="default_loan_period">Default Loan Period (days) <span class="text-danger">*</span></label>
                                    <input type="number" id="default_loan_period" name="default_loan_period" class="form-control <?php echo isset($errors['default_loan_period']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $settings['default_loan_period']; ?>" min="1" required>
                                    <?php if (isset($errors['default_loan_period'])): ?>
                                        <div class="text-danger"><?php echo $errors['default_loan_period']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="max_renewal_count">Max Renewals</label>
                                    <input type="number" id="max_renewal_count" name="max_renewal_count" class="form-control" 
                                           value="<?php echo $settings['max_renewal_count']; ?>" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="daily_fine_rate">Daily Fine Rate <?php echo $settings['currency']; ?> <span class="text-danger">*</span></label>
                                    <input type="number" id="daily_fine_rate" name="daily_fine_rate" class="form-control <?php echo isset($errors['daily_fine_rate']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $settings['daily_fine_rate']; ?>" step="0.01" min="0" required>
                                    <?php if (isset($errors['daily_fine_rate'])): ?>
                                        <div class="text-danger"><?php echo $errors['daily_fine_rate']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="reservation_period">Reservation Period (days) <span class="text-danger">*</span></label>
                                    <input type="number" id="reservation_period" name="reservation_period" class="form-control <?php echo isset($errors['reservation_period']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $settings['reservation_period']; ?>" min="1" required>
                                    <?php if (isset($errors['reservation_period'])): ?>
                                        <div class="text-danger"><?php echo $errors['reservation_period']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Logo & Info -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-image"></i> Library Logo</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (isset($settings['logo']) && $settings['logo']): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/<?php echo $settings['logo']; ?>" 
                             alt="Library Logo" style="max-width:200px;max-height:150px;margin-bottom:16px;">
                        <form method="POST" action="index.php">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="remove_logo" value="1">
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove logo?">
                                <i class="fas fa-times"></i> Remove Logo
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="width:200px;height:150px;border:2px dashed var(--gray-300);border-radius:8px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;color:var(--gray-500);">
                            <i class="fas fa-image" style="font-size:48px;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label for="logo">Upload Logo</label>
                            <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
                            <div class="form-text">Max size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB</div>
                        </div>
                        <?php if (isset($errors['logo'])): ?>
                            <div class="text-danger"><?php echo $errors['logo']; ?></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-upload"></i> Upload Logo
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> System Info</h5>
                </div>
                <div class="card-body">
                    <p><strong>Application:</strong> <?php echo APP_NAME; ?></p>
                    <p><strong>Version:</strong> <?php echo APP_VERSION; ?></p>
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>MySQL Version:</strong> <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></p>
                    <p><strong>Session Timeout:</strong> <?php echo SESSION_TIMEOUT / 60; ?> minutes</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>