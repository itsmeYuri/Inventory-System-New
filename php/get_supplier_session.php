<?php
/**
 * Get Supplier Session Data API
 * Returns supplier ID and name from PHP session
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

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

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

try {
    // Check if supplier is logged in via session
    $supplierLoggedIn = isset($_SESSION['supplier_loggedin']) && $_SESSION['supplier_loggedin'] === true;
    $supplierId = $_SESSION['supplier_id'] ?? null;
    $supplierName = $_SESSION['supplier_name'] ?? null;
    
    // If not logged in as supplier, check if user has supplier role
    if (!$supplierLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'supplier') {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId && isset($conn) && $conn) {
            // Find matching supplier by user email or name
            $userEmail = $_SESSION['user_email'] ?? '';
            $userName = $_SESSION['full_name'] ?? '';
            
            // Try to find matching supplier by email first
            if (!empty($userEmail)) {
                $supplierQuery = "SELECT id, name FROM suppliers WHERE email = ? LIMIT 1";
                $supplierStmt = mysqli_prepare($conn, $supplierQuery);
                if ($supplierStmt) {
                    mysqli_stmt_bind_param($supplierStmt, "s", $userEmail);
                    mysqli_stmt_execute($supplierStmt);
                    $supplierResult = mysqli_stmt_get_result($supplierStmt);
                    if ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
                        $supplierId = (int)$supplierRow['id'];
                        $supplierName = $supplierRow['name'];
                        mysqli_stmt_close($supplierStmt);
                    } else {
                        mysqli_stmt_close($supplierStmt);
                    }
                }
            }
            
            // If email didn't match, try by name
            if (!$supplierId && !empty($userName)) {
                $supplierQuery = "SELECT id, name FROM suppliers WHERE name = ? LIMIT 1";
                $supplierStmt = mysqli_prepare($conn, $supplierQuery);
                if ($supplierStmt) {
                    mysqli_stmt_bind_param($supplierStmt, "s", $userName);
                    mysqli_stmt_execute($supplierStmt);
                    $supplierResult = mysqli_stmt_get_result($supplierStmt);
                    if ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
                        $supplierId = (int)$supplierRow['id'];
                        $supplierName = $supplierRow['name'];
                    }
                    mysqli_stmt_close($supplierStmt);
                }
            }
        }
    }
    
    if ($supplierId) {
        echo json_encode([
            'success' => true,
            'supplier_id' => (int)$supplierId,
            'supplier_name' => $supplierName ?? 'Supplier'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found in session'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Error in get_supplier_session.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

