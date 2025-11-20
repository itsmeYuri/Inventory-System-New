<?php
// Simple JSON API to list medicines with basic filters and pagination

// Enhanced CORS headers - allow multiple origins for development
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
    // Default to allow localhost on any port for development
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
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $expiration = isset($_GET['expiration']) ? trim($_GET['expiration']) : '';

    // Build where clause safely using mysqli_real_escape_string
    $where = " WHERE 1=1 ";

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (ndc LIKE '%{$s}%' OR name LIKE '%{$s}%' OR manufacturer LIKE '%{$s}%') ";
    }

    if ($status !== '') {
        $st = mysqli_real_escape_string($conn, $status);
        $where .= " AND status = '{$st}' ";
    }

    if ($category !== '') {
        $cat = mysqli_real_escape_string($conn, $category);
        $where .= " AND category = '{$cat}' ";
    }

    if ($expiration === 'expired') {
        $where .= " AND expiration_date IS NOT NULL AND expiration_date < CURDATE() ";
    } elseif ($expiration === 'expiring-soon') {
        $where .= " AND expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ";
    } elseif ($expiration === 'expiring-later') {
        $where .= " AND (expiration_date IS NULL OR expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)) ";
    }

    // Get total count
    $countSql = "SELECT COUNT(*) AS cnt FROM medicines" . $where;
    $countRes = mysqli_query($conn, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int)$row['cnt'];
    }

    // Fetch page
    $sql = "SELECT id, ndc, name, manufacturer, category, quantity, reorder_level, price, expiration_date, batch_number, status, dosage_form
            FROM medicines
            {$where}
            ORDER BY name ASC
            LIMIT {$offset}, {$pageSize}";

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
        'limit' => $pageSize, // Also include 'limit' for backward compatibility
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_medicines.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    // Don't close $conn here as it might be used by other scripts
}