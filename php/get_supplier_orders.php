<?php
/**
 * Get Supplier Orders API
 * Returns orders for a specific supplier with pagination
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
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 25;
    $offset = ($page - 1) * $pageSize;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    if ($supplier_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid supplier ID',
            'debug' => ['supplier_id' => $supplier_id]
        ]);
        exit;
    }
    
    error_log("get_supplier_orders.php: Looking for orders with supplier_id = $supplier_id");

    // Orders are created with supplier_id from suppliers table
    // We need to find the correct suppliers.id to match orders
    
    // First, check if supplier_id exists in suppliers table (most common case)
    $checkSupplier = mysqli_query($conn, "SELECT id FROM suppliers WHERE id = $supplier_id");
    $supplierExists = $checkSupplier && mysqli_num_rows($checkSupplier) > 0;
    
    $supplierIds = [];
    
    if ($supplierExists) {
        // Direct match - supplier_id is from suppliers table
        $supplierIds[] = $supplier_id;
        error_log("get_supplier_orders.php: Found supplier in suppliers table with id = $supplier_id");
    } else {
        // Supplier ID not found in suppliers table, might be a user ID
        // Check if there's a user with role='supplier' that matches
        $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
        $hasRole = $checkRole && mysqli_num_rows($checkRole) > 0;
        
        if ($hasRole) {
            // Check if user_id column exists
            $checkUserId = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_id'");
            $hasUserId = $checkUserId && mysqli_num_rows($checkUserId) > 0;
            $userIdColumn = $hasUserId ? 'user_id' : 'id';
            
            // Find user with this ID and role='supplier'
            $userQuery = "SELECT {$userIdColumn}, email, full_name FROM users WHERE {$userIdColumn} = $supplier_id AND role = 'supplier'";
            $userResult = mysqli_query($conn, $userQuery);
            
            if ($userResult && mysqli_num_rows($userResult) > 0) {
                $userRow = mysqli_fetch_assoc($userResult);
                $userEmail = $userRow['email'] ?? '';
                $userName = $userRow['full_name'] ?? '';
                
                error_log("get_supplier_orders.php: Found user with role='supplier', email=$userEmail, name=$userName");
                
                // Find matching supplier by email (most reliable)
                if (!empty($userEmail)) {
                    $matchQuery = "SELECT id FROM suppliers WHERE email = '" . mysqli_real_escape_string($conn, $userEmail) . "'";
                    $matchResult = mysqli_query($conn, $matchQuery);
                    if ($matchResult && mysqli_num_rows($matchResult) > 0) {
                        $matchRow = mysqli_fetch_assoc($matchResult);
                        $supplierIds[] = (int)$matchRow['id'];
                        error_log("get_supplier_orders.php: Matched supplier by email, suppliers.id = " . $matchRow['id']);
                    }
                }
                
                // Also try matching by name if email didn't match
                if (empty($supplierIds) && !empty($userName)) {
                    $matchQuery = "SELECT id FROM suppliers WHERE name = '" . mysqli_real_escape_string($conn, $userName) . "'";
                    $matchResult = mysqli_query($conn, $matchQuery);
                    if ($matchResult && mysqli_num_rows($matchResult) > 0) {
                        $matchRow = mysqli_fetch_assoc($matchResult);
                        $supplierIds[] = (int)$matchRow['id'];
                        error_log("get_supplier_orders.php: Matched supplier by name, suppliers.id = " . $matchRow['id']);
                    }
                }
                
                // If still no match, check if there are any orders with this user_id as supplier_id
                // (in case orders were created with user_id instead of suppliers.id)
                if (empty($supplierIds)) {
                    $checkOrdersWithUserId = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE supplier_id = $supplier_id");
                    if ($checkOrdersWithUserId) {
                        $orderCount = mysqli_fetch_assoc($checkOrdersWithUserId);
                        if ($orderCount['cnt'] > 0) {
                            // There are orders with this user_id, include it in the search
                            $supplierIds[] = $supplier_id;
                            error_log("get_supplier_orders.php: Found orders with user_id as supplier_id, including it in search");
                        }
                    }
                }
            }
        }
    }
    
    // Build where clause - check all possible supplier IDs
    $supplierIds = array_unique(array_filter($supplierIds, function($id) { return $id > 0; }));
    if (empty($supplierIds)) {
        error_log("get_supplier_orders.php: No matching supplier found for supplier_id = $supplier_id");
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found. Please contact administrator.',
            'debug' => ['supplier_id' => $supplier_id, 'supplier_exists' => $supplierExists]
        ]);
        exit;
    }
    
    $supplierIdsStr = implode(',', array_map('intval', $supplierIds));
    error_log("get_supplier_orders.php: Checking supplier IDs: " . $supplierIdsStr);
    
    $where = "WHERE o.supplier_id IN ($supplierIdsStr)";
    if (!empty($status)) {
        $statusEscaped = mysqli_real_escape_string($conn, $status);
        $where .= " AND o.status = '$statusEscaped'";
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as cnt FROM orders o $where";
    error_log("get_supplier_orders.php: Count query: " . $countQuery);
    $countResult = mysqli_query($conn, $countQuery);
    $total = 0;
    if ($countResult) {
        $row = mysqli_fetch_assoc($countResult);
        $total = (int)$row['cnt'];
        error_log("get_supplier_orders.php: Found $total orders");
    } else {
        error_log("get_supplier_orders.php: Count query failed: " . mysqli_error($conn));
    }

    // Check if total_amount column exists
    $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
    $hasTotalAmount = $checkTotalAmount && mysqli_num_rows($checkTotalAmount) > 0;

    // Check if date column exists (some tables might have order_date, some might have date)
    $checkDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'date'");
    $hasDateColumn = $checkDateColumn && mysqli_num_rows($checkDateColumn) > 0;
    
    $checkOrderDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'order_date'");
    $hasOrderDateColumn = $checkOrderDateColumn && mysqli_num_rows($checkOrderDateColumn) > 0;
    
    // Build SELECT fields - use order_date if available, otherwise date
    if ($hasOrderDateColumn) {
        $selectFields = "o.id, o.order_date, o.status";
    } elseif ($hasDateColumn) {
        $selectFields = "o.id, o.date as order_date, o.status";
    } else {
        // Fallback if neither exists
        $selectFields = "o.id, o.status";
    }
    
    if ($hasTotalAmount) {
        $selectFields .= ", o.total_amount";
    } else {
        $selectFields .= ", COALESCE((SELECT SUM(quantity * price) FROM order_items WHERE order_id = o.id), 0) as total_amount";
    }

    // Get item count
    $selectFields .= ", (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count";

    // Build ORDER BY clause - use order_date if available, otherwise date, otherwise just id
    $orderBy = "o.id DESC";
    if ($hasOrderDateColumn) {
        $orderBy = "o.order_date DESC, o.id DESC";
    } elseif ($hasDateColumn) {
        $orderBy = "o.date DESC, o.id DESC";
    }
    
    // Fetch orders
    $sql = "SELECT $selectFields
            FROM orders o
            $where
            ORDER BY $orderBy
            LIMIT $offset, $pageSize";

    error_log("get_supplier_orders.php: Fetch query: " . $sql);
    $result = mysqli_query($conn, $sql);
    $orders = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Get order_date from the result (it's aliased as order_date in the query)
            $orderDate = $row['order_date'] ?? null;
            
            $orders[] = [
                'id' => (int)$row['id'],
                'order_date' => $orderDate,
                'date' => $orderDate, // For compatibility
                'status' => $row['status'] ?? 'pending',
                'total_amount' => (float)($row['total_amount'] ?? 0),
                'item_count' => (int)($row['item_count'] ?? 0)
            ];
        }
        error_log("get_supplier_orders.php: Fetched " . count($orders) . " orders");
    } else {
        $errorMsg = mysqli_error($conn);
        error_log("get_supplier_orders.php: Fetch query failed: " . $errorMsg);
        throw new Exception("Database query failed: " . $errorMsg);
    }

    // Always return success with data array, even if empty
    $response = [
        'success' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'data' => $orders
    ];
    
    // Include debug info to help troubleshoot
    if (empty($orders) && $total === 0) {
        error_log("get_supplier_orders.php: No orders found for supplier IDs: " . $supplierIdsStr);
        
        // Check what supplier_ids actually exist in orders table
        $checkAllOrders = mysqli_query($conn, "SELECT DISTINCT supplier_id, COUNT(*) as cnt FROM orders GROUP BY supplier_id LIMIT 10");
        $existingSupplierIds = [];
        if ($checkAllOrders) {
            while ($row = mysqli_fetch_assoc($checkAllOrders)) {
                $existingSupplierIds[] = $row['supplier_id'] . ' (' . $row['cnt'] . ' orders)';
            }
        }
        
        $response['debug'] = [
            'requested_supplier_id' => $supplier_id,
            'matched_supplier_ids' => $supplierIds,
            'supplier_ids_in_orders' => $existingSupplierIds,
            'query_used' => $sql
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_supplier_orders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

