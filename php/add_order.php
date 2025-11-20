<?php
// Add Order API
// Handles creating new orders and order items

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

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Check if total_amount column exists
        $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
        $hasTotalAmount = mysqli_num_rows($checkTotalAmount) > 0;
        
        // Check if notes column exists
        $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
        $hasNotes = mysqli_num_rows($checkNotes) > 0;
        
        // Calculate total amount
        $total_amount = 0.00;
        foreach ($items as $item) {
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $price = isset($item['price']) ? (float)$item['price'] : 0.00;
            $total_amount += $quantity * $price;
        }

        // Build INSERT statement based on column existence
        if ($hasTotalAmount && $hasNotes) {
            $orderSql = "INSERT INTO orders (supplier_id, order_date, status, total_amount, notes) VALUES (?, ?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'issds', $supplier_id, $order_date, $status, $total_amount, $notes);
        } elseif ($hasTotalAmount) {
            $orderSql = "INSERT INTO orders (supplier_id, order_date, status, total_amount) VALUES (?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'issd', $supplier_id, $order_date, $status, $total_amount);
        } elseif ($hasNotes) {
            $orderSql = "INSERT INTO orders (supplier_id, order_date, status, notes) VALUES (?, ?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'isss', $supplier_id, $order_date, $status, $notes);
        } else {
            $orderSql = "INSERT INTO orders (supplier_id, order_date, status) VALUES (?, ?, ?)";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            if (!$orderStmt) {
                throw new Exception('Database preparation error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($orderStmt, 'iss', $supplier_id, $order_date, $status);
        }
        
        if (!mysqli_stmt_execute($orderStmt)) {
            throw new Exception('Failed to create order: ' . mysqli_stmt_error($orderStmt));
        }

        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($orderStmt);

        // Insert order items and update medicine quantities
        $itemSql = "INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)";
        $itemStmt = mysqli_prepare($conn, $itemSql);
        if (!$itemStmt) {
            throw new Exception('Database preparation error for items: ' . mysqli_error($conn));
        }

        foreach ($items as $item) {
            $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $price = isset($item['price']) ? (float)$item['price'] : 0.00;
            
            if ($medicine_id <= 0 || $quantity <= 0) {
                continue; // Skip invalid items
            }

            // Insert order item
            mysqli_stmt_bind_param($itemStmt, 'iidd', $order_id, $medicine_id, $quantity, $price);
            if (!mysqli_stmt_execute($itemStmt)) {
                throw new Exception('Failed to add order item: ' . mysqli_stmt_error($itemStmt));
            }

            // Update medicine quantity and recalculate status (increment when order is completed)
            // Status: out-of-stock only if quantity = 0, otherwise based on reorder_level
            if ($status === 'completed') {
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
        mysqli_stmt_close($itemStmt);

        // Create batch for this order
        // Prepare items with expiration dates for batch creation
        $batchItems = [];
        foreach ($items as $item) {
            $medicine_id = isset($item['medicine_id']) ? (int)$item['medicine_id'] : 0;
            if ($medicine_id <= 0) continue;
            
            // Get expiration date from medicine if not provided
            $expiration_date = null;
            if (isset($item['expiration_date']) && !empty($item['expiration_date'])) {
                $expiration_date = trim($item['expiration_date']);
            } else {
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
            
            $batchItems[] = [
                'medicine_id' => $medicine_id,
                'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 0,
                'expiration_date' => $expiration_date,
                'received_quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 0
            ];
        }
        
        // Create batch
        $batch_id = createOrderBatch($conn, $order_id, $supplier_id, $order_date, $batchItems);
        if ($batch_id === false) {
            error_log("Warning: Failed to create batch for order {$order_id}");
        }

        // Commit transaction
        mysqli_commit($conn);

        // Fetch the created order with details
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

