<?php
/**
 * User Management API
 * Handles all user management operations
 */

// Start output buffering to catch any errors
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

// CORS headers
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost',
    'http://127.0.0.1:3000',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Include database connection
require_once __DIR__ . '/conn.php';

// Clear any output before JSON
ob_clean();

// Validate database connection
if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if users table exists
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Users table does not exist in database'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'get_all':
        getAllUsers();
        break;
    case 'get_user':
        getUser();
        break;
    case 'create_user':
        createUser();
        break;
    case 'update_user':
        updateUser();
        break;
    case 'delete_user':
        deleteUser();
        break;
    case 'update_status':
        updateUserStatus();
        break;
    case 'update_role':
        updateUserRole();
        break;
    case 'reset_password':
        resetPassword();
        break;
    case 'lock_account':
        lockAccount();
        break;
    case 'unlock_account':
        unlockAccount();
        break;
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ], JSON_UNESCAPED_UNICODE);
        break;
}

/**
 * Get all users
 */
function getAllUsers() {
    global $conn;
    
    try {
        // Use SELECT * to get all columns, then filter what we need
        // First check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        // Check if created_at exists
        $checkCreatedAt = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'created_at'");
        $hasCreatedAt = mysqli_num_rows($checkCreatedAt) > 0;
        
        // Build query with appropriate ORDER BY
        if ($hasCreatedAt) {
            $query = "SELECT * FROM users ORDER BY created_at DESC";
        } else {
            $query = "SELECT * FROM users ORDER BY $idColumn DESC";
        }
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            // Try simplest query if the above fails
            $query = "SELECT * FROM users";
            $result = mysqli_query($conn, $query);
            
            if (!$result) {
                throw new Exception('Database query failed: ' . mysqli_error($conn));
            }
        }
        
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Normalize user_id field - check both user_id and id
            $userId = $row['user_id'] ?? $row['id'] ?? null;
            
            $users[] = [
                'user_id' => $userId,
                'id' => $userId,
                'full_name' => $row['full_name'] ?? $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'username' => $row['username'] ?? '',
                'employee_id' => $row['employee_id'] ?? $row['employeeId'] ?? $row['emp_id'] ?? null,
                'role' => $row['role'] ?? 'employee',
                'status' => $row['status'] ?? 'active',
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null
            ];
        }
        
        // Clear any output before JSON
        ob_clean();
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('User Management API Error: ' . $e->getMessage());
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } catch (Error $e) {
        error_log('User Management API Fatal Error: ' . $e->getMessage());
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Get single user
 */
function getUser() {
    global $conn;
    
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "SELECT * FROM users WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => false,
                'error' => 'User not found'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Normalize user_id
        $normalizedUserId = $user['user_id'] ?? $user['id'] ?? null;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'user' => [
                'user_id' => $normalizedUserId,
                'id' => $normalizedUserId,
                'full_name' => $user['full_name'] ?? '',
                'email' => $user['email'] ?? '',
                'username' => $user['username'] ?? '',
                'employee_id' => $user['employee_id'] ?? $user['employeeId'] ?? $user['emp_id'] ?? null,
                'role' => $user['role'] ?? 'employee',
                'status' => $user['status'] ?? 'active'
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Create new user
 */
function createUser() {
    global $conn;
    
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    
    if (empty($email) || empty($username)) {
        echo json_encode([
            'success' => false,
            'error' => 'Email and username are required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if email or username already exists
        $checkQuery = "SELECT user_id FROM users WHERE email = ? OR username = ?";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        
        if (!$checkStmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($checkStmt, "ss", $email, $username);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            mysqli_stmt_close($checkStmt);
            echo json_encode([
                'success' => false,
                'error' => 'Email or username already exists'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        mysqli_stmt_close($checkStmt);
        
        // Generate default password (user will need to reset)
        $defaultPassword = password_hash('TempPassword123!', PASSWORD_DEFAULT);
        
        // Check which password column exists
        $checkPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
        $passwordColumn = mysqli_num_rows($checkPasswordHash) > 0 ? 'password_hash' : 'password';
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $hasUserId = mysqli_num_rows($checkUserId) > 0;
        
        // Insert new user
        $insertQuery = "INSERT INTO users (full_name, email, username, $passwordColumn, role, status) VALUES (?, ?, ?, ?, ?, 'active')";
}

/**
 * Update user
 */
function updateUser() {
        mysqli_stmt_bind_param($insertStmt, "sssss", $fullName, $email, $username, $defaultPassword, $role);
    
    $userId = $_POST['user_id'] ?? null;
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        // Check if email or username already exists for another user
        $checkQuery = "SELECT $idColumn FROM users WHERE ($idColumn != ?) AND (email = ? OR username = ?)";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        
        if (!$checkStmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($checkStmt, "iss", $userId, $email, $username);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            mysqli_stmt_close($checkStmt);
            echo json_encode([
                'success' => false,
                'error' => 'Email or username already exists for another user'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        mysqli_stmt_close($checkStmt);
        
        // Check if updated_at column exists
        $checkUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'updated_at'");
        $hasUpdatedAt = mysqli_num_rows($checkUpdatedAt) > 0;
        
        // Update user - conditionally include updated_at if column exists
        if ($hasUpdatedAt) {
            $updateQuery = "UPDATE users SET full_name = ?, email = ?, username = ?, updated_at = NOW() WHERE $idColumn = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            
            if (!$updateStmt) {
                throw new Exception('Database query failed: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($updateStmt, "sssi", $fullName, $email, $username, $userId);
        } else {
            $updateQuery = "UPDATE users SET full_name = ?, email = ?, username = ? WHERE $idColumn = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            
            if (!$updateStmt) {
                throw new Exception('Database query failed: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($updateStmt, "sssi", $fullName, $email, $username, $userId);
        }
        
        if (mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($updateStmt);
            throw new Exception('Failed to update user: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Delete user
 */
function deleteUser() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "DELETE FROM users WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to delete user: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Update user status
 */
function updateUserStatus() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Validate status
    $validStatuses = ['active', 'inactive', 'offline', 'locked', 'archived'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid status'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if status column exists, create if not
        $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
        if (mysqli_num_rows($checkStatus) === 0) {
            $alterQuery = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'offline', 'locked', 'archived') DEFAULT 'active'";
            mysqli_query($conn, $alterQuery);
        } else {
            // Ensure 'archived' exists in ENUM
            $col = mysqli_fetch_assoc($checkStatus);
            $type = $col['Type'] ?? '';
            if (strpos($type, "'archived'") === false) {
                $alterQuery = "ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'offline', 'locked', 'archived') DEFAULT 'active'";
                mysqli_query($conn, $alterQuery);
            }
        }
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "UPDATE users SET status = ? WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "si", $status, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'User status updated successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update status: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Update user role
 */
function updateUserRole() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    $role = $_POST['role'] ?? 'employee';
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Validate role
    $validRoles = ['employee', 'supplier', 'admin'];
    if (!in_array($role, $validRoles)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid role'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if role column exists, create if not
        $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
        if (mysqli_num_rows($checkRole) === 0) {
            $alterQuery = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'employee'";
            mysqli_query($conn, $alterQuery);
        }
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "UPDATE users SET role = ? WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "si", $role, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'User role updated successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update role: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Reset user password
 */
function resetPassword() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    $newPassword = $_POST['new_password'] ?? '';
    
    if (!$userId || empty($newPassword)) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID and new password are required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode([
            'success' => false,
            'error' => 'Password must be at least 8 characters long'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Hash password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Check which password column exists
        $checkPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
        $passwordColumn = mysqli_num_rows($checkPasswordHash) > 0 ? 'password_hash' : 'password';
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "UPDATE users SET $passwordColumn = ? WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'Password reset successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to reset password: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Lock user account
 */
function lockAccount() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if status column exists, create if not
        $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
        if (mysqli_num_rows($checkStatus) === 0) {
            $alterQuery = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'offline', 'locked') DEFAULT 'active'";
            mysqli_query($conn, $alterQuery);
        }
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "UPDATE users SET status = 'locked' WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'Account locked successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to lock account: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Unlock user account
 */
function unlockAccount() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'error' => 'User ID is required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if status column exists, create if not
        $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
        if (mysqli_num_rows($checkStatus) === 0) {
            $alterQuery = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'offline', 'locked') DEFAULT 'active'";
            mysqli_query($conn, $alterQuery);
        }
        
        // Check which ID column exists
        $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
        $idColumn = mysqli_num_rows($checkUserId) > 0 ? 'user_id' : 'id';
        
        $query = "UPDATE users SET status = 'active' WHERE $idColumn = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Database query failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                'success' => true,
                'message' => 'Account unlocked successfully'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to unlock account: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>


