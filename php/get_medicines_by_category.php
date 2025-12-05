<?php
// Get Medicines by Category API
// Returns all medicines grouped by category for supplier form

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enhanced CORS headers
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost',
    'http://127.0.0.1:3000',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/medicine_structure_helper.php';

try {
    $hasNew = hasNewMedicineStructure($conn);
    
    // Fetch ALL medicines (with or without category), compatible with both structures
    // Avoid columns that may not exist in the new structure (e.g., ndc)
    $sql = "SELECT 
                COALESCE(medicine_id, CAST(id AS CHAR)) AS id,
                medicine_id,
                COALESCE(medicine_id, CAST(id AS CHAR)) AS medicine_id_display,
                COALESCE(medicine_name, name) AS name,
                manufacturer,
                COALESCE(medicine_group, category, 'Uncategorized') AS category,
                COALESCE(stock, quantity, 0) AS quantity,
                COALESCE(reorder_level, 10) AS reorder_level,
                price,
                expiration_date,
                batch_number,
                status,
                COALESCE(form, dosage_form) AS dosage_form
            FROM medicines
            ORDER BY category ASC, name ASC";

    $res = mysqli_query($conn, $sql);

    $medicines = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $medicines[] = $row;
        }
    } else {
        throw new Exception('Database query failed: ' . mysqli_error($conn));
    }

    // Group by category
    $grouped = [];
    foreach ($medicines as $med) {
        $category = !empty($med['category']) ? $med['category'] : 'Uncategorized';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $med;
    }

    // Convert to array format
    $result = [];
    foreach ($grouped as $category => $meds) {
        $result[] = [
            'category' => $category,
            'medicines' => $meds
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($medicines),
        'categories' => count($result),
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_medicines_by_category.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>

