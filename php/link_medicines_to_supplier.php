<?php
// Link Medicines to Supplier API
// Links multiple medicines to a supplier using the supplier_medicines junction table

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
if (ob_get_level() == 0) {
    ob_start();
}

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

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    // Clean output buffer if it exists
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Ensure no output has been sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    
    // End output buffering if it exists
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed. Only POST requests are accepted.', null, 405);
}

// Require database connection
require_once __DIR__ . '/conn.php';

try {
    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }
    
    // Debug: Log received POST data
    error_log("link_medicines_to_supplier.php - POST data: " . print_r($_POST, true));

    // Get supplier ID
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    if ($supplier_id <= 0) {
        sendJsonResponse(false, 'Invalid supplier ID', null, 400);
    }
    
    // Verify supplier exists
    $checkSupplier = mysqli_query($conn, "SELECT id FROM suppliers WHERE id = {$supplier_id}");
    if (!$checkSupplier) {
        $error = mysqli_error($conn);
        error_log("Error checking supplier: " . $error);
        sendJsonResponse(false, 'Database error checking supplier: ' . $error, null, 500);
    }
    if (mysqli_num_rows($checkSupplier) == 0) {
        sendJsonResponse(false, 'Supplier not found with ID: ' . $supplier_id, null, 404);
    }

    // Get medicine IDs (array)
    $medicine_ids_json = isset($_POST['medicine_ids']) ? $_POST['medicine_ids'] : '[]';
    
    // Handle both JSON string and array formats
    if (is_string($medicine_ids_json)) {
        $medicine_ids = json_decode($medicine_ids_json, true);
        // If JSON decode failed, try to parse as comma-separated string
        if (json_last_error() !== JSON_ERROR_NONE) {
            $medicine_ids = array_filter(array_map('trim', explode(',', $medicine_ids_json)));
        }
    } else {
        $medicine_ids = $medicine_ids_json;
    }
    
    if (!is_array($medicine_ids)) {
        $medicine_ids = [];
    }
    
    // Filter out invalid IDs and ensure they're integers
    $medicine_ids = array_filter(array_map('intval', $medicine_ids), function($id) {
        return $id > 0;
    });
    
    // Remove duplicates
    $medicine_ids = array_unique($medicine_ids);
    $medicine_ids = array_values($medicine_ids); // Re-index array
    
    // Verify medicines exist
    if (count($medicine_ids) > 0) {
        $medicine_ids_str = implode(',', $medicine_ids);
        $checkMedicines = mysqli_query($conn, "SELECT id FROM medicines WHERE id IN ({$medicine_ids_str})");
        $existing_medicine_ids = [];
        if ($checkMedicines) {
            while ($row = mysqli_fetch_assoc($checkMedicines)) {
                $existing_medicine_ids[] = (int)$row['id'];
            }
        } else {
            $error = mysqli_error($conn);
            error_log("Error checking medicines: " . $error);
        }
        
        // Filter to only include medicines that exist
        $medicine_ids = array_intersect($medicine_ids, $existing_medicine_ids);
    }
    
    error_log("Linking medicines to supplier. Supplier ID: {$supplier_id}, Medicine IDs: " . implode(', ', $medicine_ids));
    
    // If no medicines to link, just return success (before starting transaction)
    if (count($medicine_ids) == 0) {
        sendJsonResponse(true, 'No medicines selected to link', [
            'supplier_id' => $supplier_id,
            'linked_count' => 0,
            'skipped_count' => 0,
            'total_attempted' => 0,
            'errors' => []
        ], 200);
    }

    // Ensure supplier_medicines table exists and is properly configured
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_medicines'");
    $hasTable = mysqli_num_rows($checkTable) > 0;
    
    if (!$hasTable) {
        // Create the junction table if it doesn't exist
        $createTableSql = "CREATE TABLE supplier_medicines (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT UNSIGNED NOT NULL,
            medicine_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_supplier_medicine (supplier_id, medicine_id),
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_medicine_id (medicine_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
        
        if (!mysqli_query($conn, $createTableSql)) {
            $error = mysqli_error($conn);
            error_log("Error creating supplier_medicines table: " . $error);
            sendJsonResponse(false, 'Database error: Failed to create supplier_medicines table. ' . $error, ['sql_error' => $error], 500);
        } else {
            error_log("Created supplier_medicines table");
        }
    } else {
        // Table exists - ensure it's properly configured
        // Step 1: Delete any rows with id=0
        @mysqli_query($conn, "DELETE FROM supplier_medicines WHERE id = 0");
        
        // Step 2: Get max ID and ensure AUTO_INCREMENT is set correctly
        $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM supplier_medicines");
        $maxId = 0;
        if ($maxIdQuery) {
            $maxRow = mysqli_fetch_assoc($maxIdQuery);
            $maxId = (int)($maxRow['max_id'] ?? 0);
        }
        $nextId = max(1, $maxId + 1);
        
        // Step 3: Force set AUTO_INCREMENT to ensure it's working
        $fixAutoIncrement = "ALTER TABLE supplier_medicines AUTO_INCREMENT = {$nextId}";
        @mysqli_query($conn, $fixAutoIncrement);
        
        // Step 4: Verify and fix id column structure
        $checkIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM supplier_medicines WHERE Field = 'id'");
        if ($checkIdColumn) {
            $idColumn = mysqli_fetch_assoc($checkIdColumn);
            if (strpos($idColumn['Extra'] ?? '', 'auto_increment') === false) {
                // id column doesn't have AUTO_INCREMENT - fix it
                error_log("Fixing id column to have AUTO_INCREMENT");
                @mysqli_query($conn, "ALTER TABLE supplier_medicines MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
            }
        }
        
        // Step 5: Verify AUTO_INCREMENT is working by checking table status
        $statusQuery = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'supplier_medicines'");
        if ($statusQuery) {
            $status = mysqli_fetch_assoc($statusQuery);
            $autoInc = (int)($status['Auto_increment'] ?? 0);
            if ($autoInc <= 0) {
                // AUTO_INCREMENT is broken, force fix it
                error_log("AUTO_INCREMENT is broken ({$autoInc}), forcing fix to {$nextId}");
                @mysqli_query($conn, "ALTER TABLE supplier_medicines AUTO_INCREMENT = {$nextId}");
            }
        }
    }

    // Start transaction
    if (!function_exists('mysqli_begin_transaction')) {
        mysqli_query($conn, "START TRANSACTION");
    } else {
        mysqli_begin_transaction($conn);
    }

    try {
        // First, remove all existing links for this supplier
        $deleteSql = "DELETE FROM supplier_medicines WHERE supplier_id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);
            if (!mysqli_stmt_execute($deleteStmt)) {
                error_log("Warning: Failed to delete existing links: " . mysqli_stmt_error($deleteStmt));
            }
            mysqli_stmt_close($deleteStmt);
        }

        // Insert new links using raw SQL for maximum reliability
        $inserted = 0;
        $errors = [];
        $skipped = 0;
        
        if (count($medicine_ids) > 0) {
            // Use raw SQL with INSERT IGNORE - simpler and more reliable
            $escapedSupplierId = (int)$supplier_id;
            
            foreach ($medicine_ids as $medicine_id) {
                $medicine_id = (int)$medicine_id;
                if ($medicine_id <= 0) {
                    $skipped++;
                    continue;
                }
                
                // Use raw SQL with proper escaping
                $escapedMedicineId = (int)$medicine_id;
                $insertSql = "INSERT IGNORE INTO supplier_medicines (supplier_id, medicine_id) VALUES ({$escapedSupplierId}, {$escapedMedicineId})";
                
                if (mysqli_query($conn, $insertSql)) {
                    $affectedRows = mysqli_affected_rows($conn);
                    if ($affectedRows > 0) {
                        $inserted++;
                    } else {
                        // INSERT IGNORE returns 0 affected rows for duplicates
                        $skipped++;
                    }
                } else {
                    $error = mysqli_error($conn);
                    $errorCode = mysqli_errno($conn);
                    
                    // If we get a PRIMARY KEY error with '0', fix the table and retry
                    if ($errorCode === 1062 && strpos($error, "'0'") !== false) {
                        error_log("Got PRIMARY KEY '0' error, fixing table and retrying");
                        
                        // Fix AUTO_INCREMENT
                        $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM supplier_medicines");
                        $maxId = 0;
                        if ($maxIdQuery) {
                            $maxRow = mysqli_fetch_assoc($maxIdQuery);
                            $maxId = (int)($maxRow['max_id'] ?? 0);
                        }
                        $nextId = max(1, $maxId + 1);
                        @mysqli_query($conn, "ALTER TABLE supplier_medicines AUTO_INCREMENT = {$nextId}");
                        @mysqli_query($conn, "DELETE FROM supplier_medicines WHERE id = 0");
                        
                        // Retry the insert
                        if (mysqli_query($conn, $insertSql)) {
                            $inserted++;
                        } else {
                            $retryError = mysqli_error($conn);
                            $errors[] = "Failed to link medicine ID {$medicine_id}: " . $retryError;
                            error_log("Retry insert failed for medicine {$medicine_id}: " . $retryError);
                        }
                    } elseif ($errorCode === 1062) {
                        // Regular duplicate (UNIQUE constraint on supplier_id + medicine_id)
                        $skipped++;
                        error_log("Skipped duplicate link: supplier_id={$supplier_id}, medicine_id={$medicine_id}");
                    } else {
                        $errors[] = "Failed to link medicine ID {$medicine_id}: " . $error . " (Error Code: {$errorCode})";
                        error_log("Error linking medicine {$medicine_id}: " . $error . " (Code: {$errorCode})");
                    }
                }
            }
        }
        
        // Commit transaction
        if (!mysqli_commit($conn)) {
            $error = mysqli_error($conn);
            error_log("Error committing transaction: " . $error);
            throw new Exception("Failed to commit transaction: " . $error);
        }
        
        $message = "Successfully linked {$inserted} medicine(s) to supplier";
        if ($skipped > 0) {
            $message .= " ({$skipped} duplicate(s) skipped)";
        }
        if (count($errors) > 0) {
            $message .= ". " . count($errors) . " error(s) occurred.";
        }
        
        sendJsonResponse(true, $message, [
            'supplier_id' => $supplier_id,
            'linked_count' => $inserted,
            'skipped_count' => $skipped,
            'total_attempted' => count($medicine_ids),
            'errors' => $errors
        ], 200);
        
    } catch (Exception $e) {
        if (function_exists('mysqli_rollback')) {
            mysqli_rollback($conn);
        } else {
            mysqli_query($conn, "ROLLBACK");
        }
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in link_medicines_to_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), [
        'exception' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Error $e) {
    error_log('Fatal error in link_medicines_to_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    error_log('Throwable in link_medicines_to_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

?>
