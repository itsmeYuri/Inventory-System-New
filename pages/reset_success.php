<?php
/**
 * Reset Success Page
 * Displayed after successful password reset
 */

session_start();

// Clear any remaining session data
if (isset($_SESSION['otp_verified'])) {
    unset($_SESSION['otp_verified']);
}
if (isset($_SESSION['reset_user_id'])) {
    unset($_SESSION['reset_user_id']);
}
if (isset($_SESSION['reset_email'])) {
    unset($_SESSION['reset_email']);
}
if (isset($_SESSION['reset_username'])) {
    unset($_SESSION['reset_username']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - Inventory System</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-100 flex items-center justify-center p-4">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
        <svg class="w-full h-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                    <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="0.5"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" class="text-primary-300"/>
        </svg>
    </div>

    <!-- Main Container -->
    <div class="relative w-full max-w-md">
        <!-- Success Card -->
        <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 p-8 animation-slide-up text-center">
            <!-- Success Icon -->
            <div class="inline-flex items-center justify-center w-20 h-20 bg-success-100 rounded-full mb-6">
                <i class="fas fa-check-circle text-success-600 text-4xl"></i>
            </div>

            <!-- Success Message -->
            <h1 class="text-2xl font-semibold text-text-primary mb-3">Password Reset Successful!</h1>
            <p class="text-text-secondary mb-8">Your password has been successfully reset. You can now log in with your new password.</p>

            <!-- Action Button -->
            <a href="login.html" class="inline-flex items-center justify-center w-full bg-primary hover:bg-primary-700 text-white font-medium py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-[1.02] focus:ring-2 focus:ring-primary-200 focus:ring-offset-2 shadow-lg">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Go to Login
            </a>

            <!-- Additional Info -->
            <div class="mt-6 p-4 bg-primary-50 border border-primary-200 rounded-xl">
                <p class="text-sm text-primary-700">
                    <i class="fas fa-info-circle mr-2"></i>
                    For security reasons, please keep your password confidential and do not share it with anyone.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-xs text-text-tertiary">
                © 2025 INVENTORY Management System. All Rights Reserved.
            </p>
        </div>
    </div>
</body>
</html>

