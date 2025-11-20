<?php
// Link Medicines to Supplier API
// Links existing medicines to a supplier by updating their supplier_id

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

    // Get supplier ID
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    if ($supplier_id <= 0) {
        sendJsonResponse(false, 'Invalid supplier ID', null, 400);
    }

    // Get medicine IDs (array)
    $medicine_ids_json = isset($_POST['medicine_ids']) ? $_POST['medicine_ids'] : '[]';
    if (is_string($medicine_ids_json)) {
        $medicine_ids = json_decode($medicine_ids_json, true);
    } else {
        $medicine_ids = $medicine_ids_json;
    }
    
    if (!is_array($medicine_ids)) {
        $medicine_ids = [];
    }

    // Check if supplier_medicines table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_medicines'");
    $hasTable = mysqli_num_rows($checkTable) > 0;
    
    if (!$hasTable) {
        sendJsonResponse(false, 'supplier_medicines table does not exist. Please run create_supplier_medicines_table.php first.', null, 400);
    }

    // First, remove all existing links for this supplier
    $deleteSql = "DELETE FROM supplier_medicines WHERE supplier_id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }

    // Insert new links using junction table
    $inserted = 0;
    $errors = [];
    
    if (count($medicine_ids) > 0) {
        $insertSql = "INSERT INTO supplier_medicines (supplier_id, medicine_id) VALUES (?, ?)";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        
        if (!$insertStmt) {
            sendJsonResponse(false, 'Database preparation error: ' . mysqli_error($conn), null, 500);
        }
        
        foreach ($medicine_ids as $medicine_id) {
            $medicine_id = (int)$medicine_id;
            if ($medicine_id <= 0) continue;
            
            mysqli_stmt_bind_param($insertStmt, 'ii', $supplier_id, $medicine_id);
            if (mysqli_stmt_execute($insertStmt)) {
                $inserted++;
            } else {
                // Ignore duplicate key errors (UNIQUE constraint)
                $errorCode = mysqli_stmt_errno($insertStmt);
                if ($errorCode !== 1062) { // 1062 is duplicate key error
                    $errors[] = "Failed to link medicine ID {$medicine_id}: " . mysqli_stmt_error($insertStmt);
                }
            }
        }
        mysqli_stmt_close($insertStmt);
    }

    sendJsonResponse(true, "Successfully linked {$inserted} medicine(s) to supplier", [
        'supplier_id' => $supplier_id,
        'linked_count' => $inserted,
        'errors' => $errors
    ], 200);

} catch (Exception $e) {
    error_log('Exception in link_medicines_to_supplier.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in link_medicines_to_supplier.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

