<?php
/**
 * Utility script to drop the legacy NDC column from the medicines table.
 * Run via CLI: php php/remove_ndc_column.php
 * or via browser: http://localhost:3000/php/remove_ndc_column.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'steps' => []
];

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Remove indexes referencing ndc
    $indexes = mysqli_query($conn, "SHOW INDEXES FROM medicines WHERE Column_name = 'ndc' AND Key_name != 'PRIMARY'");
    while ($row = mysqli_fetch_assoc($indexes)) {
        $indexName = $row['Key_name'];
        if (mysqli_query($conn, "ALTER TABLE medicines DROP INDEX `{$indexName}`")) {
            $response['steps'][] = "Removed index {$indexName}";
        }
    }

    // Drop column if it exists
    $dropResult = mysqli_query($conn, "ALTER TABLE medicines DROP COLUMN IF EXISTS ndc");
    if (!$dropResult) {
        throw new Exception('Failed to drop NDC column: ' . mysqli_error($conn));
    }
    $response['steps'][] = 'Dropped NDC column';

    // Verify
    $check = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'ndc'");
    if (mysqli_num_rows($check) === 0) {
        $response['steps'][] = 'Verification successful - NDC column no longer exists';
    } else {
        throw new Exception('Verification failed – NDC column still exists');
    }

    $response['success'] = true;
    $response['message'] = 'NDC column removed successfully';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
