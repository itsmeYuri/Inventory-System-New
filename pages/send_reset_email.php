<?php
/**
 * Send Password Reset Email
 * Sends password reset link via email
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get email from POST
$rawEmail = $_POST['email'] ?? $_POST['resetEmail'] ?? '';
$email = filter_var(trim((string)$rawEmail), FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Include PHPMailer
$phpmailerBase = realpath(__DIR__ . '/../PHPMailer/src');

if (!$phpmailerBase || !file_exists($phpmailerBase . '/PHPMailer.php')) {
    error_log('PHPMailer files not found');
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    exit;
}

require_once $phpmailerBase . '/Exception.php';
require_once $phpmailerBase . '/PHPMailer.php';
require_once $phpmailerBase . '/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
require_once __DIR__ . '/../php/conn.php';

if (!isset($conn) || !$conn) {
    error_log('Database connection failed');
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    exit;
}

// Lookup user (active)
$stmt = mysqli_prepare($conn, 'SELECT user_id, full_name, email FROM users WHERE email = ? AND (status = "active" OR status IS NULL) LIMIT 1');
if (!$stmt) {
    error_log('Prepare failed: ' . mysqli_error($conn));
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'The email address you entered is not registered.']);
    mysqli_close($conn);
    exit;
}

// Generate secure random token
$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 min expiry

// Invalidate any existing reset tokens
$invalidateStmt = mysqli_prepare($conn, 'UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?');
if ($invalidateStmt) {
    mysqli_stmt_bind_param($invalidateStmt, 'i', $user['user_id']);
    mysqli_stmt_execute($invalidateStmt);
    mysqli_stmt_close($invalidateStmt);
}

// Set the new reset token
$upd = mysqli_prepare($conn, 'UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?');
if (!$upd) {
    error_log('Prepare failed: ' . mysqli_error($conn));
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param($upd, 'ssi', $tokenHash, $expiresAt, $user['user_id']);
if (!mysqli_stmt_execute($upd)) {
    error_log('Execute failed: ' . mysqli_stmt_error($upd));
    mysqli_stmt_close($upd);
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($upd);

// Build reset URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$resetUrl = sprintf('%s://%s/pages/forgot_password.php?token=%s', $scheme, $host, rawurlencode($rawToken));

// Gmail credentials
$gmailUser = 'darryljohn016@gmail.com';
$gmailAppPass = 'pomnrmfgvtdpxxmc'; // Gmail App Password (no spaces)

// Send mail
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmailUser;
    $mail->Password = $gmailAppPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($gmailUser, 'Inventory System');
    $mail->addAddress($user['email'], $user['full_name'] ?? '');
    $mail->Subject = 'Password reset instructions';
    $mail->isHTML(true);
    $mail->Body =
        '<div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 40px 32px; border: 1px solid #e1e5e9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-size: 24px; color: #1e293b; font-weight: 700; margin: 0 0 8px 0; letter-spacing: -0.025em;">Password Reset Request</h1>
                <p style="font-size: 16px; color: #64748b; margin: 0;">Secure your account</p>
            </div>
            
            <div style="background: #f8fafc; border-radius: 8px; padding: 24px; margin-bottom: 24px; border-left: 4px solid #3b82f6;">
                <p style="font-size: 16px; color: #1e293b; margin: 0 0 12px 0; font-weight: 500;">Hello <strong>' . htmlspecialchars($user['full_name'] ?? 'User') . '</strong>,</p>
                <p style="font-size: 15px; color: #475569; margin: 0 0 16px 0; line-height: 1.5;">Click the link below to reset your password. This link will expire in <strong style="color: #dc2626;">30 minutes</strong> for security purposes.</p>
                
                <div style="text-align: center; margin: 24px 0;">
                    <a href="' . htmlspecialchars($resetUrl) . '" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #ffffff; font-size: 16px; font-weight: 600; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">Reset Password</a>
                </div>
                
                <p style="font-size: 13px; color: #64748b; margin: 16px 0 0 0; text-align: center;">If the button doesn\'t work, copy and paste this link:<br><span style="word-break: break-all; color: #3b82f6;">' . htmlspecialchars($resetUrl) . '</span></p>
            </div>
        </div>';
    $mail->AltBody = "Reset link:\n\n" . $resetUrl;
    $mail->send();

    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
} catch (Exception $e) {
    error_log('PHPMailer error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
}

mysqli_close($conn);
exit;
?>
