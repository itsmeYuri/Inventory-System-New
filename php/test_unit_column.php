<?php
/**
 * Test script to check if unit column exists and verify table structure
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Check if unit column exists
    $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
    $hasUnit = $checkUnit && mysqli_num_rows($checkUnit) > 0;
    
    // Get all columns
    $allColumns = mysqli_query($conn, "SHOW COLUMNS FROM medicines");
    $columns = [];
    while ($row = mysqli_fetch_assoc($allColumns)) {
        $columns[] = $row;
    }
    
    // Check table structure
    $tableInfo = mysqli_query($conn, "SHOW CREATE TABLE medicines");
    $createTable = '';
    if ($tableInfo) {
        $row = mysqli_fetch_assoc($tableInfo);
        $createTable = $row['Create Table'] ?? '';
    }
    
    echo json_encode([
        'success' => true,
        'has_unit_column' => $hasUnit,
        'columns' => $columns,
        'create_table' => $createTable
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

