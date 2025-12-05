<?php
/**
 * Suppliers Management Page
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? 'user@example.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Suppliers Management - PHARMACY Management System</title>
    <link rel="stylesheet" href="../css/main.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script type="module" src="https://static.rocket.new/rocket-web.js?_cfg=https%3A%2F%2Finventory6919back.builtwithrocket.new&_be=https%3A%2F%2Fapplication.rocket.new&_v=0.1.8"></script>
</head>
<body class="min-h-screen bg-background transition-colors duration-300">
    <?php require_once __DIR__ . '/../php/navigation.php'; render_navigation(); ?>

    <!-- Main Content - Include the rest from suppliers_management.html -->
    <?php
    // Read the HTML file and extract main content (after </head>)
    $html_file = __DIR__ . '/suppliers_management.html';
    if (file_exists($html_file)) {
        $content = file_get_contents($html_file);
        // Extract everything after </head> and before </body>
        if (preg_match('/<\/head>(.*?)<\/body>/s', $content, $matches)) {
            $main_content = $matches[1];
            // Remove the old sidebar
            $main_content = preg_replace('/<!-- Sidebar Overlay -->.*?<\/aside>/s', '', $main_content);
            // Update links
            $main_content = str_replace('dashboard.html', 'dashboard.php', $main_content);
            $main_content = str_replace('inventory_management.html', 'medicine.php', $main_content);
            $main_content = str_replace('orders_management.html', 'order.php', $main_content);
            $main_content = str_replace('reports_analytics.html', 'report.php', $main_content);
            $main_content = str_replace('suppliers_management.html', 'suppliers_management.php', $main_content);
            
            echo $main_content;
        }
    }
    ?>
</body>
</html>
