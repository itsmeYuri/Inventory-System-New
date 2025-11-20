<?php
/**
 * OTP Middleware
 * Ensures user has verified OTP before accessing reset password page
 */

function require_otp_verification() {
    session_start();
    
    // Check if OTP is verified
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        // Redirect to verify OTP page
        header('Location: verify_otp.php');
        exit;
    }
    
    // Check if reset email is set
    if (!isset($_SESSION['reset_email']) || empty($_SESSION['reset_email'])) {
        header('Location: forgot_password.php');
        exit;
    }
    
    // Check if user_id is set
    if (!isset($_SESSION['reset_user_id']) || empty($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php');
        exit;
    }
    
    return true;
}
?>

