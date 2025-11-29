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
    
    // Get unit value from form (this will be saved to dosage_form column)
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $unit = $unit !== '' ? $unit : null;
    
    // Validate unit value against allowed ENUM values
    $allowedUnits = [
        'Capsule', 'Tablet', 'Pill', 'Bottle', 'Vial', 'Ampoule', 'Syringe', 'Tube',
        'Cream', 'Ointment', 'Gel', 'Drops', 'Spray', 'Inhaler', 'Patch',
        'ml', 'mg', 'g', 'kg', 'L', 'mcg', 'IU'
    ];
    
    // If unit is provided, validate it and use it for dosage_form
    // If unit is not provided, set default to 'Tablet' (since dosage_form is NOT NULL)
    if ($unit !== null && !in_array($unit, $allowedUnits)) {
        sendJsonResponse(false, 'Invalid unit value. Please select a valid unit from the list.', null, 400);
    }
    
    // Set dosage_form to the unit value (or default to 'Tablet' if not provided)
    $dosage_form = $unit !== null ? $unit : 'Tablet';
    
    // Also set unit to the same value to keep both columns in sync
    $unit = $dosage_form;
    
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
            $checkUnitForSelect = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'unit'");
            $hasUnitForSelect = $checkUnitForSelect && mysqli_num_rows($checkUnitForSelect) > 0;
            
            if ($hasUnitForSelect) {
                $selectSql = "SELECT id, ndc, name, manufacturer, category, dosage_form, unit, quantity, reorder_level, price, expiration_date, batch_number, status, created_at, updated_at
                              FROM medicines WHERE id = ?";
            } else {
                $selectSql = "SELECT id, ndc, name, manufacturer, category, dosage_form, quantity, reorder_level, price, expiration_date, batch_number, status, created_at, updated_at
                              FROM medicines WHERE id = ?";
            }
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

    // Check if supplier_id and unit columns exist
    $checkSupplierId = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'supplier_id'");
    $hasSupplierId = $checkSupplierId && mysqli_num_rows($checkSupplierId) > 0;
    
    // Check if unit column exists
    $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'unit'");
    $hasUnit = $checkUnit && mysqli_num_rows($checkUnit) > 0;
    
    // If unit column doesn't exist, ignore unit value
    if (!$hasUnit) {
        $unit = null;
    }
    
    // Generate next primary key ID
    // Get the maximum ID from the medicines table
    $maxIdQuery = "SELECT MAX(id) as max_id FROM medicines";
    $maxIdResult = mysqli_query($conn, $maxIdQuery);
    
    if (!$maxIdResult) {
        error_log("Error getting max ID: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error while fetching next ID', null, 500);
    }
    
    $maxIdRow = mysqli_fetch_assoc($maxIdResult);
    $maxId = $maxIdRow['max_id'];
    
    // Calculate next ID
    // If table is empty or max_id is NULL, start at 1
    // Otherwise, increment by 1
    if ($maxId === null || $maxId === '') {
        $nextId = 1;
    } else {
        // Handle both numeric and formatted IDs (e.g., "MED-0005" or "5")
        // Extract numeric portion if ID contains letters
        if (preg_match('/\d+/', $maxId, $matches)) {
            $numericId = (int)$matches[0];
        } else {
            $numericId = (int)$maxId;
        }
        $nextId = $numericId + 1;
    }
    
    // Ensure ID is never 0
    if ($nextId <= 0) {
        $nextId = 1;
    }
    
    error_log("Generated next medicine ID: " . $nextId . " (max was: " . ($maxId ?? 'NULL') . ")");
    
    // Prepare SQL INSERT statement - explicitly include id column
    // Note: created_at and updated_at are handled automatically by MySQL
    if ($hasSupplierId && $hasUnit) {
        $sql = "INSERT INTO medicines (
            id,
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form,
            unit, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            supplier_id,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } elseif ($hasSupplierId) {
        $sql = "INSERT INTO medicines (
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
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } elseif ($hasUnit) {
        $sql = "INSERT INTO medicines (
            id,
            ndc, 
            name, 
            manufacturer, 
            category, 
            dosage_form,
            unit, 
            quantity, 
            reorder_level,
            price, 
            expiration_date,
            batch_number,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else {
        $sql = "INSERT INTO medicines (
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
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
    // First parameter is always the id (integer)
    if ($hasSupplierId && $hasUnit) {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'issssssiidsiis',  // 14 parameters: 1 integer (id), 6 strings, 4 integers, 1 double, 2 strings
            $nextId,
            $ndc, 
            $name, 
            $manufacturer, 
            $category, 
            $dosage_form,
            $unit,
            $quantity, 
            $reorder_level,
            $price, 
            $expiration_date,
            $batch_number,
            $supplier_id,
            $status
        );
    } elseif ($hasSupplierId) {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'isssssiidsiis',  // 13 parameters: 1 integer (id), 5 strings, 4 integers, 1 double, 2 strings
            $nextId,
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
    } elseif ($hasUnit) {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'issssssiidsis',  // 13 parameters: 1 integer (id), 6 strings, 3 integers, 1 double, 2 strings
            $nextId,
            $ndc, 
            $name, 
            $manufacturer, 
            $category, 
            $dosage_form,
            $unit,
            $quantity, 
            $reorder_level,
            $price, 
            $expiration_date,
            $batch_number,
            $status
        );
    } else {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'isssssiidsis',  // 12 parameters: 1 integer (id), 5 strings, 3 integers, 1 double, 2 strings
            $nextId,
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
        error_log("Attempted to insert with ID: " . $nextId);
        
        mysqli_stmt_close($stmt);
        
        // Check for PRIMARY KEY duplicate error (including '0' key error)
        if ((strpos($error, 'Duplicate') !== false && strpos($error, 'PRIMARY') !== false) || 
            (strpos($error, 'Duplicate') !== false && strpos($error, "'0'") !== false)) {
            // ID conflict - this should not happen with our new logic, but handle it gracefully
            error_log("Primary key conflict detected. Attempted ID: " . $nextId);
            error_log("Error details: " . $error);
            
            // If the error is about '0', it means our ID calculation failed
            if (strpos($error, "'0'") !== false || $nextId <= 0) {
                $errorMessage = 'Primary key generation error: Invalid ID (0) was generated. This indicates a problem with ID calculation.';
                sendJsonResponse(false, $errorMessage, [
                    'error_code' => $errorCode, 
                    'error' => $error,
                    'attempted_id' => $nextId,
                    'suggestion' => 'Please check the database and ensure AUTO_INCREMENT is properly configured, or contact support.'
                ], 500);
            } else {
                // Get current max ID again (in case another process inserted a record)
                $retryMaxIdQuery = "SELECT MAX(id) as max_id FROM medicines";
                $retryMaxIdResult = mysqli_query($conn, $retryMaxIdQuery);
                if ($retryMaxIdResult) {
                    $retryMaxIdRow = mysqli_fetch_assoc($retryMaxIdResult);
                    $retryMaxId = $retryMaxIdRow['max_id'];
                    
                    if ($retryMaxId === null || $retryMaxId === '') {
                        $retryNextId = 1;
                    } else {
                        if (preg_match('/\d+/', $retryMaxId, $matches)) {
                            $retryNumericId = (int)$matches[0];
                        } else {
                            $retryNumericId = (int)$retryMaxId;
                        }
                        $retryNextId = $retryNumericId + 1;
                    }
                    
                    if ($retryNextId <= 0) {
                        $retryNextId = 1;
                    }
                    
                    error_log("Recalculated next ID: " . $retryNextId . " (previous was: " . $nextId . ")");
                }
                
                $errorMessage = 'Primary key conflict detected. The calculated ID (' . $nextId . ') already exists. Please try again.';
                sendJsonResponse(false, $errorMessage, [
                    'error_code' => $errorCode, 
                    'error' => $error,
                    'attempted_id' => $nextId,
                    'suggestion' => 'Please refresh and try adding the medicine again.'
                ], 500);
            }
        }
        
        // Check for duplicate NDC
        if (strpos($error, 'Duplicate') !== false || $errorCode === 1062) {
            if (strpos($error, 'ndc') !== false || strpos($error, 'PRIMARY') === false) {
                $errorMessage = 'A medicine with this NDC Code already exists.';
                sendJsonResponse(false, $errorMessage, ['error_code' => $errorCode, 'error' => $error, 'duplicate' => true], 409);
            }
        }
        
        // Check for unknown column error
        if (strpos($error, 'Unknown column') !== false) {
            $errorMessage = 'A required database column is missing. Please run: http://localhost:3000/php/add_unit_column.php';
            sendJsonResponse(false, $errorMessage, ['error_code' => $errorCode, 'error' => $error], 500);
        }
        
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    // Get the inserted ID (we explicitly set it, so use our calculated value)
    // mysqli_insert_id will return 0 if we explicitly set the ID, so use our calculated nextId
    $insertedId = $nextId;
    mysqli_stmt_close($stmt);

    if (!$insertedId || $insertedId <= 0) {
        error_log("Warning: Inserted ID is invalid: " . $insertedId);
        // Try to get it from mysqli_insert_id as fallback
        $insertedId = mysqli_insert_id($conn);
        if (!$insertedId || $insertedId <= 0) {
            sendJsonResponse(false, 'Failed to get inserted medicine ID', null, 500);
        }
    }
    
    error_log("Successfully inserted medicine with ID: " . $insertedId);

    // Fetch the inserted medicine data to return
    $selectFields = "id, ndc, name, manufacturer, category, dosage_form";
    if ($hasUnit) {
        $selectFields .= ", unit";
    }
    $selectFields .= ", quantity, reorder_level, price, expiration_date, batch_number";
    if ($hasSupplierId) {
        $selectFields .= ", supplier_id";
    }
    $selectFields .= ", status, created_at, updated_at";
    
    $selectSql = "SELECT {$selectFields} FROM medicines WHERE id = ?";
    
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        error_log("Select statement prepare error: " . mysqli_error($conn));
        $basicData = [
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
        ];
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
    }

    mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        $basicData = [
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
        ];
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $medicine = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$medicine) {
        $basicData = [
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
        ];
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
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
