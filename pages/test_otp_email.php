<?php
/**
 * Test OTP Email Sending
 * Use this to test OTP email functionality with detailed debugging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>OTP Email Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
</style>";

// Get test email
$testEmail = $_GET['email'] ?? 'darryljohn016@gmail.com';

echo "<div class='info'><strong>Testing OTP email to:</strong> $testEmail</div>";

// Database connection
require_once __DIR__ . '/../php/conn.php';

// Check if user exists
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, email FROM users WHERE email = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $testEmail);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user) {
        echo "<div class='success'>✓ User found in database: " . htmlspecialchars($user['full_name'] ?? $user['username']) . "</div>";
    } else {
        echo "<div class='error'>✗ User not found in database. Creating test scenario...</div>";
        $user = ['user_id' => 1, 'full_name' => 'Test User', 'username' => 'testuser', 'email' => $testEmail];
    }
} else {
    echo "<div class='error'>✗ Database query failed: " . mysqli_error($conn) . "</div>";
    $user = ['user_id' => 1, 'full_name' => 'Test User', 'username' => 'testuser', 'email' => $testEmail];
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
echo "<div class='info'><strong>Generated OTP:</strong> $otp</div>";

// PHPMailer setup
$phpmailerPath = __DIR__ . '/../PHPMailer/src';
if (!file_exists($phpmailerPath . '/PHPMailer.php')) {
    echo "<div class='error'>✗ PHPMailer not found at: $phpmailerPath</div>";
    exit;
}

require_once $phpmailerPath . '/Exception.php';
require_once $phpmailerPath . '/PHPMailer.php';
require_once $phpmailerPath . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Gmail configuration
$gmailUser = 'darryljohn016@gmail.com';
$gmailAppPass = 'pomnrmfgvtdpxxmc';

echo "<div class='info'><strong>Gmail User:</strong> $gmailUser</div>";
echo "<div class='info'><strong>App Password Length:</strong> " . strlen($gmailAppPass) . " characters</div>";

if (strlen($gmailAppPass) != 16) {
    echo "<div class='error'>⚠ Warning: App password should be 16 characters (currently " . strlen($gmailAppPass) . ")</div>";
}

$mail = new PHPMailer(true);

// Capture debug output
$debugOutput = [];
$mail->SMTPDebug = 2;
$mail->Debugoutput = function($str, $level) use (&$debugOutput) {
    $debugOutput[] = "[$level] $str";
    echo "<pre style='font-size: 10px; margin: 2px 0;'>[$level] " . htmlspecialchars($str) . "</pre>";
};

try {
    echo "<h2>SMTP Configuration</h2>";
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmailUser;
    $mail->Password = $gmailAppPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    echo "<div class='info'>Configuration complete. Attempting to send...</div>";
    
    $mail->setFrom($gmailUser, 'Inventory System Test');
    $mail->addAddress($testEmail, $user['full_name'] ?? $user['username'] ?? 'User');
    $mail->Subject = 'Test OTP Code - ' . date('Y-m-d H:i:s');
    $mail->isHTML(true);
    
    $mail->Body = '
    <div style="max-width: 600px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
        <h1 style="color: #1e293b; text-align: center;">Test OTP Email</h1>
        <div style="background: #f8fafc; padding: 30px; border-radius: 8px; margin: 20px 0;">
            <p>Hello <strong>' . htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User') . '</strong>,</p>
            <p>This is a test OTP email. Your test OTP code is:</p>
            <div style="text-align: center; margin: 30px 0;">
                <div style="display: inline-block; background: #ffffff; padding: 20px 40px; border: 2px solid #3b82f6; border-radius: 8px;">
                    <p style="font-size: 32px; font-weight: bold; color: #3b82f6; letter-spacing: 8px; margin: 0; font-family: monospace;">' . $otp . '</p>
                </div>
            </div>
            <p style="color: #dc2626; text-align: center; font-weight: 600;">This is a test email.</p>
        </div>
    </div>';
    
    $mail->AltBody = "Test OTP Email\n\nYour test OTP code is: " . $otp;
    
    echo "<h2>Sending Email...</h2>";
    $sendResult = $mail->send();
    
    if ($sendResult) {
        echo "<div class='success'><strong>✓ Email sent successfully!</strong></div>";
        echo "<div class='info'>Check your inbox at: <strong>$testEmail</strong></div>";
        echo "<div class='info'>Also check your <strong>Spam/Junk folder</strong></div>";
        echo "<div class='info'><strong>Test OTP Code:</strong> $otp</div>";
    } else {
        echo "<div class='error'>✗ Email send() returned false</div>";
        echo "<div class='error'>ErrorInfo: " . ($mail->ErrorInfo ?? 'No error info') . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ Exception occurred:</strong></div>";
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>ErrorInfo: " . htmlspecialchars($mail->ErrorInfo ?? 'N/A') . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='?email=" . urlencode($testEmail) . "'>Test Again</a> | ";
echo "<a href='check_email_status.php'>Check Email Status</a></p>";
echo "<p>To test with a different email, add ?email=your@email.com to the URL</p>";

mysqli_close($conn);
?>

