<?php
/**
 * Create Supplier Notification
 * Helper function to create notifications for suppliers
 * This should be called when orders are placed or status changes
 */

require_once __DIR__ . '/conn.php';

/**
 * Create a notification for a supplier
 * 
 * @param mysqli $conn Database connection
 * @param int $supplier_id Supplier ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (new_order, order_status, low_stock, system)
 * @return bool Success status
 */
function createSupplierNotification($conn, $supplier_id, $title, $message, $type = 'system') {
    // Check if table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_notifications'");
    if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
        return false; // Table doesn't exist yet
    }

    // Validate type
    $validTypes = ['new_order', 'order_status', 'low_stock', 'system'];
    if (!in_array($type, $validTypes)) {
        $type = 'system';
    }

    // Insert notification
    $titleEscaped = mysqli_real_escape_string($conn, $title);
    $messageEscaped = mysqli_real_escape_string($conn, $message);
    $typeEscaped = mysqli_real_escape_string($conn, $type);

    $sql = "INSERT INTO supplier_notifications (supplier_id, title, message, type, read_status) 
            VALUES ($supplier_id, '$titleEscaped', '$messageEscaped', '$typeEscaped', 0)";

    return mysqli_query($conn, $sql);
}

/**
 * Create notification when a new order is placed
 */
function notifyNewOrder($conn, $supplier_id, $order_id) {
    $title = "New Order Received";
    $message = "You have received a new order #$order_id. Please review and process it.";
    return createSupplierNotification($conn, $supplier_id, $title, $message, 'new_order');
}

/**
 * Create notification when order status changes
 */
function notifyOrderStatusChange($conn, $supplier_id, $order_id, $old_status, $new_status) {
    $title = "Order Status Updated";
    $message = "Order #$order_id status has been changed from " . ucfirst($old_status) . " to " . ucfirst($new_status) . ".";
    return createSupplierNotification($conn, $supplier_id, $title, $message, 'order_status');
}

/**
 * Create notification for low stock
 */
function notifyLowStock($conn, $supplier_id, $product_name) {
    $title = "Low Stock Alert";
    $message = "Product '$product_name' is running low on stock. Please restock soon.";
    return createSupplierNotification($conn, $supplier_id, $title, $message, 'low_stock');
}
?>

