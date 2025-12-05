<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conn.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$created = [];
$messages = [];

$checkBatches = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
if (!$checkBatches || mysqli_num_rows($checkBatches) === 0) {
    $sql = "CREATE TABLE IF NOT EXISTS batches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_number VARCHAR(50) NOT NULL UNIQUE,
        order_id INT UNSIGNED NULL DEFAULT NULL,
        supplier_id INT UNSIGNED NOT NULL,
        created_date DATE NOT NULL,
        status ENUM('active','expired','completed') DEFAULT 'active',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_batch_number (batch_number),
        INDEX idx_order_id (order_id),
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_created_date (created_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (mysqli_query($conn, $sql)) {
        $created[] = 'batches';
        $messages[] = 'batches table created';
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }
} else {
    $messages[] = 'batches table exists';
}

$checkBatchItems = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
if (!$checkBatchItems || mysqli_num_rows($checkBatchItems) === 0) {
    $sql = "CREATE TABLE IF NOT EXISTS batch_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id INT UNSIGNED NOT NULL,
        medicine_id INT UNSIGNED NOT NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 0,
        expiration_date DATE NULL,
        received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
        is_expired TINYINT(1) DEFAULT 0,
        expired_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_batch_id (batch_id),
        INDEX idx_medicine_id (medicine_id),
        INDEX idx_expiration_date (expiration_date),
        INDEX idx_is_expired (is_expired),
        INDEX idx_batch_medicine (batch_id, medicine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (mysqli_query($conn, $sql)) {
        $created[] = 'batch_items';
        $messages[] = 'batch_items table created';
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }
} else {
    $messages[] = 'batch_items table exists';
}

echo json_encode([
    'success' => true,
    'created' => $created,
    'messages' => $messages
]);
?>
