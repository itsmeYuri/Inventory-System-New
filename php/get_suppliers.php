<?php
// Get Suppliers API
// Returns paginated list of suppliers with search and filter capabilities

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

    // Build where clause safely
    $where = " WHERE 1=1 ";

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (name LIKE '%{$s}%' OR contact_person LIKE '%{$s}%' OR email LIKE '%{$s}%' OR phone LIKE '%{$s}%' OR address LIKE '%{$s}%') ";
    }

    // Get total count
    $countSql = "SELECT COUNT(*) AS cnt FROM suppliers" . $where;
    $countRes = mysqli_query($conn, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int)$row['cnt'];
    }

    // Fetch page
    $sql = "SELECT id, name, contact_person, phone, email, address, created_at, updated_at
            FROM suppliers
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
        'limit' => $pageSize,
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_suppliers.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
}

?>
