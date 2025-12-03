<?php
// Simple JSON API to list medicines with basic filters and pagination

// Enhanced CORS headers - allow multiple origins for development
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
    // Default to allow localhost on any port for development
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
require_once __DIR__ . '/pos_sync_helper.php';

function normalizePosMedicinesResponse($response) {
    $list = [];
    if (isset($response['data']) && is_array($response['data'])) {
        $list = $response['data'];
    } elseif (isset($response['medicines']) && is_array($response['medicines'])) {
        $list = $response['medicines'];
    } elseif (is_array($response)) {
        $list = $response;
    }

    $normalized = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = $item['medicine_id'] ?? $item['medicineId'] ?? $item['id'] ?? null;
        if ($id === null) {
            continue;
        }

        $name = $item['medicine_name'] ?? $item['name'] ?? '';
        $group = $item['medicine_group'] ?? $item['category'] ?? 'Uncategorized';
        $stock = (int)($item['stock'] ?? $item['quantity'] ?? 0);
        $price = (float)($item['price'] ?? 0);

        $normalized[] = [
            'id' => $id,
            'medicine_id' => $id,
            'name' => $name,
            'medicine_name' => $name,
            'category' => $group,
            'medicine_group' => $group,
            'generic_name' => $item['generic_name'] ?? '',
            'dosage' => $item['dosage'] ?? $item['unit'] ?? '',
            'form' => $item['form'] ?? $item['dosage'] ?? '',
            'quantity' => $stock,
            'stock' => $stock,
            'price' => number_format($price, 2, '.', '')
        ];
    }

    return $normalized;
}

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 25;
    $offset = ($page - 1) * $pageSize;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $expiration = isset($_GET['expiration']) ? trim($_GET['expiration']) : '';

    $source = isset($_GET['source']) ? strtolower(trim($_GET['source'])) : '';

    // Build where clause safely using mysqli_real_escape_string
    $where = " WHERE 1=1 ";

    // Check structure for search
    $hasNewStructure = hasNewMedicineStructure($conn);

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        if ($hasNewStructure) {
            $where .= " AND (medicine_id LIKE '%{$s}%' OR medicine_name LIKE '%{$s}%' OR generic_name LIKE '%{$s}%' OR medicine_group LIKE '%{$s}%') ";
        } else {
        $where .= " AND (ndc LIKE '%{$s}%' OR name LIKE '%{$s}%' OR manufacturer LIKE '%{$s}%') ";
        }
    }

    // Status filter only applies to old structure
    if (!$hasNewStructure && $status !== '') {
        $st = mysqli_real_escape_string($conn, $status);
        $where .= " AND status = '{$st}' ";
    }

    if ($category !== '') {
        $cat = mysqli_real_escape_string($conn, $category);
        if ($hasNewStructure) {
            $where .= " AND medicine_group = '{$cat}' ";
        } else {
        $where .= " AND category = '{$cat}' ";
        }
    }

    // Expiration filters only apply to old structure
    if (!$hasNewStructure) {
    if ($expiration === 'expired') {
        $where .= " AND expiration_date IS NOT NULL AND expiration_date < CURDATE() ";
    } elseif ($expiration === 'expiring-soon') {
        $where .= " AND expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ";
    } elseif ($expiration === 'expiring-later') {
        $where .= " AND (expiration_date IS NULL OR expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)) ";
        }
    }

    if ($source === 'pos') {
        $posFilters = [];
        if ($search !== '') {
            $posFilters['search'] = $search;
        }
        if ($category !== '') {
            $posFilters['category'] = $category;
        }

        $posResponse = fetchMedicinesFromPOS($posFilters);
        if ($posResponse['success']) {
            $normalized = normalizePosMedicinesResponse($posResponse['response'] ?? []);
            echo json_encode([
                'success' => true,
                'page' => 1,
                'pageSize' => count($normalized),
                'limit' => count($normalized),
                'total' => count($normalized),
                'source' => 'pos',
                'data' => $normalized
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'source' => 'pos',
                'message' => $posResponse['message'] ?? 'Unable to fetch data from POS system',
                'details' => $posResponse
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // Get total count
    $countSql = "SELECT COUNT(*) AS cnt FROM medicines" . $where;
    $countRes = mysqli_query($conn, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int)$row['cnt'];
    }

    // Check which structure we're using
    $hasNewStructure = hasNewMedicineStructure($conn);
    
    // Fetch page
    if ($hasNewStructure) {
        // New POS structure
        $sql = "SELECT medicine_id as id, medicine_name as name, medicine_group as category, 
                generic_name, dosage, form, stock as quantity, price
                FROM medicines
                {$where}
                ORDER BY medicine_name ASC
                LIMIT {$offset}, {$pageSize}";
    } else {
        // Old structure
    $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
    $hasUnit = mysqli_num_rows($checkUnit) > 0;
    
    if ($hasUnit) {
        $sql = "SELECT id, ndc, name, manufacturer, category, quantity, reorder_level, price, expiration_date, batch_number, status, dosage_form, unit
                FROM medicines
                {$where}
                ORDER BY name ASC
                LIMIT {$offset}, {$pageSize}";
    } else {
        $sql = "SELECT id, ndc, name, manufacturer, category, quantity, reorder_level, price, expiration_date, batch_number, status, dosage_form
                FROM medicines
                {$where}
                ORDER BY name ASC
                LIMIT {$offset}, {$pageSize}";
        }
    }

    $res = mysqli_query($conn, $sql);

    $data = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $data[] = $r;
        }
    }

    echo json_encode([
        'success' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'limit' => $pageSize, // Also include 'limit' for backward compatibility
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_medicines.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    // Don't close $conn here as it might be used by other scripts
}