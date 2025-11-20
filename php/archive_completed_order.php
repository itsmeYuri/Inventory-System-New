<?php
// Archive Completed Order API
// Archives a completed order to the archive table

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
    require_once __DIR__ . '/archive_helper.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get order ID
    $order_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($order_id <= 0) {
        sendJsonResponse(false, 'Invalid order ID', null, 400);
    }

    // Get optional parameters
    $archived_by = isset($_POST['archived_by']) ? trim($_POST['archived_by']) : null;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Order archived after completion';

    // Check if order exists and get status
    $checkSql = "SELECT id, status FROM orders WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        sendJsonResponse(false, 'Database error during order check', null, 500);
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $order_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        mysqli_stmt_close($checkStmt);
        sendJsonResponse(false, 'Order not found', null, 404);
    }

    $order = mysqli_fetch_assoc($checkResult);
    $order_status = strtolower(trim($order['status']));
    mysqli_stmt_close($checkStmt);

    // Only allow archiving completed orders
    if ($order_status !== 'completed') {
        sendJsonResponse(false, 'Only completed orders can be archived', null, 400);
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Archive the order using the archive helper function
        if (!archiveOrder($conn, $order_id, $archived_by, $reason)) {
            throw new Exception('Failed to archive order');
        }

        // Delete the order from the active orders table
        $deleteSql = "DELETE FROM orders WHERE id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new Exception('Database error during deletion: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($deleteStmt, 'i', $order_id);

        if (!mysqli_stmt_execute($deleteStmt)) {
            throw new Exception('Failed to delete order: ' . mysqli_stmt_error($deleteStmt));
        }

        $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if ($affectedRows === 0) {
            mysqli_rollback($conn);
            sendJsonResponse(false, 'No order was deleted', null, 404);
        }

        // Commit transaction
        mysqli_commit($conn);

        sendJsonResponse(true, 'Order archived successfully', [
            'id' => $order_id
        ], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in archive_completed_order.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in archive_completed_order.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

