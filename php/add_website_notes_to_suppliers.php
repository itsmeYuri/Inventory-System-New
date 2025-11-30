<?php
/**
 * Add Website and Notes Columns to Suppliers Table
 * Migration script to add website and notes columns to the suppliers table
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Website and Notes to Suppliers</title>
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
        <h1>Add Website and Notes Columns to Suppliers Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Checking suppliers table structure...</div>';
            
            // Check if website column exists
            $checkWebsite = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'website'");
            $hasWebsite = $checkWebsite && mysqli_num_rows($checkWebsite) > 0;
            
            // Check if notes column exists
            $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'notes'");
            $hasNotes = $checkNotes && mysqli_num_rows($checkNotes) > 0;
            
            if ($hasWebsite && $hasNotes) {
                echo '<div class="success">✓ Both website and notes columns already exist in the suppliers table.</div>';
            } else {
                // Add website column if it doesn't exist
                if (!$hasWebsite) {
                    echo '<div class="info">Adding website column...</div>';
                    $addWebsiteSql = "ALTER TABLE suppliers ADD COLUMN website VARCHAR(255) NULL COMMENT 'Website URL' AFTER address";
                    
                    if (mysqli_query($conn, $addWebsiteSql)) {
                        echo '<div class="success">✓ Successfully added website column to suppliers table.</div>';
                    } else {
                        $error = mysqli_error($conn);
                        echo '<div class="error">✗ Failed to add website column: ' . htmlspecialchars($error) . '</div>';
                    }
                } else {
                    echo '<div class="info">Website column already exists.</div>';
                }
                
                // Add notes column if it doesn't exist
                if (!$hasNotes) {
                    echo '<div class="info">Adding notes column...</div>';
                    $addNotesSql = "ALTER TABLE suppliers ADD COLUMN notes TEXT NULL COMMENT 'Additional notes' AFTER website";
                    
                    if (mysqli_query($conn, $addNotesSql)) {
                        echo '<div class="success">✓ Successfully added notes column to suppliers table.</div>';
                    } else {
                        $error = mysqli_error($conn);
                        echo '<div class="error">✗ Failed to add notes column: ' . htmlspecialchars($error) . '</div>';
                    }
                } else {
                    echo '<div class="info">Notes column already exists.</div>';
                }
                
                // Verify the columns were added
                echo '<div class="info">Verifying columns...</div>';
                $verifyWebsite = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'website'");
                $verifyNotes = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'notes'");
                
                if (mysqli_num_rows($verifyWebsite) > 0 && mysqli_num_rows($verifyNotes) > 0) {
                    echo '<div class="success">✓ Verification successful! Both columns are now in the suppliers table.</div>';
                } else {
                    echo '<div class="error">✗ Verification failed. Please check the database manually.</div>';
                }
            }
            
            // Display current table structure
            echo '<h2>Current Suppliers Table Structure:</h2>';
            $columnsQuery = "SHOW COLUMNS FROM suppliers";
            $columnsResult = mysqli_query($conn, $columnsQuery);
            
            if ($columnsResult) {
                echo '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background-color: #f8f9fa;"><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                while ($row = mysqli_fetch_assoc($columnsResult)) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($row['Extra']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2>Note</h2>
            <p>After running this migration, the suppliers table will have:</p>
            <ul>
                <li><strong>website</strong> - VARCHAR(255) NULL - Website URL</li>
                <li><strong>notes</strong> - TEXT NULL - Additional notes</li>
            </ul>
            <p>You can now use these fields when adding or editing suppliers.</p>
        </div>
    </div>
</body>
</html>

