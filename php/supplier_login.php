<?php
/**
 * Supplier Authentication System
 * Handles supplier login via username/email and password
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Enhanced CORS headers
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

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

// Validate database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$loginInput = trim($_POST['loginInput'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

// Validate input
if (empty($loginInput) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username/email and password are required']);
    exit;
}

// Check if suppliers table has authentication fields
$checkUsername = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'username'");
$hasAuthFields = $checkUsername && mysqli_num_rows($checkUsername) > 0;

if (!$hasAuthFields) {
    echo json_encode([
        'success' => false, 
        'message' => 'Supplier authentication not configured. Please run php/add_supplier_auth_fields.php first.'
    ]);
    exit;
}

// Database authentication - search by username or email
$loginInputLower = strtolower(trim($loginInput));
$query = "SELECT * FROM suppliers WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND status != 'locked'";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $loginInputLower, $loginInputLower);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Invalid username/email or password']);
    exit;
}

$supplier = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Check if account is locked or inactive
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
    
    // Set session variables
    setSupplierSession($supplier, $remember);
    
    // Return supplier data (without sensitive information)
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'supplier' => getSupplierData($supplier)
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username/email or password']);
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
function setSupplierSession($supplier, $remember = false) {
    $_SESSION['supplier_loggedin'] = true;
    $_SESSION['supplier_id'] = $supplier['id'];
    $_SESSION['supplier_name'] = $supplier['name'];
    $_SESSION['supplier_email'] = $supplier['email'] ?? '';
    $_SESSION['supplier_username'] = $supplier['username'] ?? '';
    $_SESSION['role'] = 'supplier';
    
    // Set session cookie expiration
    if ($remember) {
        ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 days
    } else {
        ini_set('session.cookie_lifetime', 0); // Until browser closes
    }
}

/**
 * Get supplier data for response (without sensitive info)
 */
function getSupplierData($supplier) {
    return [
        'id' => (int)$supplier['id'],
        'name' => $supplier['name'],
        'email' => $supplier['email'] ?? '',
        'username' => $supplier['username'] ?? '',
        'contact_person' => $supplier['contact_person'] ?? '',
        'phone' => $supplier['phone'] ?? '',
        'address' => $supplier['address'] ?? '',
        'status' => $supplier['status'] ?? 'active'
    ];
}
?>

