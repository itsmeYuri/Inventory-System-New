<?php
// Get Supplier Medicines API
// Returns medicines linked to a supplier via supplier_medicines junction table

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
    
    if ($supplier_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid supplier ID'
        ]);
        exit;
    }

    // Check if supplier_medicines table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_medicines'");
    $hasTable = mysqli_num_rows($checkTable) > 0;
    
    if (!$hasTable) {
        echo json_encode([
            'success' => false,
            'message' => 'supplier_medicines table does not exist. Please run create_supplier_medicines_table.php first.',
            'data' => []
        ]);
        exit;
    }

    // Fetch medicines for this supplier using junction table
    $sql = "SELECT 
                m.id, 
                m.ndc, 
                m.name, 
                m.manufacturer, 
                m.category, 
                m.quantity, 
                m.reorder_level,
                m.price, 
                m.expiration_date, 
                m.batch_number, 
                m.status, 
                m.dosage_form,
                sm.created_at as linked_at
            FROM medicines m
            INNER JOIN supplier_medicines sm ON m.id = sm.medicine_id
            WHERE sm.supplier_id = ?
            ORDER BY m.category ASC, m.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Database preparation error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'supplier_id' => $supplier_id,
        'count' => count($data),
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_supplier_medicines.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

