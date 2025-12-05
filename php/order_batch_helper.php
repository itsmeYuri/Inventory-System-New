<?php
/**
 * Order Batch Helper Functions
 * Handles batch creation for orders - groups orders by date (one batch per day)
 */

/**
 * Generate a unique batch number for a date
 * Format: BATCH-YYYYMMDD (e.g., BATCH-20241215)
 * 
 * @param mysqli $conn Database connection
 * @param string $order_date Order date in YYYY-MM-DD format
 * @return string Unique batch number
 */
function generateDailyBatchNumber($conn, $order_date) {
    // Format: BATCH-YYYYMMDD
    $datePrefix = date('Ymd', strtotime($order_date));
    return "BATCH-{$datePrefix}";
}

function generateOrderBatchNumber($conn, $order_date, $order_id) {
    $datePrefix = date('Ymd', strtotime($order_date));
    return "BATCH-{$datePrefix}-O{$order_id}";
}

/**
 * Get or create a batch for a specific date
 * One batch per day - all orders from the same day share the same batch
 * 
 * @param mysqli $conn Database connection
 * @param string $order_date Order date in YYYY-MM-DD format
 * @return int|false Batch ID on success, false on failure
 */
function getOrCreateDailyBatch($conn, $order_date) {
    // Check if batches table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
    if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
        error_log("Batches table does not exist. Please run create_batches_tables.php first.");
        return false;
    }
    
    // Fix AUTO_INCREMENT issues in batches table
    // Delete any batches with id = 0 first
    @mysqli_query($conn, "DELETE FROM batches WHERE id = 0");
    
    // Get max ID and set AUTO_INCREMENT properly
    $maxBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batches");
    $maxBatchId = 0;
    if ($maxBatchIdQuery) {
        $maxBatchRow = mysqli_fetch_assoc($maxBatchIdQuery);
        $maxBatchId = (int)($maxBatchRow['max_id'] ?? 0);
    }
    $nextBatchId = max(1, $maxBatchId + 1);
    
    // Set AUTO_INCREMENT to ensure it's working
    @mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextBatchId}");
    
    // Verify AUTO_INCREMENT is set correctly
    $checkAutoInc = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'batches'");
    if ($checkAutoInc) {
        $statusRow = mysqli_fetch_assoc($checkAutoInc);
        $currentAutoInc = (int)($statusRow['Auto_increment'] ?? 0);
        if ($currentAutoInc <= $maxBatchId) {
            error_log("Fixing AUTO_INCREMENT: current={$currentAutoInc}, max={$maxBatchId}, setting to {$nextBatchId}");
            @mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextBatchId}");
        }
    }
    
    // Generate batch number for this date
    $batch_number = generateDailyBatchNumber($conn, $order_date);
    
    // Check if batch already exists for this date (check by batch_number or by date)
    $checkBatchSql = "SELECT id FROM batches WHERE batch_number = ? OR DATE(created_date) = DATE(?) LIMIT 1";
    $checkBatchStmt = mysqli_prepare($conn, $checkBatchSql);
    
    if ($checkBatchStmt) {
        mysqli_stmt_bind_param($checkBatchStmt, 'ss', $batch_number, $order_date);
        mysqli_stmt_execute($checkBatchStmt);
        $checkResult = mysqli_stmt_get_result($checkBatchStmt);
        
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $batchRow = mysqli_fetch_assoc($checkResult);
            mysqli_stmt_close($checkBatchStmt);
            return (int)$batchRow['id'];
        }
        mysqli_stmt_close($checkBatchStmt);
    }
    
    // Batch doesn't exist, create it
    // Get the first supplier_id from orders on this date (or use 0 if none)
    $supplierSql = "SELECT supplier_id FROM orders WHERE DATE(order_date) = ? LIMIT 1";
    $supplierStmt = mysqli_prepare($conn, $supplierSql);
    $supplier_id = 0;
    
    if ($supplierStmt) {
        mysqli_stmt_bind_param($supplierStmt, 's', $order_date);
        mysqli_stmt_execute($supplierStmt);
        $supplierResult = mysqli_stmt_get_result($supplierStmt);
        if ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
            $supplier_id = (int)$supplierRow['supplier_id'];
        }
        mysqli_stmt_close($supplierStmt);
    }
    
    // Insert new batch for this date
    $batchSql = "INSERT INTO batches (batch_number, order_id, supplier_id, created_date, status) 
                 VALUES (?, NULL, ?, ?, 'active')";
    $batchStmt = mysqli_prepare($conn, $batchSql);
    
    if (!$batchStmt) {
        $error = mysqli_error($conn);
        error_log("Batch insert prepare error: " . $error);
        return false;
    }
    
    mysqli_stmt_bind_param($batchStmt, 'sis', $batch_number, $supplier_id, $order_date);
    
    error_log("Attempting to create batch: batch_number={$batch_number}, supplier_id={$supplier_id}, created_date={$order_date}");
    
    if (!mysqli_stmt_execute($batchStmt)) {
        $error = mysqli_stmt_error($batchStmt);
        error_log("Failed to create daily batch with prepared statement: " . $error);
        mysqli_stmt_close($batchStmt);
        
        // Always try raw SQL as fallback
        // Fix AUTO_INCREMENT first
        @mysqli_query($conn, "DELETE FROM batches WHERE id = 0");
        $maxBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batches");
        $maxBatchId = 0;
        if ($maxBatchIdQuery) {
            $maxBatchRow = mysqli_fetch_assoc($maxBatchIdQuery);
            $maxBatchId = (int)($maxBatchRow['max_id'] ?? 0);
        }
        $nextBatchId = max(1, $maxBatchId + 1);
        @mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextBatchId}");
        
        // Retry with raw SQL
        $batch_number_escaped = mysqli_real_escape_string($conn, $batch_number);
        $supplier_id_escaped = (int)$supplier_id;
        $order_date_escaped = mysqli_real_escape_string($conn, $order_date);
        
        $rawBatchSql = "INSERT INTO batches (batch_number, order_id, supplier_id, created_date, status) 
                       VALUES ('{$batch_number_escaped}', NULL, {$supplier_id_escaped}, '{$order_date_escaped}', 'active')";
        
        if (!mysqli_query($conn, $rawBatchSql)) {
            $rawError = mysqli_error($conn);
            error_log("Failed to create daily batch with raw SQL: " . $rawError);
            return false;
        }
        $batch_id = mysqli_insert_id($conn);
    } else {
        $batch_id = mysqli_insert_id($conn);
    }
    
    mysqli_stmt_close($batchStmt);
    
    // Verify we got a valid batch ID
    if ($batch_id <= 0) {
        $lastBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as last_id FROM batches WHERE batch_number = '{$batch_number}'");
        if ($lastBatchIdQuery) {
            $lastBatchRow = mysqli_fetch_assoc($lastBatchIdQuery);
            $batch_id = (int)($lastBatchRow['last_id'] ?? 0);
        }
        
        if ($batch_id <= 0) {
            error_log("Failed to get batch ID after insert");
            return false;
        }
    }
    
    return $batch_id;
}

function getOrCreateOrderBatch($conn, $order_id, $supplier_id, $order_date) {
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
    if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
        return false;
    }

    @mysqli_query($conn, "DELETE FROM batches WHERE id = 0");
    $maxBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batches");
    $maxBatchId = 0;
    if ($maxBatchIdQuery) {
        $maxBatchRow = mysqli_fetch_assoc($maxBatchIdQuery);
        $maxBatchId = (int)($maxBatchRow['max_id'] ?? 0);
    }
    $nextBatchId = max(1, $maxBatchId + 1);
    @mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextBatchId}");

    $batch_number = generateOrderBatchNumber($conn, $order_date, $order_id);

    $checkBatchSql = "SELECT id FROM batches WHERE order_id = ? OR batch_number = ? LIMIT 1";
    $checkBatchStmt = mysqli_prepare($conn, $checkBatchSql);
    if ($checkBatchStmt) {
        mysqli_stmt_bind_param($checkBatchStmt, 'is', $order_id, $batch_number);
        mysqli_stmt_execute($checkBatchStmt);
        $checkResult = mysqli_stmt_get_result($checkBatchStmt);
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $batchRow = mysqli_fetch_assoc($checkResult);
            mysqli_stmt_close($checkBatchStmt);
            return (int)$batchRow['id'];
        }
        mysqli_stmt_close($checkBatchStmt);
    }

    $batchSql = "INSERT INTO batches (batch_number, order_id, supplier_id, created_date, status) VALUES (?, ?, ?, ?, 'active')";
    $batchStmt = mysqli_prepare($conn, $batchSql);
    if (!$batchStmt) {
        return false;
    }
    mysqli_stmt_bind_param($batchStmt, 'siis', $batch_number, $order_id, $supplier_id, $order_date);
    if (!mysqli_stmt_execute($batchStmt)) {
        mysqli_stmt_close($batchStmt);
        $batch_number_escaped = mysqli_real_escape_string($conn, $batch_number);
        $supplier_id_escaped = (int)$supplier_id;
        $order_date_escaped = mysqli_real_escape_string($conn, $order_date);
        $rawBatchSql = "INSERT INTO batches (batch_number, order_id, supplier_id, created_date, status) VALUES ('{$batch_number_escaped}', {$order_id}, {$supplier_id_escaped}, '{$order_date_escaped}', 'active')";
        if (!mysqli_query($conn, $rawBatchSql)) {
            return false;
        }
        $batch_id = mysqli_insert_id($conn);
    } else {
        $batch_id = mysqli_insert_id($conn);
    }
    mysqli_stmt_close($batchStmt);
    if ($batch_id <= 0) {
        $lastBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as last_id FROM batches WHERE batch_number = '{$batch_number}'");
        if ($lastBatchIdQuery) {
            $lastBatchRow = mysqli_fetch_assoc($lastBatchIdQuery);
            $batch_id = (int)($lastBatchRow['last_id'] ?? 0);
        }
        if ($batch_id <= 0) {
            return false;
        }
    }
    return $batch_id;
}

/**
 * Add order items to the daily batch for the order date
 * Groups all orders from the same day into one batch
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param int $supplier_id Supplier ID
 * @param string $order_date Order date in YYYY-MM-DD format
 * @param array $items Order items with medicine_id, quantity
 * @return bool True on success, false on failure
 */
function addOrderToDailyBatch($conn, $order_id, $supplier_id, $order_date, $items) {
    // Validate inputs
    if (!$conn) {
        error_log("addOrderToDailyBatch: Invalid database connection");
        return false;
    }
    
    if ($order_id <= 0) {
        error_log("addOrderToDailyBatch: Invalid order_id: {$order_id}");
        return false;
    }
    
    if (empty($order_date)) {
        error_log("addOrderToDailyBatch: Empty order_date");
        return false;
    }
    
    if (empty($items) || !is_array($items)) {
        error_log("addOrderToDailyBatch: No items provided or items is not an array");
        return false;
    }
    
    // Check if batch_items table exists
    $checkBatchItemsTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    if (!$checkBatchItemsTable || mysqli_num_rows($checkBatchItemsTable) === 0) {
        error_log("Batch_items table does not exist. Please run create_batches_tables.php first.");
        return false;
    }
    
    // Get or create batch for this date
    try {
        $batch_id = getOrCreateOrderBatch($conn, $order_id, $supplier_id, $order_date);
        if ($batch_id === false) {
            error_log("Failed to get or create daily batch for date {$order_date}");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in getOrCreateDailyBatch: " . $e->getMessage());
        return false;
    }
    
    // Fix AUTO_INCREMENT issues in batch_items table
    @mysqli_query($conn, "DELETE FROM batch_items WHERE id = 0");
    $maxBatchItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batch_items");
    $maxBatchItemId = 0;
    if ($maxBatchItemIdQuery) {
        $maxBatchItemRow = mysqli_fetch_assoc($maxBatchItemIdQuery);
        $maxBatchItemId = (int)($maxBatchItemRow['max_id'] ?? 0);
    }
    $nextBatchItemId = max(1, $maxBatchItemId + 1);
    @mysqli_query($conn, "ALTER TABLE batch_items AUTO_INCREMENT = {$nextBatchItemId}");
    
    $itemSql = "INSERT INTO batch_items (batch_id, medicine_id, quantity, expiration_date, received_quantity) 
                VALUES (?, ?, ?, ?, ?)";
    
    $itemsInserted = 0;
    foreach ($items as $item) {
        $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $expiration_date = isset($item['expiration_date']) ? trim($item['expiration_date']) : null;
        $received_quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        
        if ($medicine_id <= 0 || $quantity <= 0) {
            error_log("Skipping invalid batch item: medicine_id={$medicine_id}, quantity={$quantity}");
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
        
        // Prepare a new statement for each item to avoid reuse issues
        $itemStmt = mysqli_prepare($conn, $itemSql);
        if (!$itemStmt) {
            error_log("Failed to prepare batch item statement for medicine_id={$medicine_id}: " . mysqli_error($conn));
            continue;
        }
        
        mysqli_stmt_bind_param($itemStmt, 'iiisi', $batch_id, $medicine_id, $quantity, $expiration_date, $received_quantity);
        if (!mysqli_stmt_execute($itemStmt)) {
            $error = mysqli_stmt_error($itemStmt);
            error_log("Failed to insert batch item for medicine_id={$medicine_id}: " . $error);
            
            // Check if it's the "Duplicate entry '0'" error
            if (strpos($error, "Duplicate entry '0'") !== false) {
                // Fix AUTO_INCREMENT and try again with raw SQL
                @mysqli_query($conn, "DELETE FROM batch_items WHERE id = 0");
                $maxBatchItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batch_items");
                $maxBatchItemId = 0;
                if ($maxBatchItemIdQuery) {
                    $maxBatchItemRow = mysqli_fetch_assoc($maxBatchItemIdQuery);
                    $maxBatchItemId = (int)($maxBatchItemRow['max_id'] ?? 0);
                }
                $nextBatchItemId = max(1, $maxBatchItemId + 1);
                @mysqli_query($conn, "ALTER TABLE batch_items AUTO_INCREMENT = {$nextBatchItemId}");
                
                // Retry with raw SQL
                $batch_id_escaped = (int)$batch_id;
                $medicine_id_escaped = (int)$medicine_id;
                $quantity_escaped = (int)$quantity;
                $expiration_date_escaped = $expiration_date ? "'" . mysqli_real_escape_string($conn, $expiration_date) . "'" : "NULL";
                $received_quantity_escaped = (int)$received_quantity;
                
                $rawItemSql = "INSERT INTO batch_items (batch_id, medicine_id, quantity, expiration_date, received_quantity) 
                              VALUES ({$batch_id_escaped}, {$medicine_id_escaped}, {$quantity_escaped}, {$expiration_date_escaped}, {$received_quantity_escaped})";
                
                if (mysqli_query($conn, $rawItemSql)) {
                    $itemsInserted++;
                    error_log("Successfully inserted batch item using raw SQL for medicine_id={$medicine_id}");
                } else {
                    error_log("Failed to insert batch item using raw SQL for medicine_id={$medicine_id}: " . mysqli_error($conn));
                }
                } else {
            
                }
            }
            
            mysqli_stmt_close($itemStmt);
            continue;
        }
        
        $itemsInserted++;
        mysqli_stmt_close($itemStmt);
    }
    
    @mysqli_query($conn, "UPDATE batches b SET b.status='expired' WHERE b.id={$batch_id} AND EXISTS (SELECT 1 FROM batch_items bi WHERE bi.batch_id={$batch_id} AND bi.expiration_date < CURDATE())");
    error_log("Added {$itemsInserted} items from order {$order_id} to daily batch {$batch_id} for date {$order_date}");
    return true;
}

/**
 * Add order items to batch when order is confirmed
 * Items are added to the batch for the order_date (not confirmation date)
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param string $order_date Order date in YYYY-MM-DD format (used to find the batch)
 * @param array $items Order items with medicine_id, quantity, expiration_date, received_quantity
 * @return bool True on success, false on failure
 */
function addOrderItemsToBatch($conn, $order_id, $supplier_id, $order_date, $items) {
    // Validate inputs
    if (!$conn) {
        error_log("addOrderItemsToBatch: Invalid database connection");
        return false;
    }
    
    if ($order_id <= 0) {
        error_log("addOrderItemsToBatch: Invalid order_id: {$order_id}");
        return false;
    }
    
    if (empty($order_date)) {
        error_log("addOrderItemsToBatch: Empty order_date");
        return false;
    }
    
    if (empty($items) || !is_array($items)) {
        error_log("addOrderItemsToBatch: No items provided or items is not an array");
        return false;
    }
    
    // Check if batch_items table exists
    $checkBatchItemsTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
    if (!$checkBatchItemsTable || mysqli_num_rows($checkBatchItemsTable) === 0) {
        error_log("Batch_items table does not exist. Please run create_batches_tables.php first.");
        return false;
    }
    
    // Get batch for this order date (batch is created when order is created, grouped by order_date)
<<<<<<< HEAD
    try {
        $batch_id = getOrCreateDailyBatch($conn, $order_date);
        if ($batch_id === false) {
            error_log("Failed to get or create daily batch for date {$order_date}");
            return false;
        }
        
        // Update the batch's order_id and supplier_id for this specific order
        // Get the supplier_id from the current order
        $orderSupplierSql = "SELECT supplier_id FROM orders WHERE id = ? LIMIT 1";
        $orderSupplierStmt = mysqli_prepare($conn, $orderSupplierSql);
        $current_supplier_id = 0;
        
        if ($orderSupplierStmt) {
            mysqli_stmt_bind_param($orderSupplierStmt, 'i', $order_id);
            mysqli_stmt_execute($orderSupplierStmt);
            $orderSupplierResult = mysqli_stmt_get_result($orderSupplierStmt);
            if ($orderSupplierRow = mysqli_fetch_assoc($orderSupplierResult)) {
                $current_supplier_id = (int)($orderSupplierRow['supplier_id'] ?? 0);
            }
            mysqli_stmt_close($orderSupplierStmt);
        }
        
        // Update batch: set order_id if NULL or 0, and update supplier_id to match current order
        // Use a more explicit update that handles NULL and 0 values
        $updateBatchSql = "UPDATE batches SET 
                            order_id = CASE 
                                WHEN order_id IS NULL OR order_id = 0 THEN ? 
                                ELSE order_id 
                            END,
                            supplier_id = ?
                          WHERE id = ?";
        $updateBatchStmt = mysqli_prepare($conn, $updateBatchSql);
        if ($updateBatchStmt) {
            mysqli_stmt_bind_param($updateBatchStmt, 'iii', $order_id, $current_supplier_id, $batch_id);
            if (mysqli_stmt_execute($updateBatchStmt)) {
                $affected = mysqli_stmt_affected_rows($updateBatchStmt);
                if ($affected > 0) {
                    error_log("Updated batch {$batch_id} with order_id {$order_id} and supplier_id {$current_supplier_id}");
                } else {
                    // Check current batch state
                    $checkBatchSql = "SELECT order_id, supplier_id FROM batches WHERE id = ?";
                    $checkStmt = mysqli_prepare($conn, $checkBatchSql);
                    if ($checkStmt) {
                        mysqli_stmt_bind_param($checkStmt, 'i', $batch_id);
                        mysqli_stmt_execute($checkStmt);
                        $checkResult = mysqli_stmt_get_result($checkStmt);
                        if ($checkRow = mysqli_fetch_assoc($checkResult)) {
                            error_log("Batch {$batch_id} current state: order_id={$checkRow['order_id']}, supplier_id={$checkRow['supplier_id']}");
                        }
                        mysqli_stmt_close($checkStmt);
                    }
                }
            } else {
                error_log("Failed to update batch: " . mysqli_stmt_error($updateBatchStmt));
            }
            mysqli_stmt_close($updateBatchStmt);
        } else {
            error_log("Failed to prepare batch update statement: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
=======
    try {
        $batch_id = getOrCreateOrderBatch($conn, $order_id, $supplier_id, $order_date);
        if ($batch_id === false) {
            error_log("Failed to get or create daily batch for date {$order_date}");
            return false;
        }
    } catch (Exception $e) {
>>>>>>> b1ac2c0f0564fadcaa4501139af395f878d091a7
        error_log("Exception in getOrCreateDailyBatch: " . $e->getMessage());
        return false;
    }
    
    // Check if items from this order are already in the batch (avoid duplicates)
    $checkExistingSql = "SELECT COUNT(*) as count FROM batch_items bi
                        INNER JOIN batches b ON bi.batch_id = b.id
                        WHERE b.id = ? AND bi.medicine_id = ?";
    
    // Fix AUTO_INCREMENT issues in batch_items table
    @mysqli_query($conn, "DELETE FROM batch_items WHERE id = 0");
    $maxBatchItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batch_items");
    $maxBatchItemId = 0;
    if ($maxBatchItemIdQuery) {
        $maxBatchItemRow = mysqli_fetch_assoc($maxBatchItemIdQuery);
        $maxBatchItemId = (int)($maxBatchItemRow['max_id'] ?? 0);
    }
    $nextBatchItemId = max(1, $maxBatchItemId + 1);
    @mysqli_query($conn, "ALTER TABLE batch_items AUTO_INCREMENT = {$nextBatchItemId}");
    
    $itemSql = "INSERT INTO batch_items (batch_id, medicine_id, quantity, expiration_date, received_quantity) 
                VALUES (?, ?, ?, ?, ?)";
    
    $itemsInserted = 0;
    foreach ($items as $item) {
        $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $expiration_date = isset($item['expiration_date']) ? trim($item['expiration_date']) : null;
        $received_quantity = isset($item['received_quantity']) ? (int)$item['received_quantity'] : $quantity;
        
        if ($medicine_id <= 0 || $quantity <= 0) {
            error_log("Skipping invalid batch item: medicine_id={$medicine_id}, quantity={$quantity}");
            continue;
        }
        
        // Check if this item from this order is already in the batch
        $checkExistingStmt = mysqli_prepare($conn, $checkExistingSql);
        $alreadyExists = false;
        if ($checkExistingStmt) {
            mysqli_stmt_bind_param($checkExistingStmt, 'ii', $batch_id, $medicine_id);
            mysqli_stmt_execute($checkExistingStmt);
            $checkExistingResult = mysqli_stmt_get_result($checkExistingStmt);
            if ($checkExistingRow = mysqli_fetch_assoc($checkExistingResult)) {
                // Note: We allow multiple items with same medicine_id from different orders in same batch
                // So we don't check for duplicates - each order's items are added separately
            }
            mysqli_stmt_close($checkExistingStmt);
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
        
        // Prepare a new statement for each item to avoid reuse issues
        $itemStmt = mysqli_prepare($conn, $itemSql);
        if (!$itemStmt) {
            error_log("Failed to prepare batch item statement for medicine_id={$medicine_id}: " . mysqli_error($conn));
            continue;
        }
        
        mysqli_stmt_bind_param($itemStmt, 'iiisi', $batch_id, $medicine_id, $quantity, $expiration_date, $received_quantity);
        if (!mysqli_stmt_execute($itemStmt)) {
            $error = mysqli_stmt_error($itemStmt);
            error_log("Failed to insert batch item for medicine_id={$medicine_id}: " . $error);
            
            // Check if it's the "Duplicate entry '0'" error
            if (strpos($error, "Duplicate entry '0'") !== false) {
                // Fix AUTO_INCREMENT and try again with raw SQL
                @mysqli_query($conn, "DELETE FROM batch_items WHERE id = 0");
                $maxBatchItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batch_items");
                $maxBatchItemId = 0;
                if ($maxBatchItemIdQuery) {
                    $maxBatchItemRow = mysqli_fetch_assoc($maxBatchItemIdQuery);
                    $maxBatchItemId = (int)($maxBatchItemRow['max_id'] ?? 0);
                }
                $nextBatchItemId = max(1, $maxBatchItemId + 1);
                @mysqli_query($conn, "ALTER TABLE batch_items AUTO_INCREMENT = {$nextBatchItemId}");
                
                // Retry with raw SQL
                $batch_id_escaped = (int)$batch_id;
                $medicine_id_escaped = (int)$medicine_id;
                $quantity_escaped = (int)$quantity;
                $expiration_date_escaped = $expiration_date ? "'" . mysqli_real_escape_string($conn, $expiration_date) . "'" : "NULL";
                $received_quantity_escaped = (int)$received_quantity;
                
                $rawItemSql = "INSERT INTO batch_items (batch_id, medicine_id, quantity, expiration_date, received_quantity) 
                              VALUES ({$batch_id_escaped}, {$medicine_id_escaped}, {$quantity_escaped}, {$expiration_date_escaped}, {$received_quantity_escaped})";
                
                if (mysqli_query($conn, $rawItemSql)) {
                    $itemsInserted++;
                    error_log("Successfully inserted batch item using raw SQL for medicine_id={$medicine_id}");
                } else {
                    error_log("Failed to insert batch item using raw SQL for medicine_id={$medicine_id}: " . mysqli_error($conn));
                }
            }
            
            mysqli_stmt_close($itemStmt);
            continue;
        }
        
        $itemsInserted++;
        mysqli_stmt_close($itemStmt);
    }
    
    @mysqli_query($conn, "UPDATE batches b SET b.status='expired' WHERE b.id={$batch_id} AND EXISTS (SELECT 1 FROM batch_items bi WHERE bi.batch_id={$batch_id} AND bi.expiration_date < CURDATE())");
    error_log("Added {$itemsInserted} items from confirmed order {$order_id} to batch {$batch_id} for order date {$order_date}");
    return true;
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
