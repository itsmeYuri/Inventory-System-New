<?php
/**
 * Fix Tables Index
 * Lists all available table fix scripts
 */

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Table Fix Scripts</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #333; 
            border-bottom: 2px solid #4CAF50; 
            padding-bottom: 10px; 
        }
        .script-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 15px 0;
            transition: box-shadow 0.2s;
        }
        .script-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .script-card h2 {
            color: #28a745;
            margin-top: 0;
        }
        .script-card p {
            color: #666;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #218838;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }
        .url {
            font-family: 'Courier New', monospace;
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 3px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Table Fix Scripts</h1>
        
        <div class="info">
            <strong>📋 Instructions:</strong> Click on any button below to run the corresponding fix script. 
            These scripts will automatically fix AUTO_INCREMENT issues and remove problematic rows with id=0.
        </div>
        
        <div class="script-card">
            <h2>1. Fix Orders Table</h2>
            <p>Fixes AUTO_INCREMENT issues in the <code>orders</code> table. Use this if you're getting "Duplicate entry '0'" errors when creating orders.</p>
            <p><strong>URL:</strong> <span class="url">php/fix_orders_table.php</span></p>
            <a href="fix_orders_table.php" class="btn">Run Fix Orders Table Script</a>
        </div>
        
        <div class="script-card">
            <h2>2. Fix Order Items Table</h2>
            <p>Fixes AUTO_INCREMENT issues in the <code>order_items</code> table. Use this if you're getting "Duplicate entry '0'" errors when adding multiple items to an order.</p>
            <p><strong>URL:</strong> <span class="url">php/fix_order_items_table.php</span></p>
            <a href="fix_order_items_table.php" class="btn">Run Fix Order Items Table Script</a>
        </div>
        
        <div class="script-card">
            <h2>3. Fix Supplier Medicines Table</h2>
            <p>Fixes AUTO_INCREMENT issues in the <code>supplier_medicines</code> junction table. Use this if you're getting errors when linking multiple medicines to a supplier.</p>
            <p><strong>URL:</strong> <span class="url">php/fix_supplier_medicines_table.php</span></p>
            <a href="fix_supplier_medicines_table.php" class="btn">Run Fix Supplier Medicines Table Script</a>
        </div>
        
        <div class="info" style="margin-top: 30px;">
            <strong>💡 Tip:</strong> If you're experiencing issues with multiple tables, you can run all fix scripts in sequence. 
            They are safe to run multiple times and will only fix what's needed.
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;">
            <p><strong>Note:</strong> These scripts are maintenance tools. The main application code now automatically fixes these issues, 
            but these scripts are available for manual fixes if needed.</p>
        </div>
    </div>
</body>
</html>

