<?php
// Archive Supplier API
// Archives a supplier instead of deleting it

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

try {
    require_once __DIR__ . '/conn.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get supplier ID from POST
    $supplier_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($supplier_id <= 0) {
        error_log("Invalid supplier ID received: " . ($_POST['id'] ?? 'not set'));
        sendJsonResponse(false, 'Invalid supplier ID: ' . ($_POST['id'] ?? 'not provided'), null, 400);
    }

    error_log("Attempting to archive supplier ID: " . $supplier_id);

    // Check if supplier exists
    $checkSql = "SELECT * FROM suppliers WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        $error = mysqli_error($conn);
        error_log("Check prepare error: " . $error);
        sendJsonResponse(false, 'Database error during supplier check: ' . $error, null, 500);
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $supplier_id);
    if (!mysqli_stmt_execute($checkStmt)) {
        $error = mysqli_stmt_error($checkStmt);
        error_log("Check execute error: " . $error);
        mysqli_stmt_close($checkStmt);
        sendJsonResponse(false, 'Database error executing query: ' . $error, null, 500);
    }
    
    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        mysqli_stmt_close($checkStmt);
        error_log("Supplier not found in database. ID: " . $supplier_id);
        sendJsonResponse(false, 'Supplier not found with ID: ' . $supplier_id, null, 404);
    }

    $supplier = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    // Check if archived_suppliers table exists, if not create it
    $tableCheck = "SHOW TABLES LIKE 'archived_suppliers'";
    $tableResult = mysqli_query($conn, $tableCheck);
    if (!$tableResult) {
        error_log("Error checking for archived_suppliers table: " . mysqli_error($conn));
        sendJsonResponse(false, 'Database error checking archive table: ' . mysqli_error($conn), null, 500);
    }
    
    if (mysqli_num_rows($tableResult) == 0) {
        error_log("Creating archived_suppliers table...");
        // Create archived_suppliers table
        $createTableSql = "CREATE TABLE IF NOT EXISTS archived_suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
            original_id INT UNSIGNED NOT NULL COMMENT 'Original supplier ID before archiving',
            name VARCHAR(255) NOT NULL COMMENT 'Supplier name',
            contact_person VARCHAR(255) NULL COMMENT 'Contact person',
            email VARCHAR(255) NULL COMMENT 'Email address',
            phone VARCHAR(50) NULL COMMENT 'Phone number',
            address VARCHAR(255) NULL COMMENT 'Address/Location',
            website VARCHAR(255) NULL COMMENT 'Website URL',
            notes TEXT NULL COMMENT 'Additional notes',
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When supplier was archived',
            archived_by VARCHAR(255) NULL COMMENT 'User who archived the supplier',
            reason TEXT NULL COMMENT 'Reason for archiving',
            INDEX idx_original_id (original_id),
            INDEX idx_archived_at (archived_at),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived suppliers'";
        
        if (!mysqli_query($conn, $createTableSql)) {
            $error = mysqli_error($conn);
            error_log("Error creating archived_suppliers table: " . $error);
            sendJsonResponse(false, 'Failed to create archive table: ' . $error, null, 500);
        }
        error_log("archived_suppliers table created successfully");
    } else {
        error_log("archived_suppliers table already exists");
        
        // Check if contact_person column exists, if not add it
        $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM archived_suppliers LIKE 'contact_person'");
        if (!$checkColumn || mysqli_num_rows($checkColumn) == 0) {
            error_log("Adding contact_person column to archived_suppliers table...");
            $alterSql = "ALTER TABLE archived_suppliers ADD COLUMN contact_person VARCHAR(255) NULL COMMENT 'Contact person' AFTER name";
            if (!mysqli_query($conn, $alterSql)) {
                $error = mysqli_error($conn);
                error_log("Error adding contact_person column: " . $error);
                // Continue anyway, we'll handle it in the INSERT
            } else {
                error_log("contact_person column added successfully");
            }
        }
        
        // Check for other missing columns and add them if needed
        $requiredColumns = [
            'email' => "ADD COLUMN email VARCHAR(255) NULL COMMENT 'Email address' AFTER contact_person",
            'phone' => "ADD COLUMN phone VARCHAR(50) NULL COMMENT 'Phone number' AFTER email",
            'address' => "ADD COLUMN address VARCHAR(255) NULL COMMENT 'Address/Location' AFTER phone",
            'website' => "ADD COLUMN website VARCHAR(255) NULL COMMENT 'Website URL' AFTER address",
            'notes' => "ADD COLUMN notes TEXT NULL COMMENT 'Additional notes' AFTER website",
            'archived_by' => "ADD COLUMN archived_by VARCHAR(255) NULL COMMENT 'User who archived the supplier' AFTER notes",
            'reason' => "ADD COLUMN reason TEXT NULL COMMENT 'Reason for archiving' AFTER archived_by"
        ];
        
        foreach ($requiredColumns as $column => $alterSql) {
            $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM archived_suppliers LIKE '$column'");
            if (!$checkCol || mysqli_num_rows($checkCol) == 0) {
                error_log("Adding $column column to archived_suppliers table...");
                $alterQuery = "ALTER TABLE archived_suppliers " . $alterSql;
                if (!mysqli_query($conn, $alterQuery)) {
                    error_log("Warning: Failed to add $column column: " . mysqli_error($conn));
                }
            }
        }
    }
    
    // Check what columns actually exist in the table
    $columnsQuery = "SHOW COLUMNS FROM archived_suppliers";
    $columnsResult = mysqli_query($conn, $columnsQuery);
    $existingColumns = [];
    if ($columnsResult) {
        while ($row = mysqli_fetch_assoc($columnsResult)) {
            $existingColumns[] = $row['Field'];
        }
    }
    error_log("Existing columns in archived_suppliers: " . implode(', ', $existingColumns));

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Archive supplier
        $archived_by = isset($_POST['archived_by']) ? $_POST['archived_by'] : null;
        $reason = isset($_POST['reason']) ? $_POST['reason'] : 'Supplier archived';

        // Prepare values from supplier data
        $original_id = (int)$supplier['id'];
        $name = $supplier['name'] ?? '';
        $contact_person = isset($supplier['contact_person']) && $supplier['contact_person'] !== '' ? $supplier['contact_person'] : null;
        $email = isset($supplier['email']) && $supplier['email'] !== '' ? $supplier['email'] : null;
        $phone = isset($supplier['phone']) && $supplier['phone'] !== '' ? $supplier['phone'] : null;
        $address = isset($supplier['address']) && $supplier['address'] !== '' ? $supplier['address'] : null;
        $website = isset($supplier['website']) && $supplier['website'] !== '' ? $supplier['website'] : null;
        $notes = isset($supplier['notes']) && $supplier['notes'] !== '' ? $supplier['notes'] : null;
        $archived_by_value = $archived_by ?? null;
        $reason_value = $reason ?? 'Supplier archived';
        
        // Build INSERT statement dynamically based on existing columns
        $insertColumns = ['original_id', 'name'];
        $insertValues = ['?', '?'];
        $bindTypes = 'is';
        $bindValues = [&$original_id, &$name];
        
        // Add optional columns if they exist in the table
        if (in_array('contact_person', $existingColumns)) {
            $insertColumns[] = 'contact_person';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$contact_person;
        }
        
        if (in_array('email', $existingColumns)) {
            $insertColumns[] = 'email';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$email;
        }
        
        if (in_array('phone', $existingColumns)) {
            $insertColumns[] = 'phone';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$phone;
        }
        
        if (in_array('address', $existingColumns)) {
            $insertColumns[] = 'address';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$address;
        }
        
        if (in_array('website', $existingColumns)) {
            $insertColumns[] = 'website';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$website;
        }
        
        if (in_array('notes', $existingColumns)) {
            $insertColumns[] = 'notes';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$notes;
        }
        
        if (in_array('archived_by', $existingColumns)) {
            $insertColumns[] = 'archived_by';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$archived_by_value;
        }
        
        if (in_array('reason', $existingColumns)) {
            $insertColumns[] = 'reason';
            $insertValues[] = '?';
            $bindTypes .= 's';
            $bindValues[] = &$reason_value;
        }
        
        $archiveSql = "INSERT INTO archived_suppliers (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        
        error_log("Archive SQL: " . $archiveSql);
        error_log("Bind types: " . $bindTypes);
        error_log("Columns being inserted: " . implode(', ', $insertColumns));
        
        $archiveStmt = mysqli_prepare($conn, $archiveSql);
        if (!$archiveStmt) {
            $error = mysqli_error($conn);
            error_log("Archive prepare error: " . $error);
            throw new Exception('Database error during archiving: ' . $error);
        }
        
        // Bind parameters - use call_user_func_array with proper references
        $bindArgs = array_merge([$archiveStmt, $bindTypes], $bindValues);
        if (!call_user_func_array('mysqli_stmt_bind_param', $bindArgs)) {
            $error = mysqli_stmt_error($archiveStmt);
            error_log("Bind parameter error: " . $error);
            mysqli_stmt_close($archiveStmt);
            throw new Exception('Failed to bind parameters: ' . $error);
        }

        if (!mysqli_stmt_execute($archiveStmt)) {
            $error = mysqli_stmt_error($archiveStmt);
            $errorCode = mysqli_stmt_errno($archiveStmt);
            error_log("Archive execute error [$errorCode]: " . $error);
            error_log("Archive SQL: " . $archiveSql);
            error_log("Supplier data: " . print_r($supplier, true));
            error_log("Bind values: original_id=$original_id, name=$name, contact_person=$contact_person, email=$email, phone=$phone, address=$address, archived_by=$archived_by_value, reason=$reason_value");
            mysqli_stmt_close($archiveStmt);
            throw new Exception('Failed to archive supplier: ' . $error . ' (Error Code: ' . $errorCode . ')');
        }
        
        $archivedId = mysqli_insert_id($conn);
        error_log("Supplier archived successfully to archived_suppliers table with archive ID: $archivedId");
        mysqli_stmt_close($archiveStmt);

        // Handle foreign key constraints before deleting
        // 1. Update batches to set supplier_id to NULL (batches table has FK without ON DELETE CASCADE)
        $updateBatchesSql = "UPDATE batches SET supplier_id = NULL WHERE supplier_id = ?";
        $updateBatchesStmt = mysqli_prepare($conn, $updateBatchesSql);
        if ($updateBatchesStmt) {
            mysqli_stmt_bind_param($updateBatchesStmt, 'i', $supplier_id);
            if (!mysqli_stmt_execute($updateBatchesStmt)) {
                error_log("Warning: Failed to update batches supplier_id: " . mysqli_stmt_error($updateBatchesStmt));
                // Continue anyway, as this is not critical
            }
            mysqli_stmt_close($updateBatchesStmt);
        }

        // 2. Check if there are orders with this supplier (orders has ON DELETE RESTRICT)
        // We'll update the supplier_id to NULL in orders as well, or we could prevent archiving
        // For now, let's update orders to set supplier_id to NULL
        $updateOrdersSql = "UPDATE orders SET supplier_id = NULL WHERE supplier_id = ?";
        $updateOrdersStmt = mysqli_prepare($conn, $updateOrdersSql);
        if ($updateOrdersStmt) {
            mysqli_stmt_bind_param($updateOrdersStmt, 'i', $supplier_id);
            if (!mysqli_stmt_execute($updateOrdersStmt)) {
                error_log("Warning: Failed to update orders supplier_id: " . mysqli_stmt_error($updateOrdersStmt));
                // Continue anyway
            }
            mysqli_stmt_close($updateOrdersStmt);
        }

        // 3. Delete supplier_medicines relationships (junction table)
        // This table may have ON DELETE RESTRICT or no CASCADE, so we need to delete manually
        $deleteSupplierMedicinesSql = "DELETE FROM supplier_medicines WHERE supplier_id = ?";
        $deleteSupplierMedicinesStmt = mysqli_prepare($conn, $deleteSupplierMedicinesSql);
        if (!$deleteSupplierMedicinesStmt) {
            throw new Exception('Database error preparing supplier_medicines deletion: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($deleteSupplierMedicinesStmt, 'i', $supplier_id);
        if (!mysqli_stmt_execute($deleteSupplierMedicinesStmt)) {
            $error = mysqli_stmt_error($deleteSupplierMedicinesStmt);
            mysqli_stmt_close($deleteSupplierMedicinesStmt);
            throw new Exception('Failed to delete supplier_medicines relationships: ' . $error);
        }
        mysqli_stmt_close($deleteSupplierMedicinesStmt);

        // Delete supplier from active table
        $deleteSql = "DELETE FROM suppliers WHERE id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new Exception('Database error during deletion: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);

        if (!mysqli_stmt_execute($deleteStmt)) {
            $error = mysqli_stmt_error($deleteStmt);
            mysqli_stmt_close($deleteStmt);
            throw new Exception('Failed to delete supplier: ' . $error);
        }

        $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if ($affectedRows === 0) {
            mysqli_rollback($conn);
            sendJsonResponse(false, 'No supplier was deleted after archiving', null, 404);
        }

        // Commit transaction
        mysqli_commit($conn);

        // Success response
        sendJsonResponse(true, "Supplier '{$supplier['name']}' archived successfully", [
            'id' => $supplier_id,
            'name' => $supplier['name']
        ], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('File: ' . $e->getFile() . ', Line: ' . $e->getLine());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), [
        'exception' => $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 500);
} catch (Error $e) {
    error_log('Fatal error in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('File: ' . $e->getFile() . ', Line: ' . $e->getLine());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), [
        'error' => $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 500);
} catch (Throwable $e) {
    error_log('Throwable in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('File: ' . $e->getFile() . ', Line: ' . $e->getLine());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), [
        'error' => $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 500);
}

?>

