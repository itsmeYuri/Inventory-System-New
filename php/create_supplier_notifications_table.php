<?php
/**
 * Create Supplier Notifications Table
 * Creates table to store notifications for suppliers
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h2>Creating Supplier Notifications Table</h2>\n";
echo "<pre>\n";

try {
    // First, check if suppliers table exists
    $checkSuppliersTable = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
    if (!$checkSuppliersTable || mysqli_num_rows($checkSuppliersTable) === 0) {
        echo "✗ Error: 'suppliers' table does not exist. Please create it first.\n";
        echo "   You can create it by adding a supplier through the system.\n";
        exit;
    }
    echo "✓ 'suppliers' table exists\n";
    
    // Check suppliers table structure
    $checkSuppliersId = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'id'");
    if ($checkSuppliersId && mysqli_num_rows($checkSuppliersId) > 0) {
        $idColumn = mysqli_fetch_assoc($checkSuppliersId);
        $supplierIdType = $idColumn['Type'];
        echo "✓ Found 'id' column in suppliers table (Type: " . $supplierIdType . ")\n";
        
        // Determine the matching type for supplier_id
        // If suppliers.id is int(11) (signed), use INT, otherwise use INT UNSIGNED
        $isUnsigned = stripos($supplierIdType, 'unsigned') !== false;
        $supplierIdColumnType = $isUnsigned ? 'INT UNSIGNED' : 'INT';
        echo "✓ Will use '$supplierIdColumnType' for supplier_id to match suppliers.id\n";
    } else {
        echo "✗ Error: 'id' column not found in suppliers table\n";
        exit;
    }
    
    // Check if table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_notifications'");
    
    if ($checkTable && mysqli_num_rows($checkTable) > 0) {
        echo "⚠ 'supplier_notifications' table already exists\n";
        
        // Check if foreign key exists
        $checkFK = mysqli_query($conn, "SELECT CONSTRAINT_NAME 
                                        FROM information_schema.KEY_COLUMN_USAGE 
                                        WHERE TABLE_SCHEMA = DATABASE() 
                                        AND TABLE_NAME = 'supplier_notifications' 
                                        AND REFERENCED_TABLE_NAME = 'suppliers'");
        
        if ($checkFK && mysqli_num_rows($checkFK) > 0) {
            echo "✓ Foreign key constraint already exists\n";
            echo "✅ Table is already properly configured!\n";
        } else {
            // Check current supplier_id column type
            $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM supplier_notifications WHERE Field = 'supplier_id'");
            if ($checkColumn && mysqli_num_rows($checkColumn) > 0) {
                $colInfo = mysqli_fetch_assoc($checkColumn);
                $currentType = $colInfo['Type'];
                echo "   Current supplier_id type: $currentType\n";
                
                // If types don't match, we need to alter the column
                if (($isUnsigned && stripos($currentType, 'unsigned') === false) || 
                    (!$isUnsigned && stripos($currentType, 'unsigned') !== false)) {
                    echo "⚠ Column type mismatch. Altering supplier_id column...\n";
                    $alterSql = "ALTER TABLE supplier_notifications MODIFY supplier_id $supplierIdColumnType NOT NULL";
                    if (mysqli_query($conn, $alterSql)) {
                        echo "✓ Updated supplier_id column type\n";
                    } else {
                        echo "⚠ Could not alter column: " . mysqli_error($conn) . "\n";
                    }
                }
            }
            
            // Try to add foreign key to existing table
            echo "   Attempting to add foreign key constraint...\n";
            $fkSql = "ALTER TABLE supplier_notifications 
                      ADD CONSTRAINT fk_supplier_notifications_supplier_id 
                      FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE";
            
            if (mysqli_query($conn, $fkSql)) {
                echo "✓ Added foreign key constraint to existing table\n";
            } else {
                $fkError = mysqli_error($conn);
                echo "⚠ Could not add foreign key constraint: " . $fkError . "\n";
                echo "   Table exists and will work without foreign key constraint.\n";
                echo "   This is okay - the table will still work, but cascading deletes won't work.\n";
            }
        }
    } else {
        // Create table with matching data type (no foreign key in CREATE statement)
        $createTableSql = "CREATE TABLE supplier_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_id $supplierIdColumnType NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('new_order', 'order_status', 'low_stock', 'system') DEFAULT 'system',
            read_status TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_read_status (read_status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $createTableSql)) {
            echo "✓ Created 'supplier_notifications' table\n";
            
            // Try to add foreign key constraint separately
            echo "   Attempting to add foreign key constraint...\n";
            $fkSql = "ALTER TABLE supplier_notifications 
                      ADD CONSTRAINT fk_supplier_notifications_supplier_id 
                      FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE";
            
            if (mysqli_query($conn, $fkSql)) {
                echo "✓ Added foreign key constraint\n";
            } else {
                $fkError = mysqli_error($conn);
                echo "⚠ Could not add foreign key constraint: " . $fkError . "\n";
                echo "   Table created successfully without foreign key constraint.\n";
                echo "   This is okay - the table will still work, but cascading deletes won't work.\n";
            }
        } else {
            echo "✗ Error creating table: " . mysqli_error($conn) . "\n";
        }
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>

