<?php
// Turn off error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

// Enhanced CORS headers - allow multiple origins for development
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
    // Default to allow localhost on any port for development
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

require_once __DIR__ . '/conn.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method. Only POST is allowed.', null, 405);
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get and sanitize form data - match form field names exactly
    $ndc = isset($_POST['ndcCode']) ? trim($_POST['ndcCode']) : '';
    $name = isset($_POST['medicineName']) ? trim($_POST['medicineName']) : '';
    
    // For nullable fields, convert empty strings to NULL
    $manufacturer = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
    $manufacturer = $manufacturer !== '' ? $manufacturer : null;
    
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $category = $category !== '' ? $category : null;
    
    $dosage_form = isset($_POST['dosageForm']) ? trim($_POST['dosageForm']) : '';
    $dosage_form = $dosage_form !== '' ? $dosage_form : null;
    
    // Get supplier_id if provided
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    if ($supplier_id !== null && $supplier_id <= 0) {
        $supplier_id = null;
    }
    
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    
    $expiration_date = isset($_POST['expirationDate']) ? trim($_POST['expirationDate']) : '';
    $expiration_date = $expiration_date !== '' ? $expiration_date : null;
    
    $reorder_level = isset($_POST['reorderLevel']) ? (int)$_POST['reorderLevel'] : 10; // Default to 10 if not provided
    
    // Validate required fields
    if (empty($ndc)) {
        sendJsonResponse(false, 'NDC Code is required', null, 400);
    }
    if (empty($name)) {
        sendJsonResponse(false, 'Medicine Name is required', null, 400);
    }
    if ($quantity < 0) {
        sendJsonResponse(false, 'Quantity cannot be negative', null, 400);
    }
    if ($price < 0) {
        sendJsonResponse(false, 'Price cannot be negative', null, 400);
    }
    if ($reorder_level < 0) {
        sendJsonResponse(false, 'Reorder level cannot be negative', null, 400);
    }
    
    // Validate expiration date format if provided
    if ($expiration_date !== null && $expiration_date !== '') {
        // Validate date format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
            sendJsonResponse(false, 'Invalid expiration date format. Use YYYY-MM-DD', null, 400);
        }
        
        // Validate it's a valid date
        $dateParts = explode('-', $expiration_date);
        if (!checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            sendJsonResponse(false, 'Invalid expiration date', null, 400);
        }
    } else {
        $expiration_date = null; // Ensure it's NULL, not empty string
    }

    // Check for existing medicine with same NDC
    // Database has UNIQUE constraint on ndc column
    // Rules:
    // - Same NDC + Same Name → Increment quantity (update existing)
    // - Same NDC + Different Name → Error (not allowed)
    // - Different NDC → Create new medicine
    $ndcCheckSql = "SELECT id, ndc, name, quantity, expiration_date, batch_number FROM medicines 
                    WHERE ndc = ? 
                    LIMIT 1";
    $ndcCheckStmt = mysqli_prepare($conn, $ndcCheckSql);
    if (!$ndcCheckStmt) {
        error_log("NDC check prepare error: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error during NDC check', null, 500);
    }
    
    mysqli_stmt_bind_param($ndcCheckStmt, 's', $ndc);
    mysqli_stmt_execute($ndcCheckStmt);
    $ndcCheckResult = mysqli_stmt_get_result($ndcCheckStmt);
    
    if ($ndcCheckResult && mysqli_num_rows($ndcCheckResult) > 0) {
        $existing = mysqli_fetch_assoc($ndcCheckResult);
        mysqli_stmt_close($ndcCheckStmt);
        
        // Check if name matches
        if (strcasecmp($existing['name'], $name) === 0) {
            // Same NDC + Same Name → Increment quantity
            $existingId = (int)$existing['id'];
            $existingQuantity = (int)$existing['quantity'];
            $newQuantity = $existingQuantity + $quantity;
            
            // Get or create batch number based on expiration date
            require_once __DIR__ . '/batch_helper.php';
            $batch_number = getOrCreateBatchNumber($conn, $expiration_date);
            
            // Calculate status based on new quantity, reorder_level, and expiration date
            $currentDate = date('Y-m-d');
            $status = 'in-stock';
            
            if ($expiration_date !== null && $expiration_date < $currentDate) {
                $status = 'expired';
            } elseif ($newQuantity === 0) {
                $status = 'out-of-stock';
            } elseif ($newQuantity > 0 && $newQuantity <= $reorder_level) {
                $status = 'low-stock';
            }
            
            // Update existing medicine: increment quantity and update expiration/batch if different
            $updateSql = "UPDATE medicines SET 
                          quantity = ?,
                          expiration_date = ?,
                          batch_number = ?,
                          status = ?,
                          updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?";
            
            $updateStmt = mysqli_prepare($conn, $updateSql);
            if (!$updateStmt) {
                error_log("Update prepare error: " . mysqli_error($conn));
                sendJsonResponse(false, 'Database error during quantity update', null, 500);
            }
            
            mysqli_stmt_bind_param($updateStmt, 'isisi', $newQuantity, $expiration_date, $batch_number, $status, $existingId);
            
            if (!mysqli_stmt_execute($updateStmt)) {
                $error = mysqli_stmt_error($updateStmt);
                error_log("Update execute error: " . $error);
                mysqli_stmt_close($updateStmt);
                sendJsonResponse(false, 'Failed to update medicine quantity: ' . $error, null, 500);
            }
            
            mysqli_stmt_close($updateStmt);
            
            // Fetch updated medicine data
            $selectSql = "SELECT id, ndc, name, manufacturer, category, dosage_form, quantity, reorder_level, price, expiration_date, batch_number, status, created_at, updated_at
                          FROM medicines WHERE id = ?";
            $selectStmt = mysqli_prepare($conn, $selectSql);
            mysqli_stmt_bind_param($selectStmt, 'i', $existingId);
            mysqli_stmt_execute($selectStmt);
            $selectResult = mysqli_stmt_get_result($selectStmt);
            $updatedMedicine = mysqli_fetch_assoc($selectResult);
            mysqli_stmt_close($selectStmt);
            
            if (isset($updatedMedicine['price'])) {
                $updatedMedicine['price'] = number_format((float)$updatedMedicine['price'], 2, '.', '');
            }
            
            // Return success with updated medicine
            sendJsonResponse(true, "Medicine quantity updated successfully. Quantity increased from {$existingQuantity} to {$newQuantity}.", $updatedMedicine, 200);
            exit;
        } else {
            // Same NDC + Different Name → Error
            mysqli_stmt_close($ndcCheckStmt);
            ob_clean();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'duplicate' => true,
                'message' => "A medicine with NDC Code '{$ndc}' already exists with a different name ('{$existing['name']}'). Each NDC Code must refer to only one medicine.",
                'data' => [
                    'duplicate' => true, 
                    'field' => 'NDC Code',
                    'existing_id' => (int)$existing['id'],
                    'existing_name' => $existing['name'],
                    'new_name' => $name
                ]
            ], JSON_UNESCAPED_UNICODE);
            ob_end_flush();
            exit;
        }
    }
    mysqli_stmt_close($ndcCheckStmt);

    // Calculate status based on quantity, reorder_level, and expiration date
    $currentDate = date('Y-m-d');
    $status = 'in-stock';
    
    // Check expiration first (highest priority)
    if ($expiration_date !== null && $expiration_date < $currentDate) {
        $status = 'expired';
    } 
    // Then check quantity
    elseif ($quantity === 0) {
        $status = 'out-of-stock';
    } 
    // Then check low stock (quantity <= reorder_level)
    elseif ($quantity > 0 && $quantity <= $reorder_level) {
        $status = 'low-stock';
    }
    // Otherwise, it's in-stock (already set above)

    // Get or create batch number based on expiration date
    require_once __DIR__ . '/batch_helper.php';
    $batch_number = getOrCreateBatchNumber($conn, $expiration_date);

    // Check if supplier_id column exists
    $checkSupplierId = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'supplier_id'");
    $hasSupplierId = mysqli_num_rows($checkSupplierId) > 0;

    // Prepare SQL INSERT statement - match database columns exactly
    // Note: created_at and updated_at are handled automatically by MySQL
    if ($hasSupplierId) {
        $sql = "INSERT INTO medicines (
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            supplier_id,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else {
        $sql = "INSERT INTO medicines (
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    // Prepare statement
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

    // Bind parameters
    // Types: s=string, i=integer, d=double/decimal
    // Note: For NULL values, we need to pass actual NULL, not empty string
    if ($hasSupplierId) {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'sssssiidsiis',  // 12 parameters: 5 strings, 4 integers, 1 double, 2 strings
            $ndc, 
            $name, 
            $manufacturer, 
            $category, 
            $dosage_form,
            $quantity, 
            $reorder_level,
            $price, 
            $expiration_date,
            $batch_number,
            $supplier_id,
            $status
        );
    } else {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'sssssiidsis',  // 11 parameters: 5 strings, 3 integers, 1 double, 2 strings
            $ndc, 
            $name, 
            $manufacturer, 
            $category, 
            $dosage_form,
            $quantity, 
            $reorder_level,
            $price, 
            $expiration_date,
            $batch_number,
            $status
        );
    }
    
    // Verify binding was successful
    if (!$bound) {
        $error = 'Failed to bind parameters: ' . mysqli_stmt_error($stmt);
        error_log($error);
        mysqli_stmt_close($stmt);
        sendJsonResponse(false, 'Database binding error: ' . $error, null, 500);
    }

    // Execute statement
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        $errorCode = mysqli_stmt_errno($stmt);
        error_log("MySQL execute error [$errorCode]: " . $error);
        
        mysqli_stmt_close($stmt);
        
        // Check for specific error types - handle unique constraint violation on NDC
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'duplicate') !== false || $errorCode === 1062) {
            // Unique constraint on NDC column was violated
            $errorMessage = 'A medicine with this NDC Code already exists. Each NDC Code must refer to only one medicine.';
            if (strpos($error, 'ndc') !== false) {
                $errorMessage = 'A medicine with this NDC Code already exists with a different name. Each NDC Code must refer to only one medicine.';
            }
            sendJsonResponse(false, $errorMessage, ['error_code' => $errorCode, 'error' => $error, 'duplicate' => true], 409);
        }
        
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    // Get the inserted ID
    $insertedId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$insertedId) {
        sendJsonResponse(false, 'Failed to get inserted medicine ID', null, 500);
    }

    // Fetch the inserted medicine data to return
    if ($hasSupplierId) {
        $selectSql = "SELECT 
            id, 
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            supplier_id,
            status,
            created_at,
            updated_at
        FROM medicines 
        WHERE id = ?";
    } else {
        $selectSql = "SELECT 
            id, 
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            status,
            created_at,
            updated_at
        FROM medicines 
        WHERE id = ?";
    }
    
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        error_log("Select statement prepare error: " . mysqli_error($conn));
        // Return success with basic data if select fails
        sendJsonResponse(true, 'Medicine added successfully', [
            'id' => $insertedId,
            'ndc' => $ndc,
            'name' => $name,
            'manufacturer' => $manufacturer,
            'category' => $category,
            'dosage_form' => $dosage_form,
            'quantity' => $quantity,
            'reorder_level' => $reorder_level,
            'price' => number_format($price, 2, '.', ''),
            'expiration_date' => $expiration_date,
            'batch_number' => $batch_number,
            'status' => $status
        ], 200);
    }

    mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        // Return success with basic data if select fails
        sendJsonResponse(true, 'Medicine added successfully', [
            'id' => $insertedId,
            'ndc' => $ndc,
            'name' => $name,
            'manufacturer' => $manufacturer,
            'category' => $category,
            'dosage_form' => $dosage_form,
            'quantity' => $quantity,
            'reorder_level' => $reorder_level,
            'price' => number_format($price, 2, '.', ''),
            'expiration_date' => $expiration_date,
            'batch_number' => $batch_number,
            'status' => $status
        ], 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $medicine = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$medicine) {
        // Return success with basic data if fetch fails
        sendJsonResponse(true, 'Medicine added successfully', [
            'id' => $insertedId,
            'ndc' => $ndc,
            'name' => $name,
            'manufacturer' => $manufacturer,
            'category' => $category,
            'dosage_form' => $dosage_form,
            'quantity' => $quantity,
            'reorder_level' => $reorder_level,
            'price' => number_format($price, 2, '.', ''),
            'expiration_date' => $expiration_date,
            'batch_number' => $batch_number,
            'status' => $status
        ], 200);
    }

    // Format price for response
    if (isset($medicine['price'])) {
        $medicine['price'] = number_format((float)$medicine['price'], 2, '.', '');
    }

    // Success response
    sendJsonResponse(true, 'Medicine added successfully', $medicine, 200);

} catch (Exception $e) {
    error_log('Exception in add_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    // Catch PHP 7+ fatal errors
    error_log('Fatal error in add_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    // Catch any other throwable
    error_log('Throwable in add_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

// This should never be reached, but just in case
ob_end_flush();
sendJsonResponse(false, 'Unexpected error occurred', null, 500);
?>
