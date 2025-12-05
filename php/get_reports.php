<?php
// Reports & Analytics API
// Provides data for reports dashboard including metrics, charts, and analytics

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
    $action = $_GET['action'] ?? 'dashboard';
    $dateRange = $_GET['dateRange'] ?? 'monthly';
    $category = $_GET['category'] ?? '';

    switch ($action) {
        case 'dashboard':
            getDashboardData($conn, $dateRange, $category);
            break;
        case 'top-selling':
            getTopSellingMedicines($conn, $dateRange, $category);
            break;
        case 'low-stock':
            getLowStockMedicines($conn);
            break;
        case 'stock-availability':
            getStockAvailability($conn);
            break;
        case 'category-distribution':
            getCategoryDistribution($conn);
            break;
        case 'seasonal-trends':
            getSeasonalTrends($conn, $dateRange);
            break;
        case 'top-medicines':
            getTopMedicines($conn, $dateRange, $category);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error in get_reports.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getDashboardData($conn, $dateRange, $category) {
    $categoryFilter = '';
    if (!empty($category)) {
        $cat = mysqli_real_escape_string($conn, $category);
        $categoryFilter = " AND COALESCE(medicine_group, category) = '{$cat}'";
    }

    $medicineDateFilter = getDateFilterForColumn($dateRange, 'created_at', $conn);
    $orderDateFilter = getDateFilterForColumn($dateRange, 'order_date', $conn);

    $stockedSql = "SELECT COALESCE(SUM(stock), 0) AS total_stocked
                   FROM medicines
                   WHERE status != 'deleted' {$categoryFilter} AND {$medicineDateFilter}";
    $stockedResult = mysqli_query($conn, $stockedSql);
    $totalStocked = 0;
    if ($stockedResult) {
        $row = mysqli_fetch_assoc($stockedResult);
        $totalStocked = (int)($row['total_stocked'] ?? 0);
    }

    $turnoverSql = "SELECT COALESCE(SUM(stock), 0) AS total_quantity
                    FROM medicines
                    WHERE status IN ('in-stock', 'low-stock') {$categoryFilter} AND {$medicineDateFilter}";
    $turnoverResult = mysqli_query($conn, $turnoverSql);
    $turnover = 0;
    if ($turnoverResult) {
        $row = mysqli_fetch_assoc($turnoverResult);
        $turnover = (int)($row['total_quantity'] ?? 0);
    }

    $completedOrdersSql = "SELECT COUNT(*) AS completed_count
                           FROM orders
                           WHERE status = 'completed' AND {$orderDateFilter}";
    $completedOrdersResult = mysqli_query($conn, $completedOrdersSql);
    $completedOrders = 0;
    if ($completedOrdersResult) {
        $row = mysqli_fetch_assoc($completedOrdersResult);
        $completedOrders = (int)($row['completed_count'] ?? 0);
    }

    $topSellingSql = "SELECT COUNT(*) AS count
                      FROM medicines
                      WHERE stock > 0 {$categoryFilter} AND {$medicineDateFilter}";
    $topSellingResult = mysqli_query($conn, $topSellingSql);
    $topSellingCount = 0;
    if ($topSellingResult) {
        $row = mysqli_fetch_assoc($topSellingResult);
        $topSellingCount = (int)($row['count'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalStocked' => $totalStocked,
            'medicineTurnover' => $turnover,
            'completedOrders' => $completedOrders,
            'topSellingItems' => $topSellingCount
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getLowStockMedicines($conn) {
    $sql = "SELECT 
                COALESCE(medicine_id, id) AS id,
                COALESCE(medicine_name, name) AS name,
                COALESCE(stock, quantity) AS quantity,
                COALESCE(reorder_level, 10) AS reorder_level,
                status
            FROM medicines 
            WHERE COALESCE(stock, quantity) <= COALESCE(reorder_level, 10)
               OR status IN ('low-stock', 'out-of-stock')
            ORDER BY COALESCE(stock, quantity) ASC, COALESCE(medicine_name, name) ASC
            LIMIT 50";

    $result = mysqli_query($conn, $sql);
    $medicines = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'reorderLevel' => (int)$row['reorder_level'],
                'status' => $row['status'] ?? 'in-stock'
            ];
        }
    } else {
        error_log("SQL Error in getLowStockMedicines: " . mysqli_error($conn));
        echo json_encode([
            'success' => false,
            'message' => 'Database query failed: ' . mysqli_error($conn),
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getStockAvailability($conn) {
    // Dynamically get all categories from the database
    $categorySql = "SELECT DISTINCT COALESCE(medicine_group, category) as category
                    FROM medicines 
                    WHERE COALESCE(medicine_group, category) IS NOT NULL 
                      AND COALESCE(medicine_group, category) != '' 
                    ORDER BY category ASC";
    
    $categoryResult = mysqli_query($conn, $categorySql);
    $categories = [];
    
    if ($categoryResult) {
        while ($row = mysqli_fetch_assoc($categoryResult)) {
            if (!empty($row['category'])) {
                $categories[] = $row['category'];
            }
        }
    }
    
    // If no categories found, return empty data
    if (empty($categories)) {
        echo json_encode([
            'success' => true,
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $stockData = [];

    foreach ($categories as $category) {
        $cat = mysqli_real_escape_string($conn, $category);
        
        // Get total quantity and count for this category
        $sql = "SELECT 
                    SUM(stock) as total_quantity,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_items
                FROM medicines 
                WHERE COALESCE(medicine_group, category) = '{$cat}'";
        
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $totalQuantity = (int)($row['total_quantity'] ?? 0);
            $totalItems = (int)($row['total_items'] ?? 0);
            $inStockItems = (int)($row['in_stock_items'] ?? 0);
            
            // Calculate percentage (in-stock items / total items)
            $percentage = $totalItems > 0 ? round(($inStockItems / $totalItems) * 100) : 0;
            
            $stockData[] = [
                'category' => $category,
                'totalQuantity' => $totalQuantity,
                'totalItems' => $totalItems,
                'inStockItems' => $inStockItems,
                'percentage' => $percentage
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $stockData
    ], JSON_UNESCAPED_UNICODE);
}

function getCategoryDistribution($conn) {
    $sql = "SELECT 
                COALESCE(medicine_group, category) as category,
                COUNT(*) as count,
                SUM(stock) as total_quantity,
                SUM(stock * price) as total_value
            FROM medicines 
            WHERE COALESCE(medicine_group, category) IS NOT NULL AND COALESCE(medicine_group, category) != ''
            GROUP BY COALESCE(medicine_group, category)
            ORDER BY total_value DESC";

    $result = mysqli_query($conn, $sql);
    $distribution = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $distribution[] = [
                'category' => $row['category'],
                'count' => (int)$row['count'],
                'totalQuantity' => (int)$row['total_quantity'],
                'totalValue' => (float)$row['total_value']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $distribution
    ], JSON_UNESCAPED_UNICODE);
}

function getSeasonalTrends($conn, $dateRange) {
    // Get order trends by month for the last 12 months
    $currentDate = date('Y-m-d');
    
    // Determine date range based on parameter
    $monthsBack = 12; // Default to 12 months
    switch ($dateRange) {
        case 'daily':
            $monthsBack = 1;
            break;
        case 'weekly':
            $monthsBack = 3;
            break;
        case 'monthly':
            $monthsBack = 6;
            break;
        default:
            $monthsBack = 12;
    }
    
    $sql = "SELECT 
                DATE_FORMAT(o.order_date, '%Y-%m') as month,
                DATE_FORMAT(o.order_date, '%b %Y') as month_label,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(oi.quantity), 0) as total_quantity,
                COALESCE(SUM(oi.quantity * oi.price), 0) as total_value,
                COUNT(DISTINCT oi.medicine_id) as unique_medicines
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.order_date >= DATE_SUB('{$currentDate}', INTERVAL {$monthsBack} MONTH)
                AND o.order_date <= '{$currentDate}'
                AND o.status != 'cancelled'
            GROUP BY DATE_FORMAT(o.order_date, '%Y-%m'), DATE_FORMAT(o.order_date, '%b %Y')
            ORDER BY month ASC";
    
    $result = mysqli_query($conn, $sql);
    $trends = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $trends[] = [
                'month' => $row['month'],
                'monthLabel' => $row['month_label'],
                'orderCount' => (int)$row['order_count'],
                'totalQuantity' => (float)$row['total_quantity'],
                'totalValue' => (float)$row['total_value'],
                'uniqueMedicines' => (int)$row['unique_medicines']
            ];
        }
    } else {
        error_log("SQL Error in getSeasonalTrends: " . mysqli_error($conn));
        echo json_encode([
            'success' => false,
            'message' => 'Database query failed: ' . mysqli_error($conn),
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $trends
    ], JSON_UNESCAPED_UNICODE);
}

function getTopMedicines($conn, $dateRange, $category) {
    $categoryFilter = '';
    if (!empty($category)) {
        $cat = mysqli_real_escape_string($conn, $category);
        $categoryFilter = " AND COALESCE(m.medicine_group, m.category) = '{$cat}'";
    }

    $orderDateFilter = getDateFilterForColumn($dateRange, 'o.order_date', $conn);

    $sql = "SELECT 
                COALESCE(m.medicine_id, m.id) AS id,
                COALESCE(m.medicine_name, m.name) AS name,
                COALESCE(m.medicine_group, m.category) AS category,
                m.price,
                COALESCE(m.stock, m.quantity) AS current_stock,
                COUNT(DISTINCT o.id) AS order_count,
                COALESCE(SUM(oi.quantity), 0) AS total_ordered,
                COALESCE(SUM(oi.quantity * oi.price), 0) AS total_value
            FROM medicines m
            LEFT JOIN order_items oi ON (oi.medicine_id = m.medicine_id OR oi.medicine_id = m.id)
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled' AND {$orderDateFilter}
            WHERE m.status != 'deleted' {$categoryFilter}
            GROUP BY COALESCE(m.medicine_id, m.id), COALESCE(m.medicine_name, m.name), category, m.price, COALESCE(m.stock, m.quantity)
            HAVING order_count > 0 OR total_ordered > 0
            ORDER BY total_ordered DESC, order_count DESC, total_value DESC
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $medicines = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'category' => $row['category'] ?? 'N/A',
                'price' => (float)$row['price'],
                'currentStock' => (int)$row['current_stock'],
                'orderCount' => (int)$row['order_count'],
                'totalOrdered' => (float)$row['total_ordered'],
                'totalValue' => (float)$row['total_value']
            ];
        }
    } else {
        error_log("SQL Error in getTopMedicines: " . mysqli_error($conn));
    }
    
    // If no medicines with orders found, fall back to top medicines by current stock value
    if (empty($medicines)) {
        $fallbackSql = "SELECT 
                    COALESCE(medicine_id, id) AS id,
                    COALESCE(medicine_name, name) AS name,
                    COALESCE(medicine_group, category) AS category,
                    price,
                    COALESCE(stock, quantity) AS current_stock,
                    (COALESCE(stock, quantity) * price) AS total_value
                FROM medicines 
                WHERE status != 'deleted' AND status != 'expired' AND COALESCE(stock, quantity) > 0 {$categoryFilter}
                ORDER BY total_value DESC, current_stock DESC
                LIMIT 10";
        
        $fallbackResult = mysqli_query($conn, $fallbackSql);
        if ($fallbackResult) {
            while ($row = mysqli_fetch_assoc($fallbackResult)) {
                $medicines[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'category' => $row['category'] ?? 'N/A',
                    'price' => (float)$row['price'],
                    'currentStock' => (int)$row['current_stock'],
                    'orderCount' => 0,
                    'totalOrdered' => 0,
                    'totalValue' => (float)$row['total_value']
                ];
            }
        } else {
            error_log("SQL Error in getTopMedicines fallback: " . mysqli_error($conn));
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getDateFilter($dateRange) {
    // Legacy function - kept for backward compatibility
    return getDateFilterForColumn($dateRange, 'created_at');
}

function getDateFilterForColumn($dateRange, $columnName = 'created_at', $conn = null) {
    $currentDate = date('Y-m-d');
    
    // Escape column name (safe for column names, not user input)
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    
    switch ($dateRange) {
        case 'daily':
            return "DATE({$column}) = '{$currentDate}'";
        case 'weekly':
            return "{$column} >= DATE_SUB('{$currentDate}', INTERVAL 7 DAY)";
        case 'monthly':
            return "{$column} >= DATE_SUB('{$currentDate}', INTERVAL 30 DAY)";
        case 'custom':
            // For custom, check if dateFrom and dateTo are provided
            $dateFrom = $_GET['dateFrom'] ?? '';
            $dateTo = $_GET['dateTo'] ?? '';
            if (!empty($dateFrom) && !empty($dateTo)) {
                // Escape dates
                if ($conn) {
                    $dateFrom = mysqli_real_escape_string($conn, $dateFrom);
                    $dateTo = mysqli_real_escape_string($conn, $dateTo);
                } else {
                    // Fallback if no connection provided
                    $dateFrom = addslashes($dateFrom);
                    $dateTo = addslashes($dateTo);
                }
                return "DATE({$column}) >= '{$dateFrom}' AND DATE({$column}) <= '{$dateTo}'";
            }
            return "1=1";
        default:
            return "1=1";
    }
}

?>

