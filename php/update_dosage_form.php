<?php
/**
 * Migration script to update NULL dosage_form values in the medicines table
 * Sets default values for existing medicines that have NULL dosage_form
 */

// Turn off error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

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

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Check if dosage_form column exists
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'dosage_form'");
    if (mysqli_num_rows($checkColumn) === 0) {
        sendJsonResponse(false, 'dosage_form column does not exist in medicines table', null, 400);
    }

    // Get count of records with NULL dosage_form
    $countSql = "SELECT COUNT(*) AS cnt FROM medicines WHERE dosage_form IS NULL";
    $countResult = mysqli_query($conn, $countSql);
    $nullCount = 0;
    if ($countResult) {
        $row = mysqli_fetch_assoc($countResult);
        $nullCount = (int)$row['cnt'];
    }

    if ($nullCount === 0) {
        sendJsonResponse(true, 'All medicines already have dosage_form values. No updates needed.', [
            'updated' => 0,
            'total_null' => 0
        ]);
    }

    // Get all medicines with NULL dosage_form
    $selectSql = "SELECT id, name, category, dosage_form FROM medicines WHERE dosage_form IS NULL";
    $selectResult = mysqli_query($conn, $selectSql);
    
    if (!$selectResult) {
        $error = mysqli_error($conn);
        error_log("MySQL select error: " . $error);
        sendJsonResponse(false, 'Failed to fetch medicines: ' . $error, ['sql_error' => $error], 500);
    }

    $updatedCount = 0;
    $updateDetails = [];

    // Function to determine dosage form based on medicine name or category
    function determineDosageForm($name, $category) {
        $nameLower = strtolower($name);
        
        // Check for specific patterns in name
        if (preg_match('/\b(capsule|caps)\b/i', $nameLower)) {
            return 'Capsule';
        } elseif (preg_match('/\b(tablet|tab)\b/i', $nameLower)) {
            return 'Tablet';
        } elseif (preg_match('/\b(syrup|suspension|solution|drops)\b/i', $nameLower)) {
            return 'ml';
        } elseif (preg_match('/\b(cream|ointment|gel)\b/i', $nameLower)) {
            if (preg_match('/\bcream\b/i', $nameLower)) return 'Cream';
            if (preg_match('/\bointment\b/i', $nameLower)) return 'Ointment';
            if (preg_match('/\bgel\b/i', $nameLower)) return 'Gel';
        } elseif (preg_match('/\b(inhaler|inhalation)\b/i', $nameLower)) {
            return 'Inhaler';
        } elseif (preg_match('/\b(spray)\b/i', $nameLower)) {
            return 'Spray';
        } elseif (preg_match('/\b(patch)\b/i', $nameLower)) {
            return 'Patch';
        } elseif (preg_match('/\b(vial|ampoule|ampule)\b/i', $nameLower)) {
            if (preg_match('/\bvial\b/i', $nameLower)) return 'Vial';
            return 'Ampoule';
        } elseif (preg_match('/\b(bottle)\b/i', $nameLower)) {
            return 'Bottle';
        }
        
        // Default based on category
        if (strtolower($category) === 'medicine') {
            return 'Tablet'; // Default for medicines
        }
        
        // General default
        return 'Tablet';
    }

    // Update each medicine individually
    while ($row = mysqli_fetch_assoc($selectResult)) {
        $medicineId = (int)$row['id'];
        $medicineName = $row['name'];
        $medicineCategory = $row['category'];
        
        $determinedDosageForm = determineDosageForm($medicineName, $medicineCategory);
        
        $updateSql = "UPDATE medicines SET dosage_form = ? WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'si', $determinedDosageForm, $medicineId);
            
            if (mysqli_stmt_execute($updateStmt)) {
                $updatedCount++;
                $updateDetails[] = [
                    'id' => $medicineId,
                    'name' => $medicineName,
                    'assigned_dosage_form' => $determinedDosageForm
                ];
            }
            mysqli_stmt_close($updateStmt);
        }
    }

    // Verify the update
    $verifySql = "SELECT COUNT(*) AS cnt FROM medicines WHERE dosage_form IS NULL";
    $verifyResult = mysqli_query($conn, $verifySql);
    $remainingNull = 0;
    if ($verifyResult) {
        $row = mysqli_fetch_assoc($verifyResult);
        $remainingNull = (int)$row['cnt'];
    }

    // Get summary of assigned values
    $summarySql = "SELECT dosage_form, COUNT(*) as count FROM medicines WHERE dosage_form IS NOT NULL GROUP BY dosage_form";
    $summaryResult = mysqli_query($conn, $summarySql);
    $summary = [];
    if ($summaryResult) {
        while ($row = mysqli_fetch_assoc($summaryResult)) {
            $summary[$row['dosage_form']] = (int)$row['count'];
        }
    }

    sendJsonResponse(true, "Successfully updated {$updatedCount} medicine(s) with dosage_form values", [
        'updated' => $updatedCount,
        'total_null_before' => $nullCount,
        'remaining_null' => $remainingNull,
        'update_details' => $updateDetails,
        'summary' => $summary
    ]);

} catch (Exception $e) {
    error_log("Exception in update_dosage_form.php: " . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), [
        'exception' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], 500);
}

