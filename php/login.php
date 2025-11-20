<?php
/**
 * User Authentication System
 * Handles login via hardcoded credentials (temporary) and database authentication
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/conn.php';

// Validate database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$loginInput = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($loginInput) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email/username and password are required']);
    exit;
}

// Temporary hardcoded login credentials
$hardcodedUsers = [
    [
        'email' => 'admin',
        'username' => 'admin',
        'password' => 'admin12345',
        'role' => 'admin',
        'user_id' => 1,
        'full_name' => 'Administrator'
    ],
    [
        'email' => 'Admin',
        'username' => 'Admin',
        'password' => 'admin12345',
        'role' => 'admin',
        'user_id' => 1,
        'full_name' => 'Administrator'
    ],
    [
        'email' => 'test@test.com',
        'username' => 'test',
        'password' => 'test123',
        'role' => 'user',
        'user_id' => 2,
        'full_name' => 'Test User'
    ]
];

// Check hardcoded credentials
foreach ($hardcodedUsers as $hardcodedUser) {
    $emailMatch = strtolower(trim($loginInput)) === strtolower(trim($hardcodedUser['email']));
    $usernameMatch = strtolower(trim($loginInput)) === strtolower(trim($hardcodedUser['username']));
    
    if (($emailMatch || $usernameMatch) && $password === $hardcodedUser['password']) {
        // Try to fetch actual user data from database first
        $dbUser = null;
        if (isset($hardcodedUser['user_id'])) {
            $dbQuery = "SELECT * FROM users WHERE user_id = ?";
            $dbStmt = mysqli_prepare($conn, $dbQuery);
            if ($dbStmt) {
                mysqli_stmt_bind_param($dbStmt, "i", $hardcodedUser['user_id']);
                mysqli_stmt_execute($dbStmt);
                $dbResult = mysqli_stmt_get_result($dbStmt);
                $dbUser = mysqli_fetch_assoc($dbResult);
                mysqli_stmt_close($dbStmt);
            }
        }
        
        // Use database user if found, otherwise use hardcoded user
        $user = $dbUser ? $dbUser : $hardcodedUser;
        
        // Update user status to 'active' when logging in
        if (isset($user['user_id'])) {
            updateUserStatus($conn, $user, 'active');
        }
        setUserSession($user);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => getUserData($user)
        ]);
        exit;
    }
}

// Database authentication
$loginInputLower = strtolower(trim($loginInput));
$query = "SELECT * FROM users WHERE email = ? OR username = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $loginInput, $loginInput);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Try case-insensitive search if no results
if (!$result || mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    $query = "SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $loginInputLower, $loginInputLower);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    }
}

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password']);
    exit;
}

$user = mysqli_fetch_assoc($result);

// Check if account is locked
if (isset($user['status']) && strtolower($user['status']) === 'locked') {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Account Locked by Admin. Please contact administrator.']);
    exit;
}

// Check account status (inactive/offline users can still login, but locked cannot)
if (isset($user['status']) && strtolower($user['status']) === 'inactive') {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact administrator.']);
    exit;
}

// Verify password - use password_hash column (or password as fallback)
$storedPassword = $user['password_hash'] ?? $user['password'] ?? '';
if (empty($storedPassword)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password']);
    exit;
}

$passwordValid = verifyPassword($password, $storedPassword);

// Upgrade plain text password to hash if valid
if ($passwordValid && !isPasswordHashed($storedPassword)) {
    upgradePasswordToHash($conn, $user, $password);
}

mysqli_stmt_close($stmt);

if ($passwordValid) {
    // Update user status to 'active' when logging in
    updateUserStatus($conn, $user, 'active');
    setUserSession($user);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => getUserData($user, $loginInput)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password']);
}

/**
 * Check if password is hashed
 */
function isPasswordHashed($password) {
    return strlen($password) === 60 && 
           in_array(substr($password, 0, 4), ['$2y$', '$2a$', '$2b$']);
}

/**
 * Verify password (supports both hashed and plain text)
 */
function verifyPassword($inputPassword, $storedPassword) {
    if (isPasswordHashed($storedPassword)) {
        return password_verify($inputPassword, $storedPassword);
    }
    return $storedPassword === $inputPassword;
}

/**
 * Upgrade plain text password to hash
 */
function upgradePasswordToHash($conn, $user, $password) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userId = $user['user_id'] ?? $user['id'] ?? null;
    
    if (!$userId) {
        return;
    }
    
    $idColumn = isset($user['user_id']) ? 'user_id' : 'id';
    // Check which password column exists
    $checkPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
    $passwordColumn = mysqli_num_rows($checkPasswordHash) > 0 ? 'password_hash' : 'password';
    $query = "UPDATE users SET $passwordColumn = ? WHERE $idColumn = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Set user session variables
 */
function setUserSession($user) {
    $_SESSION['loggedin'] = true;
    $_SESSION['user_email'] = $user['email'] ?? null;
    $_SESSION['username'] = $user['username'] ?? null;
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['user_id'] = $user['user_id'] ?? $user['id'] ?? null;
    $_SESSION['full_name'] = $user['full_name'] ?? null;
}

/**
 * Get user data for response
 */
function getUserData($user, $loginInput = null) {
    // Check if must_change_password column exists and get its value
    $mustChangePassword = 0;
    if (isset($user['must_change_password'])) {
        $mustChangePassword = (int)$user['must_change_password'];
    } else {
        // Check if column exists in database
        global $conn;
        if (isset($conn) && $conn) {
            $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
            if (mysqli_num_rows($checkColumn) > 0 && isset($user['user_id'])) {
                // Column exists but not in user array, fetch it
                $userId = $user['user_id'] ?? $user['id'] ?? null;
                if ($userId) {
                    $checkStmt = mysqli_prepare($conn, "SELECT must_change_password FROM users WHERE user_id = ?");
                    if ($checkStmt) {
                        mysqli_stmt_bind_param($checkStmt, "i", $userId);
                        mysqli_stmt_execute($checkStmt);
                        $checkResult = mysqli_stmt_get_result($checkStmt);
                        if ($checkRow = mysqli_fetch_assoc($checkResult)) {
                            $mustChangePassword = (int)($checkRow['must_change_password'] ?? 0);
                        }
                        mysqli_stmt_close($checkStmt);
                    }
                }
            }
        }
    }
    
    return [
        'email' => $user['email'] ?? $loginInput,
        'username' => $user['username'] ?? null,
        'role' => $user['role'] ?? 'user',
        'user_id' => $user['user_id'] ?? $user['id'] ?? null,
        'full_name' => $user['full_name'] ?? null,
        'must_change_password' => $mustChangePassword === 1
    ];
}

/**
 * Update user status (active/offline/inactive/locked)
 * Automatically creates status column if it doesn't exist with all enum values
 */
function updateUserStatus($conn, $user, $status) {
    if (!$conn || !$user) {
        return false;
    }
    
    $userId = $user['user_id'] ?? $user['id'] ?? null;
    if (!$userId) {
        return false;
    }
    
    $idColumn = isset($user['user_id']) ? 'user_id' : 'id';
    
    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    if (mysqli_num_rows($checkStatus) === 0) {
        // Create status column if it doesn't exist with all enum values
        $alterQuery = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'offline', 'locked') DEFAULT 'active' AFTER role";
        mysqli_query($conn, $alterQuery);
    } else {
        // Check if the enum includes all required values
        $columnInfo = mysqli_fetch_assoc($checkStatus);
        $enumValues = $columnInfo['Type'] ?? '';
        if (strpos($enumValues, 'locked') === false) {
            // Update enum to include locked
            $alterQuery = "ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'offline', 'locked') DEFAULT 'active'";
            mysqli_query($conn, $alterQuery);
        }
    }
    
    // Update user status
    $query = "UPDATE users SET status = ? WHERE $idColumn = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $status, $userId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    
    return false;
}
?>
