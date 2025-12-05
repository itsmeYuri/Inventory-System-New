<?php
// Get Suppliers API
// Returns paginated list of suppliers with search and filter capabilities

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

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 25;
    $offset = ($page - 1) * $pageSize;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

    // Build where clause safely for suppliers table
    $where = " WHERE 1=1 ";

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (name LIKE '%{$s}%' OR contact_person LIKE '%{$s}%' OR email LIKE '%{$s}%' OR phone LIKE '%{$s}%' OR address LIKE '%{$s}%') ";
    }
    
    // Filter by status if requested (e.g., for orders form to show only active suppliers)
    if ($statusFilter !== '' && $hasStatus) {
        $status = mysqli_real_escape_string($conn, $statusFilter);
        $where .= " AND status = '{$status}' ";
    }

    // Get total count from suppliers table
    $countSql = "SELECT COUNT(*) AS cnt FROM suppliers" . $where;
    $countRes = mysqli_query($conn, $countSql);
    $totalSuppliers = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $totalSuppliers = (int)$row['cnt'];
    }

    // Get total count from users table with supplier role
        $userWhere = " WHERE role = 'supplier' ";
        if ($search !== '') {
            $s = mysqli_real_escape_string($conn, $search);
            $userWhere .= " AND (full_name LIKE '%{$s}%' OR email LIKE '%{$s}%' OR username LIKE '%{$s}%') ";
        }
        // Filter users by status if requested
        if ($statusFilter !== '') {
            $status = mysqli_real_escape_string($conn, $statusFilter);
            $userWhere .= " AND status = '{$status}' ";
        }
    
    // Check if role column exists in users table
    $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
    $hasRole = $checkRole && mysqli_num_rows($checkRole) > 0;
    
    $totalUsers = 0;
    if ($hasRole) {
        $userCountSql = "SELECT COUNT(*) AS cnt FROM users" . $userWhere;
        $userCountRes = mysqli_query($conn, $userCountSql);
        if ($userCountRes) {
            $userRow = mysqli_fetch_assoc($userCountRes);
            $totalUsers = (int)$userRow['cnt'];
        }
    }
    
    $total = $totalSuppliers + $totalUsers;

    // Check if website and notes columns exist
    $checkWebsite = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'website'");
    $hasWebsite = $checkWebsite && mysqli_num_rows($checkWebsite) > 0;
    
    $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'notes'");
    $hasNotes = $checkNotes && mysqli_num_rows($checkNotes) > 0;
    
    // Check if authentication fields exist
    $checkUsername = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'username'");
    $hasUsername = $checkUsername && mysqli_num_rows($checkUsername) > 0;
    
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'status'");
    $hasStatus = $checkStatus && mysqli_num_rows($checkStatus) > 0;
    
    $checkPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'password_hash'");
    $hasPasswordHash = $checkPasswordHash && mysqli_num_rows($checkPasswordHash) > 0;
    
    // Check if timestamp columns exist
    $checkCreatedAt = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'created_at'");
    $hasCreatedAt = $checkCreatedAt && mysqli_num_rows($checkCreatedAt) > 0;
    
    $checkUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM suppliers WHERE Field = 'updated_at'");
    $hasUpdatedAt = $checkUpdatedAt && mysqli_num_rows($checkUpdatedAt) > 0;
    
    // Build SELECT statement
    $selectFields = "id, name, contact_person, phone, email, address";
    if ($hasWebsite) {
        $selectFields .= ", website";
    }
    if ($hasNotes) {
        $selectFields .= ", notes";
    }
    if ($hasUsername) {
        $selectFields .= ", username";
    }
    if ($hasStatus) {
        $selectFields .= ", status";
    }
    if ($hasPasswordHash) {
        // Include password_hash to check if account is set up (but don't expose the hash value)
        $selectFields .= ", CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 1 ELSE 0 END AS has_login_account";
    }
    if ($hasCreatedAt) {
        $selectFields .= ", created_at";
    }
    if ($hasUpdatedAt) {
        $selectFields .= ", updated_at";
    }
    
    // Fetch suppliers from suppliers table
    $sql = "SELECT {$selectFields}
            FROM suppliers
            {$where}
            ORDER BY name ASC";
    
    // For pagination, we need to fetch all and then merge with users
    // This is not ideal for large datasets, but necessary for merging
    $res = mysqli_query($conn, $sql);
    
    $suppliersData = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $r['source'] = 'suppliers_table'; // Mark as from suppliers table
            $suppliersData[] = $r;
        }
    }
    
    // Fetch users with supplier role
    $usersData = [];
    if ($hasRole) {
        $userSelectFields = "user_id AS id, full_name AS name, email, username, status";
        
        // Check if password_hash exists in users table
        $checkUserPasswordHash = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasUserPasswordHash = $checkUserPasswordHash && mysqli_num_rows($checkUserPasswordHash) > 0;
        
        if ($hasUserPasswordHash) {
            $userSelectFields .= ", CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 1 ELSE 0 END AS has_login_account";
        } else {
            $userSelectFields .= ", 0 AS has_login_account";
        }
        
        $userSelectFields .= ", full_name AS contact_person, '' AS phone, '' AS address";
        if ($hasWebsite) {
            $userSelectFields .= ", '' AS website";
        }
        if ($hasNotes) {
            $userSelectFields .= ", '' AS notes";
        }
        
        // Check if timestamp columns exist in users table
        $checkUserCreatedAt = mysqli_query($conn, "SHOW COLUMNS FROM users WHERE Field = 'created_at'");
        $hasUserCreatedAt = $checkUserCreatedAt && mysqli_num_rows($checkUserCreatedAt) > 0;
        
        $checkUserUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM users WHERE Field = 'updated_at'");
        $hasUserUpdatedAt = $checkUserUpdatedAt && mysqli_num_rows($checkUserUpdatedAt) > 0;
        
        if ($hasUserCreatedAt) {
            $userSelectFields .= ", created_at";
        }
        if ($hasUserUpdatedAt) {
            $userSelectFields .= ", updated_at";
        }
        
        $userSql = "SELECT {$userSelectFields}
                    FROM users
                    {$userWhere}
                    ORDER BY full_name ASC";
        
        $userRes = mysqli_query($conn, $userSql);
        if ($userRes) {
            while ($ur = mysqli_fetch_assoc($userRes)) {
                $ur['source'] = 'users_table'; // Mark as from users table
                $usersData[] = $ur;
            }
        }
    }
    
    // Merge suppliers and users
    $allData = array_merge($suppliersData, $usersData);
    
    // Sort by name
    usort($allData, function($a, $b) {
        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    });
    
    // Apply pagination after merging
    $data = array_slice($allData, $offset, $pageSize);

    echo json_encode([
        'success' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'limit' => $pageSize,
        'total' => $total,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get_suppliers.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
}

?>
