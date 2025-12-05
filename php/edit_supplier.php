<?php
// Edit Supplier API
// Handles updating existing suppliers in the database

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
if (ob_get_level() == 0) {
    ob_start();
}

// Enhanced CORS headers
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost',
    'http://127.0.0.1:3000',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    // Clean output buffer if it exists
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Ensure no output has been sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    
    // End output buffering if it exists
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit(0);
}

// Only allow POST/PUT requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    sendJsonResponse(false, 'Method not allowed. Only POST/PUT requests are accepted.', null, 405);
}

try {
    require_once __DIR__ . '/conn.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }
    
    // Check if suppliers table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
        sendJsonResponse(false, 'Suppliers table does not exist. Please run database setup first.', null, 500);
    }

    // Get supplier ID
    $supplier_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($supplier_id <= 0) {
        sendJsonResponse(false, 'Invalid supplier ID', null, 400);
    }

    // Get and sanitize form data
    // Support both field name formats: 'name' or 'supplierName', 'phone' or 'phoneNumber', 'address' or 'location'
    $name = isset($_POST['name']) ? trim($_POST['name']) : (isset($_POST['supplierName']) ? trim($_POST['supplierName']) : '');
    
    // For nullable fields, convert empty strings to NULL
    $contact_person = isset($_POST['contactPerson']) ? trim($_POST['contactPerson']) : '';
    $contact_person = $contact_person !== '' ? $contact_person : null;
    
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : (isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : '');
    $phone = $phone !== '' ? $phone : null;
    
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $email = $email !== '' ? $email : null;
    
    $address = isset($_POST['address']) ? trim($_POST['address']) : (isset($_POST['location']) ? trim($_POST['location']) : '');
    $address = $address !== '' ? $address : null;
    
    $website = isset($_POST['website']) ? trim($_POST['website']) : '';
    $website = $website !== '' ? $website : null;
    
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $notes = $notes !== '' ? $notes : null;
    
    // Validate required fields
    if (empty($name)) {
        sendJsonResponse(false, 'Supplier Name is required', null, 400);
    }
    
    // Validate email format if provided
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid email address format', null, 400);
    }
    
    // Validate website URL format if provided
    if ($website !== null && $website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        sendJsonResponse(false, 'Invalid website URL format', null, 400);
    }
    
    // Check if website and notes columns exist
    $checkWebsite = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'website'");
    $hasWebsite = $checkWebsite && mysqli_num_rows($checkWebsite) > 0;
    
    $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'notes'");
    $hasNotes = $checkNotes && mysqli_num_rows($checkNotes) > 0;
    
    // Check if authentication fields exist
    $checkUsername = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'username'");
    $hasUsername = $checkUsername && mysqli_num_rows($checkUsername) > 0;
    
    $checkPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'password_hash'");
    $hasPasswordHash = $checkPasswordHash && mysqli_num_rows($checkPasswordHash) > 0;
    
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'status'");
    $hasStatus = $checkStatus && mysqli_num_rows($checkStatus) > 0;
    
    // Get authentication fields if provided
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;
    $password_hash = null;
    $updatePassword = false;
    
    // If username is provided and changed, validate it
    if ($username !== null && $username !== '') {
        // Check if username already exists for another supplier
        if ($hasUsername) {
            $checkUsernameQuery = "SELECT id FROM suppliers WHERE username = ? AND id != ?";
            $checkStmt = mysqli_prepare($conn, $checkUsernameQuery);
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "si", $username, $supplier_id);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                    mysqli_stmt_close($checkStmt);
                    sendJsonResponse(false, 'Username already exists. Please choose a different username.', null, 400);
                }
                mysqli_stmt_close($checkStmt);
            }
        }
        
        // If password is provided, hash it
        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                sendJsonResponse(false, 'Password must be at least 8 characters long', null, 400);
            }
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $updatePassword = true;
        }
    }

    // Build UPDATE SQL statement dynamically
    $updateFields = ['name = ?', 'contact_person = ?', 'phone = ?', 'email = ?', 'address = ?'];
    $updateValues = [$name, $contact_person, $phone, $email, $address];
    $updateTypes = 'sssss';
    
    if ($hasWebsite) {
        $updateFields[] = 'website = ?';
        $updateValues[] = $website;
        $updateTypes .= 's';
    }
    
    if ($hasNotes) {
        $updateFields[] = 'notes = ?';
        $updateValues[] = $notes;
        $updateTypes .= 's';
    }
    
    if ($hasUsername && $username !== null) {
        $updateFields[] = 'username = ?';
        $updateValues[] = $username;
        $updateTypes .= 's';
    }
    
    if ($hasPasswordHash && $updatePassword) {
        $updateFields[] = 'password_hash = ?';
        $updateValues[] = $password_hash;
        $updateTypes .= 's';
    }
    
    // Always keep suppliers active when editing (unless explicitly changed)
    if ($hasStatus) {
        // Only update status if explicitly provided in POST, otherwise set to active
        $explicitStatus = isset($_POST['status']) ? trim($_POST['status']) : null;
        if ($explicitStatus !== null && in_array($explicitStatus, ['active', 'inactive', 'locked'])) {
            $updateFields[] = 'status = ?';
            $updateValues[] = $explicitStatus;
            $updateTypes .= 's';
        } else {
            // If no explicit status provided, ensure supplier is active (for orders)
            $updateFields[] = 'status = ?';
            $updateValues[] = 'active';
            $updateTypes .= 's';
        }
    }
    
    $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
    $updateValues[] = $supplier_id; // For WHERE clause
    $updateTypes .= 'i';
    
    $sql = "UPDATE suppliers SET " . implode(', ', $updateFields) . " WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

    // Bind parameters dynamically
    $bound = mysqli_stmt_bind_param($stmt, $updateTypes, ...$updateValues);
    
    if (!$bound) {
        $error = 'Failed to bind parameters: ' . mysqli_stmt_error($stmt);
        error_log($error);
        mysqli_stmt_close($stmt);
        sendJsonResponse(false, 'Database binding error: ' . $error, null, 500);
    }

    // Execute statement
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        $errorCode = mysqli_stmt_errno($stmt);
        error_log("MySQL execute error [$errorCode]: " . $error);
        
        mysqli_stmt_close($stmt);
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedRows === 0) {
        sendJsonResponse(false, 'No supplier found with the provided ID or no changes were made', null, 404);
    }

    // Fetch the updated supplier data
    $selectSql = "SELECT 
        id, 
        name, 
        contact_person, 
        phone, 
        email, 
        address";
    
    if ($hasWebsite) {
        $selectSql .= ", website";
    }
    if ($hasNotes) {
        $selectSql .= ", notes";
    }
    
    $selectSql .= ", created_at, updated_at
    FROM suppliers 
    WHERE id = ?";
    
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        error_log("Select statement prepare error: " . mysqli_error($conn));
        sendJsonResponse(true, 'Supplier updated successfully', ['id' => $supplier_id], 200);
    }

    mysqli_stmt_bind_param($selectStmt, 'i', $supplier_id);
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        sendJsonResponse(true, 'Supplier updated successfully', ['id' => $supplier_id], 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $supplier = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$supplier) {
        sendJsonResponse(true, 'Supplier updated successfully', ['id' => $supplier_id], 200);
    }

    // Success response
    sendJsonResponse(true, 'Supplier updated successfully', $supplier, 200);

} catch (Exception $e) {
    error_log('Exception in edit_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in edit_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in edit_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

?>
