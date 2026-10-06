<?php
/**
 * Login Page
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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $pdo = getDBConnection();
            $result = loginUser($pdo, $username, $password);
            
            if ($result['success']) {
                $role = $result['user']['role'];
                $dashboardMap = [
                    'admin' => 'admin/dashboard.php',
                    'librarian' => 'librarian/dashboard.php',
                    'member' => 'member/dashboard.php'
                ];
                redirect($dashboardMap[$role] ?? 'member/dashboard.php');
            } else {
                $error = $result['message'];
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// Check for session expired message
if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please login again.';
}

$pageTitle = 'Login';
$settingsPdo = getDBConnection();
$libraryName = getSetting($settingsPdo, 'library_name', APP_NAME);
$logoFilename = getSetting($settingsPdo, 'logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
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
        
        .login-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 440px;
            padding: 48px 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-header .logo {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 8px;
        }

        .login-header .logo-image {
            display: block;
            width: min(220px, 100%);
            height: 96px;
            margin: 0 auto 8px;
            object-fit: contain;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        
        .login-header p {
            color: #6c7a8a;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
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
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 6px;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .form-group input::placeholder {
            color: #a0aec0;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .form-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4a5568;
            cursor: pointer;
        }
        
        .form-options label input[type="checkbox"] {
            accent-color: #667eea;
        }
        
        .form-options a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-options a:hover {
            text-decoration: underline;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
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
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #6c7a8a;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .version {
            text-align: center;
            margin-top: 12px;
            font-size: 12px;
            color: #a0aec0;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 32px 20px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <?php if ($logoFilename): ?>
                <img class="logo-image" src="<?php echo htmlspecialchars(rtrim(APP_URL, '/') . '/uploads/' . $logoFilename, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($libraryName, ENT_QUOTES, 'UTF-8'); ?> logo">
            <?php else: ?>
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($libraryName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Sign in to access the library management system</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username or email" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            
            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember" value="1"> Remember me
                </label>
                <a href="forgot-password.php">Forgot password?</a>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="login-footer">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
        
        <div class="version">
            Version <?php echo APP_VERSION; ?>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    </script>
</body>
</html>