<?php
/**
 * Send OTP API
 * Generates and sends OTP code via email
 */

session_start();
header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/../php/conn.php';

// Check PHPMailer
$phpmailerPath = __DIR__ . '/../PHPMailer/src';
if (!file_exists($phpmailerPath . '/PHPMailer.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Email service not available']);
    exit;
}

    require_once $phpmailerPath . '/Exception.php';
    require_once $phpmailerPath . '/PHPMailer.php';
    require_once $phpmailerPath . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get email
$email = trim($_POST['email'] ?? '');

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Check if user exists
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username FROM users WHERE email = ? AND (status = 'active' OR status IS NULL) LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Always return success for security (don't reveal if email exists)
if (!$user) {
    echo json_encode(['success' => true, 'message' => 'If your email is registered, you will receive an OTP code.']);
    exit;
}

// Ensure OTP columns exist
$checkOtp = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'otp'");
if (mysqli_num_rows($checkOtp) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN otp VARCHAR(6) NULL");
}

$checkOtpExpiry = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'otp_expiry'");
if (mysqli_num_rows($checkOtpExpiry) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN otp_expiry DATETIME NULL");
}
    
    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expiry = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// Store OTP in database
$updateStmt = mysqli_prepare($conn, "UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?");
    if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP']);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "sss", $otp, $otp_expiry, $email);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

// Gmail configuration
$gmailUser = 'darryljohn016@gmail.com';
$gmailAppPass = 'pomnrmfgvtdpxxmc';

// Send email via PHPMailer
$mail = new PHPMailer(true);
try {
    // Enable debug output (logs to error_log)
    $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug [$level]: $str");
    };
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmailUser;
    $mail->Password = $gmailAppPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    // SSL options for local development
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->setFrom($gmailUser, 'Inventory System');
    $mail->addAddress($email, $user['full_name'] ?? $user['username'] ?? 'User');
    $mail->Subject = 'Password Reset OTP Code';
    $mail->isHTML(true);
    
    $mail->Body = '
    <div style="max-width: 600px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
        <h1 style="color: #1e293b; text-align: center;">Password Reset Request</h1>
        <div style="background: #f8fafc; padding: 30px; border-radius: 8px; margin: 20px 0;">
            <p>Hello <strong>' . htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User') . '</strong>,</p>
            <p>Your OTP code is:</p>
            <div style="text-align: center; margin: 30px 0;">
                <div style="display: inline-block; background: #ffffff; padding: 20px 40px; border: 2px solid #3b82f6; border-radius: 8px;">
                    <p style="font-size: 32px; font-weight: bold; color: #3b82f6; letter-spacing: 8px; margin: 0; font-family: monospace;">' . $otp . '</p>
                </div>
            </div>
            <p style="color: #dc2626; text-align: center; font-weight: 600;">This OTP will expire in 10 minutes.</p>
        </div>
        <p style="color: #64748b; text-align: center; font-size: 13px;">If you did not request this, please ignore this email.</p>
    </div>';
    
    $mail->AltBody = "Password Reset OTP\n\nYour OTP code is: " . $otp . "\n\nThis OTP will expire in 10 minutes.";
    
    // Attempt to send
    $sendResult = $mail->send();
    
    if ($sendResult) {
        // Store email in session
        $_SESSION['reset_email'] = $email;
        
        error_log("OTP Email sent successfully to: $email");
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP code has been sent to your email. Please check your inbox and spam folder.',
            'debug' => 'Email sent successfully. OTP: ' . $otp // Remove this in production
        ]);
    } else {
        throw new Exception('Email send() returned false. ErrorInfo: ' . ($mail->ErrorInfo ?? 'Unknown error'));
    }
    
} catch (Exception $e) {
    $errorMsg = $mail->ErrorInfo ?? $e->getMessage();
    error_log("PHPMailer Error: " . $errorMsg);
    error_log("PHPMailer Exception: " . $e->getMessage());
    error_log("PHPMailer Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send email: ' . htmlspecialchars($errorMsg),
        'debug_info' => 'Check PHP error log for details'
    ]);
}

mysqli_close($conn);
?>

