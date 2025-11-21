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
    // Build category filter
    $categoryFilter = '';
    if (!empty($category)) {
        $cat = mysqli_real_escape_string($conn, $category);
        $categoryFilter = " AND category = '{$cat}'";
    }

    // Get date filter for medicines (using created_at)
    $medicineDateFilter = getDateFilterForColumn($dateRange, 'created_at', $conn);
    
    // Get date filter for orders (using order_date)
    $orderDateFilter = getDateFilterForColumn($dateRange, 'order_date', $conn);

    // Total Stocked (total quantity of items in stock within date range)
    $stockedSql = "SELECT SUM(quantity) as total_stocked 
                   FROM medicines 
                   WHERE status IN ('in-stock', 'low-stock') 
                   AND {$medicineDateFilter} {$categoryFilter}";
    $stockedResult = mysqli_query($conn, $stockedSql);
    $totalStocked = 0;
    if ($stockedResult) {
        $row = mysqli_fetch_assoc($stockedResult);
        $totalStocked = (int)($row['total_stocked'] ?? 0);
    }

    // Medicine Turnover (total quantity of in-stock items within date range)
    $turnoverSql = "SELECT SUM(quantity) as total_quantity 
                    FROM medicines 
                    WHERE status IN ('in-stock', 'low-stock') 
                    AND {$medicineDateFilter} {$categoryFilter}";
    $turnoverResult = mysqli_query($conn, $turnoverSql);
    $turnover = 0;
    if ($turnoverResult) {
        $row = mysqli_fetch_assoc($turnoverResult);
        $turnover = (int)($row['total_quantity'] ?? 0);
    }

    // Order Completed (count of completed orders within date range)
    $completedOrdersSql = "SELECT COUNT(*) as completed_count 
                          FROM orders 
                          WHERE status = 'completed' 
                          AND {$orderDateFilter}";
    $completedOrdersResult = mysqli_query($conn, $completedOrdersSql);
    $completedOrders = 0;
    if ($completedOrdersResult) {
        $row = mysqli_fetch_assoc($completedOrdersResult);
        $completedOrders = (int)($row['completed_count'] ?? 0);
    }

    // Top Selling Items (medicines with highest quantity within date range)
    $topSellingSql = "SELECT COUNT(*) as count 
                      FROM medicines 
                      WHERE quantity > 0 
                      AND {$medicineDateFilter} {$categoryFilter}";
    $topSellingResult = mysqli_query($conn, $topSellingSql);
    $topSellingCount = 0;
    if ($topSellingResult) {
        $row = mysqli_fetch_assoc($topSellingResult);
        $topSellingCount = (int)($row['count'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalStocked' => number_format($totalStocked),
            'medicineTurnover' => number_format($turnover),
            'completedOrders' => number_format($completedOrders),
            'topSellingItems' => $topSellingCount
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getTopSellingMedicines($conn, $dateRange, $category) {
    $categoryFilter = '';
    if (!empty($category)) {
        $cat = mysqli_real_escape_string($conn, $category);
        $categoryFilter = " AND category = '{$cat}'";
    }

    // Get top medicines by quantity (as proxy for sales)
    $sql = "SELECT name, quantity, price, category, 
                   (quantity * price) as total_value
            FROM medicines 
            WHERE quantity > 0 {$categoryFilter}
            ORDER BY quantity DESC, total_value DESC
            LIMIT 10";

    $result = mysqli_query($conn, $sql);
    $medicines = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'price' => (float)$row['price'],
                'category' => $row['category'],
                'totalValue' => (float)$row['total_value']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getLowStockMedicines($conn) {
    // Get medicines where quantity is below reorder_level
    $sql = "SELECT id, name, quantity, reorder_level, status
            FROM medicines 
            WHERE quantity <= reorder_level OR status IN ('low-stock', 'out-of-stock')
            ORDER BY quantity ASC, name ASC";

    $result = mysqli_query($conn, $sql);
    $medicines = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'reorderLevel' => (int)$row['reorder_level'],
                'status' => $row['status']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getStockAvailability($conn) {
    // Dynamically get all categories from the database
    $categorySql = "SELECT DISTINCT category 
                    FROM medicines 
                    WHERE category IS NOT NULL AND category != '' 
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
                    SUM(quantity) as total_quantity,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as in_stock_items
                FROM medicines 
                WHERE category = '{$cat}'";
        
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
                category,
                COUNT(*) as count,
                SUM(quantity) as total_quantity,
                SUM(quantity * price) as total_value
            FROM medicines 
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category
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

