<?php
/**
 * Reset Password API
 * Resets the user's password after OTP verification
 */

session_start();
header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/../php/conn.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if OTP was verified
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'OTP verification required']);
    exit;
}

// Get data
$user_id = intval($_POST['user_id'] ?? 0);
$password = $_POST['password'] ?? '';

// Validate
if ($user_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

if (empty($password) || strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

// Verify user_id matches session
if ($user_id != $_SESSION['reset_user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid user']);
    exit;
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Check and create must_change_password column if it doesn't exist
$checkMustChange = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
if (mysqli_num_rows($checkMustChange) === 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 0 AFTER status");
}

// Check if password_hash column exists, otherwise use password
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
$hasPasswordHash = mysqli_num_rows($checkColumn) > 0;

if ($hasPasswordHash) {
    // Clear must_change_password flag when password is changed
    $updateStmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, must_change_password = 0, otp = NULL, otp_expiry = NULL WHERE user_id = ?");
} else {
    $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ?, must_change_password = 0, otp = NULL, otp_expiry = NULL WHERE user_id = ?");
}

if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if ($hasPasswordHash) {
    mysqli_stmt_bind_param($updateStmt, "si", $passwordHash, $user_id);
} else {
    mysqli_stmt_bind_param($updateStmt, "si", $passwordHash, $user_id);
}

mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

// Clear session
unset($_SESSION['otp_verified']);
unset($_SESSION['reset_user_id']);
unset($_SESSION['reset_email']);

echo json_encode([
    'success' => true,
    'message' => 'Password reset successfully'
]);

mysqli_close($conn);
?>
