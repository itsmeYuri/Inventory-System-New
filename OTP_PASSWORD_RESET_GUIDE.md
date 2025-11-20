# OTP-Based Password Reset System - Complete Guide

## 📁 Folder Structure

```
In-1/
├── php/
│   ├── db.php                 # Database connection
│   └── otp_middleware.php    # OTP verification middleware
├── pages/
│   ├── forgot_password.php   # Email input form
│   ├── send_otp.php          # Backend API - generates & sends OTP
│   ├── verify_otp.php        # OTP verification form
│   ├── reset_password.php   # New password form
│   └── reset_success.php    # Success confirmation page
└── PHPMailer/
    └── src/                  # PHPMailer library
```

## 🔄 System Flow

### Step-by-Step Process:

1. **User enters email** (`forgot_password.php`)
   - User submits email address
   - System validates email format
   - Calls `send_otp.php` API

2. **OTP Generation & Email** (`send_otp.php`)
   - Checks if email exists in database
   - Generates 6-digit OTP code
   - Stores OTP + expiry (10 minutes) in database
   - Sends OTP via Gmail using PHPMailer
   - Returns success message (generic for security)

3. **OTP Verification** (`verify_otp.php`)
   - User enters 6-digit OTP code
   - System validates OTP against database
   - Checks if OTP is expired (10 minutes)
   - Sets session variables on success
   - Redirects to `reset_password.php`

4. **Password Reset** (`reset_password.php`)
   - Middleware checks OTP verification
   - User enters new password (min 8 characters)
   - User confirms password
   - System hashes password using `password_hash()`
   - Updates database and clears OTP fields
   - Redirects to `reset_success.php`

5. **Success Confirmation** (`reset_success.php`)
   - Shows success message
   - Clears all session data
   - Provides link to login page

## 🔧 PHPMailer Setup Instructions

### 1. Gmail App Password Setup

1. Go to your Google Account settings
2. Navigate to **Security** → **2-Step Verification** (enable if not already)
3. Go to **App passwords**
4. Generate a new app password for "Mail"
5. Copy the 16-character password (e.g., `abcd efgh ijkl mnop`)

### 2. Update Gmail Credentials

Edit `pages/send_otp.php` and update these lines:

```php
$gmailUser = 'YOUR_GMAIL_ADDRESS@gmail.com'; // Replace with your Gmail
$gmailAppPass = 'YOUR_APP_PASSWORD'; // Replace with your App Password
```

**Example:**
```php
$gmailUser = 'myemail@gmail.com';
$gmailAppPass = 'abcd efgh ijkl mnop';
```

### 3. PHPMailer Configuration

The system uses:
- **SMTP Server:** `smtp.gmail.com`
- **Port:** `587`
- **Encryption:** `TLS` (STARTTLS)
- **Authentication:** Required

## 🗄️ Database Structure

### Required Columns in `users` Table:

```sql
- email VARCHAR(255)          -- User's email address
- password_hash VARCHAR(255)  -- Hashed password (or 'password' as fallback)
- otp VARCHAR(6)             -- 6-digit OTP code (nullable)
- otp_expiry DATETIME        -- OTP expiration time (nullable)
```

### Auto-Creation

The system automatically creates `otp` and `otp_expiry` columns if they don't exist.

## 🔒 Security Features

1. **Prepared Statements:** All database queries use `mysqli_prepare()` and `bind_param()`
2. **Password Hashing:** Uses `password_hash(PASSWORD_DEFAULT)`
3. **OTP Expiry:** 10-minute expiration time
4. **Email Privacy:** Generic success message (doesn't reveal if email exists)
5. **Session Management:** Secure session-based flow
6. **OTP Cleanup:** OTP fields cleared after successful reset
7. **Middleware Protection:** `otp_middleware.php` prevents bypassing OTP step

## 📝 File Descriptions

### `php/db.php`
- Database connection file
- Uses MySQLi
- UTF-8 charset
- Error logging enabled

### `php/otp_middleware.php`
- Middleware function `require_otp_verification()`
- Checks if OTP is verified before allowing password reset
- Redirects to appropriate pages if verification fails

### `pages/forgot_password.php`
- Email input form
- Validates email format
- Calls `send_otp.php` API
- Redirects to `verify_otp.php` on success

### `pages/send_otp.php`
- Backend API endpoint
- Generates 6-digit OTP
- Stores OTP in database
- Sends OTP via PHPMailer
- Returns JSON response

### `pages/verify_otp.php`
- OTP input form
- Validates 6-digit OTP
- Checks expiration
- Sets session variables
- Redirects to `reset_password.php` on success

### `pages/reset_password.php`
- New password form
- Uses `otp_middleware.php` for protection
- Validates password (min 8 characters)
- Hashes password using `password_hash()`
- Clears OTP fields after reset
- Redirects to `reset_success.php`

### `pages/reset_success.php`
- Success confirmation page
- Clears all session data
- Provides login link

## 🚀 Usage

1. **User visits:** `pages/forgot_password.php`
2. **Enters email** and clicks "Send OTP Code"
3. **Receives email** with 6-digit OTP
4. **Enters OTP** in `verify_otp.php`
5. **Sets new password** in `reset_password.php`
6. **Sees confirmation** in `reset_success.php`
7. **Logs in** with new password

## ⚠️ Important Notes

1. **Gmail Credentials:** Must update Gmail username and app password in `send_otp.php`
2. **Database:** Ensure `users` table exists with required columns
3. **PHPMailer:** Must have PHPMailer library installed in `PHPMailer/src/`
4. **Session:** Ensure PHP sessions are enabled
5. **Error Handling:** All errors are logged using `error_log()`

## 🐛 Troubleshooting

### OTP Not Received
- Check Gmail credentials in `send_otp.php`
- Verify Gmail app password is correct
- Check PHP error logs
- Ensure PHPMailer is properly installed

### OTP Expired
- OTP expires after 10 minutes
- User must request a new OTP from `forgot_password.php`

### Database Errors
- Check database connection in `php/db.php`
- Verify `users` table exists
- Ensure columns `otp` and `otp_expiry` exist (auto-created if missing)

### Session Issues
- Ensure PHP sessions are enabled
- Check session storage permissions
- Verify session cookies are working

## ✅ Testing Checklist

- [ ] Email validation works
- [ ] OTP is generated and stored
- [ ] Email is sent successfully
- [ ] OTP verification works
- [ ] Expired OTP is rejected
- [ ] Invalid OTP is rejected
- [ ] Password reset works
- [ ] OTP is cleared after reset
- [ ] Success page displays correctly
- [ ] User can login with new password

---

**System Status:** ✅ Complete and Ready for Use

**Last Updated:** 2025

