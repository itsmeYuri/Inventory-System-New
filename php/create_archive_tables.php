<?php
/**
 * Create Archive Tables
 * Creates tables for storing archived items (deleted medicines, cancelled orders, expired items)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Read SQL file
    $sqlFile = __DIR__ . '/create_archive_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL file not found: ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $executed = [];
    $errors = [];

    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        // Execute each statement
        if (mysqli_query($conn, $statement)) {
            // Extract table name from CREATE TABLE statement
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                $executed[] = $matches[1];
            } else {
                $executed[] = 'statement';
            }
        } else {
            $error = mysqli_error($conn);
            // Ignore "table already exists" errors
            if (strpos($error, 'already exists') === false) {
                $errors[] = $error;
            } else {
                // Extract table name for already exists
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    $executed[] = $matches[1] . ' (already exists)';
                }
            }
        }
    }

    if (count($errors) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Some errors occurred while creating tables',
            'executed' => $executed,
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Archive tables created successfully',
            'tables_created' => $executed
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("Error in create_archive_tables.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>

