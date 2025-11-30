<?php
/**
 * Get Supplier Metrics API
 * Returns dashboard metrics for a specific supplier
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
    
    if ($supplier_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid supplier ID'
        ]);
        exit;
    }

    // Verify supplier exists
    $checkSupplier = mysqli_query($conn, "SELECT id FROM suppliers WHERE id = $supplier_id");
    if (!$checkSupplier || mysqli_num_rows($checkSupplier) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found'
        ]);
        exit;
    }

    // Total orders
    $totalOrdersQuery = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = $supplier_id";
    $totalOrdersResult = mysqli_query($conn, $totalOrdersQuery);
    $totalOrders = 0;
    if ($totalOrdersResult) {
        $row = mysqli_fetch_assoc($totalOrdersResult);
        $totalOrders = (int)$row['total'];
    }

    // Pending orders
    $pendingOrdersQuery = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = $supplier_id AND status IN ('pending', 'processing')";
    $pendingOrdersResult = mysqli_query($conn, $pendingOrdersQuery);
    $pendingOrders = 0;
    if ($pendingOrdersResult) {
        $row = mysqli_fetch_assoc($pendingOrdersResult);
        $pendingOrders = (int)$row['total'];
    }

    // Completed orders
    $completedOrdersQuery = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = $supplier_id AND status = 'completed'";
    $completedOrdersResult = mysqli_query($conn, $completedOrdersQuery);
    $completedOrders = 0;
    if ($completedOrdersResult) {
        $row = mysqli_fetch_assoc($completedOrdersResult);
        $completedOrders = (int)$row['total'];
    }

    // Total revenue (from completed orders)
    $revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE supplier_id = $supplier_id AND status = 'completed'";
    $revenueResult = mysqli_query($conn, $revenueQuery);
    $totalRevenue = 0.00;
    if ($revenueResult) {
        $row = mysqli_fetch_assoc($revenueResult);
        $totalRevenue = (float)$row['total'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'completedOrders' => $completedOrders,
            'totalRevenue' => $totalRevenue
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_supplier_metrics.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

