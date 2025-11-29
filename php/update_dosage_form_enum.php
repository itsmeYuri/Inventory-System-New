<?php
/**
 * Update Dosage Form Column to ENUM Type
 * This script converts the dosage_form column to an ENUM type with predefined unit values
 * and makes it NOT NULL
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Dosage Form to ENUM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Update Dosage Form Column to ENUM Type</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Check if dosage_form column exists
            $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'dosage_form'");
            if (mysqli_num_rows($checkColumn) === 0) {
                echo '<div class="error">✗ The dosage_form column does not exist in medicines table.</div>';
                exit;
            }
            
            $columnInfo = mysqli_fetch_assoc($checkColumn);
            echo '<div class="info">Current dosage_form column type: <code>' . htmlspecialchars($columnInfo['Type']) . '</code></div>';
            
            // Define the ENUM values (exact list from HTML select)
            $enumValues = [
                // Form Types
                'Capsule',
                'Tablet',
                'Pill',
                'Bottle',
                'Vial',
                'Ampoule',
                'Syringe',
                'Tube',
                'Cream',
                'Ointment',
                'Gel',
                'Drops',
                'Spray',
                'Inhaler',
                'Patch',
                // Measurement Units
                'ml',
                'mg',
                'g',
                'kg',
                'L',
                'mcg',
                'IU'
            ];
            
            // Escape enum values for SQL (single quotes, escaped)
            $escapedEnumValues = array_map(function($value) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $value) . "'";
            }, $enumValues);
            
            $enumString = implode(',', $escapedEnumValues);
            
            echo '<h2>Step 1: Check for Invalid Values</h2>';
            
            // Check for values that won't be in the new ENUM
            $invalidValuesQuery = "SELECT DISTINCT dosage_form, COUNT(*) as count 
                                   FROM medicines 
                                   WHERE dosage_form IS NOT NULL 
                                   AND dosage_form NOT IN ($enumString)
                                   GROUP BY dosage_form";
            $invalidResult = mysqli_query($conn, $invalidValuesQuery);
            
            $invalidValues = [];
            if ($invalidResult && mysqli_num_rows($invalidResult) > 0) {
                echo '<div class="warning">⚠ Found medicines with dosage_form values that are not in the new ENUM list:</div>';
                echo '<ul>';
                while ($row = mysqli_fetch_assoc($invalidResult)) {
                    $invalidValues[] = $row['dosage_form'];
                    echo '<li><strong>' . htmlspecialchars($row['dosage_form']) . '</strong> - ' . $row['count'] . ' medicine(s)</li>';
                }
                echo '</ul>';
                echo '<div class="info">These values will need to be mapped to valid ENUM values before conversion.</div>';
            } else {
                echo '<div class="success">✓ All existing dosage_form values are valid for the new ENUM.</div>';
            }
            
            // Count NULL values
            $nullCountQuery = "SELECT COUNT(*) as count FROM medicines WHERE dosage_form IS NULL";
            $nullCountResult = mysqli_query($conn, $nullCountQuery);
            $nullCount = 0;
            if ($nullCountResult) {
                $row = mysqli_fetch_assoc($nullCountResult);
                $nullCount = (int)$row['count'];
            }
            
            if ($nullCount > 0) {
                echo '<div class="warning">⚠ Found ' . $nullCount . ' medicine(s) with NULL dosage_form. These will be set to default value "Tablet".</div>';
            }
            
            // Step 2: Update invalid values to a default (Tablet)
            if (count($invalidValues) > 0) {
                echo '<h2>Step 2: Updating Invalid Values</h2>';
                $updateCount = 0;
                foreach ($invalidValues as $invalidValue) {
                    $escapedInvalid = mysqli_real_escape_string($conn, $invalidValue);
                    $updateQuery = "UPDATE medicines SET dosage_form = 'Tablet' WHERE dosage_form = '$escapedInvalid'";
                    if (mysqli_query($conn, $updateQuery)) {
                        $affected = mysqli_affected_rows($conn);
                        $updateCount += $affected;
                        echo '<div class="info">Updated ' . $affected . ' medicine(s) with value "' . htmlspecialchars($invalidValue) . '" to "Tablet"</div>';
                    }
                }
                echo '<div class="success">✓ Updated ' . $updateCount . ' medicine(s) with invalid values.</div>';
            }
            
            // Step 3: Update NULL values to default (Tablet)
            if ($nullCount > 0) {
                echo '<h2>Step 3: Updating NULL Values</h2>';
                $updateNullQuery = "UPDATE medicines SET dosage_form = 'Tablet' WHERE dosage_form IS NULL";
                if (mysqli_query($conn, $updateNullQuery)) {
                    $affected = mysqli_affected_rows($conn);
                    echo '<div class="success">✓ Updated ' . $affected . ' medicine(s) with NULL dosage_form to "Tablet".</div>';
                } else {
                    throw new Exception('Failed to update NULL values: ' . mysqli_error($conn));
                }
            }
            
            // Step 4: Alter column to ENUM type
            echo '<h2>Step 4: Converting Column to ENUM Type</h2>';
            
            // First, make it nullable ENUM (to avoid issues)
            $alterSql1 = "ALTER TABLE medicines 
                         MODIFY COLUMN dosage_form ENUM($enumString) NULL";
            
            echo '<div class="info">Step 4a: Converting to ENUM (nullable)...</div>';
            if (mysqli_query($conn, $alterSql1)) {
                echo '<div class="success">✓ Column converted to ENUM type (nullable).</div>';
            } else {
                throw new Exception('Failed to convert column to ENUM: ' . mysqli_error($conn));
            }
            
            // Step 5: Make it NOT NULL with default
            echo '<h2>Step 5: Making Column NOT NULL</h2>';
            $alterSql2 = "ALTER TABLE medicines 
                         MODIFY COLUMN dosage_form ENUM($enumString) NOT NULL DEFAULT 'Tablet'";
            
            echo '<div class="info">Step 5a: Making column NOT NULL with default "Tablet"...</div>';
            if (mysqli_query($conn, $alterSql2)) {
                echo '<div class="success">✓ Column is now NOT NULL with default value "Tablet".</div>';
            } else {
                throw new Exception('Failed to make column NOT NULL: ' . mysqli_error($conn));
            }
            
            // Verify the change
            $verifyQuery = "SHOW COLUMNS FROM medicines WHERE Field = 'dosage_form'";
            $verifyResult = mysqli_query($conn, $verifyQuery);
            if ($verifyResult) {
                $verifyRow = mysqli_fetch_assoc($verifyResult);
                echo '<div class="success" style="margin-top: 20px; padding: 15px; font-weight: bold;">';
                echo '✓ Verification: dosage_form column is now:<br>';
                echo 'Type: <code>' . htmlspecialchars($verifyRow['Type']) . '</code><br>';
                echo 'Null: <code>' . htmlspecialchars($verifyRow['Null']) . '</code><br>';
                echo 'Default: <code>' . htmlspecialchars($verifyRow['Default'] ?? 'NULL') . '</code>';
                echo '</div>';
            }
            
            // Show summary
            echo '<h2>Step 6: Final Summary</h2>';
            $summaryQuery = "SELECT dosage_form, COUNT(*) as count FROM medicines GROUP BY dosage_form ORDER BY count DESC";
            $summaryResult = mysqli_query($conn, $summaryQuery);
            
            if ($summaryResult && mysqli_num_rows($summaryResult) > 0) {
                echo '<div class="info">Current distribution of dosage_form values:</div>';
                echo '<ul>';
                while ($row = mysqli_fetch_assoc($summaryResult)) {
                    echo '<li><strong>' . htmlspecialchars($row['dosage_form']) . '</strong>: ' . $row['count'] . ' medicine(s)</li>';
                }
                echo '</ul>';
            }
            
            echo '<div class="success" style="margin-top: 20px; padding: 15px;">';
            echo '<strong>✓ Migration completed successfully!</strong><br>';
            echo 'The dosage_form column is now an ENUM type with ' . count($enumValues) . ' predefined values and is NOT NULL.';
            echo '</div>';
            
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
            <h2>ENUM Values</h2>
            <p>The following values are now valid for the <code>dosage_form</code> column:</p>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
                <div>
                    <strong>Form Types:</strong>
                    <ul>
                        <li>Capsule</li>
                        <li>Tablet</li>
                        <li>Pill</li>
                        <li>Bottle</li>
                        <li>Vial</li>
                        <li>Ampoule</li>
                        <li>Syringe</li>
                        <li>Tube</li>
                        <li>Cream</li>
                        <li>Ointment</li>
                        <li>Gel</li>
                        <li>Drops</li>
                        <li>Spray</li>
                        <li>Inhaler</li>
                        <li>Patch</li>
                    </ul>
                </div>
                <div>
                    <strong>Measurement Units:</strong>
                    <ul>
                        <li>ml (milliliter)</li>
                        <li>mg (milligram)</li>
                        <li>g (gram)</li>
                        <li>kg (kilogram)</li>
                        <li>L (liter)</li>
                        <li>mcg (microgram)</li>
                        <li>IU (International Unit)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

