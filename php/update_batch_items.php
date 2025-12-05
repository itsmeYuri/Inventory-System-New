<?php
/**
 * Update Batch Items API
 * Updates expiration dates for batch items
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

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/archive_helper.php';

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
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get batch ID
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
    if ($batch_id <= 0) {
        sendJsonResponse(false, 'Invalid batch ID', null, 400);
    }

    // Get updates JSON
    $updatesJson = isset($_POST['updates']) ? $_POST['updates'] : '';
    if (empty($updatesJson)) {
        sendJsonResponse(false, 'No updates provided', null, 400);
    }

    $updates = json_decode($updatesJson, true);
    if (!is_array($updates) || empty($updates)) {
        sendJsonResponse(false, 'Invalid updates format', null, 400);
    }

    // Check if batch_items table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    if (mysqli_num_rows($checkTable) === 0) {
        sendJsonResponse(false, 'batch_items table does not exist', null, 500);
    }

    // Verify batch exists
    $checkBatchSql = "SELECT id FROM batches WHERE id = ?";
    $checkBatchStmt = mysqli_prepare($conn, $checkBatchSql);
    if (!$checkBatchStmt) {
        sendJsonResponse(false, 'Database error during batch check', null, 500);
    }

    mysqli_stmt_bind_param($checkBatchStmt, 'i', $batch_id);
    mysqli_stmt_execute($checkBatchStmt);
    $checkBatchResult = mysqli_stmt_get_result($checkBatchStmt);

    if (!$checkBatchResult || mysqli_num_rows($checkBatchResult) === 0) {
        mysqli_stmt_close($checkBatchStmt);
        sendJsonResponse(false, 'Batch not found', null, 404);
    }
    mysqli_stmt_close($checkBatchStmt);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $updatedCount = 0;
        $errors = [];

        // Update each batch item
        foreach ($updates as $update) {
            $item_id = isset($update['item_id']) ? (int)$update['item_id'] : 0;
            $expiration_date = isset($update['expiration_date']) && !empty($update['expiration_date']) 
                ? trim($update['expiration_date']) 
                : null;

            if ($item_id <= 0) {
                $errors[] = 'Invalid item ID in update';
                continue;
            }

            // Get current item state (to check if it was already expired)
            $getItemSql = "SELECT id, medicine_id, quantity, received_quantity, is_expired, expiration_date 
                          FROM batch_items 
                          WHERE id = ? AND batch_id = ?";
            $getItemStmt = mysqli_prepare($conn, $getItemSql);
            if (!$getItemStmt) {
                $errors[] = "Database error getting item $item_id";
                continue;
            }

            mysqli_stmt_bind_param($getItemStmt, 'ii', $item_id, $batch_id);
            mysqli_stmt_execute($getItemStmt);
            $getItemResult = mysqli_stmt_get_result($getItemStmt);

            if (!$getItemResult || mysqli_num_rows($getItemResult) === 0) {
                mysqli_stmt_close($getItemStmt);
                $errors[] = "Item $item_id does not belong to batch $batch_id";
                continue;
            }

            $currentItem = mysqli_fetch_assoc($getItemResult);
            $wasExpired = ($currentItem['is_expired'] == 1);
            $medicine_id = $currentItem['medicine_id'];
            // Use received_quantity if available, otherwise use quantity
            $received_quantity = isset($currentItem['received_quantity']) && $currentItem['received_quantity'] > 0 
                ? (int)$currentItem['received_quantity'] 
                : (isset($currentItem['quantity']) ? (int)$currentItem['quantity'] : 0);
            mysqli_stmt_close($getItemStmt);

            // Update expiration date
            if ($expiration_date !== null) {
                // Validate date format
                $dateParts = explode('-', $expiration_date);
                if (count($dateParts) !== 3 || !checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
                    $errors[] = "Invalid date format for item $item_id: $expiration_date";
                    continue;
                }

                // Update expiration date and is_expired flag based on new date
                $updateSql = "UPDATE batch_items 
                             SET expiration_date = ?, 
                                 updated_at = CURRENT_TIMESTAMP,
                                 is_expired = CASE 
                                     WHEN ? < CURDATE() THEN 1 
                                     ELSE 0 
                                 END,
                                 expired_at = CASE 
                                     WHEN ? < CURDATE() AND expired_at IS NULL THEN CURRENT_TIMESTAMP
                                     WHEN ? >= CURDATE() THEN NULL
                                     ELSE expired_at
                                 END
                             WHERE id = ? AND batch_id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    $errors[] = "Database error preparing update for item $item_id";
                    continue;
                }

                mysqli_stmt_bind_param($updateStmt, 'ssssii', $expiration_date, $expiration_date, $expiration_date, $expiration_date, $item_id, $batch_id);
                if (mysqli_stmt_execute($updateStmt)) {
                    $updatedCount++;
                    
                    // Check if item just became expired (was not expired, now is expired)
                    $isNowExpired = ($expiration_date < date('Y-m-d'));
                    if ($isNowExpired && !$wasExpired && $received_quantity > 0 && $medicine_id > 0) {
                        // Get current medicine info to calculate new status
                        $getMedicineSql = "SELECT quantity, reorder_level, expiration_date 
                                          FROM medicines 
                                          WHERE id = ?";
                        $getMedicineStmt = mysqli_prepare($conn, $getMedicineSql);
                        if ($getMedicineStmt) {
                            mysqli_stmt_bind_param($getMedicineStmt, 'i', $medicine_id);
                            mysqli_stmt_execute($getMedicineStmt);
                            $medicineResult = mysqli_stmt_get_result($getMedicineStmt);
                            
                            if ($medicineResult && mysqli_num_rows($medicineResult) > 0) {
                                $medicine = mysqli_fetch_assoc($medicineResult);
                                $currentQuantity = (int)$medicine['quantity'];
                                $reorder_level = (int)$medicine['reorder_level'];
                                $medicineExpirationDate = $medicine['expiration_date'];
                                
                                // Calculate new quantity after decrement
                                $newQuantity = max(0, $currentQuantity - $received_quantity);
                                
                                // Calculate new status (expiration has highest priority)
                                $currentDate = date('Y-m-d');
                                $newStatus = 'in-stock';
                                
                                if ($medicineExpirationDate !== null && $medicineExpirationDate < $currentDate) {
                                    $newStatus = 'expired';
                                } elseif ($newQuantity === 0) {
                                    $newStatus = 'out-of-stock';
                                } elseif ($newQuantity > 0 && $newQuantity <= $reorder_level) {
                                    $newStatus = 'low-stock';
                                }
                                
                                // Decrement inventory quantity and update status
                                $decrementSql = "UPDATE medicines 
                                                SET quantity = GREATEST(0, quantity - ?),
                                                    status = ?
                                                WHERE id = ?";
                                $decrementStmt = mysqli_prepare($conn, $decrementSql);
                                if ($decrementStmt) {
                                    mysqli_stmt_bind_param($decrementStmt, 'isi', $received_quantity, $newStatus, $medicine_id);
                                    if (!mysqli_stmt_execute($decrementStmt)) {
                                        error_log("Failed to decrement inventory for medicine $medicine_id: " . mysqli_stmt_error($decrementStmt));
                                    }
                                    mysqli_stmt_close($decrementStmt);
                                }
                            }
                            mysqli_stmt_close($getMedicineStmt);
                        }
                    }
                } else {
                    $errors[] = "Failed to update item $item_id: " . mysqli_stmt_error($updateStmt);
                }
                mysqli_stmt_close($updateStmt);
            } else {
                // Set expiration_date to NULL and reset expired status
                $updateSql = "UPDATE batch_items 
                             SET expiration_date = NULL, 
                                 updated_at = CURRENT_TIMESTAMP,
                                 is_expired = 0,
                                 expired_at = NULL
                             WHERE id = ? AND batch_id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                if (!$updateStmt) {
                    $errors[] = "Database error preparing update for item $item_id";
                    continue;
                }

                mysqli_stmt_bind_param($updateStmt, 'ii', $item_id, $batch_id);
                if (mysqli_stmt_execute($updateStmt)) {
                    $updatedCount++;
                } else {
                    $errors[] = "Failed to update item $item_id: " . mysqli_stmt_error($updateStmt);
                }
                mysqli_stmt_close($updateStmt);
            }
        }

        $updateBatchStatusSql = "UPDATE batches b SET b.status = 'expired' WHERE b.id = ? AND EXISTS (SELECT 1 FROM batch_items bi WHERE bi.batch_id = b.id AND bi.is_expired = 1)";
        $updateBatchStmt = mysqli_prepare($conn, $updateBatchStatusSql);
        if ($updateBatchStmt) {
            mysqli_stmt_bind_param($updateBatchStmt, 'i', $batch_id);
            mysqli_stmt_execute($updateBatchStmt);
            mysqli_stmt_close($updateBatchStmt);
        }

        // Archive expired items that were just marked as expired
        $archivedCount = 0;
        try {
            $archivedCount = archiveExpiredItems($conn);
        } catch (Exception $e) {
            error_log("Error archiving expired items: " . $e->getMessage());
            // Don't fail the transaction if archiving fails, but log it
        }

        if (count($errors) > 0 && $updatedCount === 0) {
            mysqli_rollback($conn);
            sendJsonResponse(false, 'Failed to update any items: ' . implode(', ', $errors), ['errors' => $errors], 400);
        }

        // Commit transaction
        mysqli_commit($conn);

        $message = "Successfully updated $updatedCount item(s)";
        if ($archivedCount > 0) {
            $message .= ". $archivedCount expired item(s) archived.";
        }
        if (count($errors) > 0) {
            $message .= ". " . count($errors) . " error(s) occurred.";
        }

        sendJsonResponse(true, $message, [
            'updated_count' => $updatedCount,
            'archived_count' => $archivedCount,
            'errors' => $errors
        ], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in update_batch_items.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in update_batch_items.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

