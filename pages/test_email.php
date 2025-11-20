<?php
/**
 * Test Email Script
 * Use this to test if email sending is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../php/password_reset_logger.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$gmailUser = 'darryljohn016@gmail.com';
$gmailAppPass = 'pomnrmfgvtdpxxmc'; // Remove spaces from app password
$testEmail = $_GET['email'] ?? 'darryljohn016@gmail.com'; // Test email address

echo "<h2>Email Test</h2>";
echo "<p>Testing email configuration...</p>";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2; // Enable verbose debug output
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
    
    $mail->setFrom($gmailUser, 'Inventory System Test');
    $mail->addAddress($testEmail);
    $mail->Subject = 'Test Email - OTP System';
    $mail->isHTML(true);
    $mail->Body = '<h1>Test Email</h1><p>If you receive this, email sending is working!</p>';
    $mail->AltBody = 'Test Email - If you receive this, email sending is working!';
    
    echo "<p>Attempting to send email to: <strong>$testEmail</strong></p>";
    echo "<pre>";
    $mail->send();
    echo "</pre>";
    echo "<p style='color: green;'><strong>✓ Email sent successfully!</strong></p>";
    echo "<p>Check your inbox at: <strong>$testEmail</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Error sending email:</strong></p>";
    echo "<pre>";
    echo "Error Info: " . $mail->ErrorInfo . "\n";
    echo "Exception: " . $e->getMessage() . "\n";
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='?email=" . urlencode($testEmail) . "'>Test Again</a></p>";
echo "<p>To test with a different email, add ?email=your@email.com to the URL</p>";
?>

