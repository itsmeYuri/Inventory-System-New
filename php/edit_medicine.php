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
    // If no origin header, allow localhost
    header('Access-Control-Allow-Origin: http://localhost');
}

header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/medicine_structure_helper.php';
require_once __DIR__ . '/pos_sync_helper.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJsonResponse(false, 'Invalid request method. Only POST or PUT is allowed.', null, 405);
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Check which structure we're using
    $hasNewStructure = hasNewMedicineStructure($conn);

    // Get medicine ID
    $medicine_id = isset($_POST['id']) ? trim($_POST['id']) : '';
    if (empty($medicine_id)) {
        sendJsonResponse(false, 'Invalid medicine ID', null, 400);
    }

    // Get and sanitize form data
    $ndc = isset($_POST['ndcCode']) ? trim($_POST['ndcCode']) : '';
    $name = isset($_POST['medicineName']) ? trim($_POST['medicineName']) : '';
    
    // For nullable fields, convert empty strings to NULL
    $manufacturer = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
    $manufacturer = $manufacturer !== '' ? $manufacturer : null;
    
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $category = $category !== '' ? $category : 'Uncategorized';
    
    // Get generic_name (new field for POS)
    $generic_name = isset($_POST['genericName']) ? trim($_POST['genericName']) : '';
    $generic_name = $generic_name !== '' ? $generic_name : '';
    
    // Get unit/dosage value from form
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $unit = $unit !== '' ? $unit : 'Tablet';
    
    // Validate unit value
    $allowedUnits = [
        'Capsule', 'Tablet', 'Pill', 'Bottle', 'Vial', 'Ampoule', 'Syringe', 'Tube',
        'Cream', 'Ointment', 'Gel', 'Drops', 'Spray', 'Inhaler', 'Patch',
        'ml', 'mg', 'g', 'kg', 'L', 'mcg', 'IU'
    ];
    
    if ($unit !== null && !in_array($unit, $allowedUnits)) {
        sendJsonResponse(false, 'Invalid unit value. Please select a valid unit from the list.', null, 400);
    }
    
    // For new structure: dosage and form are separate
    // For old structure: dosage_form is used
    $dosage = $unit;
    $form = $unit;
    $dosage_form = $unit;
    
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $stock = $quantity; // New structure uses 'stock'
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    
    $expiration_date = isset($_POST['expirationDate']) ? trim($_POST['expirationDate']) : '';
    $expiration_date = $expiration_date !== '' ? $expiration_date : null;
    
    $reorder_level = isset($_POST['reorderLevel']) ? (int)$_POST['reorderLevel'] : 10;
    
    // Validate required fields
    if (empty($name)) {
        sendJsonResponse(false, 'Medicine Name is required', null, 400);
    }
    if (empty($category)) {
        $category = 'Uncategorized';
    }
    if (empty($generic_name)) {
        $generic_name = '';
    }
    
    // NDC only required for old structure
    if (!$hasNewStructure && empty($ndc)) {
        sendJsonResponse(false, 'NDC Code is required', null, 400);
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
            sendJsonResponse(false, 'Invalid expiration date format. Use YYYY-MM-DD', null, 400);
        }
        
        $dateParts = explode('-', $expiration_date);
        if (!checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            sendJsonResponse(false, 'Invalid expiration date', null, 400);
        }
    } else {
        $expiration_date = null;
    }

    // Check for duplicate names (new structure) or NDC (old structure)
    if ($hasNewStructure) {
        // New structure: Check by medicine_name
        $checkSql = "SELECT medicine_id, medicine_name FROM medicines WHERE medicine_name = ? AND medicine_id != ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'ss', $name, $medicine_id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            
            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $existing = mysqli_fetch_assoc($checkResult);
                mysqli_stmt_close($checkStmt);
                
                ob_clean();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'duplicate' => true,
                    'message' => "A medicine with name '{$name}' already exists.",
                    'data' => [
                        'duplicate' => true,
                        'field' => 'Medicine Name',
                        'existing_id' => $existing['medicine_id'],
                        'existing_name' => $existing['medicine_name']
                    ]
                ], JSON_UNESCAPED_UNICODE);
                ob_end_flush();
                exit;
            }
            mysqli_stmt_close($checkStmt);
        }
    } else {
        // Old structure: Check by NDC
    $currentSql = "SELECT ndc, name FROM medicines WHERE id = ?";
    $currentStmt = mysqli_prepare($conn, $currentSql);
    $currentNdc = null;
    $currentName = null;
    
    if ($currentStmt) {
        mysqli_stmt_bind_param($currentStmt, 'i', $medicine_id);
        mysqli_stmt_execute($currentStmt);
        $currentResult = mysqli_stmt_get_result($currentStmt);
        if ($currentResult && mysqli_num_rows($currentResult) > 0) {
            $current = mysqli_fetch_assoc($currentResult);
            $currentNdc = $current['ndc'];
            $currentName = $current['name'];
        }
        mysqli_stmt_close($currentStmt);
    }
    
    // Check if NDC is being changed
    if ($currentNdc !== null && strcasecmp($currentNdc, $ndc) !== 0) {
        $ndcCheckSql = "SELECT id, ndc, name FROM medicines WHERE ndc = ? AND id != ? LIMIT 1";
        $ndcCheckStmt = mysqli_prepare($conn, $ndcCheckSql);
        
        if ($ndcCheckStmt) {
            mysqli_stmt_bind_param($ndcCheckStmt, 'si', $ndc, $medicine_id);
            mysqli_stmt_execute($ndcCheckStmt);
            $ndcCheckResult = mysqli_stmt_get_result($ndcCheckStmt);
            
            if ($ndcCheckResult && mysqli_num_rows($ndcCheckResult) > 0) {
                $existing = mysqli_fetch_assoc($ndcCheckResult);
                mysqli_stmt_close($ndcCheckStmt);
                
                if (strcasecmp($existing['name'], $name) !== 0) {
                    ob_clean();
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'duplicate' => true,
                            'message' => "Cannot change NDC Code. A medicine with NDC Code '{$ndc}' already exists with a different name ('{$existing['name']}').",
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
        }
    } else {
        $ndcCheckSql = "SELECT id, ndc, name FROM medicines WHERE ndc = ? AND name != ? AND id != ? LIMIT 1";
        $ndcCheckStmt = mysqli_prepare($conn, $ndcCheckSql);
        
        if ($ndcCheckStmt) {
            mysqli_stmt_bind_param($ndcCheckStmt, 'ssi', $ndc, $name, $medicine_id);
            mysqli_stmt_execute($ndcCheckStmt);
            $ndcCheckResult = mysqli_stmt_get_result($ndcCheckStmt);
            
            if ($ndcCheckResult && mysqli_num_rows($ndcCheckResult) > 0) {
                $existing = mysqli_fetch_assoc($ndcCheckResult);
                mysqli_stmt_close($ndcCheckStmt);
                
                ob_clean();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'duplicate' => true,
                        'message' => "Cannot change medicine name. A medicine with NDC Code '{$ndc}' already exists with a different name ('{$existing['name']}').",
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
            mysqli_stmt_close($ndcCheckStmt);
        }
    }
    }

    // Calculate status (only for old structure)
    $status = null;
    $batch_number = null;

    if (!$hasNewStructure) {
    $currentDate = date('Y-m-d');
    $status = 'in-stock';
    
    if ($expiration_date !== null && $expiration_date < $currentDate) {
        $status = 'expired';
    } elseif ($quantity === 0) {
        $status = 'out-of-stock';
    } elseif ($quantity > 0 && $quantity <= $reorder_level) {
        $status = 'low-stock';
    }

    // Get current medicine to check if expiration_date changed
    $currentSql = "SELECT expiration_date, batch_number FROM medicines WHERE id = ?";
    $currentStmt = mysqli_prepare($conn, $currentSql);
    $old_expiration_date = null;
    $old_batch_number = null;
    
    if ($currentStmt) {
        mysqli_stmt_bind_param($currentStmt, 'i', $medicine_id);
        mysqli_stmt_execute($currentStmt);
        $currentResult = mysqli_stmt_get_result($currentStmt);
        if ($currentResult && mysqli_num_rows($currentResult) > 0) {
            $current = mysqli_fetch_assoc($currentResult);
            $old_expiration_date = $current['expiration_date'];
            $old_batch_number = $current['batch_number'];
        }
        mysqli_stmt_close($currentStmt);
    }

    require_once __DIR__ . '/batch_helper.php';
    $batch_number = getOrCreateBatchNumber($conn, $expiration_date);
    
    if ($expiration_date === null) {
        $batch_number = null;
    }
    }

    // Update SQL statement based on structure
    if ($hasNewStructure) {
        $sql = "UPDATE medicines SET 
            medicine_group = ?,
            medicine_name = ?,
            generic_name = ?,
            dosage = ?,
            form = ?,
            stock = ?,
            price = ?
        WHERE medicine_id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $error = mysqli_error($conn);
            error_log("MySQL prepare error: " . $error);
            sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
        }
        
        $bound = mysqli_stmt_bind_param(
            $stmt,
            'sssssids',  // 8 parameters
            $category,
            $name,
            $generic_name,
            $dosage,
            $form,
            $stock,
            $price,
            $medicine_id
        );
    } else {
        // Old structure
    $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
    $hasUnit = mysqli_num_rows($checkUnit) > 0;
    
    if ($hasUnit) {
        $sql = "UPDATE medicines SET 
            ndc = ?, 
            name = ?, 
            manufacturer = ?, 
            category = ?, 
            dosage_form = ?,
            unit = ?,
            quantity = ?, 
            reorder_level = ?,
            price = ?, 
            expiration_date = ?,
            batch_number = ?,
            status = ?
        WHERE id = ?";
    } else {
        $sql = "UPDATE medicines SET 
            ndc = ?, 
            name = ?, 
            manufacturer = ?, 
            category = ?, 
            dosage_form = ?, 
            quantity = ?, 
            reorder_level = ?,
            price = ?, 
            expiration_date = ?,
            batch_number = ?,
            status = ?
        WHERE id = ?";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

    if ($hasUnit) {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'ssssssiidsisi',  // 13 parameters
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
            $status,
            $medicine_id
        );
    } else {
        $bound = mysqli_stmt_bind_param(
            $stmt, 
            'sssssiidsisi',  // 12 parameters
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
            $status,
            $medicine_id
        );
        }
    }
    
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

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedRows === 0) {
        sendJsonResponse(false, 'No medicine found with the provided ID or no changes were made', null, 404);
    }

    // Fetch the updated medicine data
    if ($hasNewStructure) {
        $selectSql = "SELECT medicine_id as id, medicine_name as name, medicine_group as category, 
                     generic_name, dosage, form, stock as quantity, price
                     FROM medicines 
                     WHERE medicine_id = ?";
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if (!$selectStmt) {
            error_log("Select statement prepare error: " . mysqli_error($conn));
            sendJsonResponse(true, 'Medicine updated successfully', ['id' => $medicine_id], 200);
        }
        mysqli_stmt_bind_param($selectStmt, 's', $medicine_id);
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
    
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        error_log("Select statement prepare error: " . mysqli_error($conn));
        sendJsonResponse(true, 'Medicine updated successfully', ['id' => $medicine_id], 200);
    }
    mysqli_stmt_bind_param($selectStmt, 'i', $medicine_id);
    }
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        sendJsonResponse(true, 'Medicine updated successfully', ['id' => $medicine_id], 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $medicine = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$medicine) {
        sendJsonResponse(true, 'Medicine updated successfully', ['id' => $medicine_id], 200);
    }

    // Format price for response
    if (isset($medicine['price'])) {
        $medicine['price'] = number_format((float)$medicine['price'], 2, '.', '');
    }

    // Sync updated medicine to POS system
    try {
        $posSyncResult = updateMedicineInPOS($medicine);
        if ($posSyncResult['success']) {
            error_log("Updated medicine successfully synced to POS system: " . $medicine_id);
        } else {
            error_log("POS sync failed for updated medicine ID " . $medicine_id . ": " . $posSyncResult['message']);
        }
    } catch (Exception $e) {
        error_log("POS sync exception for updated medicine ID " . $medicine_id . ": " . $e->getMessage());
    }

    // Success response
    sendJsonResponse(true, 'Medicine updated successfully', $medicine, 200);

} catch (Exception $e) {
    error_log('Exception in edit_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in edit_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in edit_medicine.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

ob_end_flush();
sendJsonResponse(false, 'Unexpected error occurred', null, 500);
?>

