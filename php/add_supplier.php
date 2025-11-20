<?php
// Add Supplier API
// Handles adding new suppliers to the database

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    require_once __DIR__ . '/conn.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
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
    
    // Validate required fields
    if (empty($name)) {
        sendJsonResponse(false, 'Supplier Name is required', null, 400);
    }
    
    // Validate email format if provided
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid email address format', null, 400);
    }

    // Prepare SQL INSERT statement
    $sql = "INSERT INTO suppliers (
        name, 
        contact_person, 
        phone, 
        email, 
        address
    ) VALUES (?, ?, ?, ?, ?)";

    // Prepare statement
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

    // Bind parameters
    $bound = mysqli_stmt_bind_param(
        $stmt, 
        'sssss',  // 5 string parameters
        $name, 
        $contact_person, 
        $phone, 
        $email, 
        $address
    );
    
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
        
        mysqli_stmt_close($stmt);
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    // Get the inserted ID
    $insertedId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$insertedId) {
        sendJsonResponse(false, 'Failed to get inserted supplier ID', null, 500);
    }

    // Fetch the inserted supplier data to return
    $selectSql = "SELECT 
        id, 
        name, 
        contact_person, 
        phone, 
        email, 
        address,
        created_at,
        updated_at
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
