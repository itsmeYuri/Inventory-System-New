<?php
/**
 * Test Medicines API
 * Quick test to verify get_medicines_by_category.php is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Medicines API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #bee5eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        a {
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Medicines API</h1>
        
        <?php
        try {
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Connected to database: <strong>' . mysqli_get_server_info($conn) . '</strong></div>';
            
            // Test 1: Count total medicines
            $countSql = "SELECT COUNT(*) as total FROM medicines";
            $countRes = mysqli_query($conn, $countSql);
            $countRow = mysqli_fetch_assoc($countRes);
            $totalMedicines = $countRow['total'];
            
            echo '<div class="info">';
            echo '<strong>Total medicines in database:</strong> ' . $totalMedicines;
            echo '</div>';
            
            if ($totalMedicines == 0) {
                echo '<div class="error">';
                echo '<strong>⚠ No medicines found!</strong><br>';
                echo 'You need to add medicines to the inventory first before you can link them to suppliers.';
                echo '</div>';
            } else {
                // Test 2: Test the API endpoint
                echo '<h2>Testing API Endpoint</h2>';
                
                // Include and test the API
                ob_start();
                include __DIR__ . '/get_medicines_by_category.php';
                $apiOutput = ob_get_clean();
                
                $apiData = json_decode($apiOutput, true);
                
                if ($apiData && isset($apiData['success']) && $apiData['success']) {
                    echo '<div class="success">';
                    echo '<strong>✓ API is working!</strong><br>';
                    echo 'Total medicines: ' . $apiData['count'] . '<br>';
                    echo 'Categories: ' . $apiData['categories'];
                    echo '</div>';
                    
                    // Show categories
                    if (isset($apiData['data']) && count($apiData['data']) > 0) {
                        echo '<h3>Categories and Medicines:</h3>';
                        echo '<table>';
                        echo '<tr><th>Category</th><th>Medicine Count</th><th>Medicines</th></tr>';
                        foreach ($apiData['data'] as $categoryGroup) {
                            $medNames = array_map(function($m) {
                                return htmlspecialchars($m['name'] ?: 'N/A');
                            }, array_slice($categoryGroup['medicines'], 0, 5));
                            $more = count($categoryGroup['medicines']) > 5 ? '... (+' . (count($categoryGroup['medicines']) - 5) . ' more)' : '';
                            echo '<tr>';
                            echo '<td><strong>' . htmlspecialchars($categoryGroup['category']) . '</strong></td>';
                            echo '<td>' . count($categoryGroup['medicines']) . '</td>';
                            echo '<td>' . implode(', ', $medNames) . $more . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                } else {
                    echo '<div class="error">';
                    echo '<strong>✗ API Error:</strong><br>';
                    echo htmlspecialchars($apiData['message'] ?? 'Unknown error');
                    echo '<pre>' . htmlspecialchars($apiOutput) . '</pre>';
                    echo '</div>';
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><a href="../pages/suppliers_management.html">Go to Suppliers Management Page</a></p>
            <p><a href="../pages/inventory_management.html">Go to Inventory Management (Add Medicines)</a></p>
            <p><a href="test_connection.php">Test Database Connection</a></p>
        </div>
    </div>
</body>
</html>

