<?php
/**
 * Process Expired Batches
 * This script should be run periodically (via cron) to check for expired items
 * and decrement inventory accordingly
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/order_batch_helper.php';
require_once __DIR__ . '/archive_helper.php';

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Process expired batch items
    $stats = processExpiredBatchItems($conn);

    // Archive expired items
    $archived = archiveExpiredItems($conn);
    if ($archived > 0) {
        $stats['archived'] = $archived;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Expired batch items processed successfully',
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in process_expired_batches.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

