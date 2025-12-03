<?php
// Turn off error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

// Enhanced CORS headers - allow multiple origins for development
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
    // If no origin header, allow localhost
    header('Access-Control-Allow-Origin: http://localhost');
}

header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/archive_helper.php';
require_once __DIR__ . '/medicine_structure_helper.php';
require_once __DIR__ . '/pos_sync_helper.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        sendJsonResponse(false, 'Invalid request method. Only POST or DELETE is allowed.', null, 405);
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Check which structure we're using
    $hasNewStructure = hasNewMedicineStructure($conn);

    // Get medicine ID
    $medicine_id = isset($_POST['id']) ? trim($_POST['id']) : '';
    if (empty($medicine_id)) {
        sendJsonResponse(false, 'Invalid medicine ID', null, 400);
    }

    // First, check if medicine exists
    if ($hasNewStructure) {
        $checkSql = "SELECT medicine_id, medicine_name as name FROM medicines WHERE medicine_id = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        if (!$checkStmt) {
            error_log("Check statement prepare error: " . mysqli_error($conn));
            sendJsonResponse(false, 'Database error', null, 500);
        }
        mysqli_stmt_bind_param($checkStmt, 's', $medicine_id);
    } else {
    $checkSql = "SELECT id, name FROM medicines WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        error_log("Check statement prepare error: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error', null, 500);
        }
        $medicine_id_int = (int)$medicine_id;
        mysqli_stmt_bind_param($checkStmt, 'i', $medicine_id_int);
    }
    
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        mysqli_stmt_close($checkStmt);
        sendJsonResponse(false, 'Medicine not found', null, 404);
    }
    
    $medicine = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    // Archive the medicine before deleting
    $deleted_by = isset($_POST['deleted_by']) ? $_POST['deleted_by'] : null;
    $reason = isset($_POST['reason']) ? $_POST['reason'] : null;
    
    if (!archiveMedicine($conn, $medicine_id, $deleted_by, $reason)) {
        error_log("Warning: Failed to archive medicine before deletion");
        // Continue with deletion even if archiving fails
    }

    // Delete the medicine
    if ($hasNewStructure) {
        $sql = "DELETE FROM medicines WHERE medicine_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $error = mysqli_error($conn);
            error_log("MySQL prepare error: " . $error);
            sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
        }
        mysqli_stmt_bind_param($stmt, 's', $medicine_id);
    } else {
    $sql = "DELETE FROM medicines WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }
        $medicine_id_int = (int)$medicine_id;
        mysqli_stmt_bind_param($stmt, 'i', $medicine_id_int);
    }

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
        sendJsonResponse(false, 'No medicine was deleted', null, 404);
    }

    // Delete from POS system (non-blocking)
    try {
        $posDeleteResult = deleteMedicineFromPOS($medicine_id);
        if ($posDeleteResult['success']) {
            error_log("Medicine successfully deleted from POS system: " . $medicine_id);
        } else {
            error_log("POS delete failed for medicine ID " . $medicine_id . ": " . $posDeleteResult['message']);
        }
    } catch (Exception $e) {
        error_log("POS delete exception for medicine ID " . $medicine_id . ": " . $e->getMessage());
    }

    // Success response
    sendJsonResponse(true, 'Medicine deleted successfully', ['id' => $medicine_id, 'name' => $medicine['name']], 200);

} catch (Exception $e) {
    error_log('Exception in delete_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in delete_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in delete_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

ob_end_flush();
sendJsonResponse(false, 'Unexpected error occurred', null, 500);
?>

