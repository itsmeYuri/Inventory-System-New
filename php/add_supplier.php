<?php
// Add Supplier API
// Handles adding new suppliers to the database

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

header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed. Only POST requests are accepted.', null, 405);
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
        // Create suppliers table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS suppliers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            address VARCHAR(255) NULL,
            website VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $createTableSql)) {
            $error = mysqli_error($conn);
            error_log("Error creating suppliers table: " . $error);
            sendJsonResponse(false, 'Database error: Failed to create suppliers table. ' . $error, ['sql_error' => $error], 500);
        }
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

    // Generate next primary key ID
    // Get the maximum ID from the suppliers table
    $maxIdQuery = "SELECT MAX(id) as max_id FROM suppliers";
    $maxIdResult = mysqli_query($conn, $maxIdQuery);
    
    if (!$maxIdResult) {
        error_log("Error getting max ID: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error while fetching next ID', null, 500);
    }
    
    $maxIdRow = mysqli_fetch_assoc($maxIdResult);
    $maxId = $maxIdRow['max_id'];
    
    // Calculate next ID
    // If table is empty or max_id is NULL, start at 1
    // Otherwise, increment by 1
    if ($maxId === null || $maxId === '') {
        $nextId = 1;
    } else {
        // Handle both numeric and formatted IDs (e.g., "SUP-0005" or "5")
        // Extract numeric portion if ID contains letters
        if (preg_match('/\d+/', $maxId, $matches)) {
            $numericId = (int)$matches[0];
        } else {
            $numericId = (int)$maxId;
        }
        $nextId = $numericId + 1;
    }
    
    // Ensure ID is never 0
    if ($nextId <= 0) {
        $nextId = 1;
    }
    
    error_log("Generated next supplier ID: " . $nextId . " (max was: " . ($maxId ?? 'NULL') . ")");

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
    
    // If username is provided, validate it
    if ($username !== null && $username !== '') {
        // Check if username already exists
        if ($hasUsername) {
            $checkUsernameQuery = "SELECT id FROM suppliers WHERE username = ?";
            $checkStmt = mysqli_prepare($conn, $checkUsernameQuery);
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "s", $username);
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
        } else if ($hasUsername) {
            // If username is set but no password, set a default password
            $password_hash = password_hash('supplier123', PASSWORD_DEFAULT);
        }
    }
    
    // Prepare SQL INSERT statement - explicitly include id column
    $fields = ['id', 'name', 'contact_person', 'phone', 'email', 'address'];
    $placeholders = ['?', '?', '?', '?', '?', '?'];
    $values = [$nextId, $name, $contact_person, $phone, $email, $address];
    $types = 'isssss'; // id (integer), then strings
    
    if ($hasWebsite) {
        $fields[] = 'website';
        $placeholders[] = '?';
        $values[] = $website;
        $types .= 's';
    }
    
    if ($hasNotes) {
        $fields[] = 'notes';
        $placeholders[] = '?';
        $values[] = $notes;
        $types .= 's';
    }
    
    if ($hasUsername && $username !== null && $username !== '') {
        $fields[] = 'username';
        $placeholders[] = '?';
        $values[] = $username;
        $types .= 's';
    }
    
    if ($hasPasswordHash && $password_hash !== null) {
        $fields[] = 'password_hash';
        $placeholders[] = '?';
        $values[] = $password_hash;
        $types .= 's';
    }
    
    if ($hasStatus) {
        $fields[] = 'status';
        $placeholders[] = '?';
        // Set all new suppliers to 'active' by default so they appear in orders
        $statusValue = 'active';
        $values[] = $statusValue;
        $types .= 's';
    }
    
    $sql = "INSERT INTO suppliers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

    // Prepare statement
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

    // Bind parameters dynamically
    $bound = mysqli_stmt_bind_param($stmt, $types, ...$values);
    
    // Verify binding was successful
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
        error_log("Attempted to insert with ID: " . $nextId);
        
        mysqli_stmt_close($stmt);
        
        // Check for PRIMARY KEY duplicate error
        if ((strpos($error, 'Duplicate') !== false && strpos($error, 'PRIMARY') !== false) || 
            (strpos($error, 'Duplicate') !== false && strpos($error, "'0'") !== false)) {
            // ID conflict - recalculate and retry
            error_log("Primary key conflict detected. Attempted ID: " . $nextId);
            
            // Get current max ID again (in case another process inserted a record)
            $retryMaxIdQuery = "SELECT MAX(id) as max_id FROM suppliers";
            $retryMaxIdResult = mysqli_query($conn, $retryMaxIdQuery);
            if ($retryMaxIdResult) {
                $retryMaxIdRow = mysqli_fetch_assoc($retryMaxIdResult);
                $retryMaxId = $retryMaxIdRow['max_id'];
                
                if ($retryMaxId === null || $retryMaxId === '') {
                    $retryNextId = 1;
                } else {
                    if (preg_match('/\d+/', $retryMaxId, $matches)) {
                        $retryNumericId = (int)$matches[0];
                    } else {
                        $retryNumericId = (int)$retryMaxId;
                    }
                    $retryNextId = $retryNumericId + 1;
                }
                
                if ($retryNextId <= 0) {
                    $retryNextId = 1;
                }
                
                error_log("Recalculated next ID: " . $retryNextId . " (previous was: " . $nextId . ")");
            }
            
            $errorMessage = 'Primary key conflict detected. The calculated ID (' . $nextId . ') already exists. Please try again.';
            sendJsonResponse(false, $errorMessage, [
                'error_code' => $errorCode, 
                'error' => $error,
                'attempted_id' => $nextId,
                'suggestion' => 'Please refresh and try adding the supplier again.'
            ], 500);
        }
        
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    // Use the explicitly set ID
    $insertedId = $nextId;
    mysqli_stmt_close($stmt);

    // Fetch the inserted supplier data to return
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
        sendJsonResponse(true, 'Supplier added successfully', [
            'id' => $insertedId,
            'name' => $name
        ], 200);
    }

    mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        sendJsonResponse(true, 'Supplier added successfully', [
            'id' => $insertedId,
            'name' => $name
        ], 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $supplier = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$supplier) {
        sendJsonResponse(true, 'Supplier added successfully', [
            'id' => $insertedId,
            'name' => $name
        ], 200);
    }

    // Success response
    sendJsonResponse(true, 'Supplier added successfully', $supplier, 200);

} catch (Exception $e) {
    error_log('Exception in add_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in add_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in add_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

?>
