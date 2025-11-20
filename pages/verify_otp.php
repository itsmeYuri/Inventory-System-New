<?php
/**
 * Verify OTP API
 * Verifies the OTP code entered by user
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

// Get data
$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');

// Validate
if (empty($email) || empty($otp)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Email and OTP are required']);
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'OTP must be 6 digits']);
    exit;
}

// Verify OTP
$stmt = mysqli_prepare($conn, "SELECT user_id, email, otp, otp_expiry FROM users WHERE email = ? AND otp = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid OTP code']);
    exit;
}

// Check expiry
$currentTime = date('Y-m-d H:i:s');
if ($user['otp_expiry'] && $currentTime > $user['otp_expiry']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
    exit;
}

// OTP is valid - store in session
$_SESSION['otp_verified'] = true;
$_SESSION['reset_user_id'] = $user['user_id'];
$_SESSION['reset_email'] = $email;

echo json_encode([
    'success' => true,
    'message' => 'OTP verified successfully',
    'user_id' => $user['user_id']
]);

mysqli_close($conn);
?>

