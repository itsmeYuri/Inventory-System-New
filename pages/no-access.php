<?php
/**
 * Access Denied Page
 * 
 * This page is displayed when a user tries to access a page they don't have permission for.
 */

// Start session to get user info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? 'Unknown';
$user_name = $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - PHARMACY Management System</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .access-denied-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .access-denied-card {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .access-denied-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
        }
        .access-denied-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 15px;
        }
        .access-denied-message {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .user-info {
            background: #f7fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .user-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-info-item:last-child {
            border-bottom: none;
        }
        .user-info-label {
            font-weight: 600;
            color: #2d3748;
        }
        .user-info-value {
            color: #4a5568;
            text-transform: capitalize;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="access-denied-container">
        <div class="access-denied-card">
            <div class="access-denied-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1 class="access-denied-title">Access Denied</h1>
            <p class="access-denied-message">
                You don't have permission to access this page. Please contact your administrator if you believe this is an error.
            </p>
            
            <div class="user-info">
                <div class="user-info-item">
                    <span class="user-info-label">User:</span>
                    <span class="user-info-value"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <div class="user-info-item">
                    <span class="user-info-label">Role:</span>
                    <span class="user-info-value"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
            </div>
            
            <div class="btn-group">
                <?php if ($user_role === 'supplier'): ?>
                    <a href="supplier_dashboard.html" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Go to Supplier Dashboard
                    </a>
                <?php else: ?>
                    <a href="dashboard.html" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Go to Dashboard
                    </a>
                <?php endif; ?>
                <a href="../php/logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>

