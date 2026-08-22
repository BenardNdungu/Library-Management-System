<?php
/**
 * 500 Internal Server Error Page
 * Library Management System
 */

$pageTitle = 'Server Error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            max-width: 500px;
            padding: 48px 40px;
            text-align: center;
        }
        
        .error-code {
            font-size: 120px;
            font-weight: 800;
            background: linear-gradient(135deg, #f6ad55 0%, #dd6b20 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        
        .error-icon {
            font-size: 64px;
            color: #dd6b20;
            margin: 16px 0;
        }
        
        h1 {
            font-size: 24px;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        
        p {
            color: #6c7a8a;
            font-size: 16px;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        
        .btn-primary {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            display: inline-block;
            padding: 12px 32px;
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #4a5568;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 8px;
        }
        
        .btn-secondary:hover {
            background: #edf2f7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">500</div>
        <div class="error-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h1>Server Error</h1>
        <p>Something went wrong on our end. Please try again later or contact support if the problem persists.</p>
        <div>
            <a href="../index.php" class="btn-primary">
                <i class="fas fa-home"></i> Go Home
            </a>
            <a href="javascript:history.back()" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>
    </div>
</body>
</html>