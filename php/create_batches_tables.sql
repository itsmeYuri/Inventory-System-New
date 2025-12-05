-- =====================================================
-- CREATE BATCHES AND BATCH_ITEMS TABLES
-- Run this SQL script in phpMyAdmin or MySQL command line
-- =====================================================

-- =====================================================
-- 1. CREATE BATCHES TABLE
-- =====================================================
CREATE TABLE batches (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique batch ID',
    batch_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique batch number (e.g., BATCH-20251205)',
    order_id INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'Reference to orders.id',
    supplier_id INT(11) NOT NULL COMMENT 'Reference to suppliers.id',
    created_date DATE NOT NULL COMMENT 'Date when batch was created',
    status ENUM('active', 'expired', 'completed') DEFAULT 'active' COMMENT 'Batch status',
    notes TEXT NULL COMMENT 'Additional notes about the batch',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    
    INDEX idx_batch_number (batch_number),
    INDEX idx_order_id (order_id),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_created_date (created_date),
    INDEX idx_status (status)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1;

-- =====================================================
-- 2. CREATE BATCH_ITEMS TABLE
-- =====================================================
CREATE TABLE batch_items (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique batch item ID',
    batch_id INT(11) NOT NULL COMMENT 'Reference to batches.id',
    medicine_id INT(10) UNSIGNED NOT NULL COMMENT 'Reference to medicines.id',
    quantity INT(11) NOT NULL DEFAULT 0 COMMENT 'Quantity of medicine in this batch',
    expiration_date DATE NULL DEFAULT NULL COMMENT 'Expiration date for this specific item',
    received_quantity INT(11) NULL DEFAULT 0 COMMENT 'Actual quantity received (may differ from ordered)',
    is_expired TINYINT(1) NULL DEFAULT 0 COMMENT 'Flag to track if this item has expired',
    expired_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when item expired',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Row update timestamp',
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_medicine_id (medicine_id),
    INDEX idx_expiration_date (expiration_date),
    INDEX idx_is_expired (is_expired),
    INDEX idx_batch_medicine (batch_id, medicine_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1;

-- =====================================================
-- 3. VERIFY TABLES WERE CREATED
-- =====================================================
-- Run these queries to verify:
-- SHOW TABLES LIKE 'batches';
-- SHOW TABLES LIKE 'batch_items';
-- DESCRIBE batches;
-- DESCRIBE batch_items;

-- =====================================================
-- 4. CHECK TABLE STRUCTURE
-- =====================================================
-- SHOW CREATE TABLE batches;
-- SHOW CREATE TABLE batch_items;

