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
    $reportType = $_GET['reportType'] ?? 'sales';

    switch ($action) {
        case 'dashboard':
            getDashboardData($conn, $dateRange, $category, $reportType);
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

function getDashboardData($conn, $dateRange, $category, $reportType) {
    // Calculate date range
    $dateFilter = getDateFilter($dateRange);
    
    // Build category filter
    $categoryFilter = '';
    if (!empty($category)) {
        $cat = mysqli_real_escape_string($conn, $category);
        $categoryFilter = " AND category = '{$cat}'";
    }

    // Total Revenue (sum of all inventory value: quantity * price)
    $revenueSql = "SELECT SUM(quantity * price) as total_revenue 
                   FROM medicines 
                   WHERE 1=1 {$categoryFilter}";
    $revenueResult = mysqli_query($conn, $revenueSql);
    $revenue = 0;
    if ($revenueResult) {
        $row = mysqli_fetch_assoc($revenueResult);
        $revenue = (float)($row['total_revenue'] ?? 0);
    }

    // Medicine Turnover (total quantity of in-stock items)
    $turnoverSql = "SELECT SUM(quantity) as total_quantity 
                    FROM medicines 
                    WHERE status IN ('in-stock', 'low-stock') {$categoryFilter}";
    $turnoverResult = mysqli_query($conn, $turnoverSql);
    $turnover = 0;
    if ($turnoverResult) {
        $row = mysqli_fetch_assoc($turnoverResult);
        $turnover = (int)($row['total_quantity'] ?? 0);
    }

    // Profit Margin (estimated: assume 30% margin on inventory value)
    $profitMargin = $revenue > 0 ? ($revenue * 0.30) : 0;
    $profitMarginPercent = $revenue > 0 ? 30 : 0;

    // Top Selling Items (medicines with highest quantity)
    $topSellingSql = "SELECT COUNT(*) as count 
                      FROM medicines 
                      WHERE quantity > 0 {$categoryFilter}";
    $topSellingResult = mysqli_query($conn, $topSellingSql);
    $topSellingCount = 0;
    if ($topSellingResult) {
        $row = mysqli_fetch_assoc($topSellingResult);
        $topSellingCount = (int)($row['count'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalRevenue' => number_format($revenue, 2),
            'medicineTurnover' => number_format($turnover),
            'profitMargin' => number_format($profitMargin, 2),
            'profitMarginPercent' => number_format($profitMarginPercent, 1),
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
    $currentDate = date('Y-m-d');
    
    switch ($dateRange) {
        case 'daily':
            return "DATE(created_at) = '{$currentDate}'";
        case 'weekly':
            return "created_at >= DATE_SUB('{$currentDate}', INTERVAL 7 DAY)";
        case 'monthly':
            return "created_at >= DATE_SUB('{$currentDate}', INTERVAL 30 DAY)";
        case 'custom':
            // For custom, we'd need start and end dates from request
            return "1=1";
        default:
            return "1=1";
    }
}

?>

