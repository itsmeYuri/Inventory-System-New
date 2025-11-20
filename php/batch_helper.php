<?php
/**
 * Batch Number Helper Functions
 * Handles automatic batch number assignment based on expiration dates
 * 
 * Note: This file does NOT require conn.php - the connection should be
 * passed as a parameter to the functions. This allows the functions to
 * be used with any database connection.
 */

/**
 * Get or create batch number for a given expiration date
 * If a medicine with the same expiration_date exists, return its batch_number
 * Otherwise, generate a new batch_number (MAX + 1)
 * 
 * @param mysqli $conn Database connection
 * @param string|null $expiration_date Expiration date in YYYY-MM-DD format or NULL
 * @return int|null Batch number or NULL if expiration_date is NULL
 */
function getOrCreateBatchNumber($conn, $expiration_date) {
    // If no expiration date, return NULL (batch_number can be NULL)
    if ($expiration_date === null || $expiration_date === '') {
        return null;
    }
    
    // Check if any medicine exists with this expiration_date
    $checkSql = "SELECT batch_number FROM medicines 
                 WHERE expiration_date = ? AND batch_number IS NOT NULL 
                 LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    
    if (!$checkStmt) {
        error_log("Batch check prepare error: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($checkStmt, 's', $expiration_date);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($checkStmt);
        return (int)$row['batch_number'];
    }
    
    mysqli_stmt_close($checkStmt);
    
    // No existing batch found, generate new batch number
    $maxSql = "SELECT MAX(batch_number) as max_batch FROM medicines WHERE batch_number IS NOT NULL";
    $maxResult = mysqli_query($conn, $maxSql);
    
    $newBatchNumber = 1;
    if ($maxResult) {
        $row = mysqli_fetch_assoc($maxResult);
        if ($row && $row['max_batch'] !== null) {
            $newBatchNumber = (int)$row['max_batch'] + 1;
        }
    }
    
    return $newBatchNumber;
}

/**
 * Update batch numbers for all medicines with a specific expiration date
 * This is used when expiration_date is changed in edit
 * 
 * @param mysqli $conn Database connection
 * @param string $expiration_date Expiration date
 * @param int $batch_number Batch number to assign
 */
function updateBatchForExpirationDate($conn, $expiration_date, $batch_number) {
    if ($expiration_date === null || $expiration_date === '') {
        return;
    }
    
    $updateSql = "UPDATE medicines SET batch_number = ? WHERE expiration_date = ?";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    
    if (!$updateStmt) {
        error_log("Batch update prepare error: " . mysqli_error($conn));
        return;
    }
    
    mysqli_stmt_bind_param($updateStmt, 'is', $batch_number, $expiration_date);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
}

?>

