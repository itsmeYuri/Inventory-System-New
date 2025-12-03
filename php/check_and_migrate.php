<?php
/**
 * Quick script to check database structure and run migration if needed
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/medicine_structure_helper.php';

echo "Checking database structure...\n\n";

$hasNewStructure = hasNewMedicineStructure($conn);

if ($hasNewStructure) {
    echo "✓ New POS structure already exists!\n";
    echo "The medicines table has the following new columns:\n";
    
    $columns = ['medicine_id', 'medicine_name', 'medicine_group', 'generic_name', 'dosage', 'form', 'stock'];
    foreach ($columns as $col) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = '{$col}'");
        if (mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            echo "  - {$col} ({$row['Type']})\n";
        } else {
            echo "  - {$col} (MISSING!)\n";
        }
    }
    exit(0);
} else {
    echo "✗ New POS structure NOT found.\n";
    echo "You need to run the migration script.\n\n";
    echo "Please visit this URL in your browser:\n";
    echo "http://localhost:3000/php/migrate_medicines_to_pos_structure.php\n\n";
    echo "Or run from command line:\n";
    echo "php php/migrate_medicines_to_pos_structure.php\n";
    exit(1);
}
?>
