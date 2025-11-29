<?php
/**
 * Add Unit Column to Medicines Table
 * This script adds a 'unit' column to track product units (capsule, tablet, ml, mg, etc.)
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Unit Column to Medicines</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Unit Column to Medicines Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Check if unit column already exists
            $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
            if (mysqli_num_rows($checkUnit) > 0) {
                echo '<div class="info">✓ medicines table already has unit column.</div>';
            } else {
                echo '<div class="info">Adding unit column to medicines table...</div>';
                
                // Add unit column
                $alterSql = "ALTER TABLE medicines 
                             ADD COLUMN unit VARCHAR(50) NULL DEFAULT NULL AFTER dosage_form";
                
                if (mysqli_query($conn, $alterSql)) {
                    echo '<div class="success">✓ unit column added to medicines table successfully.</div>';
                    
                    // Add index for better query performance
                    $indexSql = "ALTER TABLE medicines ADD INDEX idx_unit (unit)";
                    if (mysqli_query($conn, $indexSql)) {
                        echo '<div class="success">✓ Index added on unit column.</div>';
                    } else {
                        echo '<div class="info">Note: Index could not be added (may already exist).</div>';
                    }
                } else {
                    throw new Exception('Error adding unit column: ' . mysqli_error($conn));
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        } catch (Error $e) {
            echo '<div class="error">';
            echo '<strong>✗ Fatal Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2>Available Units</h2>
            <p>The following units are supported:</p>
            <ul>
                <li><strong>Form Types:</strong> Capsule, Tablet, Pill, Bottle, Vial, Ampoule, Syringe, Tube, Cream, Ointment, Gel, Drops, Spray, Inhaler, Patch</li>
                <li><strong>Measurement Units:</strong> ml (milliliter), mg (milligram), g (gram), kg (kilogram), L (liter), mcg (microgram), IU (International Unit)</li>
            </ul>
        </div>
    </div>
</body>
</html>

