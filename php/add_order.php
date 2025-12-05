<?php
// Add Order API
// Handles creating new orders and order items

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

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
    ob_clean();
    http_response_code(200);
    exit(0);
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
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
    require_once __DIR__ . '/batch_helper.php';
    require_once __DIR__ . '/order_batch_helper.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get form data
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $order_date = isset($_POST['order_date']) ? trim($_POST['order_date']) : date('Y-m-d');
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Get order items (JSON string or array)
    $items_json = isset($_POST['items']) ? $_POST['items'] : '[]';
    if (is_string($items_json)) {
        $items = json_decode($items_json, true);
    } else {
        $items = $items_json;
    }
    
    if (!is_array($items)) {
        $items = [];
    }

    // Validate required fields
    if ($supplier_id <= 0) {
        sendJsonResponse(false, 'Supplier is required', null, 400);
    }
    
    if (empty($order_date)) {
        sendJsonResponse(false, 'Order date is required', null, 400);
    }
    
    if (empty($items)) {
        sendJsonResponse(false, 'At least one order item is required', null, 400);
    }

    // Validate status
    $valid_statuses = ['pending', 'shipping', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending';
    }

    // Detect date column name in orders table
    $orderDateColumn = 'order_date';
    $checkDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'date'");
    $hasDateColumn = $checkDateColumn && mysqli_num_rows($checkDateColumn) > 0;
    $checkOrderDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'order_date'");
    $hasOrderDateColumn = $checkOrderDateColumn && mysqli_num_rows($checkOrderDateColumn) > 0;
    if ($hasOrderDateColumn) {
        $orderDateColumn = 'order_date';
    } elseif ($hasDateColumn) {
        $orderDateColumn = 'date';
    }

    // Check if tables exist, create if missing
    $checkOrdersTable = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
    if (!$checkOrdersTable || mysqli_num_rows($checkOrdersTable) === 0) {
        $createOrdersSql = "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT UNSIGNED NOT NULL,
            order_date DATE NOT NULL,
            status ENUM('pending','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
            total_amount DECIMAL(12,2) NULL DEFAULT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_order_date (order_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($conn, $createOrdersSql)) {
            sendJsonResponse(false, 'Failed to create orders table: ' . mysqli_error($conn), null, 500);
        }
    }
    
    $checkOrderItemsTable = mysqli_query($conn, "SHOW TABLES LIKE 'order_items'");
    if (!$checkOrderItemsTable || mysqli_num_rows($checkOrderItemsTable) === 0) {
        $createOrderItemsSql = "CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            medicine_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 0,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_medicine_id (medicine_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($conn, $createOrderItemsSql)) {
            sendJsonResponse(false, 'Failed to create order_items table: ' . mysqli_error($conn), null, 500);
        }
    }

    // Fix AUTO_INCREMENT issues in orders table (similar to medicines/suppliers fix)
    // Delete any rows with id=0
    @mysqli_query($conn, "DELETE FROM orders WHERE id = 0");
    
    // Get max ID and fix AUTO_INCREMENT
    $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
    $maxId = 0;
    if ($maxIdQuery) {
        $maxRow = mysqli_fetch_assoc($maxIdQuery);
        $maxId = (int)($maxRow['max_id'] ?? 0);
    }
    $nextId = max(1, $maxId + 1);
    
    // Fix AUTO_INCREMENT
    @mysqli_query($conn, "ALTER TABLE orders AUTO_INCREMENT = {$nextId}");
    
    // Check if id column has AUTO_INCREMENT
    $checkIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'id'");
    if ($checkIdColumn) {
        $idColumn = mysqli_fetch_assoc($checkIdColumn);
        $hasAutoIncrement = strpos($idColumn['Extra'] ?? '', 'auto_increment') !== false;
        $isPrimary = strpos($idColumn['Key'] ?? '', 'PRI') !== false;
        
        if (!$hasAutoIncrement) {
            if ($isPrimary) {
                @mysqli_query($conn, "ALTER TABLE orders MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
            } else {
                @mysqli_query($conn, "ALTER TABLE orders MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
            }
        }
    }

    // Start transaction
    if (!function_exists('mysqli_begin_transaction') || !mysqli_begin_transaction($conn)) {
        // Fallback for older MySQL versions
        mysqli_query($conn, "START TRANSACTION");
    }

    try {
        // Check if total_amount column exists
        $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
        $hasTotalAmount = $checkTotalAmount && mysqli_num_rows($checkTotalAmount) > 0;
        
        // Check if notes column exists
        $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
        $hasNotes = $checkNotes && mysqli_num_rows($checkNotes) > 0;
        
        // Calculate total amount
        $total_amount = 0.00;
        foreach ($items as $item) {
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $price = isset($item['price']) ? (float)$item['price'] : 0.00;
            $total_amount += $quantity * $price;
        }

        // Build INSERT statement based on column existence
        if ($hasTotalAmount && $hasNotes) {
            $orderSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, total_amount, notes) VALUES (?, ?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'issds', $supplier_id, $order_date, $status, $total_amount, $notes);
        } elseif ($hasTotalAmount) {
            $orderSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, total_amount) VALUES (?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'issd', $supplier_id, $order_date, $status, $total_amount);
        } elseif ($hasNotes) {
            $orderSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, notes) VALUES (?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'isss', $supplier_id, $order_date, $status, $notes);
        } else {
            $orderSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status) VALUES (?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'iss', $supplier_id, $order_date, $status);
        }
        
        if (!mysqli_stmt_execute($orderStmt)) {
            $error = mysqli_stmt_error($orderStmt);
            mysqli_stmt_close($orderStmt);
            
            // Check if it's the "Duplicate entry '0'" error
            if (strpos($error, "Duplicate entry '0'") !== false) {
                // Fix AUTO_INCREMENT and try again with raw SQL
                @mysqli_query($conn, "DELETE FROM orders WHERE id = 0");
                $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
                $maxId = 0;
                if ($maxIdQuery) {
                    $maxRow = mysqli_fetch_assoc($maxIdQuery);
                    $maxId = (int)($maxRow['max_id'] ?? 0);
                }
                $nextId = max(1, $maxId + 1);
                @mysqli_query($conn, "ALTER TABLE orders AUTO_INCREMENT = {$nextId}");
                
                // Retry with raw SQL (more reliable)
                $supplier_id_escaped = (int)$supplier_id;
                $order_date_escaped = mysqli_real_escape_string($conn, $order_date);
                $status_escaped = mysqli_real_escape_string($conn, $status);
                
                if ($hasTotalAmount && $hasNotes) {
                    $notes_escaped = mysqli_real_escape_string($conn, $notes ?? '');
                    $total_amount_escaped = (float)$total_amount;
                    $rawSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, total_amount, notes) VALUES ({$supplier_id_escaped}, '{$order_date_escaped}', '{$status_escaped}', {$total_amount_escaped}, '{$notes_escaped}')";
                } elseif ($hasTotalAmount) {
                    $total_amount_escaped = (float)$total_amount;
                    $rawSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, total_amount) VALUES ({$supplier_id_escaped}, '{$order_date_escaped}', '{$status_escaped}', {$total_amount_escaped})";
                } elseif ($hasNotes) {
                    $notes_escaped = mysqli_real_escape_string($conn, $notes ?? '');
                    $rawSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status, notes) VALUES ({$supplier_id_escaped}, '{$order_date_escaped}', '{$status_escaped}', '{$notes_escaped}')";
                } else {
                    $rawSql = "INSERT INTO orders (supplier_id, {$orderDateColumn}, status) VALUES ({$supplier_id_escaped}, '{$order_date_escaped}', '{$status_escaped}')";
                }
                
                if (!mysqli_query($conn, $rawSql)) {
                    throw new Exception('Failed to create order after fix: ' . mysqli_error($conn));
                }
            } else {
                throw new Exception('Failed to create order: ' . $error);
            }
        }

        $order_id = mysqli_insert_id($conn);
        
        // Verify we got a valid ID
        if ($order_id <= 0) {
            // Fallback: get the last inserted ID manually
            $lastIdQuery = mysqli_query($conn, "SELECT MAX(id) as last_id FROM orders");
            if ($lastIdQuery) {
                $lastRow = mysqli_fetch_assoc($lastIdQuery);
                $order_id = (int)($lastRow['last_id'] ?? 0);
            }
            
            if ($order_id <= 0) {
                mysqli_stmt_close($orderStmt);
                throw new Exception('Failed to get order ID after insert');
            }
        }
        
        mysqli_stmt_close($orderStmt);

        // Fix AUTO_INCREMENT issues in order_items table before inserting items
        // Delete any rows with id=0
        @mysqli_query($conn, "DELETE FROM order_items WHERE id = 0");
        
        // Get max ID and fix AUTO_INCREMENT
        $maxItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM order_items");
        $maxItemId = 0;
        if ($maxItemIdQuery) {
            $maxItemRow = mysqli_fetch_assoc($maxItemIdQuery);
            $maxItemId = (int)($maxItemRow['max_id'] ?? 0);
        }
        $nextItemId = max(1, $maxItemId + 1);
        
        // Fix AUTO_INCREMENT
        @mysqli_query($conn, "ALTER TABLE order_items AUTO_INCREMENT = {$nextItemId}");
        
        // Check if id column has AUTO_INCREMENT
        $checkItemIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM order_items WHERE Field = 'id'");
        if ($checkItemIdColumn) {
            $itemIdColumn = mysqli_fetch_assoc($checkItemIdColumn);
            $hasItemAutoIncrement = strpos($itemIdColumn['Extra'] ?? '', 'auto_increment') !== false;
            $isItemPrimary = strpos($itemIdColumn['Key'] ?? '', 'PRI') !== false;
            
            if (!$hasItemAutoIncrement) {
                if ($isItemPrimary) {
                    @mysqli_query($conn, "ALTER TABLE order_items MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
                } else {
                    @mysqli_query($conn, "ALTER TABLE order_items MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
                }
            }
        }

        // Insert order items and update medicine quantities
        foreach ($items as $item) {
            $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $price = isset($item['price']) ? (float)$item['price'] : 0.00;
            
            if ($medicine_id <= 0 || $quantity <= 0) {
                continue; // Skip invalid items
            }

            // Insert order item - prepare new statement for each item to avoid reuse issues
            $itemSql = "INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)";
            $itemStmt = mysqli_prepare($conn, $itemSql);
            if (!$itemStmt) {
                throw new Exception('Database preparation error for items: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($itemStmt, 'iidd', $order_id, $medicine_id, $quantity, $price);
            if (!mysqli_stmt_execute($itemStmt)) {
                $error = mysqli_stmt_error($itemStmt);
                mysqli_stmt_close($itemStmt);
                
                // Check if it's the "Duplicate entry '0'" error
                if (strpos($error, "Duplicate entry '0'") !== false) {
                    // Fix AUTO_INCREMENT and try again with raw SQL
                    @mysqli_query($conn, "DELETE FROM order_items WHERE id = 0");
                    $maxItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM order_items");
                    $maxItemId = 0;
                    if ($maxItemIdQuery) {
                        $maxItemRow = mysqli_fetch_assoc($maxItemIdQuery);
                        $maxItemId = (int)($maxItemRow['max_id'] ?? 0);
                    }
                    $nextItemId = max(1, $maxItemId + 1);
                    @mysqli_query($conn, "ALTER TABLE order_items AUTO_INCREMENT = {$nextItemId}");
                    
                    // Retry with raw SQL (more reliable)
                    $order_id_escaped = (int)$order_id;
                    $medicine_id_escaped = (int)$medicine_id;
                    $quantity_escaped = (int)$quantity;
                    $price_escaped = (float)$price;
                    
                    $rawItemSql = "INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES ({$order_id_escaped}, {$medicine_id_escaped}, {$quantity_escaped}, {$price_escaped})";
                    
                    if (!mysqli_query($conn, $rawItemSql)) {
                        throw new Exception('Failed to add order item after fix: ' . mysqli_error($conn));
                    }
                } else {
                    throw new Exception('Failed to add order item: ' . $error);
                }
            } else {
                mysqli_stmt_close($itemStmt);
            }

            // Update medicine quantity and recalculate status (increment when order is completed)
            // Status: out-of-stock only if quantity = 0, otherwise based on reorder_level
            if ($status === 'completed') {
                $checkMedicinesTable = mysqli_query($conn, "SHOW TABLES LIKE 'medicines'");
                if (!$checkMedicinesTable || mysqli_num_rows($checkMedicinesTable) === 0) {
                    // Skip medicine updates if table does not exist
                    continue;
                }
                // Get current quantity, reorder_level, and expiration_date for status calculation
                $getMedicineSql = "SELECT quantity, reorder_level, expiration_date FROM medicines WHERE id = ?";
                $getMedicineStmt = mysqli_prepare($conn, $getMedicineSql);
                $currentQuantity = 0;
                $reorder_level = 10; // Default
                $expiration_date = null;
                
                if ($getMedicineStmt) {
                    mysqli_stmt_bind_param($getMedicineStmt, 'i', $medicine_id);
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
                $finalQuantity = $currentQuantity + $quantity;
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
                
                $updateMedicineSql = "UPDATE medicines SET 
                    quantity = quantity + ?,
                    status = ?
                    WHERE id = ?";
                $updateMedicineStmt = mysqli_prepare($conn, $updateMedicineSql);
                if ($updateMedicineStmt) {
                    mysqli_stmt_bind_param($updateMedicineStmt, 'isi', $quantity, $newStatus, $medicine_id);
                    mysqli_stmt_execute($updateMedicineStmt);
                    mysqli_stmt_close($updateMedicineStmt);
                }
            }
        }

        $itemsForBatch = [];
        foreach ($items as $it) {
            $itemsForBatch[] = [
                'medicine_id' => (int)($it['medicine_id'] ?? 0),
                'quantity' => (int)($it['quantity'] ?? 0),
                'expiration_date' => null
            ];
        }
        if (!empty($itemsForBatch)) {
            try {
                if (function_exists('addOrderToDailyBatch')) {
                    addOrderToDailyBatch($conn, $order_id, $supplier_id, $order_date, $itemsForBatch);
                }
            } catch (Exception $e) {
                error_log('Error adding items to daily batch on order creation: ' . $e->getMessage());
            }
        }

        try {
            if (function_exists('getOrCreateOrderBatch')) {
                $batch_id = getOrCreateOrderBatch($conn, $order_id, $supplier_id, $order_date);
                if ($batch_id === false) {
                    error_log("ERROR: Failed to create/get batch for order date {$order_date} for order {$order_id}");
                    $errorCheck = mysqli_error($conn);
                    if ($errorCheck) {
                        error_log("MySQL Error: " . $errorCheck);
                    }
                } else {
                    error_log("SUCCESS: Batch {$batch_id} created/retrieved for order {$order_id} with order date {$order_date}");
                }
            }
        } catch (Exception $batchException) {
            error_log("EXCEPTION creating batch: " . $batchException->getMessage());
            error_log("Stack trace: " . $batchException->getTraceAsString());
        } catch (Error $batchError) {
            error_log("FATAL ERROR creating batch: " . $batchError->getMessage());
        }

        // Commit transaction
        mysqli_commit($conn);

        // Fetch the created order with details
        // Detect suppliers table presence
        $checkSuppliersTable = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
        $hasSuppliersTable = $checkSuppliersTable && mysqli_num_rows($checkSuppliersTable) > 0;
        $dateSelect = $orderDateColumn === 'order_date' ? 'o.order_date' : 'o.date as order_date';
        $selectFields = $hasSuppliersTable
            ? "o.id, o.supplier_id, s.name as supplier_name, {$dateSelect}, o.status"
            : "o.id, o.supplier_id, NULL as supplier_name, {$dateSelect}, o.status";
        
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
        
        $selectSql = $hasSuppliersTable
            ? "SELECT {$selectFields} FROM orders o LEFT JOIN suppliers s ON o.supplier_id = s.id WHERE o.id = ?"
            : "SELECT {$selectFields} FROM orders o WHERE o.id = ?";
        
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if ($selectStmt) {
            mysqli_stmt_bind_param($selectStmt, 'i', $order_id);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            $order = mysqli_fetch_assoc($result);
            mysqli_stmt_close($selectStmt);
        }

        sendJsonResponse(true, 'Order created successfully', $order ?? ['id' => $order_id], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in add_order.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in add_order.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
}

?>

