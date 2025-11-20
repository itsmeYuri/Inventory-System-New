<?php
/**
 * Order Batch Helper Functions
 * Handles batch creation for orders
 */

/**
 * Generate a unique batch number for an order
 * Format: BATCH-YYYYMMDD-XXX (e.g., BATCH-20241215-001)
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @return string Unique batch number
 */
function generateOrderBatchNumber($conn, $order_id) {
    $datePrefix = date('Ymd'); // YYYYMMDD format
    
    // Find the highest sequence number for today
    $checkSql = "SELECT batch_number FROM batches 
                 WHERE batch_number LIKE ? 
                 ORDER BY batch_number DESC 
                 LIMIT 1";
    $pattern = "BATCH-{$datePrefix}-%";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    
    if (!$checkStmt) {
        error_log("Batch number check prepare error: " . mysqli_error($conn));
        // Fallback to simple format
        return "BATCH-{$datePrefix}-{$order_id}";
    }
    
    mysqli_stmt_bind_param($checkStmt, 's', $pattern);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    
    $sequence = 1;
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastBatch = $row['batch_number'];
        // Extract sequence number from format BATCH-YYYYMMDD-XXX
        if (preg_match('/BATCH-\d+-(\d+)$/', $lastBatch, $matches)) {
            $sequence = (int)$matches[1] + 1;
        }
    }
    mysqli_stmt_close($checkStmt);
    
    // Format: BATCH-YYYYMMDD-XXX (3-digit sequence)
    return sprintf("BATCH-%s-%03d", $datePrefix, $sequence);
}

/**
 * Create a batch for an order
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param int $supplier_id Supplier ID
 * @param string $order_date Order date
 * @param array $items Order items with medicine_id, quantity, expiration_date
 * @return int|false Batch ID on success, false on failure
 */
function createOrderBatch($conn, $order_id, $supplier_id, $order_date, $items) {
    // Check if batches table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
    if (mysqli_num_rows($checkTable) === 0) {
        error_log("Batches table does not exist. Please run create_batches_tables.php first.");
        return false;
    }
    
    // Generate unique batch number
    $batch_number = generateOrderBatchNumber($conn, $order_id);
    
    // Insert batch
    $batchSql = "INSERT INTO batches (batch_number, order_id, supplier_id, created_date, status) 
                 VALUES (?, ?, ?, ?, 'active')";
    $batchStmt = mysqli_prepare($conn, $batchSql);
    
    if (!$batchStmt) {
        error_log("Batch insert prepare error: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($batchStmt, 'siis', $batch_number, $order_id, $supplier_id, $order_date);
    
    if (!mysqli_stmt_execute($batchStmt)) {
        error_log("Failed to create batch: " . mysqli_stmt_error($batchStmt));
        mysqli_stmt_close($batchStmt);
        return false;
    }
    
    $batch_id = mysqli_insert_id($conn);
    mysqli_stmt_close($batchStmt);
    
    // Insert batch items
    if (!empty($items) && $batch_id > 0) {
        $itemSql = "INSERT INTO batch_items (batch_id, medicine_id, quantity, expiration_date, received_quantity) 
                    VALUES (?, ?, ?, ?, ?)";
        $itemStmt = mysqli_prepare($conn, $itemSql);
        
        if ($itemStmt) {
            foreach ($items as $item) {
                $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                $expiration_date = isset($item['expiration_date']) ? trim($item['expiration_date']) : null;
                $received_quantity = isset($item['received_quantity']) ? (int)$item['received_quantity'] : $quantity;
                
                if ($medicine_id <= 0 || $quantity <= 0) {
                    continue;
                }
                
                // If expiration_date is empty, try to get it from medicines table
                if (empty($expiration_date)) {
                    $medSql = "SELECT expiration_date FROM medicines WHERE id = ?";
                    $medStmt = mysqli_prepare($conn, $medSql);
                    if ($medStmt) {
                        mysqli_stmt_bind_param($medStmt, 'i', $medicine_id);
                        mysqli_stmt_execute($medStmt);
                        $medResult = mysqli_stmt_get_result($medStmt);
                        if ($medRow = mysqli_fetch_assoc($medResult)) {
                            $expiration_date = $medRow['expiration_date'];
                        }
                        mysqli_stmt_close($medStmt);
                    }
                }
                
                mysqli_stmt_bind_param($itemStmt, 'iiisi', $batch_id, $medicine_id, $quantity, $expiration_date, $received_quantity);
                mysqli_stmt_execute($itemStmt);
            }
            mysqli_stmt_close($itemStmt);
        }
    }
    
    return $batch_id;
}

/**
 * Process expired batch items and decrement inventory
 * 
 * @param mysqli $conn Database connection
 * @return array Statistics about processed items
 */
function processExpiredBatchItems($conn) {
    $stats = [
        'processed' => 0,
        'decremented' => 0,
        'errors' => 0
    ];
    
    // Check if batch_items table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    if (mysqli_num_rows($checkTable) === 0) {
        return $stats;
    }
    
    $currentDate = date('Y-m-d');
    
    // Find expired items that haven't been processed yet
    $expiredSql = "SELECT bi.id, bi.batch_id, bi.medicine_id, bi.quantity, bi.received_quantity, bi.is_expired
                    FROM batch_items bi
                    WHERE bi.expiration_date < ? 
                    AND bi.is_expired = 0
                    AND bi.received_quantity > 0";
    
    $expiredStmt = mysqli_prepare($conn, $expiredSql);
    if (!$expiredStmt) {
        error_log("Expired items query prepare error: " . mysqli_error($conn));
        return $stats;
    }
    
    mysqli_stmt_bind_param($expiredStmt, 's', $currentDate);
    mysqli_stmt_execute($expiredStmt);
    $result = mysqli_stmt_get_result($expiredStmt);
    
    while ($item = mysqli_fetch_assoc($result)) {
        $stats['processed']++;
        
        // Decrement medicine quantity
        $decrementQty = $item['received_quantity'];
        $updateSql = "UPDATE medicines SET quantity = GREATEST(0, quantity - ?) WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'ii', $decrementQty, $item['medicine_id']);
            if (mysqli_stmt_execute($updateStmt)) {
                $stats['decremented']++;
                
                // Mark item as expired
                $markExpiredSql = "UPDATE batch_items SET is_expired = 1, expired_at = CURRENT_TIMESTAMP WHERE id = ?";
                $markStmt = mysqli_prepare($conn, $markExpiredSql);
                if ($markStmt) {
                    mysqli_stmt_bind_param($markStmt, 'i', $item['id']);
                    mysqli_stmt_execute($markStmt);
                    mysqli_stmt_close($markStmt);
                }
            } else {
                $stats['errors']++;
            }
            mysqli_stmt_close($updateStmt);
        } else {
            $stats['errors']++;
        }
    }
    
    mysqli_stmt_close($expiredStmt);
    
    // Update batch status if all items are expired
    $updateBatchStatusSql = "UPDATE batches b
                             SET b.status = 'expired'
                             WHERE b.status = 'active'
                             AND NOT EXISTS (
                                 SELECT 1 FROM batch_items bi 
                                 WHERE bi.batch_id = b.id 
                                 AND bi.is_expired = 0
                             )";
    mysqli_query($conn, $updateBatchStatusSql);
    
    return $stats;
}

?>

