<?php
// Get Orders API
// Returns paginated list of orders with filters

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
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 25;
    $offset = ($page - 1) * $pageSize;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $dateFrom = isset($_GET['dateFrom']) ? trim($_GET['dateFrom']) : '';
    $dateTo = isset($_GET['dateTo']) ? trim($_GET['dateTo']) : '';

    // Build where clause safely
    $where = " WHERE 1=1 ";

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (o.id LIKE '%{$s}%' OR s.name LIKE '%{$s}%') ";
    }

    if ($status !== '') {
        $st = mysqli_real_escape_string($conn, $status);
        $where .= " AND o.status = '{$st}' ";
    }

    if ($supplier_id > 0) {
        $where .= " AND o.supplier_id = {$supplier_id} ";
    }

    if ($dateFrom !== '') {
        $df = mysqli_real_escape_string($conn, $dateFrom);
        $where .= " AND o.order_date >= '{$df}' ";
    }

    if ($dateTo !== '') {
        $dt = mysqli_real_escape_string($conn, $dateTo);
        $where .= " AND o.order_date <= '{$dt}' ";
    }

    // Get total count (need to join suppliers if search is used)
    if ($search !== '') {
        $countSql = "SELECT COUNT(*) AS cnt FROM orders o LEFT JOIN suppliers s ON o.supplier_id = s.id" . $where;
    } else {
        $countSql = "SELECT COUNT(*) AS cnt FROM orders o" . $where;
    }
    $countRes = mysqli_query($conn, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int)$row['cnt'];
    }

    // Check if columns exist
    $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
    $hasTotalAmount = mysqli_num_rows($checkTotalAmount) > 0;
    $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
    $hasNotes = mysqli_num_rows($checkNotes) > 0;
    
    // Build SELECT based on what columns exist
    $selectFields = "o.id, o.supplier_id, s.name as supplier_name, o.order_date, o.status";
    
    if ($hasTotalAmount) {
        $selectFields .= ", o.total_amount";
    } else {
        $selectFields .= ", COALESCE(SUM(oi.quantity * oi.price), 0) as total_amount";
    }
    
    if ($hasNotes) {
        $selectFields .= ", o.notes";
    } else {
        $selectFields .= ", NULL as notes";
    }
    
    $selectFields .= ", o.created_at, o.updated_at, (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id) as item_count";
    
    // Fetch page with supplier info
    if ($hasTotalAmount && $hasNotes) {
        $sql = "SELECT {$selectFields}
                FROM orders o
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                {$where}
                ORDER BY o.order_date DESC, o.id DESC
                LIMIT {$offset}, {$pageSize}";
    } else {
        // Need GROUP BY if calculating total_amount
        $sql = "SELECT {$selectFields}
                FROM orders o
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                {$where}
                GROUP BY o.id, o.supplier_id, s.name, o.order_date, o.status, o.created_at, o.updated_at" . ($hasNotes ? ", o.notes" : "") . "
                ORDER BY o.order_date DESC, o.id DESC
                LIMIT {$offset}, {$pageSize}";
    }

    $res = mysqli_query($conn, $sql);

    $data = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $data[] = $r;
        }
    }

    echo json_encode([
        'success' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_orders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

