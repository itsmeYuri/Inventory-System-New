<?php
// Dashboard API
// Provides data for dashboard including metrics, low stock alerts, recent activities, and calendar events

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
    $action = $_GET['action'] ?? 'metrics';

    switch ($action) {
        case 'metrics':
            getDashboardMetrics($conn);
            break;
        case 'low-stock':
            getLowStockData($conn);
            break;
        case 'expiration-alerts':
            getExpirationAlerts($conn);
            break;
        case 'activities':
            getRecentActivities($conn);
            break;
        case 'calendar':
            getCalendarEvents($conn);
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
    error_log("Error in get_dashboard.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getDashboardMetrics($conn) {
    // Total Medicines
    $totalMedicinesSql = "SELECT COUNT(*) as total FROM medicines";
    $totalMedicinesResult = mysqli_query($conn, $totalMedicinesSql);
    $totalMedicines = 0;
    if ($totalMedicinesResult) {
        $row = mysqli_fetch_assoc($totalMedicinesResult);
        $totalMedicines = (int)($row['total'] ?? 0);
    }

    // Pending Orders (if orders table exists, otherwise use low-stock as proxy)
    // For now, we'll use medicines with low stock as "pending" items
    $pendingOrdersSql = "SELECT COUNT(*) as total FROM medicines WHERE status = 'low-stock'";
    $pendingOrdersResult = mysqli_query($conn, $pendingOrdersSql);
    $pendingOrders = 0;
    if ($pendingOrdersResult) {
        $row = mysqli_fetch_assoc($pendingOrdersResult);
        $pendingOrders = (int)($row['total'] ?? 0);
    }

    // Total Inventory Value
    $totalValueSql = "SELECT SUM(quantity * price) as total_value FROM medicines";
    $totalValueResult = mysqli_query($conn, $totalValueSql);
    $totalValue = 0;
    if ($totalValueResult) {
        $row = mysqli_fetch_assoc($totalValueResult);
        $totalValue = (float)($row['total_value'] ?? 0);
    }

    // Low Stock Count
    $lowStockSql = "SELECT COUNT(*) as total FROM medicines WHERE status IN ('low-stock', 'out-of-stock')";
    $lowStockResult = mysqli_query($conn, $lowStockSql);
    $lowStockCount = 0;
    if ($lowStockResult) {
        $row = mysqli_fetch_assoc($lowStockResult);
        $lowStockCount = (int)($row['total'] ?? 0);
    }

    // Low Stock Percentage
    $lowStockPercentage = $totalMedicines > 0 ? round(($lowStockCount / $totalMedicines) * 100) : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'totalMedicines' => $totalMedicines,
            'pendingOrders' => $pendingOrders,
            'totalValue' => number_format($totalValue, 2),
            'lowStockCount' => $lowStockCount,
            'lowStockPercentage' => $lowStockPercentage
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getLowStockData($conn) {
    $sql = "SELECT id, name, quantity, reorder_level, status 
            FROM medicines 
            WHERE status IN ('low-stock', 'out-of-stock')
            ORDER BY quantity ASC
            LIMIT 10";

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

function getRecentActivities($conn) {
    // Get recent medicine additions and updates
    $sql = "SELECT 
                id,
                name,
                'medicine_added' as activity_type,
                created_at as activity_date,
                'Medicine added to inventory' as description
            FROM medicines
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            UNION ALL
            
            SELECT 
                id,
                name,
                'medicine_updated' as activity_type,
                updated_at as activity_date,
                'Medicine updated' as description
            FROM medicines
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND updated_at != created_at
            
            ORDER BY activity_date DESC
            LIMIT 10";

    $result = mysqli_query($conn, $sql);
    $activities = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $activities[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'type' => $row['activity_type'],
                'date' => $row['activity_date'],
                'description' => $row['description']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $activities
    ], JSON_UNESCAPED_UNICODE);
}

function getExpirationAlerts($conn) {
    $currentDate = date('Y-m-d');
    $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
    
    // Check if batch_items table exists
    $checkBatchItems = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    $hasBatchItems = $checkBatchItems && mysqli_num_rows($checkBatchItems) > 0;
    $checkBatches = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
    $hasBatches = $checkBatches && mysqli_num_rows($checkBatches) > 0;
    $hasBatchTables = $hasBatchItems && $hasBatches;
    
    // Get expired medicines count (from medicines table and optionally batch_items)
    if ($hasBatchTables) {
        $expiredSql = "SELECT COUNT(DISTINCT m.id) as count 
                       FROM medicines m
                       WHERE (
                           (m.expiration_date IS NOT NULL 
                            AND m.expiration_date != ''
                            AND m.expiration_date != '0000-00-00'
                            AND m.expiration_date < ?)
                           OR EXISTS (
                               SELECT 1 
                               FROM batch_items bi 
                               INNER JOIN batches b ON bi.batch_id = b.id 
                               WHERE b.medicine_id = m.id 
                               AND bi.expiration_date IS NOT NULL 
                               AND bi.expiration_date != ''
                               AND bi.expiration_date != '0000-00-00'
                               AND bi.expiration_date < ?
                           )
                       )";
        $expiredStmt = mysqli_prepare($conn, $expiredSql);
        if ($expiredStmt) {
            mysqli_stmt_bind_param($expiredStmt, 'ss', $currentDate, $currentDate);
            mysqli_stmt_execute($expiredStmt);
            $expiredResult = mysqli_stmt_get_result($expiredStmt);
            $expiredCount = 0;
            if ($expiredResult) {
                $row = mysqli_fetch_assoc($expiredResult);
                $expiredCount = (int)($row['count'] ?? 0);
            }
            mysqli_stmt_close($expiredStmt);
        } else {
            error_log("Error preparing expired count query: " . mysqli_error($conn));
            $expiredCount = 0;
        }
    } else {
        // Fallback: only check medicines table
        $expiredSql = "SELECT COUNT(*) as count 
                       FROM medicines 
                       WHERE expiration_date IS NOT NULL 
                       AND expiration_date != ''
                       AND expiration_date != '0000-00-00'
                       AND expiration_date < ?";
        $expiredStmt = mysqli_prepare($conn, $expiredSql);
        if ($expiredStmt) {
            mysqli_stmt_bind_param($expiredStmt, 's', $currentDate);
            mysqli_stmt_execute($expiredStmt);
            $expiredResult = mysqli_stmt_get_result($expiredStmt);
            $expiredCount = 0;
            if ($expiredResult) {
                $row = mysqli_fetch_assoc($expiredResult);
                $expiredCount = (int)($row['count'] ?? 0);
            }
            mysqli_stmt_close($expiredStmt);
        } else {
            error_log("Error preparing expired count query: " . mysqli_error($conn));
            $expiredCount = 0;
        }
    }
    
    // Get expiring soon (within 30 days) - from medicines table and optionally batch_items
    if ($hasBatchTables) {
        $expiringSoonSql = "SELECT COUNT(DISTINCT m.id) as count 
                            FROM medicines m
                            WHERE (
                                (m.expiration_date IS NOT NULL 
                                 AND m.expiration_date != ''
                                 AND m.expiration_date != '0000-00-00'
                                 AND m.expiration_date >= ? 
                                 AND m.expiration_date <= ?)
                                OR EXISTS (
                                    SELECT 1 
                                    FROM batch_items bi 
                                    INNER JOIN batches b ON bi.batch_id = b.id 
                                    WHERE b.medicine_id = m.id 
                                    AND bi.expiration_date IS NOT NULL 
                                    AND bi.expiration_date != ''
                                    AND bi.expiration_date != '0000-00-00'
                                    AND bi.expiration_date >= ?
                                    AND bi.expiration_date <= ?
                                )
                            )";
        $expiringSoonStmt = mysqli_prepare($conn, $expiringSoonSql);
        if ($expiringSoonStmt) {
            mysqli_stmt_bind_param($expiringSoonStmt, 'ssss', $currentDate, $thirtyDaysFromNow, $currentDate, $thirtyDaysFromNow);
            mysqli_stmt_execute($expiringSoonStmt);
            $expiringSoonResult = mysqli_stmt_get_result($expiringSoonStmt);
            $expiringSoonCount = 0;
            if ($expiringSoonResult) {
                $row = mysqli_fetch_assoc($expiringSoonResult);
                $expiringSoonCount = (int)($row['count'] ?? 0);
            }
            mysqli_stmt_close($expiringSoonStmt);
        } else {
            error_log("Error preparing expiring soon count query: " . mysqli_error($conn));
            $expiringSoonCount = 0;
        }
    } else {
        // Fallback: only check medicines table
        $expiringSoonSql = "SELECT COUNT(*) as count 
                            FROM medicines 
                            WHERE expiration_date IS NOT NULL 
                            AND expiration_date != ''
                            AND expiration_date != '0000-00-00'
                            AND expiration_date >= ? 
                            AND expiration_date <= ?";
        $expiringSoonStmt = mysqli_prepare($conn, $expiringSoonSql);
        if ($expiringSoonStmt) {
            mysqli_stmt_bind_param($expiringSoonStmt, 'ss', $currentDate, $thirtyDaysFromNow);
            mysqli_stmt_execute($expiringSoonStmt);
            $expiringSoonResult = mysqli_stmt_get_result($expiringSoonStmt);
            $expiringSoonCount = 0;
            if ($expiringSoonResult) {
                $row = mysqli_fetch_assoc($expiringSoonResult);
                $expiringSoonCount = (int)($row['count'] ?? 0);
            }
            mysqli_stmt_close($expiringSoonStmt);
        } else {
            error_log("Error preparing expiring soon count query: " . mysqli_error($conn));
            $expiringSoonCount = 0;
        }
    }
    
    // Get expired medicines list (top 5) - includes medicines with expiration dates in medicines table or batch_items
    if ($hasBatchTables) {
        $expiredListSql = "SELECT DISTINCT
                           m.id, 
                           m.name, 
                           COALESCE(m.expiration_date, 
                               (SELECT MIN(bi.expiration_date) 
                                FROM batch_items bi 
                                INNER JOIN batches b ON bi.batch_id = b.id 
                                WHERE b.medicine_id = m.id 
                                AND bi.expiration_date IS NOT NULL 
                                AND bi.expiration_date != ''
                                AND bi.expiration_date != '0000-00-00'
                                AND bi.expiration_date < ?
                               )
                           ) as expiration_date,
                           m.batch_number, 
                           m.quantity
                           FROM medicines m
                           WHERE (
                               (m.expiration_date IS NOT NULL 
                                AND m.expiration_date != ''
                                AND m.expiration_date != '0000-00-00'
                                AND m.expiration_date < ?)
                               OR EXISTS (
                                   SELECT 1 
                                   FROM batch_items bi 
                                   INNER JOIN batches b ON bi.batch_id = b.id 
                                   WHERE b.medicine_id = m.id 
                                   AND bi.expiration_date IS NOT NULL 
                                   AND bi.expiration_date != ''
                                   AND bi.expiration_date != '0000-00-00'
                                   AND bi.expiration_date < ?
                               )
                           )
                           ORDER BY expiration_date ASC
                           LIMIT 5";
        $expiredListStmt = mysqli_prepare($conn, $expiredListSql);
        if ($expiredListStmt) {
            mysqli_stmt_bind_param($expiredListStmt, 'sss', $currentDate, $currentDate, $currentDate);
            mysqli_stmt_execute($expiredListStmt);
            $expiredListResult = mysqli_stmt_get_result($expiredListStmt);
            
            $expiredMedicines = [];
            if ($expiredListResult) {
                while ($row = mysqli_fetch_assoc($expiredListResult)) {
                    if (!empty($row['expiration_date']) && $row['expiration_date'] !== '0000-00-00') {
                        $expiredMedicines[] = [
                            'id' => (int)$row['id'],
                            'name' => $row['name'],
                            'expirationDate' => $row['expiration_date'],
                            'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                            'quantity' => (int)$row['quantity']
                        ];
                    }
                }
            }
            mysqli_stmt_close($expiredListStmt);
        } else {
            error_log("Error preparing expired list query: " . mysqli_error($conn));
            $expiredMedicines = [];
        }
    } else {
        // Fallback: only check medicines table
        $expiredListSql = "SELECT id, name, expiration_date, batch_number, quantity
                           FROM medicines 
                           WHERE expiration_date IS NOT NULL 
                           AND expiration_date != ''
                           AND expiration_date != '0000-00-00'
                           AND expiration_date < ?
                           ORDER BY expiration_date ASC
                           LIMIT 5";
        $expiredListStmt = mysqli_prepare($conn, $expiredListSql);
        if ($expiredListStmt) {
            mysqli_stmt_bind_param($expiredListStmt, 's', $currentDate);
            mysqli_stmt_execute($expiredListStmt);
            $expiredListResult = mysqli_stmt_get_result($expiredListStmt);
            
            $expiredMedicines = [];
            if ($expiredListResult) {
                while ($row = mysqli_fetch_assoc($expiredListResult)) {
                    $expiredMedicines[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'expirationDate' => $row['expiration_date'],
                        'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                        'quantity' => (int)$row['quantity']
                    ];
                }
            }
            mysqli_stmt_close($expiredListStmt);
        } else {
            error_log("Error preparing expired list query: " . mysqli_error($conn));
            $expiredMedicines = [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'expiredCount' => $expiredCount,
            'expiringSoonCount' => $expiringSoonCount,
            'totalAlerts' => $expiredCount + $expiringSoonCount,
            'expiredMedicines' => $expiredMedicines
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getCalendarEvents($conn) {
    $events = [];
    $currentDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+365 days')); // Show up to 1 year in the future
    
    try {
        // 1. Get medicines with expiration dates (show expired items from past 6 months and future up to 1 year)
        // Also include medicines that have expiration dates in batch_items even if medicines.expiration_date is NULL
        $expirationSql = "SELECT DISTINCT
                    m.id,
                    m.name,
                    COALESCE(m.expiration_date, 
                        (SELECT MIN(bi.expiration_date) 
                         FROM batch_items bi 
                         INNER JOIN batches b ON bi.batch_id = b.id 
                         WHERE b.medicine_id = m.id 
                         AND bi.expiration_date IS NOT NULL 
                         AND bi.expiration_date != ''
                         AND bi.expiration_date != '0000-00-00'
                         AND (bi.expiration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                              OR bi.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 365 DAY))
                        )
                    ) as expiration_date,
                    m.quantity,
                    m.status,
                    m.batch_number
                FROM medicines m
                WHERE (
                    (m.expiration_date IS NOT NULL 
                     AND m.expiration_date != ''
                     AND m.expiration_date != '0000-00-00'
                     AND (m.expiration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          OR m.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 365 DAY)))
                    )
                    OR EXISTS (
                        SELECT 1 
                        FROM batch_items bi 
                        INNER JOIN batches b ON bi.batch_id = b.id 
                        WHERE b.medicine_id = m.id 
                        AND bi.expiration_date IS NOT NULL 
                        AND bi.expiration_date != ''
                        AND bi.expiration_date != '0000-00-00'
                        AND (bi.expiration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                             OR bi.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 365 DAY))
                    )
                )
                ORDER BY expiration_date ASC";

        $expirationResult = mysqli_query($conn, $expirationSql);
        if ($expirationResult) {
            $expirationCount = 0;
            while ($row = mysqli_fetch_assoc($expirationResult)) {
                $status = $row['status'] ?? 'in-stock';
                $expDate = $row['expiration_date'];
                
                // Skip if expiration_date is still invalid
                if (empty($expDate) || $expDate === '0000-00-00') {
                    continue;
                }
                
                $isExpired = strtotime($expDate) < strtotime($currentDate);
                $daysUntilExpiry = floor((strtotime($expDate) - strtotime($currentDate)) / 86400);
                
                // Color coding for expiration dates
                $color = '#22C55E'; // green for in-stock, not expired
                if ($isExpired) {
                    $color = '#EF4444'; // red for expired
                } elseif ($daysUntilExpiry <= 7) {
                    $color = '#DC2626'; // dark red for expiring within 7 days
                } elseif ($daysUntilExpiry <= 30) {
                    $color = '#F97316'; // orange for expiring soon (within 30 days)
                } elseif ($status === 'low-stock') {
                    $color = '#F59E0B'; // orange for low stock
                } elseif ($status === 'out-of-stock') {
                    $color = '#6B7280'; // gray for out of stock
                }

                $events[] = [
                    'id' => 'exp_' . (int)$row['id'],
                    'title' => $row['name'] . ' (Expires)',
                    'start' => $expDate,
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'expiration',
                        'medicineId' => (int)$row['id'],
                        'quantity' => (int)$row['quantity'],
                        'status' => $status,
                        'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                        'isExpired' => $isExpired,
                        'daysUntilExpiry' => $daysUntilExpiry
                    ]
                ];
                $expirationCount++;
            }
            error_log("Calendar: Found {$expirationCount} medicine expiration events");
        } else {
            error_log("Calendar: Error executing expiration query: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        error_log("Error fetching expiration dates: " . $e->getMessage());
    }
    
    try {
        // 2. Get orders with order dates
        $orderSql = "SELECT 
                    o.id,
                    o.order_date,
                    o.status,
                    s.name as supplier_name,
                    COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.order_date IS NOT NULL
                    AND o.order_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                GROUP BY o.id, o.order_date, o.status, s.name
                ORDER BY o.order_date ASC";

        $orderResult = mysqli_query($conn, $orderSql);
        if ($orderResult) {
            while ($row = mysqli_fetch_assoc($orderResult)) {
                $status = $row['status'];
                $color = '#3B82F6'; // blue for pending orders
                if ($status === 'completed') {
                    $color = '#22C55E'; // green for completed
                } elseif ($status === 'cancelled') {
                    $color = '#6B7280'; // gray for cancelled
                } elseif ($status === 'shipping') {
                    $color = '#8B5CF6'; // purple for shipping
                }

                $supplierName = $row['supplier_name'] ? $row['supplier_name'] : 'Unknown Supplier';
                $events[] = [
                    'id' => 'order_' . (int)$row['id'],
                    'title' => 'Order #' . $row['id'] . ' (' . $supplierName . ')',
                    'start' => $row['order_date'],
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'order',
                        'orderId' => (int)$row['id'],
                        'status' => $status,
                        'supplierName' => $supplierName,
                        'itemCount' => (int)$row['item_count']
                    ]
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching order dates: " . $e->getMessage());
    }
    
    try {
        // 3. Get batch items with expiration dates (if batch_items table exists)
        $checkBatchItems = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
        if ($checkBatchItems && mysqli_num_rows($checkBatchItems) > 0) {
            // Check if batches table exists
            $checkBatches = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
            if ($checkBatches && mysqli_num_rows($checkBatches) > 0) {
                // Get batch items with expiration dates (wider date range: past 6 months to future 1 year)
                $batchExpirationSql = "SELECT 
                            bi.id,
                            bi.expiration_date,
                            bi.quantity,
                            bi.is_expired,
                            b.batch_number,
                            b.medicine_id,
                            m.name as medicine_name
                        FROM batch_items bi
                        INNER JOIN batches b ON bi.batch_id = b.id
                        LEFT JOIN medicines m ON b.medicine_id = m.id
                        WHERE bi.expiration_date IS NOT NULL
                            AND bi.expiration_date != ''
                            AND bi.expiration_date != '0000-00-00'
                            AND (
                                bi.expiration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                OR bi.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 365 DAY)
                            )
                        ORDER BY bi.expiration_date ASC";

                $batchResult = mysqli_query($conn, $batchExpirationSql);
                if ($batchResult) {
                    $batchCount = 0;
                    while ($row = mysqli_fetch_assoc($batchResult)) {
                        $expDate = $row['expiration_date'];
                        if (empty($expDate) || $expDate === '0000-00-00') {
                            continue;
                        }
                        
                        $isExpired = (int)$row['is_expired'] === 1 || strtotime($expDate) < strtotime($currentDate);
                        $daysUntilExpiry = floor((strtotime($expDate) - strtotime($currentDate)) / 86400);
                        
                        // Get medicine name
                        $medicineName = $row['medicine_name'] ? $row['medicine_name'] : 'Unknown Medicine';
                        if (empty($medicineName) && isset($row['medicine_id']) && $row['medicine_id']) {
                            $medSql = "SELECT name FROM medicines WHERE id = " . (int)$row['medicine_id'];
                            $medResult = mysqli_query($conn, $medSql);
                            if ($medResult && $medRow = mysqli_fetch_assoc($medResult)) {
                                $medicineName = $medRow['name'];
                            }
                        }
                        
                        // Color coding
                        $color = '#10B981'; // lighter green for batch items
                        if ($isExpired) {
                            $color = '#DC2626'; // darker red for expired batches
                        } elseif ($daysUntilExpiry <= 7) {
                            $color = '#DC2626'; // dark red for expiring within 7 days
                        } elseif ($daysUntilExpiry <= 30) {
                            $color = '#F59E0B'; // orange for expiring soon (within 30 days)
                        }

                        $events[] = [
                            'id' => 'batch_' . (int)$row['id'],
                            'title' => $medicineName . ' Batch #' . $row['batch_number'] . ' (Expires)',
                            'start' => $expDate,
                            'color' => $color,
                            'extendedProps' => [
                                'type' => 'batch_expiration',
                                'batchItemId' => (int)$row['id'],
                                'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                                'quantity' => (int)$row['quantity'],
                                'isExpired' => $isExpired,
                                'medicineName' => $medicineName,
                                'daysUntilExpiry' => $daysUntilExpiry
                            ]
                        ];
                        $batchCount++;
                    }
                    error_log("Calendar: Found {$batchCount} batch expiration events");
                } else {
                    error_log("Calendar: Error executing batch expiration query: " . mysqli_error($conn));
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching batch expiration dates: " . $e->getMessage());
    }

    // Log summary
    $expirationCount = count(array_filter($events, function($e) {
        return isset($e['extendedProps']['type']) && $e['extendedProps']['type'] === 'expiration';
    }));
    $orderCount = count(array_filter($events, function($e) {
        return isset($e['extendedProps']['type']) && $e['extendedProps']['type'] === 'order';
    }));
    $batchCount = count(array_filter($events, function($e) {
        return isset($e['extendedProps']['type']) && $e['extendedProps']['type'] === 'batch_expiration';
    }));
    
    error_log("Calendar: Returning " . count($events) . " total events (Expirations: {$expirationCount}, Orders: {$orderCount}, Batches: {$batchCount})");
    
    echo json_encode([
        'success' => true,
        'data' => $events,
        'summary' => [
            'total' => count($events),
            'expirations' => $expirationCount,
            'orders' => $orderCount,
            'batches' => $batchCount
        ]
    ], JSON_UNESCAPED_UNICODE);
}

?>

