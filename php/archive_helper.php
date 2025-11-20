<?php
/**
 * Archive Helper Functions
 * Functions to archive deleted items, cancelled orders, and expired items
 */

require_once __DIR__ . '/conn.php';

/**
 * Archive a deleted medicine
 */
function archiveMedicine($conn, $medicine_id, $deleted_by = null, $reason = null) {
    // First, get the medicine data
    $sql = "SELECT * FROM medicines WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $medicine_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $medicine = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Insert into archive table
    $archiveSql = "INSERT INTO archived_medicines 
                    (original_id, ndc, name, manufacturer, category, dosage_form, price, quantity, description, deleted_by, reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $archiveStmt = mysqli_prepare($conn, $archiveSql);
    if (!$archiveStmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($archiveStmt, 'isssssdssss',
        $medicine['id'],
        $medicine['ndc'],
        $medicine['name'],
        $medicine['manufacturer'],
        $medicine['category'],
        $medicine['dosage_form'],
        $medicine['price'],
        $medicine['quantity'],
        $medicine['description'] ?? null,
        $deleted_by,
        $reason
    );
    
    $success = mysqli_stmt_execute($archiveStmt);
    mysqli_stmt_close($archiveStmt);
    
    return $success;
}

/**
 * Archive a cancelled order
 */
function archiveOrder($conn, $order_id, $cancelled_by = null, $reason = null) {
    // Get order data
    $orderSql = "SELECT o.*, s.name as supplier_name 
                 FROM orders o 
                 LEFT JOIN suppliers s ON o.supplier_id = s.id 
                 WHERE o.id = ?";
    $stmt = mysqli_prepare($conn, $orderSql);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Insert into archived_orders
    $archiveSql = "INSERT INTO archived_orders 
                    (original_id, supplier_id, supplier_name, order_date, total_amount, notes, cancellation_reason, original_status, cancelled_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $archiveStmt = mysqli_prepare($conn, $archiveSql);
    if (!$archiveStmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($archiveStmt, 'iisssssss',
        $order['id'],
        $order['supplier_id'],
        $order['supplier_name'],
        $order['order_date'],
        $order['total_amount'],
        $order['notes'],
        $reason,
        $order['status'],
        $cancelled_by
    );
    
    if (!mysqli_stmt_execute($archiveStmt)) {
        mysqli_stmt_close($archiveStmt);
        return false;
    }
    
    $archived_order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($archiveStmt);
    
    // Archive order items
    $itemsSql = "SELECT oi.*, m.name as medicine_name 
                 FROM order_items oi 
                 LEFT JOIN medicines m ON oi.medicine_id = m.id 
                 WHERE oi.order_id = ?";
    $itemsStmt = mysqli_prepare($conn, $itemsSql);
    if ($itemsStmt) {
        mysqli_stmt_bind_param($itemsStmt, 'i', $order_id);
        mysqli_stmt_execute($itemsStmt);
        $itemsResult = mysqli_stmt_get_result($itemsStmt);
        
        $itemArchiveSql = "INSERT INTO archived_order_items 
                           (archived_order_id, original_item_id, medicine_id, medicine_name, quantity, price)
                           VALUES (?, ?, ?, ?, ?, ?)";
        $itemArchiveStmt = mysqli_prepare($conn, $itemArchiveSql);
        
        if ($itemArchiveStmt) {
            while ($item = mysqli_fetch_assoc($itemsResult)) {
                mysqli_stmt_bind_param($itemArchiveStmt, 'iiisdd',
                    $archived_order_id,
                    $item['id'],
                    $item['medicine_id'],
                    $item['medicine_name'],
                    $item['quantity'],
                    $item['price']
                );
                mysqli_stmt_execute($itemArchiveStmt);
            }
            mysqli_stmt_close($itemArchiveStmt);
        }
        
        mysqli_stmt_close($itemsStmt);
    }
    
    return true;
}

/**
 * Archive expired batch items
 */
function archiveExpiredItems($conn) {
    // Check if archived_expired_items table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'archived_expired_items'");
    if (mysqli_num_rows($checkTable) === 0) {
        error_log("archived_expired_items table does not exist");
        return false;
    }
    
    // Get expired batch items that haven't been archived yet
    $sql = "SELECT bi.*, b.batch_number, s.id as supplier_id, s.name as supplier_name, m.name as medicine_name, m.ndc as medicine_ndc,
            COALESCE(bi.received_quantity, bi.quantity, 0) as expired_quantity
            FROM batch_items bi
            INNER JOIN batches b ON bi.batch_id = b.id
            LEFT JOIN suppliers s ON b.supplier_id = s.id
            LEFT JOIN medicines m ON bi.medicine_id = m.id
            WHERE bi.is_expired = 1 
            AND bi.expired_at IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM archived_expired_items aei 
                WHERE aei.original_batch_item_id = bi.id
            )";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("Error querying expired items: " . mysqli_error($conn));
        return false;
    }
    
    $archived = 0;
    $errors = [];
    
    // Prepare INSERT statement - archived_at will use default CURRENT_TIMESTAMP
    $archiveSql = "INSERT INTO archived_expired_items 
                    (original_batch_item_id, batch_id, batch_number, medicine_id, medicine_name, medicine_ndc, 
                     quantity, expiration_date, expired_at, supplier_id, supplier_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $archiveStmt = mysqli_prepare($conn, $archiveSql);
    if (!$archiveStmt) {
        error_log("Error preparing archive statement: " . mysqli_error($conn));
        return false;
    }
    
    while ($item = mysqli_fetch_assoc($result)) {
        // Use received_quantity if available, otherwise use quantity
        $expiredQuantity = isset($item['expired_quantity']) ? (int)$item['expired_quantity'] : 
                          (isset($item['received_quantity']) ? (int)$item['received_quantity'] : (int)$item['quantity']);
        
        // Ensure all values are properly set
        $original_batch_item_id = isset($item['id']) ? (int)$item['id'] : null;
        $batch_id = isset($item['batch_id']) ? (int)$item['batch_id'] : null;
        $batch_number = isset($item['batch_number']) ? $item['batch_number'] : null;
        $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : null;
        $medicine_name = isset($item['medicine_name']) ? $item['medicine_name'] : null;
        $medicine_ndc = isset($item['medicine_ndc']) ? $item['medicine_ndc'] : null;
        $expiration_date = isset($item['expiration_date']) ? $item['expiration_date'] : null;
        $expired_at = isset($item['expired_at']) ? $item['expired_at'] : date('Y-m-d H:i:s');
        $supplier_id = isset($item['supplier_id']) ? (int)$item['supplier_id'] : null;
        $supplier_name = isset($item['supplier_name']) ? $item['supplier_name'] : null;
        
        // Bind parameters - Fixed: quantity should be 'i' (integer), not 's' (string)
        // Type string breakdown: i i s i s s i s s i s
        //                       1 2 3 4 5 6 7 8 9 10 11
        // Parameters: original_batch_item_id(i), batch_id(i), batch_number(s), medicine_id(i), 
        //            medicine_name(s), medicine_ndc(s), quantity(i), expiration_date(s), 
        //            expired_at(s), supplier_id(i), supplier_name(s)
        // CORRECTED: Changed position 7 from 's' to 'i' for quantity
        // Type string breakdown (11 parameters):
        // 1=i, 2=i, 3=s, 4=i, 5=s, 6=s, 7=i, 8=s, 9=s, 10=i, 11=s
        // Corrected: position 7 (quantity) is now 'i' instead of 's'
        $typeString = 'i' . 'i' . 's' . 'i' . 's' . 's' . 'i' . 's' . 's' . 'i' . 's'; // Explicitly constructed
        mysqli_stmt_bind_param($archiveStmt, $typeString,
            $original_batch_item_id,    // 1: i - int
            $batch_id,                  // 2: i - int
            $batch_number,              // 3: s - string
            $medicine_id,               // 4: i - int
            $medicine_name,             // 5: s - string
            $medicine_ndc,              // 6: s - string
            $expiredQuantity,           // 7: i - int (CORRECTED from 's' to 'i')
            $expiration_date,           // 8: s - string/date
            $expired_at,                // 9: s - string/timestamp
            $supplier_id,               // 10: i - int
            $supplier_name              // 11: s - string
        );
        
        if (mysqli_stmt_execute($archiveStmt)) {
            $archived++;
        } else {
            $errorMsg = "Failed to archive item ID {$original_batch_item_id}: " . mysqli_stmt_error($archiveStmt);
            error_log($errorMsg);
            $errors[] = $errorMsg;
        }
    }
    
    mysqli_stmt_close($archiveStmt);
    
    if (count($errors) > 0) {
        error_log("Archive errors: " . implode(", ", $errors));
    }
    
    return $archived;
}

?>

