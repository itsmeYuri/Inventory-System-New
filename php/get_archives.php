<?php
/**
 * Get Archives API
 * Retrieves archived items: expired items, cancelled orders, and deleted items
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
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Helper function to ensure archive tables exist
function ensureArchiveTablesExist($conn) {
    $tables = [
        'archived_expired_items',
        'archived_orders',
        'archived_order_items',
        'archived_medicines',
        'archived_suppliers'
    ];
    
    $missingTables = [];
    foreach ($tables as $table) {
        $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$check || mysqli_num_rows($check) === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        return true; // All tables exist
    }
    
    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/create_archive_tables.sql';
    if (!file_exists($sqlFile)) {
        error_log("Archive tables SQL file not found: $sqlFile");
        return false;
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        error_log("Failed to read archive tables SQL file");
        return false;
    }
    
    // Split SQL into individual statements
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove comment lines
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strlen(trim($stmt)) > 10; // Filter out very short strings
        }
    );
    
    $created = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        // Execute statement
        if (mysqli_query($conn, $statement)) {
            $created++;
        } else {
            $error = mysqli_error($conn);
            // Ignore "table already exists" errors (MySQL error 1050)
            if (strpos($error, 'already exists') === false && 
                strpos($error, 'Duplicate') === false &&
                mysqli_errno($conn) !== 1050) {
                error_log("Error creating archive table: " . $error . " | Statement: " . substr($statement, 0, 150));
            } else {
                // Table already exists, count as success
                $created++;
            }
        }
    }
    
    return $created > 0;
}

try {
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Ensure archive tables exist
    ensureArchiveTablesExist($conn);

    $type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, expired, cancelled, deleted
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $result = [
        'expired' => [],
        'cancelled' => [],
        'deleted' => [],
        'suppliers' => []
    ];

    // Get expired items
    if ($type === 'all' || $type === 'expired') {
        // Check if table exists
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_expired_items'");
        if (mysqli_num_rows($checkTable) > 0) {
            $expiredSql = "SELECT 
                            ae.id,
                            ae.original_batch_item_id,
                            ae.batch_id,
                            ae.batch_number,
                            ae.medicine_id,
                            ae.medicine_name,
                            ae.medicine_ndc,
                            ae.quantity,
                            ae.expiration_date,
                            ae.expired_at,
                            ae.archived_at,
                            ae.supplier_id,
                            ae.supplier_name,
                            DATEDIFF(CURDATE(), ae.expiration_date) as days_expired
                        FROM archived_expired_items ae
                        ORDER BY ae.expired_at DESC
                        LIMIT ? OFFSET ?";
            
            $stmt = mysqli_prepare($conn, $expiredSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
                if (mysqli_stmt_execute($stmt)) {
                    $expiredResult = mysqli_stmt_get_result($stmt);
                    
                    if ($expiredResult) {
                        while ($row = mysqli_fetch_assoc($expiredResult)) {
                            $result['expired'][] = $row;
                        }
                    } else {
                        error_log("Error getting expired items result: " . mysqli_error($conn));
                    }
                } else {
                    error_log("Error executing expired items query: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing expired items query: " . mysqli_error($conn));
            }
        }
    }

    // Get cancelled orders
    if ($type === 'all' || $type === 'cancelled') {
        // Check if table exists
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_orders'");
        if (mysqli_num_rows($checkTable) > 0) {
            $cancelledSql = "SELECT 
                                ao.id,
                                ao.original_id,
                                ao.supplier_id,
                                ao.supplier_name,
                                ao.order_date,
                                ao.cancelled_at,
                                ao.cancelled_by,
                                ao.total_amount,
                                ao.notes,
                                ao.cancellation_reason,
                                ao.original_status,
                                (SELECT COUNT(*) FROM archived_order_items WHERE archived_order_id = ao.id) as item_count
                            FROM archived_orders ao
                            ORDER BY ao.cancelled_at DESC
                            LIMIT ? OFFSET ?";
            
            $stmt = mysqli_prepare($conn, $cancelledSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
                if (mysqli_stmt_execute($stmt)) {
                    $cancelledResult = mysqli_stmt_get_result($stmt);
                    
                    if ($cancelledResult) {
                        while ($row = mysqli_fetch_assoc($cancelledResult)) {
                            // Get order items
                            $itemsSql = "SELECT 
                                            aoi.id,
                                            aoi.medicine_id,
                                            aoi.medicine_name,
                                            aoi.quantity,
                                            aoi.price
                                        FROM archived_order_items aoi
                                        WHERE aoi.archived_order_id = ?";
                            $itemsStmt = mysqli_prepare($conn, $itemsSql);
                            if ($itemsStmt) {
                                mysqli_stmt_bind_param($itemsStmt, 'i', $row['id']);
                                mysqli_stmt_execute($itemsStmt);
                                $itemsResult = mysqli_stmt_get_result($itemsStmt);
                                $row['items'] = [];
                                if ($itemsResult) {
                                    while ($item = mysqli_fetch_assoc($itemsResult)) {
                                        $row['items'][] = $item;
                                    }
                                }
                                mysqli_stmt_close($itemsStmt);
                            }
                            $result['cancelled'][] = $row;
                        }
                    } else {
                        error_log("Error getting cancelled orders result: " . mysqli_error($conn));
                    }
                } else {
                    error_log("Error executing cancelled orders query: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing cancelled orders query: " . mysqli_error($conn));
            }
        }
    }

    // Get deleted medicines
    if ($type === 'all' || $type === 'deleted') {
        // Check if table exists
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_medicines'");
        if (mysqli_num_rows($checkTable) > 0) {
            $deletedSql = "SELECT 
                            am.id,
                            am.original_id,
                            am.ndc,
                            am.name,
                            am.manufacturer,
                            am.category,
                            am.dosage_form,
                            am.price,
                            am.quantity,
                            am.description,
                            am.deleted_at,
                            am.deleted_by,
                            am.reason
                        FROM archived_medicines am
                        ORDER BY am.deleted_at DESC
                        LIMIT ? OFFSET ?";
            
            $stmt = mysqli_prepare($conn, $deletedSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
                if (mysqli_stmt_execute($stmt)) {
                    $deletedResult = mysqli_stmt_get_result($stmt);
                    
                    if ($deletedResult) {
                        while ($row = mysqli_fetch_assoc($deletedResult)) {
                            $result['deleted'][] = $row;
                        }
                    } else {
                        error_log("Error getting deleted medicines result: " . mysqli_error($conn));
                    }
                } else {
                    error_log("Error executing deleted medicines query: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing deleted medicines query: " . mysqli_error($conn));
            }
        }
    }

    // Get archived suppliers
    if ($type === 'all' || $type === 'suppliers') {
        // Check if table exists
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_suppliers'");
        if (mysqli_num_rows($checkTable) > 0) {
            $suppliersSql = "SELECT 
                            asup.id,
                            asup.original_id,
                            asup.name,
                            asup.contact,
                            asup.email,
                            asup.phone,
                            asup.location,
                            asup.website,
                            asup.notes,
                            asup.archived_at,
                            asup.archived_by,
                            asup.reason
                        FROM archived_suppliers asup
                        ORDER BY asup.archived_at DESC
                        LIMIT ? OFFSET ?";
            
            $stmt = mysqli_prepare($conn, $suppliersSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
                if (mysqli_stmt_execute($stmt)) {
                    $suppliersResult = mysqli_stmt_get_result($stmt);
                    
                    if ($suppliersResult) {
                        while ($row = mysqli_fetch_assoc($suppliersResult)) {
                            $result['suppliers'][] = $row;
                        }
                    } else {
                        error_log("Error getting archived suppliers result: " . mysqli_error($conn));
                    }
                } else {
                    error_log("Error executing archived suppliers query: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing archived suppliers query: " . mysqli_error($conn));
            }
        }
    }

    // Get counts
    $counts = [
        'expired' => 0,
        'cancelled' => 0,
        'deleted' => 0,
        'suppliers' => 0
    ];

    $countSql = "SELECT COUNT(*) as count FROM archived_expired_items";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['expired'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    $countSql = "SELECT COUNT(*) as count FROM archived_orders";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['cancelled'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    $countSql = "SELECT COUNT(*) as count FROM archived_medicines";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['deleted'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    // Get archived suppliers count
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_suppliers'");
    if (mysqli_num_rows($checkTable) > 0) {
        $countSql = "SELECT COUNT(*) as count FROM archived_suppliers";
        $countResult = mysqli_query($conn, $countSql);
        if ($countResult) {
            $counts['suppliers'] = (int)mysqli_fetch_assoc($countResult)['count'];
        }
    }

    sendJsonResponse(true, 'Archives retrieved successfully', [
        'items' => $result,
        'counts' => $counts
    ], 200);

} catch (Exception $e) {
    error_log('Exception in get_archives.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in get_archives.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

