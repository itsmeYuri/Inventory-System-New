<?php
// Delete Supplier API
// Handles deleting suppliers from the database

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

header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
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

    // Get supplier ID from POST or DELETE request
    $supplier_id = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $supplier_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // For DELETE requests, ID might be in URL or request body
        parse_str(file_get_contents('php://input'), $deleteData);
        $supplier_id = isset($deleteData['id']) ? (int)$deleteData['id'] : 0;
    }

    if ($supplier_id <= 0) {
        sendJsonResponse(false, 'Invalid supplier ID', null, 400);
    }

    // Check if supplier exists
    $checkSql = "SELECT id, name FROM suppliers WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        error_log("Check prepare error: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error during supplier check', null, 500);
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $supplier_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        mysqli_stmt_close($checkStmt);
        sendJsonResponse(false, 'Supplier not found', null, 404);
    }

    $supplier = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    // Delete supplier
    $deleteSql = "DELETE FROM suppliers WHERE id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if (!$deleteStmt) {
        error_log("Delete prepare error: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error during deletion', null, 500);
    }

    mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);

    if (!mysqli_stmt_execute($deleteStmt)) {
        $error = mysqli_stmt_error($deleteStmt);
        $errorCode = mysqli_stmt_errno($deleteStmt);
        error_log("Delete execute error [$errorCode]: " . $error);
        mysqli_stmt_close($deleteStmt);
        sendJsonResponse(false, 'Failed to delete supplier: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($affectedRows === 0) {
        sendJsonResponse(false, 'No supplier was deleted', null, 404);
    }

    // Success response
    sendJsonResponse(true, "Supplier '{$supplier['name']}' deleted successfully", [
        'id' => $supplier_id,
        'name' => $supplier['name']
    ], 200);

} catch (Exception $e) {
    error_log('Exception in delete_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in delete_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in delete_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

?>
