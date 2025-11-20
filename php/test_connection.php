<?php
// Database Connection Test Script
// Access this file via browser: http://localhost/In/php/test_connection.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'connection' => [],
    'database' => [],
    'tables' => [],
    'sample_data' => []
];

try {
    // Test 1: Connection Status
    if ($conn) {
        $results['connection']['status'] = 'success';
        $results['connection']['message'] = 'Database connection established';
        $results['connection']['host'] = 'localhost';
        $results['connection']['database'] = $database;
        
        // Get actual database name being used
        $dbNameQuery = mysqli_query($conn, "SELECT DATABASE() as db_name");
        if ($dbNameQuery) {
            $dbRow = mysqli_fetch_assoc($dbNameQuery);
            $results['connection']['actual_database'] = $dbRow['db_name'];
        }
    } else {
        $results['connection']['status'] = 'failed';
        $results['connection']['message'] = 'Database connection failed: ' . mysqli_connect_error();
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit;
    }

    // Test 2: Database Info
    $dbInfo = mysqli_get_server_info($conn);
    $results['database']['server_version'] = $dbInfo;
    $results['database']['charset'] = mysqli_character_set_name($conn);

    // Test 3: Check if medicines table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'medicines'");
    if (mysqli_num_rows($tableCheck) > 0) {
        $results['tables']['medicines'] = 'exists';
        
        // Get table structure
        $result = mysqli_query($conn, "DESCRIBE medicines");
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row;
        }
        $results['tables']['medicines_columns'] = $columns;
        
        // Test 4: Get sample data
        $sampleQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM medicines");
        if ($sampleQuery) {
            $count = mysqli_fetch_assoc($sampleQuery);
            $results['sample_data']['total_medicines'] = (int)$count['total'];
        }
        
        // Get a few sample records
        $sampleQuery = mysqli_query($conn, "SELECT id, name, quantity, status FROM medicines LIMIT 5");
        $samples = [];
        if ($sampleQuery) {
            while ($row = mysqli_fetch_assoc($sampleQuery)) {
                $samples[] = $row;
            }
        }
        $results['sample_data']['sample_records'] = $samples;
        
    } else {
        $results['tables']['medicines'] = 'not_found';
        $results['tables']['message'] = 'medicines table does not exist';
    }

    // Test 5: Check other important tables
    $tablesToCheck = ['users'];
    foreach ($tablesToCheck as $table) {
        $check = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        $results['tables'][$table] = mysqli_num_rows($check) > 0 ? 'exists' : 'not_found';
    }

    // Overall status
    $results['overall_status'] = 'success';
    $results['message'] = 'Database connection test completed successfully';

} catch (Exception $e) {
    $results['overall_status'] = 'error';
    $results['error'] = $e->getMessage();
    $results['error_trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $results['overall_status'] = 'fatal_error';
    $results['error'] = $e->getMessage();
    $results['error_trace'] = $e->getTraceAsString();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>

