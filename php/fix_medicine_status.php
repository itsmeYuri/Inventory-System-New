<?php
// Script to fix medicine status based on current quantity
// Out-of-stock ONLY if quantity = 0
// Low stock if quantity > 0 and <= reorder_level
// In stock otherwise

require_once __DIR__ . '/conn.php';

if (!isset($conn) || !$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Update status based on quantity and reorder_level
    // Out-of-stock ONLY if quantity = 0
    $updateSql = "UPDATE medicines SET 
        status = CASE 
            -- Check expiration first (highest priority)
            WHEN expiration_date IS NOT NULL AND expiration_date < CURDATE() THEN 'expired'
            -- Out-of-stock ONLY if quantity is exactly 0
            WHEN quantity = 0 THEN 'out-of-stock'
            -- Low stock if quantity > 0 and <= reorder_level
            WHEN quantity > 0 AND quantity <= reorder_level THEN 'low-stock'
            -- Otherwise in-stock
            ELSE 'in-stock'
        END";
    
    $result = mysqli_query($conn, $updateSql);
    
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        
        // Get summary of status distribution
        $summarySql = "SELECT status, COUNT(*) as count FROM medicines GROUP BY status";
        $summaryResult = mysqli_query($conn, $summarySql);
        $summary = [];
        while ($row = mysqli_fetch_assoc($summaryResult)) {
            $summary[$row['status']] = (int)$row['count'];
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Updated status for {$affected} medicines based on quantity.",
            'affected_rows' => $affected,
            'status_summary' => $summary
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . mysqli_error($conn)
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

mysqli_close($conn);
?>

