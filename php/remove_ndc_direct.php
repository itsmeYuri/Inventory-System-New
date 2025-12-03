<?php
/**
 * Direct removal of NDC column
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/conn.php';

if (!isset($conn) || !$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json; charset=utf-8');

try {
    // First, remove any indexes/constraints on NDC
    $indexes = mysqli_query($conn, "SHOW INDEXES FROM medicines WHERE Column_name = 'ndc' AND Key_name != 'PRIMARY'");
    $indexesToRemove = [];
    while ($row = mysqli_fetch_assoc($indexes)) {
        if (!in_array($row['Key_name'], $indexesToRemove)) {
            $indexesToRemove[] = $row['Key_name'];
        }
    }
    
    foreach ($indexesToRemove as $indexName) {
        mysqli_query($conn, "ALTER TABLE medicines DROP INDEX {$indexName}");
    }
    
    // Then remove the column
    $result = mysqli_query($conn, "ALTER TABLE medicines DROP COLUMN ndc");
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'NDC column removed successfully',
            'removed_indexes' => $indexesToRemove
        ], JSON_PRETTY_PRINT);
    } else {
        throw new Exception(mysqli_error($conn));
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
