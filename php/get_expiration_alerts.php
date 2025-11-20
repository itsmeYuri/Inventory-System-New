<?php
// Expiration Alerts API
// Provides data about expired and expiring medicines

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
    $action = $_GET['action'] ?? 'alerts';

    switch ($action) {
        case 'alerts':
            getExpirationAlerts($conn);
            break;
        case 'expired':
            getExpiredMedicines($conn);
            break;
        case 'expiring-soon':
            getExpiringSoonMedicines($conn);
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
    error_log("Error in get_expiration_alerts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getExpirationAlerts($conn) {
    $currentDate = date('Y-m-d');
    $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
    
    // Get expired medicines
    $expiredSql = "SELECT COUNT(*) as count FROM medicines 
                   WHERE expiration_date IS NOT NULL 
                   AND expiration_date < ?";
    $expiredStmt = mysqli_prepare($conn, $expiredSql);
    mysqli_stmt_bind_param($expiredStmt, 's', $currentDate);
    mysqli_stmt_execute($expiredStmt);
    $expiredResult = mysqli_stmt_get_result($expiredStmt);
    $expiredCount = 0;
    if ($expiredResult) {
        $row = mysqli_fetch_assoc($expiredResult);
        $expiredCount = (int)($row['count'] ?? 0);
    }
    mysqli_stmt_close($expiredStmt);
    
    // Get expiring soon (within 30 days)
    $expiringSoonSql = "SELECT COUNT(*) as count FROM medicines 
                        WHERE expiration_date IS NOT NULL 
                        AND expiration_date >= ? 
                        AND expiration_date <= ?";
    $expiringSoonStmt = mysqli_prepare($conn, $expiringSoonSql);
    mysqli_stmt_bind_param($expiringSoonStmt, 'ss', $currentDate, $thirtyDaysFromNow);
    mysqli_stmt_execute($expiringSoonStmt);
    $expiringSoonResult = mysqli_stmt_get_result($expiringSoonStmt);
    $expiringSoonCount = 0;
    if ($expiringSoonResult) {
        $row = mysqli_fetch_assoc($expiringSoonResult);
        $expiringSoonCount = (int)($row['count'] ?? 0);
    }
    mysqli_stmt_close($expiringSoonStmt);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'expired' => $expiredCount,
            'expiringSoon' => $expiringSoonCount,
            'totalAlerts' => $expiredCount + $expiringSoonCount
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getExpiredMedicines($conn) {
    $currentDate = date('Y-m-d');
    
    $sql = "SELECT id, name, expiration_date, batch_number, quantity, status
            FROM medicines 
            WHERE expiration_date IS NOT NULL 
            AND expiration_date < ?
            ORDER BY expiration_date ASC
            LIMIT 20";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $currentDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $medicines = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'expirationDate' => $row['expiration_date'],
                'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                'quantity' => (int)$row['quantity'],
                'status' => $row['status']
            ];
        }
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getExpiringSoonMedicines($conn) {
    $currentDate = date('Y-m-d');
    $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
    
    $sql = "SELECT id, name, expiration_date, batch_number, quantity, status
            FROM medicines 
            WHERE expiration_date IS NOT NULL 
            AND expiration_date >= ?
            AND expiration_date <= ?
            ORDER BY expiration_date ASC
            LIMIT 20";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $currentDate, $thirtyDaysFromNow);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $medicines = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'expirationDate' => $row['expiration_date'],
                'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                'quantity' => (int)$row['quantity'],
                'status' => $row['status']
            ];
        }
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

?>

