<?php
/**
 * User Authentication System
 * Handles login via hardcoded credentials (temporary) and database authentication
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Forgot-password actions proxy to external HR API
$forgotAction = $_POST['action'] ?? null;
if ($forgotAction === 'send_otp' || $forgotAction === 'verify_otp' || $forgotAction === 'update_password') {
    $forgotApi = 'http://localhost/HR-EMPLOYEE-MANAGEMENT/API/forgot-password.php';
    $payload = [];
    if ($forgotAction === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }
        $payload = ['email' => $email, 'action' => 'send_otp'];
    } elseif ($forgotAction === 'verify_otp') {
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        if (!$email || !$otp) {
            echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
            exit;
        }
        $payload = ['email' => $email, 'otp' => $otp, 'action' => 'verify_otp'];
    } elseif ($forgotAction === 'update_password') {
        $email = trim($_POST['email'] ?? '');
        $temporary_password = trim($_POST['temporary_password'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (!$email || !$temporary_password || !$new_password || !$confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        $payload = [
            'email' => $email,
            'temporary_password' => $temporary_password,
            'new_password' => $new_password,
            'confirm_password' => $confirm_password,
            'action' => 'update_password'
        ];
    }

    $ch = curl_init($forgotApi);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to reach password reset service', 'error' => $err ?: 'Unable to reach password reset service', 'http_code' => $code ?? 0]);
        exit;
    }

    $api = json_decode($resp, true);
    if (!is_array($api)) {
        echo json_encode(['success' => false, 'message' => 'Invalid response from password reset service', 'error' => 'Invalid response from password reset service', 'http_code' => $code ?? 0, 'raw' => $resp]);
        exit;
    }

    if (isset($api['status']) && strtolower($api['status']) === 'success') {
        if ($forgotAction === 'send_otp') {
            echo json_encode(['success' => true, 'message' => $api['message'] ?? 'OTP sent', 'email' => $api['email'] ?? null]);
            exit;
        }
        if ($forgotAction === 'verify_otp') {
            $tmp = $api['temporary_password'] ?? null;
            echo json_encode(['success' => true, 'message' => $api['message'] ?? 'OTP verified', 'temporary_password' => $tmp]);
            exit;
        }
        if ($forgotAction === 'update_password') {
            echo json_encode(['success' => true, 'message' => $api['message'] ?? 'Password updated successfully']);
            exit;
        }
    } else {
        $msg = $api['message'] ?? 'Request failed';
        echo json_encode(['success' => false, 'message' => $msg, 'error' => $msg, 'http_code' => $code ?? 0, 'raw' => $resp]);
        exit;
    }
}

// Load DB connection for normal login after handling forgot-password actions
include __DIR__ . '/conn.php';

// Validate database connection (only needed for login path)
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$loginInput = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($loginInput) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// External HR API authentication
$externalUrl = 'http://26.137.144.53/HR-EMPLOYEE-MANAGEMENT/API/get_users.php';
$ch = curl_init($externalUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $loginInput, 'password' => $password]));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
    echo json_encode(['success' => false, 'message' => 'Unable to reach authentication service']);
    exit;
}

$apiData = json_decode($resp, true);
if (!is_array($apiData)) {
    echo json_encode(['success' => false, 'message' => 'Invalid response from authentication service']);
    exit;
}

if (isset($apiData['status']) && strtolower($apiData['status']) === 'success') {
    $userObj = null;
    if (isset($apiData['user']) && is_array($apiData['user'])) {
        $userObj = $apiData['user'];
    } elseif (isset($apiData['users']) && is_array($apiData['users']) && count($apiData['users']) > 0) {
        $userObj = $apiData['users'][0];
    }
    if ($userObj) {
        $roleApi = $userObj['role'] ?? 'Employee';
        $role = strtolower($roleApi);
        $fullName = $userObj['fullname'] ?? null;
        $userId = $userObj['applicant_employee_id'] ?? null;
        $mustChange = isset($userObj['reset_required']) ? ((int)$userObj['reset_required'] === 1) : false;
        $subRole = $userObj['sub_role'] ?? null;

        $_SESSION['loggedin'] = true;
        $_SESSION['user_email'] = $userObj['email'] ?? $loginInput;
        $_SESSION['username'] = null;
        $_SESSION['role'] = $role;
        $_SESSION['user_id'] = $userId;
        $_SESSION['full_name'] = $fullName;
        if ($subRole) {
            $_SESSION['sub_role'] = $subRole;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'email' => $_SESSION['user_email'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'user_id' => $_SESSION['user_id'],
                'full_name' => $_SESSION['full_name'],
                'sub_role' => $subRole,
                'must_change_password' => $mustChange
            ],
            'redirect' => 'dashboard.html'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid email or password'], JSON_UNESCAPED_UNICODE);
exit;

// Stop here: external API is authoritative for authentication
// The remaining database-based code is intentionally bypassed.

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

// If not found in users table, check suppliers table
if (!$result || mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    
    // Check suppliers table
    $checkSupplierAuth = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'username'");
    $hasSupplierAuth = $checkSupplierAuth && mysqli_num_rows($checkSupplierAuth) > 0;
    
    if ($hasSupplierAuth) {
        $supplierQuery = "SELECT * FROM suppliers WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND status != 'locked'";
        $supplierStmt = mysqli_prepare($conn, $supplierQuery);
        
        if ($supplierStmt) {
            mysqli_stmt_bind_param($supplierStmt, "ss", $loginInputLower, $loginInputLower);
            mysqli_stmt_execute($supplierStmt);
            $supplierResult = mysqli_stmt_get_result($supplierStmt);
            
            if ($supplierResult && mysqli_num_rows($supplierResult) === 1) {
                $supplier = mysqli_fetch_assoc($supplierResult);
                mysqli_stmt_close($supplierStmt);
                
                // Check supplier status
                if (isset($supplier['status'])) {
                    $status = strtolower($supplier['status']);
                    if ($status === 'locked') {
                        echo json_encode(['success' => false, 'message' => 'Account Locked. Please contact administrator.']);
                        exit;
                    }
                    if ($status === 'inactive') {
                        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact administrator.']);
                        exit;
                    }
                }
                
                // Verify password
                $storedPassword = $supplier['password_hash'] ?? '';
                if (empty($storedPassword)) {
                    echo json_encode(['success' => false, 'message' => 'Account not configured for login. Please contact administrator.']);
                    exit;
                }
                
                $passwordValid = verifyPassword($password, $storedPassword);
                
                if ($passwordValid) {
                    // Update supplier status to active
                    updateSupplierStatus($conn, $supplier['id'], 'active');
                    
                    // Set supplier session
                    setSupplierSession($supplier);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => getSupplierData($supplier),
                        'redirect' => 'supplier_dashboard.html'
                    ]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password']);
                    exit;
                }
            }
            mysqli_stmt_close($supplierStmt);
        }
    }
    
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
    
    // Get user data (this will include supplier_id if user is a supplier)
    $userData = getUserData($user, $loginInput);
    
    // Determine redirect based on role
    $role = strtolower($user['role'] ?? 'employee');
    $redirect = 'dashboard.html';
    
    // If supplier, redirect to supplier dashboard
    
    
    // Ensure supplier_id is set for suppliers (fallback to user_id if matching failed)
    if ($role === 'supplier' && !isset($userData['supplier_id'])) {
        $userData['supplier_id'] = $userData['user_id'] ?? $userData['id'] ?? null;
        error_log("login.php: Supplier ID not found via matching, using user_id as fallback: " . $userData['supplier_id']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $userData,
        'redirect' => $redirect
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password'], JSON_UNESCAPED_UNICODE);
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
    $_SESSION['role'] = $user['role'] ?? 'employee';
    $_SESSION['user_id'] = $user['user_id'] ?? $user['id'] ?? null;
    $_SESSION['full_name'] = $user['full_name'] ?? null;
}

/**
 * Get user data for response
 */
function getUserData($user, $loginInput = null) {
    global $conn;
    
    // Check if must_change_password column exists and get its value
    $mustChangePassword = 0;
    if (isset($user['must_change_password'])) {
        $mustChangePassword = (int)$user['must_change_password'];
    } else {
        // Check if column exists in database
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
    
    $userData = [
        'email' => $user['email'] ?? $loginInput,
        'username' => $user['username'] ?? null,
        'role' => $user['role'] ?? 'employee',
        'user_id' => $user['user_id'] ?? $user['id'] ?? null,
        'full_name' => $user['full_name'] ?? null,
        'must_change_password' => $mustChangePassword === 1
    ];
    
    // If user is a supplier, find their corresponding suppliers.id
    $role = strtolower($user['role'] ?? 'employee');
    if ($role === 'supplier' && isset($conn) && $conn) {
        $userEmail = $user['email'] ?? '';
        $userName = $user['full_name'] ?? '';
        
        // Try to find matching supplier by email first (most reliable)
        if (!empty($userEmail)) {
            $supplierQuery = "SELECT id, name FROM suppliers WHERE email = ? LIMIT 1";
            $supplierStmt = mysqli_prepare($conn, $supplierQuery);
            if ($supplierStmt) {
                mysqli_stmt_bind_param($supplierStmt, "s", $userEmail);
                mysqli_stmt_execute($supplierStmt);
                $supplierResult = mysqli_stmt_get_result($supplierStmt);
                if ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
                    $userData['supplier_id'] = (int)$supplierRow['id'];
                    $userData['name'] = $supplierRow['name'];
                    mysqli_stmt_close($supplierStmt);
                    return $userData;
                }
                mysqli_stmt_close($supplierStmt);
            }
        }
        
        // If email didn't match, try by name
        if (!empty($userName) && !isset($userData['supplier_id'])) {
            $supplierQuery = "SELECT id, name FROM suppliers WHERE name = ? LIMIT 1";
            $supplierStmt = mysqli_prepare($conn, $supplierQuery);
            if ($supplierStmt) {
                mysqli_stmt_bind_param($supplierStmt, "s", $userName);
                mysqli_stmt_execute($supplierStmt);
                $supplierResult = mysqli_stmt_get_result($supplierStmt);
                if ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
                    $userData['supplier_id'] = (int)$supplierRow['id'];
                    $userData['name'] = $supplierRow['name'];
                }
                mysqli_stmt_close($supplierStmt);
            }
        }
    }
    
    return $userData;
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

/**
 * Update supplier status
 */
function updateSupplierStatus($conn, $supplierId, $status) {
    $query = "UPDATE suppliers SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $status, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Set supplier session variables
 */
function setSupplierSession($supplier) {
    $_SESSION['supplier_loggedin'] = true;
    $_SESSION['supplier_id'] = $supplier['id'];
    $_SESSION['supplier_name'] = $supplier['name'];
    $_SESSION['supplier_email'] = $supplier['email'] ?? '';
    $_SESSION['supplier_username'] = $supplier['username'] ?? '';
    $_SESSION['role'] = 'supplier';
    $_SESSION['loggedin'] = true; // Also set for compatibility
    $_SESSION['user_id'] = $supplier['id']; // For compatibility
}

/**
 * Get supplier data for response
 */
function getSupplierData($supplier) {
    return [
        'id' => (int)$supplier['id'],
        'name' => $supplier['name'],
        'email' => $supplier['email'] ?? '',
        'username' => $supplier['username'] ?? '',
        'role' => 'supplier',
        'supplier_id' => (int)$supplier['id']
    ];
}
?>
