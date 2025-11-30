-- Migration Script: Change 'user' role to 'employee' in users table
-- Run this script in your MySQL database management tool (phpMyAdmin, MySQL Workbench, etc.)

-- First, check if role column exists, if not create it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'role'
);

-- If column doesn't exist, create it
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT ''employee'' AFTER status',
    'SELECT ''Role column already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update all 'user' roles to 'employee'
UPDATE users 
SET role = 'employee' 
WHERE role = 'user';

-- Update the default value for the column (if supported)
ALTER TABLE users MODIFY COLUMN role VARCHAR(20) DEFAULT 'employee';

-- Show results
SELECT 
    COUNT(*) AS total_users,
    SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) AS employee_count,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
    SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) AS supplier_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS old_user_count
FROM users;

