<?php
// Get Order Items API
// Returns all items for a specific order

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
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    if ($order_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID'
        ]);
        exit;
    }

    // Fetch order items with medicine details
    $sql = "SELECT 
                oi.id,
                oi.order_id,
                oi.medicine_id,
                oi.quantity,
                oi.price,
                oi.created_at,
                oi.updated_at,
                m.ndc,
                m.name as medicine_name,
                m.batch_number,
                m.expiration_date
            FROM order_items oi
            LEFT JOIN medicines m ON oi.medicine_id = m.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Database preparation error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'count' => count($data),
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_order_items.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

