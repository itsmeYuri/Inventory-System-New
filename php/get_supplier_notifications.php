<?php
/**
 * Get Supplier Notifications API
 * Returns notifications for a specific supplier
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

header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

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
        // Return empty array if table doesn't exist
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }

    // Build where clause
    $where = "WHERE supplier_id = $supplier_id";
    if ($unread_only) {
        $where .= " AND read_status = 0";
    }

    // Fetch notifications
    $sql = "SELECT id, title, message, type, read_status, created_at
            FROM supplier_notifications
            $where
            ORDER BY created_at DESC
            LIMIT $limit";

    $result = mysqli_query($conn, $sql);
    $notifications = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'] ?? 'system',
                'read' => (bool)$row['read_status'],
                'created_at' => $row['created_at']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $notifications
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_supplier_notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

