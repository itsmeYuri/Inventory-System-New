<?php
// Get Batches API
// Returns batches with pagination, search, and filtering

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
    // Check if batches table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
    if (mysqli_num_rows($checkTable) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Batches table does not exist. Please run create_batches_tables.php first.',
            'data' => []
        ]);
        exit;
    }

    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, (int)$_GET['pageSize'])) : 25;
    $offset = ($page - 1) * $pageSize;

    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $whereConditions[] = "(b.batch_number LIKE ? OR s.name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    if (!empty($status)) {
        $whereConditions[] = "b.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($supplier_id > 0) {
        $whereConditions[] = "b.supplier_id = ?";
        $params[] = $supplier_id;
        $types .= 'i';
    }

    $where = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM batches b
                 LEFT JOIN suppliers s ON b.supplier_id = s.id
                 {$where}";
    
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt && !empty($types)) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    
    if ($countStmt) {
        if (!empty($types)) {
            mysqli_stmt_execute($countStmt);
        } else {
            mysqli_stmt_execute($countStmt);
        }
        $countResult = mysqli_stmt_get_result($countStmt);
        $totalRow = mysqli_fetch_assoc($countResult);
        $total = (int)($totalRow['total'] ?? 0);
        mysqli_stmt_close($countStmt);
    } else {
        $total = 0;
    }

    // Fetch batches with supplier and order info
    // Show all batches, even if they have no items yet (item_count will be 0)
    $sql = "SELECT 
                b.id,
                b.batch_number,
                b.order_id,
                b.supplier_id,
                COALESCE(s.name, 'Unknown Supplier') as supplier_name,
                b.created_date,
                b.status,
                b.notes,
                b.created_at,
                b.updated_at,
                o.order_date,
                o.status as order_status,
                COALESCE((SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id), 0) as item_count,
                COALESCE((SELECT SUM(bi.received_quantity) FROM batch_items bi WHERE bi.batch_id = b.id), 0) as total_quantity,
                COALESCE((SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id AND bi.is_expired = 1), 0) as expired_count
            FROM batches b
            LEFT JOIN suppliers s ON b.supplier_id = s.id
            LEFT JOIN orders o ON b.order_id = o.id
            {$where}
            ORDER BY b.created_date DESC, b.id DESC
            LIMIT {$offset}, {$pageSize}";

    // Debug: Log the SQL query
    error_log("get_batches.php - SQL: " . $sql);
    error_log("get_batches.php - WHERE: " . $where);
    error_log("get_batches.php - Types: " . $types);
    error_log("get_batches.php - Params: " . print_r($params, true));
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("get_batches.php - Prepare error: " . $error);
        throw new Exception("Failed to prepare statement: " . $error);
    }
    
    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log("get_batches.php - Execute error: " . $error);
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to execute statement: " . $error);
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        $error = mysqli_error($conn);
        error_log("get_batches.php - Get result error: " . $error);
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to get result: " . $error);
    }
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    error_log("get_batches.php - Found " . count($data) . " batches");

    echo json_encode([
        'success' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_batches.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

