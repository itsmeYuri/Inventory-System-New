<?php
/**
 * Get Next Medicine ID
 * Returns the next available primary key ID for a new medicine entry
 */

// Turn off error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

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

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/medicine_structure_helper.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse(false, 'Invalid request method. Only GET is allowed.', null, 405);
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Check which structure we're using
    $hasNewStructure = hasNewMedicineStructure($conn);
    
    if ($hasNewStructure) {
        // New structure: medicine_id is VARCHAR(50)
        $maxIdQuery = "SELECT MAX(CAST(medicine_id AS UNSIGNED)) as max_id FROM medicines WHERE medicine_id REGEXP '^[0-9]+$'";
        $maxIdResult = mysqli_query($conn, $maxIdQuery);
        
        if (!$maxIdResult) {
            error_log("Error getting max ID: " . mysqli_error($conn));
            sendJsonResponse(false, 'Database error while fetching next ID', null, 500);
        }
        
        $maxIdRow = mysqli_fetch_assoc($maxIdResult);
        $maxId = (int)($maxIdRow['max_id'] ?? 0);
        
        // Calculate next ID (max ID + 1)
        $nextId = $maxId + 1;
        if ($nextId <= 0) {
            $nextId = 1;
        }
        
        // Format the ID for display (MED-XXXX format)
        $formattedId = 'MED-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        
        sendJsonResponse(true, 'Next ID retrieved successfully', [
            'next_id' => (string)$nextId,
            'formatted_id' => $formattedId,
            'max_id' => $maxId
        ], 200);
    } else {
        // Old structure: id is INT
    $maxIdQuery = "SELECT MAX(id) as max_id FROM medicines";
    $maxIdResult = mysqli_query($conn, $maxIdQuery);
    
    if (!$maxIdResult) {
        error_log("Error getting max ID: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error while fetching next ID', null, 500);
    }
    
    $maxIdRow = mysqli_fetch_assoc($maxIdResult);
    $maxId = (int)($maxIdRow['max_id'] ?? 0);
    
    // Calculate next ID (max ID + 1)
    $nextId = $maxId + 1;
    
    // Also check the AUTO_INCREMENT value from table status
    $autoIncrementQuery = "SHOW TABLE STATUS LIKE 'medicines'";
    $autoIncrementResult = mysqli_query($conn, $autoIncrementQuery);
    $tableStatus = mysqli_fetch_assoc($autoIncrementResult);
    $autoIncrementValue = (int)($tableStatus['Auto_increment'] ?? 0);
    
    // Use the higher value between max_id+1 and auto_increment
    $finalNextId = max($nextId, $autoIncrementValue);
    
    // Format the ID for display (MED-XXXX format)
    $formattedId = 'MED-' . str_pad($finalNextId, 4, '0', STR_PAD_LEFT);
    
    sendJsonResponse(true, 'Next ID retrieved successfully', [
        'next_id' => $finalNextId,
        'formatted_id' => $formattedId,
        'max_id' => $maxId,
        'auto_increment' => $autoIncrementValue
    ], 200);
    }

} catch (Exception $e) {
    error_log('Exception in get_next_medicine_id.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage()], 500);
} catch (Error $e) {
    error_log('Fatal error in get_next_medicine_id.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
}

// This should never be reached, but just in case
ob_end_flush();
sendJsonResponse(false, 'Unexpected error occurred', null, 500);
?>

