<?php
/**
 * Process and Archive Expired Items API
 * Automatically processes expired batch items and archives them
 * This can be called from the frontend to ensure expired items are archived
 */

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

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/order_batch_helper.php';
require_once __DIR__ . '/archive_helper.php';

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
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Process expired batch items (marks them as expired and decrements inventory)
    $stats = processExpiredBatchItems($conn);

    // Archive expired items that haven't been archived yet
    $archived = archiveExpiredItems($conn);
    
    if ($archived === false) {
        error_log("Error archiving expired items");
        $archived = 0;
    }

    // Add archived count to stats
    $stats['archived'] = $archived;

    sendJsonResponse(true, 'Expired items processed and archived successfully', [
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ], 200);

} catch (Exception $e) {
    error_log('Exception in process_and_archive_expired.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in process_and_archive_expired.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

