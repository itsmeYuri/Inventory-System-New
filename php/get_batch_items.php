<?php
// Get Batch Items API
// Returns all items for a specific batch

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
    $batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
    
    if ($batch_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid batch ID'
        ]);
        exit;
    }

    // Check if batch_items table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    if (mysqli_num_rows($checkTable) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'batch_items table does not exist. Please run create_batches_tables.php first.',
            'data' => []
        ]);
        exit;
    }

    // Fetch batch items with medicine details
    $sql = "SELECT 
                bi.id,
                bi.batch_id,
                bi.medicine_id,
                bi.quantity,
                bi.expiration_date,
                bi.received_quantity,
                bi.is_expired,
                bi.expired_at,
                bi.created_at,
                bi.updated_at,
                m.ndc,
                m.name as medicine_name,
                m.manufacturer,
                m.category,
                m.dosage_form,
                m.price,
                m.quantity as current_stock
            FROM batch_items bi
            LEFT JOIN medicines m ON bi.medicine_id = m.id
            WHERE bi.batch_id = ?
            ORDER BY bi.expiration_date ASC, bi.id ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Database preparation error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $batch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate days until expiration
        $daysUntilExpiry = null;
        if ($row['expiration_date']) {
            $expDate = new DateTime($row['expiration_date']);
            $today = new DateTime();
            $diff = $today->diff($expDate);
            $daysUntilExpiry = $expDate < $today ? -$diff->days : $diff->days;
        }
        $row['days_until_expiry'] = $daysUntilExpiry;
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'batch_id' => $batch_id,
        'count' => count($data),
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_batch_items.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

