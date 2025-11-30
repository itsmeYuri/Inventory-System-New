<?php
/**
 * Mark Notifications as Read API
 * Marks all notifications for a supplier as read
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

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

try {
    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

    if ($supplier_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid supplier ID'
        ]);
        exit;
    }

    // Check if table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_notifications'");
    if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notifications table does not exist'
        ]);
        exit;
    }

    // Mark all notifications as read
    $sql = "UPDATE supplier_notifications SET read_status = 1 WHERE supplier_id = $supplier_id AND read_status = 0";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $affected = mysqli_affected_rows($conn);
        echo json_encode([
            'success' => true,
            'message' => "Marked $affected notification(s) as read"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notifications: ' . mysqli_error($conn)
        ]);
    }

} catch (Exception $e) {
    error_log("Error in mark_notifications_read.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

