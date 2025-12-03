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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method. Only POST is allowed.', null, 405);
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Check which structure we're using
    $hasNewStructure = hasNewMedicineStructure($conn);
    
    if (!$hasNewStructure) {
        sendJsonResponse(false, 'Database table has not been migrated to the new POS structure. Please run the migration script first: php/migrate_medicines_to_pos_structure.php', [
            'migration_required' => true,
            'migration_script' => 'php/migrate_medicines_to_pos_structure.php'
        ], 500);
    }

    // Always use new POS structure - Get and sanitize form data
    $name = isset($_POST['medicineName']) ? trim($_POST['medicineName']) : '';
    
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $category = $category !== '' ? $category : 'Uncategorized';
    
    // Get generic_name (required for POS)
    $generic_name = isset($_POST['genericName']) ? trim($_POST['genericName']) : '';
    $generic_name = $generic_name !== '' ? $generic_name : '';
    
    // Get unit/dosage value from form
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $unit = $unit !== '' ? $unit : 'Tablet';
    
    // Validate unit value against allowed values
    $allowedUnits = [
        'Capsule', 'Tablet', 'Pill', 'Bottle', 'Vial', 'Ampoule', 'Syringe', 'Tube',
        'Cream', 'Ointment', 'Gel', 'Drops', 'Spray', 'Inhaler', 'Patch',
        'ml', 'mg', 'g', 'kg', 'L', 'mcg', 'IU'
    ];
    
    if ($unit !== null && !in_array($unit, $allowedUnits)) {
        sendJsonResponse(false, 'Invalid unit value. Please select a valid unit from the list.', null, 400);
    }
    
    // For POS structure: dosage and form are separate (both use unit value)
    $dosage = $unit;
    $form = $unit;
    
    $stock = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    
    // Validate required fields
    if (empty($name)) {
        sendJsonResponse(false, 'Medicine Name is required', null, 400);
    }
    if (empty($category)) {
        $category = 'Uncategorized';
    }
    if (empty($generic_name)) {
        $generic_name = ''; // Allow empty generic name
    }
    if ($stock < 0) {
        sendJsonResponse(false, 'Stock cannot be negative', null, 400);
    }
    if ($price < 0) {
        sendJsonResponse(false, 'Price cannot be negative', null, 400);
    }

    // Check for existing medicine with same name - Always use new POS structure
    $checkSql = "SELECT medicine_id, medicine_name, stock FROM medicines 
                WHERE medicine_name = ? 
                    LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        error_log("Check prepare error: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error during duplicate check', null, 500);
    }
    
    mysqli_stmt_bind_param($checkStmt, 's', $name);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        $existing = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);
        
        // Same name → Increment stock
        $existingId = $existing['medicine_id'];
        $existingStock = (int)$existing['stock'];
        $newStock = $existingStock + $stock;
            
        // Update existing medicine
            $updateSql = "UPDATE medicines SET 
                      stock = ?,
                      price = ?
                      WHERE medicine_id = ?";
            
            $updateStmt = mysqli_prepare($conn, $updateSql);
            if (!$updateStmt) {
                error_log("Update prepare error: " . mysqli_error($conn));
            sendJsonResponse(false, 'Database error during stock update', null, 500);
            }
            
        mysqli_stmt_bind_param($updateStmt, 'ids', $newStock, $price, $existingId);
            
            if (!mysqli_stmt_execute($updateStmt)) {
                $error = mysqli_stmt_error($updateStmt);
                error_log("Update execute error: " . $error);
                mysqli_stmt_close($updateStmt);
            sendJsonResponse(false, 'Failed to update medicine stock: ' . $error, null, 500);
            }
            
            mysqli_stmt_close($updateStmt);
            
        // Fetch updated medicine
        $selectSql = "SELECT medicine_id as id, medicine_name as name, medicine_group as category, 
                     generic_name, dosage, form, stock as quantity, price
                     FROM medicines WHERE medicine_id = ?";
            $selectStmt = mysqli_prepare($conn, $selectSql);
        mysqli_stmt_bind_param($selectStmt, 's', $existingId);
            mysqli_stmt_execute($selectStmt);
            $selectResult = mysqli_stmt_get_result($selectStmt);
            $updatedMedicine = mysqli_fetch_assoc($selectResult);
            mysqli_stmt_close($selectStmt);
            
            if (isset($updatedMedicine['price'])) {
                $updatedMedicine['price'] = number_format((float)$updatedMedicine['price'], 2, '.', '');
            }
            
        // Sync updated medicine to POS system
        try {
            $posSyncResult = updateMedicineInPOS($updatedMedicine);
            if ($posSyncResult['success']) {
                error_log("Updated medicine successfully synced to POS system: " . $existingId);
        } else {
                error_log("POS sync failed for updated medicine ID " . $existingId . ": " . $posSyncResult['message']);
        }
        } catch (Exception $e) {
            error_log("POS sync exception for updated medicine ID " . $existingId . ": " . $e->getMessage());
    }
        
        sendJsonResponse(true, "Medicine stock updated successfully. Stock increased from {$existingStock} to {$newStock}.", $updatedMedicine, 200);
        exit;
    }
    mysqli_stmt_close($checkStmt);
    
    // Generate next primary key ID - Always use new POS structure
    $maxIdQuery = "SELECT MAX(CAST(medicine_id AS UNSIGNED)) as max_id FROM medicines WHERE medicine_id REGEXP '^[0-9]+$'";
    $maxIdResult = mysqli_query($conn, $maxIdQuery);
    
    if (!$maxIdResult) {
        error_log("Error getting max ID: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error while fetching next ID', null, 500);
    }
    
    $maxIdRow = mysqli_fetch_assoc($maxIdResult);
    $maxId = $maxIdRow['max_id'];
    
    if ($maxId === null || $maxId === '') {
        $nextId = 1;
    } else {
        $nextId = (int)$maxId + 1;
    }
    
    if ($nextId <= 0) {
        $nextId = 1;
    }
    
    $medicine_id = (string)$nextId;
    error_log("Generated next medicine_id: " . $medicine_id);
    
    // Prepare SQL INSERT statement - Always use new POS structure columns
        $sql = "INSERT INTO medicines (
        medicine_id,
        medicine_group,
        medicine_name,
        generic_name,
        dosage,
        form,
        stock,
        price
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = mysqli_error($conn);
        error_log("MySQL prepare error: " . $error);
        sendJsonResponse(false, 'Database preparation error: ' . $error, ['sql_error' => $error], 500);
    }

        $bound = mysqli_stmt_bind_param(
            $stmt, 
        'ssssssid',  // 8 parameters: medicine_id, medicine_group, medicine_name, generic_name, dosage, form, stock, price
        $medicine_id,
            $category, 
            $name, 
        $generic_name,
        $dosage,
        $form,
        $stock,
        $price
        );
    
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
        error_log("Attempted to insert with ID: " . $medicine_id);
        
        mysqli_stmt_close($stmt);
        
        // Check for unique constraint errors (especially ndc_name from old structure)
        if (strpos($error, 'Duplicate') !== false || $errorCode === 1062) {
            if (strpos($error, 'ndc_name') !== false || strpos($error, 'ndc') !== false) {
                $errorMessage = 'Database constraint error: The medicines table has a unique constraint on NDC that conflicts with the new POS structure. Please run: php/remove_ndc_constraint.php to remove this constraint.';
                sendJsonResponse(false, $errorMessage, [
                    'error_code' => $errorCode,
                    'error' => $error,
                    'fix_script' => 'php/remove_ndc_constraint.php',
                    'migration_required' => true
                ], 500);
            }
        }
        
        // Check for PRIMARY KEY duplicate error
        if ((strpos($error, 'Duplicate') !== false && strpos($error, 'PRIMARY') !== false) || 
            (strpos($error, 'Duplicate') !== false && strpos($error, "'0'") !== false)) {
            error_log("Primary key conflict detected. Attempted ID: " . $medicine_id);
            error_log("Error details: " . $error);
            
            // If the error is about '0', it means our ID calculation failed
            if (strpos($error, "'0'") !== false || $medicine_id <= 0) {
                $errorMessage = 'Primary key generation error: Invalid ID (0) was generated. This indicates a problem with ID calculation.';
                sendJsonResponse(false, $errorMessage, [
                    'error_code' => $errorCode, 
                    'error' => $error,
                    'attempted_id' => $medicine_id,
                    'suggestion' => 'Please check the database and ensure the medicines table structure is correct.'
                ], 500);
            } else {
                // Get current max ID again (in case another process inserted a record)
                $retryMaxIdQuery = "SELECT MAX(CAST(medicine_id AS UNSIGNED)) as max_id FROM medicines WHERE medicine_id REGEXP '^[0-9]+$'";
                $retryMaxIdResult = mysqli_query($conn, $retryMaxIdQuery);
                if ($retryMaxIdResult) {
                    $retryMaxIdRow = mysqli_fetch_assoc($retryMaxIdResult);
                    $retryMaxId = $retryMaxIdRow['max_id'];
                    
                    if ($retryMaxId === null || $retryMaxId === '') {
                        $retryNextId = 1;
                    } else {
                        $retryNextId = (int)$retryMaxId + 1;
                    }
                    
                    if ($retryNextId <= 0) {
                        $retryNextId = 1;
                    }
                    
                    error_log("Recalculated next ID: " . $retryNextId . " (previous was: " . $medicine_id . ")");
                }
                
                $errorMessage = 'Primary key conflict detected. The calculated ID (' . $medicine_id . ') already exists. Please try again.';
                sendJsonResponse(false, $errorMessage, [
                    'error_code' => $errorCode, 
                    'error' => $error,
                    'attempted_id' => $medicine_id,
                    'suggestion' => 'Please refresh and try adding the medicine again.'
                ], 500);
            }
        }
        
        // Check for duplicate medicine_name
        if (strpos($error, 'Duplicate') !== false || $errorCode === 1062) {
            if (strpos($error, 'medicine_name') !== false || strpos($error, 'PRIMARY') === false) {
                $errorMessage = 'A medicine with this name already exists.';
                sendJsonResponse(false, $errorMessage, ['error_code' => $errorCode, 'error' => $error, 'duplicate' => true], 409);
            }
        }
        
        // Check for unknown column error
        if (strpos($error, 'Unknown column') !== false) {
            $errorMessage = 'A required database column is missing. Please run the migration script: php/migrate_medicines_to_pos_structure.php';
            sendJsonResponse(false, $errorMessage, ['error_code' => $errorCode, 'error' => $error], 500);
        }
        
        sendJsonResponse(false, 'Database error: ' . $error, ['error_code' => $errorCode, 'error' => $error], 500);
    }

    // Get the inserted ID - Always use new POS structure
    $insertedId = $medicine_id;
    
    mysqli_stmt_close($stmt);
    error_log("Successfully inserted medicine with ID: " . $insertedId);

    // Fetch the inserted medicine data to return - Always use new POS structure
    $selectSql = "SELECT medicine_id as id, medicine_name as name, medicine_group as category, 
                 generic_name, dosage, form, stock as quantity, price
                 FROM medicines WHERE medicine_id = ?";
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        error_log("Select statement prepare error: " . mysqli_error($conn));
        $basicData = [
            'id' => $insertedId,
            'name' => $name,
            'category' => $category,
            'generic_name' => $generic_name,
            'dosage' => $dosage,
            'form' => $form,
            'quantity' => $stock,
            'price' => number_format($price, 2, '.', '')
        ];
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
    }
    mysqli_stmt_bind_param($selectStmt, 's', $insertedId);
    
    if (!mysqli_stmt_execute($selectStmt)) {
        error_log("Select statement execute error: " . mysqli_stmt_error($selectStmt));
        mysqli_stmt_close($selectStmt);
        $basicData = [
            'id' => $insertedId,
            'name' => $name,
            'category' => $category,
            'generic_name' => $generic_name,
            'dosage' => $dosage,
            'form' => $form,
            'quantity' => $stock,
            'price' => number_format($price, 2, '.', '')
        ];
        
        // Sync to POS system before sending response
        try {
            $posSyncResult = syncMedicineToPOS($basicData);
            if ($posSyncResult['success']) {
                error_log("Medicine successfully synced to POS system: " . $insertedId);
            } else {
                error_log("POS sync failed for medicine ID " . $insertedId . ": " . $posSyncResult['message']);
            }
        } catch (Exception $e) {
            error_log("POS sync exception for medicine ID " . $insertedId . ": " . $e->getMessage());
        }
        
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
    }

    $result = mysqli_stmt_get_result($selectStmt);
    $medicine = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$medicine) {
        $basicData = [
            'id' => $insertedId,
            'name' => $name,
            'category' => $category,
            'generic_name' => $generic_name,
            'dosage' => $dosage,
            'form' => $form,
            'quantity' => $stock,
            'price' => number_format($price, 2, '.', '')
        ];
        
        // Sync to POS system before sending response
        try {
            $posSyncResult = syncMedicineToPOS($basicData);
            if ($posSyncResult['success']) {
                error_log("Medicine successfully synced to POS system: " . $insertedId);
            } else {
                error_log("POS sync failed for medicine ID " . $insertedId . ": " . $posSyncResult['message']);
            }
        } catch (Exception $e) {
            error_log("POS sync exception for medicine ID " . $insertedId . ": " . $e->getMessage());
        }
        
        sendJsonResponse(true, 'Medicine added successfully', $basicData, 200);
    }

    // Format price for response
    if (isset($medicine['price'])) {
        $medicine['price'] = number_format((float)$medicine['price'], 2, '.', '');
    }

    // Sync to POS system (non-blocking - don't fail if sync fails)
    try {
        $posSyncResult = syncMedicineToPOS($medicine);
        if ($posSyncResult['success']) {
            error_log("Medicine successfully synced to POS system: " . $insertedId);
        } else {
            error_log("POS sync failed for medicine ID " . $insertedId . ": " . $posSyncResult['message']);
            // Don't fail the operation, just log the error
        }
    } catch (Exception $e) {
        error_log("POS sync exception for medicine ID " . $insertedId . ": " . $e->getMessage());
        // Don't fail the operation, just log the error
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
