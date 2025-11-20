<?php
/**
 * Check Email Status
 * Comprehensive email troubleshooting tool
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Configuration Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// Check 1: PHPMailer files
echo "<h2>1. PHPMailer Files Check</h2>";
$phpmailerBase = realpath(__DIR__ . '/../PHPMailer/src');
if ($phpmailerBase && file_exists($phpmailerBase . '/PHPMailer.php')) {
    echo "<div class='success'>✓ PHPMailer files found at: $phpmailerBase</div>";
} else {
    echo "<div class='error'>✗ PHPMailer files NOT found at: " . __DIR__ . '/../PHPMailer/src' . "</div>";
    exit;
}

// Check 2: Gmail credentials
echo "<h2>2. Gmail Credentials Check</h2>";
$gmailUser = 'darryljohn016@gmail.com';
$gmailAppPass = 'pomnrmfgvtdpxxmc'; // Without spaces (Gmail app passwords should not have spaces)

echo "<div class='info'>Gmail User: $gmailUser</div>";
echo "<div class='info'>App Password Length: " . strlen($gmailAppPass) . " characters</div>";

if (strlen($gmailAppPass) < 16) {
    echo "<div class='error'>✗ App password seems too short. Gmail app passwords are usually 16 characters.</div>";
}

// Check 3: Test SMTP connection
echo "<h2>3. SMTP Connection Test</h2>";

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$testEmail = $_GET['email'] ?? $gmailUser;

try {
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->Debugoutput = function($str, $level) {
        echo "<pre style='font-size: 11px;'>$str</pre>";
    };
    
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
    
    echo "<div class='info'>Attempting to connect to SMTP server...</div>";
    
    // Test connection without sending
    $mail->smtpConnect();
    
    echo "<div class='success'>✓ SMTP Connection successful!</div>";
    
    // Now try to send a test email
    echo "<h2>4. Test Email Send</h2>";
    $mail->setFrom($gmailUser, 'Inventory System Test');
    $mail->addAddress($testEmail);
    $mail->Subject = 'Test Email - OTP System';
    $mail->isHTML(true);
    $mail->Body = '<h1>Test Email</h1><p>If you receive this, email sending is working!</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'Test Email - If you receive this, email sending is working!';
    
    echo "<div class='info'>Sending test email to: <strong>$testEmail</strong></div>";
    
    $mail->send();
    $mail->smtpClose();
    
    echo "<div class='success'>✓ Email sent successfully!</div>";
    echo "<div class='info'>Please check your inbox at: <strong>$testEmail</strong></div>";
    echo "<div class='info'>Also check your <strong>Spam/Junk folder</strong> if you don't see it in inbox.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error occurred:</div>";
    echo "<pre>";
    echo "Error Info: " . ($mail->ErrorInfo ?? 'N/A') . "\n";
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "</pre>";
    
    // Common error solutions
    echo "<h2>Common Solutions:</h2>";
    echo "<ul>";
    echo "<li><strong>Invalid credentials:</strong> Make sure your Gmail app password is correct (16 characters, no spaces)</li>";
    echo "<li><strong>2-Step Verification:</strong> Ensure 2-Step Verification is enabled on your Gmail account</li>";
    echo "<li><strong>App Password:</strong> Generate a new app password from <a href='https://myaccount.google.com/apppasswords' target='_blank'>Google Account Settings</a></li>";
    echo "<li><strong>Account locked:</strong> Check if your Gmail account is locked or restricted</li>";
    echo "<li><strong>Firewall:</strong> Ensure port 587 is not blocked by firewall</li>";
    echo "</ul>";
}

// Check 5: Logs
echo "<h2>5. Recent Logs</h2>";
$logFile = __DIR__ . '/../logs/password_reset.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -10); // Last 10 lines
    echo "<pre>";
    foreach ($recentLines as $line) {
        $log = json_decode($line, true);
        if ($log) {
            $status = $log['status'] ?? 'info';
            $color = $status === 'error' ? 'red' : ($status === 'success' ? 'green' : 'blue');
            echo "<span style='color: $color;'>[" . ($log['timestamp'] ?? '') . "] " . ($log['action'] ?? '') . " - " . ($log['error'] ?? 'OK') . "</span>\n";
        }
    }
    echo "</pre>";
} else {
    echo "<div class='info'>No log file found yet. Logs will be created after first attempt.</div>";
}

echo "<hr>";
echo "<p><a href='?email=" . urlencode($testEmail) . "'>Test Again</a> | ";
echo "<a href='test_email.php'>Simple Test</a> | ";
echo "<a href='view_password_reset_logs.php'>View All Logs</a></p>";
echo "<p>To test with a different email, add ?email=your@email.com to the URL</p>";
?>

