<?php
// Edit Order API
// Handles updating existing orders

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

header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

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

try {
    require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/archive_helper.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Ensure 'cancelled' status exists in orders table
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'status'");
    if ($checkStatus && mysqli_num_rows($checkStatus) > 0) {
        $statusColumn = mysqli_fetch_assoc($checkStatus);
        $currentType = $statusColumn['Type'] ?? '';
        // Check if 'cancelled' is not in the ENUM
        if (strpos($currentType, "'cancelled'") === false) {
            // Add 'cancelled' to the ENUM
            $alterSql = "ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'shipping', 'completed', 'cancelled') DEFAULT 'pending'";
            mysqli_query($conn, $alterSql);
            // Log but don't fail if this doesn't work
            error_log("Attempted to add 'cancelled' status to orders table");
        }
    }

    // Get order ID
    $order_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($order_id <= 0) {
        sendJsonResponse(false, 'Invalid order ID', null, 400);
    }

    // Get form data
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $order_date = isset($_POST['order_date']) ? trim($_POST['order_date']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    $received_quantities = isset($_POST['received_quantities']) ? $_POST['received_quantities'] : null;

    // Check if this is a status-only update (for cancel/delivered actions)
    // Status-only update if status is provided but supplier_id and order_date are not provided or are empty
    $hasSupplierId = isset($_POST['supplier_id']) && !empty($_POST['supplier_id']);
    $hasOrderDate = isset($_POST['order_date']) && !empty(trim($_POST['order_date']));
    $statusOnlyUpdate = !empty($status) && !$hasSupplierId && !$hasOrderDate;
    
    // If not status-only, validate required fields
    if (!$statusOnlyUpdate) {
        if ($supplier_id <= 0) {
            sendJsonResponse(false, 'Supplier is required', null, 400);
        }
        
        if (empty($order_date)) {
            sendJsonResponse(false, 'Order date is required', null, 400);
        }
    }

    // Validate status
    $valid_statuses = ['pending', 'shipping', 'completed', 'cancelled'];
    if (empty($status)) {
        // If status is empty and not a status-only update, default to pending
        if (!$statusOnlyUpdate) {
            $status = 'pending';
        } else {
            sendJsonResponse(false, 'Status is required', null, 400);
        }
    } elseif (!in_array($status, $valid_statuses)) {
        $status = 'pending';
    }

    // Get old status to handle quantity updates
    $oldStatusSql = "SELECT status FROM orders WHERE id = ?";
    $oldStatusStmt = mysqli_prepare($conn, $oldStatusSql);
    $old_status = 'pending';
    if ($oldStatusStmt) {
        mysqli_stmt_bind_param($oldStatusStmt, 'i', $order_id);
        mysqli_stmt_execute($oldStatusStmt);
        $oldResult = mysqli_stmt_get_result($oldStatusStmt);
        if ($oldRow = mysqli_fetch_assoc($oldResult)) {
            $old_status = $oldRow['status'];
        }
        mysqli_stmt_close($oldStatusStmt);
    }

    // Archive order if status is being changed to cancelled
    if ($status === 'cancelled' && $old_status !== 'cancelled') {
        $cancelled_by = isset($_POST['cancelled_by']) ? $_POST['cancelled_by'] : null;
        $reason = isset($_POST['cancellation_reason']) ? $_POST['cancellation_reason'] : (isset($_POST['notes']) ? $_POST['notes'] : null);
        
        if (!archiveOrder($conn, $order_id, $cancelled_by, $reason)) {
            error_log("Warning: Failed to archive order when cancelling");
            // Continue with status update even if archiving fails
        }
    }

    // Check if total_amount and notes columns exist
    $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
    $hasTotalAmount = mysqli_num_rows($checkTotalAmount) > 0;
    
    $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
    $hasNotes = mysqli_num_rows($checkNotes) > 0;

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // If status changed to completed, update medicine quantities
        if ($old_status !== 'completed' && $status === 'completed') {
            // Parse received quantities if provided
            $receivedQtyMap = [];
            if ($received_quantities) {
                $receivedQtyData = json_decode($received_quantities, true);
                if (is_array($receivedQtyData)) {
                    foreach ($receivedQtyData as $itemId => $qtyData) {
                        if (isset($qtyData['medicine_id']) && isset($qtyData['received_qty'])) {
                            $receivedQtyMap[$qtyData['medicine_id']] = (int)$qtyData['received_qty'];
                        }
                    }
                }
            }
            
            // Check if batch exists for this order to get received quantities
            $batchCheckSql = "SELECT bi.medicine_id, bi.received_quantity 
                             FROM batch_items bi
                             INNER JOIN batches b ON bi.batch_id = b.id
                             WHERE b.order_id = ?";
            $batchCheckStmt = mysqli_prepare($conn, $batchCheckSql);
            $batchQtyMap = [];
            if ($batchCheckStmt) {
                mysqli_stmt_bind_param($batchCheckStmt, 'i', $order_id);
                mysqli_stmt_execute($batchCheckStmt);
                $batchResult = mysqli_stmt_get_result($batchCheckStmt);
                while ($batchRow = mysqli_fetch_assoc($batchResult)) {
                    $batchQtyMap[$batchRow['medicine_id']] = (int)$batchRow['received_quantity'];
                }
                mysqli_stmt_close($batchCheckStmt);
            }
            
            $itemsSql = "SELECT medicine_id, quantity FROM order_items WHERE order_id = ?";
            $itemsStmt = mysqli_prepare($conn, $itemsSql);
            if ($itemsStmt) {
                mysqli_stmt_bind_param($itemsStmt, 'i', $order_id);
                mysqli_stmt_execute($itemsStmt);
                $itemsResult = mysqli_stmt_get_result($itemsStmt);
                
                while ($item = mysqli_fetch_assoc($itemsResult)) {
                    // Priority: received_quantities param > batch received_quantity > ordered quantity
                    $qtyToAdd = isset($receivedQtyMap[$item['medicine_id']]) 
                        ? $receivedQtyMap[$item['medicine_id']] 
                        : (isset($batchQtyMap[$item['medicine_id']]) && $batchQtyMap[$item['medicine_id']] > 0
                            ? $batchQtyMap[$item['medicine_id']]
                            : $item['quantity']);
                    
                    // Get current quantity, reorder_level, and expiration_date for status calculation
                    $getMedicineSql = "SELECT quantity, reorder_level, expiration_date FROM medicines WHERE id = ?";
                    $getMedicineStmt = mysqli_prepare($conn, $getMedicineSql);
                    $currentQuantity = 0;
                    $reorder_level = 10; // Default
                    $expiration_date = null;
                    
                    if ($getMedicineStmt) {
                        mysqli_stmt_bind_param($getMedicineStmt, 'i', $item['medicine_id']);
                        mysqli_stmt_execute($getMedicineStmt);
                        $medicineResult = mysqli_stmt_get_result($getMedicineStmt);
                        if ($medicineRow = mysqli_fetch_assoc($medicineResult)) {
                            $currentQuantity = (int)$medicineRow['quantity'];
                            $reorder_level = (int)$medicineRow['reorder_level'];
                            $expiration_date = $medicineRow['expiration_date'];
                        }
                        mysqli_stmt_close($getMedicineStmt);
                    }
                    
                    // Calculate final quantity and status
                    $finalQuantity = $currentQuantity + $qtyToAdd;
                    $currentDate = date('Y-m-d');
                    $newStatus = 'in-stock';
                    
                    // Check expiration first (highest priority)
                    if ($expiration_date !== null && $expiration_date < $currentDate) {
                        $newStatus = 'expired';
                    }
                    // Out-of-stock ONLY if final quantity is exactly 0
                    elseif ($finalQuantity === 0) {
                        $newStatus = 'out-of-stock';
                    }
                    // Low stock if quantity > 0 and <= reorder_level
                    elseif ($finalQuantity > 0 && $finalQuantity <= $reorder_level) {
                        $newStatus = 'low-stock';
                    }
                    // Otherwise in-stock (already set above)
                    
                    $updateSql = "UPDATE medicines SET 
                        quantity = quantity + ?,
                        status = ?
                        WHERE id = ?";
                    $updateStmt = mysqli_prepare($conn, $updateSql);
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, 'isi', $qtyToAdd, $newStatus, $item['medicine_id']);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                }
                mysqli_stmt_close($itemsStmt);
            }
        }
        // If status changed from completed, reverse the quantity update
        elseif ($old_status === 'completed' && $status !== 'completed') {
            $itemsSql = "SELECT medicine_id, quantity FROM order_items WHERE order_id = ?";
            $itemsStmt = mysqli_prepare($conn, $itemsSql);
            if ($itemsStmt) {
                mysqli_stmt_bind_param($itemsStmt, 'i', $order_id);
                mysqli_stmt_execute($itemsStmt);
                $itemsResult = mysqli_stmt_get_result($itemsStmt);
                
                while ($item = mysqli_fetch_assoc($itemsResult)) {
                    // Get current quantity, reorder_level, and expiration_date for status calculation
                    $getMedicineSql = "SELECT quantity, reorder_level, expiration_date FROM medicines WHERE id = ?";
                    $getMedicineStmt = mysqli_prepare($conn, $getMedicineSql);
                    $currentQuantity = 0;
                    $reorder_level = 10; // Default
                    $expiration_date = null;
                    
                    if ($getMedicineStmt) {
                        mysqli_stmt_bind_param($getMedicineStmt, 'i', $item['medicine_id']);
                        mysqli_stmt_execute($getMedicineStmt);
                        $medicineResult = mysqli_stmt_get_result($getMedicineStmt);
                        if ($medicineRow = mysqli_fetch_assoc($medicineResult)) {
                            $currentQuantity = (int)$medicineRow['quantity'];
                            $reorder_level = (int)$medicineRow['reorder_level'];
                            $expiration_date = $medicineRow['expiration_date'];
                        }
                        mysqli_stmt_close($getMedicineStmt);
                    }
                    
                    // Calculate final quantity and status
                    $finalQuantity = max(0, $currentQuantity - $item['quantity']);
                    $currentDate = date('Y-m-d');
                    $newStatus = 'in-stock';
                    
                    // Check expiration first (highest priority)
                    if ($expiration_date !== null && $expiration_date < $currentDate) {
                        $newStatus = 'expired';
                    }
                    // Out-of-stock ONLY if final quantity is exactly 0
                    elseif ($finalQuantity === 0) {
                        $newStatus = 'out-of-stock';
                    }
                    // Low stock if quantity > 0 and <= reorder_level
                    elseif ($finalQuantity > 0 && $finalQuantity <= $reorder_level) {
                        $newStatus = 'low-stock';
                    }
                    // Otherwise in-stock (already set above)
                    
                    $updateSql = "UPDATE medicines SET 
                        quantity = GREATEST(0, quantity - ?),
                        status = ?
                        WHERE id = ?";
                    $updateStmt = mysqli_prepare($conn, $updateSql);
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, 'isi', $item['quantity'], $newStatus, $item['medicine_id']);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                }
                mysqli_stmt_close($itemsStmt);
            }
        }

        // Build UPDATE statement based on update type and column existence
        if ($statusOnlyUpdate) {
            // Status-only update (for cancel/delivered actions)
            if ($hasNotes && $notes !== null) {
                $updateSql = "UPDATE orders SET 
                    status = ?, 
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    throw new Exception('Database preparation error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($updateStmt, 'ssi', $status, $notes, $order_id);
            } else {
                $updateSql = "UPDATE orders SET 
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    throw new Exception('Database preparation error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($updateStmt, 'si', $status, $order_id);
            }
        } else {
            // Full update (requires supplier_id and order_date)
            if ($hasNotes) {
                $updateSql = "UPDATE orders SET 
                    supplier_id = ?, 
                    order_date = ?, 
                    status = ?, 
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    throw new Exception('Database preparation error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($updateStmt, 'isssi', $supplier_id, $order_date, $status, $notes, $order_id);
            } else {
                $updateSql = "UPDATE orders SET 
                    supplier_id = ?, 
                    order_date = ?, 
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    throw new Exception('Database preparation error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($updateStmt, 'issi', $supplier_id, $order_date, $status, $order_id);
            }
        }
        
        if (!mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            throw new Exception('Failed to update order: ' . mysqli_stmt_error($updateStmt));
        }

        $affectedRows = mysqli_stmt_affected_rows($updateStmt);
        mysqli_stmt_close($updateStmt);

        if ($affectedRows === 0) {
            mysqli_rollback($conn);
            // Check if order exists
            $checkOrderSql = "SELECT id, status FROM orders WHERE id = ?";
            $checkStmt = mysqli_prepare($conn, $checkOrderSql);
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 'i', $order_id);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                if ($checkRow = mysqli_fetch_assoc($checkResult)) {
                    // Order exists but status might already be the same
                    if ($checkRow['status'] === $status) {
                        // Status is already set, so update was successful (no change needed)
                        mysqli_rollback($conn);
                        mysqli_stmt_close($checkStmt);
                        sendJsonResponse(true, 'Order status is already set to ' . $status, ['id' => $order_id, 'status' => $status], 200);
                    } else {
                        mysqli_stmt_close($checkStmt);
                        sendJsonResponse(false, 'No order found with the provided ID or update failed', null, 404);
                    }
                } else {
                    mysqli_stmt_close($checkStmt);
                    sendJsonResponse(false, 'Order not found', null, 404);
                }
            } else {
                sendJsonResponse(false, 'No order found with the provided ID or no changes were made', null, 404);
            }
        }

        // Commit transaction
        mysqli_commit($conn);

        // Fetch updated order
        $selectFields = "o.id, o.supplier_id, s.name as supplier_name, o.order_date, o.status";
        
        if ($hasTotalAmount) {
            $selectFields .= ", o.total_amount";
        } else {
            // Calculate total from order_items if column doesn't exist
            $selectFields .= ", COALESCE((SELECT SUM(quantity * price) FROM order_items WHERE order_id = o.id), 0) as total_amount";
        }
        
        if ($hasNotes) {
            $selectFields .= ", o.notes";
        } else {
            $selectFields .= ", NULL as notes";
        }
        
        $selectFields .= ", o.created_at, o.updated_at";
        
        $selectSql = "SELECT {$selectFields}
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        WHERE o.id = ?";
        
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if ($selectStmt) {
            mysqli_stmt_bind_param($selectStmt, 'i', $order_id);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            $order = mysqli_fetch_assoc($result);
            mysqli_stmt_close($selectStmt);
        }

        sendJsonResponse(true, 'Order updated successfully', $order ?? ['id' => $order_id], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in edit_order.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in edit_order.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

