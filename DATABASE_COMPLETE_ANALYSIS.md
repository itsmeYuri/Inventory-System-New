# Complete Database Structure Analysis

## Overview
This document provides a complete analysis of the actual database structure based on the SQL dump, compared with codebase expectations.

---

## ✅ ALL TABLES PRESENT (17 Total)

### Core Tables (5)
1. ✅ **users** - Present
2. ✅ **medicines** - Present
3. ✅ **suppliers** - Present
4. ✅ **orders** - Present
5. ✅ **order_items** - Present

### Batch Management (2)
6. ✅ **batches** - Present
7. ✅ **batch_items** - Present

### Relationship Tables (1)
8. ✅ **supplier_medicines** - Present

### Notification Tables (1)
9. ✅ **supplier_notifications** - Present

### Archive Tables (5)
10. ✅ **archived_expired_items** - Present
11. ✅ **archived_orders** - Present
12. ✅ **archived_order_items** - Present
13. ✅ **archived_medicines** - Present
14. ✅ **archived_suppliers** - Present

### Tracking Tables (3) - **FOUND IN DATABASE!**
15. ✅ **return_tracking** - Present (for tracking returned items)
16. ✅ **sample_tracking** - Present (for tracking sample medicines)
17. ✅ **tracking_summary** - Present (VIEW, not a table)

---

## 📊 DETAILED TABLE STRUCTURES

### 1. **users** Table
**Status**: ✅ Complete
**Primary Key**: `user_id` (INT UNSIGNED)

**Columns Present**:
- ✅ `user_id` (INT UNSIGNED, PRIMARY KEY)
- ✅ `full_name` (VARCHAR(255))
- ✅ `employee_id` (VARCHAR(50), UNIQUE)
- ✅ `email` (VARCHAR(255), UNIQUE)
- ✅ `username` (VARCHAR(100), UNIQUE)
- ✅ `password_hash` (VARCHAR(255))
- ✅ `role` (VARCHAR(20), DEFAULT 'employee')
- ✅ `status` (ENUM: 'active', 'inactive', 'offline', 'locked')
- ✅ `must_change_password` (TINYINT(1), DEFAULT 1)
- ✅ `created_at` (TIMESTAMP)
- ✅ `password_reset_token` (VARCHAR(64))
- ✅ `password_reset_expires` (DATETIME)
- ✅ `otp` (VARCHAR(6))
- ✅ `otp_expiry` (DATETIME)

**Missing Columns**: None - All expected columns are present!

---

### 2. **medicines** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT UNSIGNED)

**Columns Present**:
- ✅ `id` (INT UNSIGNED, PRIMARY KEY)
- ✅ `ndc` (VARCHAR(32), UNIQUE)
- ✅ `name` (VARCHAR(255))
- ✅ `manufacturer` (VARCHAR(255))
- ✅ `category` (VARCHAR(100))
- ✅ `dosage_form` (VARCHAR(50))
- ✅ `quantity` (INT UNSIGNED, DEFAULT 0)
- ✅ `reorder_level` (INT UNSIGNED, DEFAULT 10)
- ✅ `price` (DECIMAL(10,2), DEFAULT 0.00)
- ✅ `expiration_date` (DATE)
- ✅ `batch_number` (INT(11))
- ✅ `status` (ENUM: 'in-stock', 'low-stock', 'out-of-stock', 'expired')
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Missing Columns**: 
- ⚠️ `unit` - Referenced in code (`php/get_medicines.php` checks for it) but NOT in database
- ⚠️ `description` - Referenced in archived_medicines but not in main table

**Indexes**: ✅ Well indexed (ndc, name, category, expiration_date)

---

### 3. **suppliers** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT(11))

**Columns Present**:
- ✅ `id` (INT(11), PRIMARY KEY)
- ✅ `name` (VARCHAR(255))
- ✅ `contact_person` (VARCHAR(255))
- ✅ `phone` (VARCHAR(50))
- ✅ `email` (VARCHAR(255))
- ✅ `username` (VARCHAR(100), UNIQUE) - For supplier login
- ✅ `password_hash` (VARCHAR(255)) - For supplier login
- ✅ `status` (ENUM: 'active', 'inactive', 'locked')
- ✅ `address` (VARCHAR(255))
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Missing Columns**:
- ⚠️ `website` - Referenced in code but NOT in database structure
- ⚠️ `notes` - Referenced in code but NOT in database structure

**Indexes**: ✅ Well indexed (name, email, username)

---

### 4. **orders** Table
**Status**: ⚠️ Missing Some Columns
**Primary Key**: `id` (INT UNSIGNED)

**Columns Present**:
- ✅ `id` (INT UNSIGNED, PRIMARY KEY)
- ✅ `supplier_id` (INT(11))
- ✅ `order_date` (DATE)
- ✅ `status` (ENUM: 'pending', 'shipping', 'completed', 'cancelled')
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Missing Columns**:
- ⚠️ `total_amount` (DECIMAL(10,2)) - Referenced in code but NOT in database
- ⚠️ `notes` (TEXT) - Referenced in code but NOT in database

**Note**: Code calculates `total_amount` from order_items, but column doesn't exist to store it.

---

### 5. **order_items** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT UNSIGNED)

**Columns Present**:
- ✅ `id` (INT UNSIGNED, PRIMARY KEY)
- ✅ `order_id` (INT UNSIGNED)
- ✅ `medicine_id` (INT UNSIGNED)
- ✅ `quantity` (INT(11), DEFAULT 0)
- ✅ `price` (DECIMAL(10,2), DEFAULT 0.00)
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Missing Columns**: None - All expected columns are present!

**Indexes**: ✅ Well indexed (order_id, medicine_id)

---

### 6. **batches** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT(11))

**Columns Present**:
- ✅ `id` (INT(11), PRIMARY KEY)
- ✅ `batch_number` (VARCHAR(50), UNIQUE)
- ✅ `order_id` (INT UNSIGNED, NOT NULL) - **Note**: Code allows NULL, but DB has NOT NULL
- ✅ `supplier_id` (INT(11))
- ✅ `created_date` (DATE)
- ✅ `status` (ENUM: 'active', 'expired', 'completed')
- ✅ `notes` (TEXT)
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Discrepancy**: 
- ⚠️ `order_id` is NOT NULL in database, but code allows NULL (see `php/check_and_fix_batches.php`)

**Indexes**: ✅ Well indexed

---

### 7. **batch_items** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT(11))

**Columns Present**:
- ✅ `id` (INT(11), PRIMARY KEY)
- ✅ `batch_id` (INT(11))
- ✅ `medicine_id` (INT UNSIGNED)
- ✅ `quantity` (INT(11), DEFAULT 0)
- ✅ `expiration_date` (DATE)
- ✅ `received_quantity` (INT(11), DEFAULT 0)
- ✅ `is_expired` (TINYINT(1), DEFAULT 0)
- ✅ `expired_at` (TIMESTAMP)
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Missing Columns**: None - All expected columns are present!

**Indexes**: ✅ Well indexed (batch_id, medicine_id, expiration_date, is_expired)

---

### 8. **supplier_medicines** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT(11))

**Columns Present**:
- ✅ `id` (INT(11), PRIMARY KEY)
- ✅ `supplier_id` (INT(11))
- ✅ `medicine_id` (INT UNSIGNED)
- ✅ `created_at` (TIMESTAMP)
- ✅ `updated_at` (TIMESTAMP)

**Constraints**: ✅ UNIQUE constraint on (supplier_id, medicine_id)

**Indexes**: ✅ Well indexed

---

### 9. **supplier_notifications** Table
**Status**: ✅ Complete
**Primary Key**: `id` (INT UNSIGNED)

**Columns Present**:
- ✅ `id` (INT UNSIGNED, PRIMARY KEY)
- ✅ `supplier_id` (INT(11))
- ✅ `title` (VARCHAR(255))
- ✅ `message` (TEXT)
- ✅ `type` (ENUM: 'new_order', 'order_status', 'low_stock', 'system')
- ✅ `read_status` (TINYINT(1), DEFAULT 0)
- ✅ `created_at` (TIMESTAMP)

**Foreign Keys**: ✅ Foreign key to suppliers.id with CASCADE DELETE

**Indexes**: ✅ Well indexed

---

### 10-14. **Archive Tables**
All archive tables are present and properly structured:
- ✅ `archived_expired_items` - Complete
- ✅ `archived_orders` - Complete
- ✅ `archived_order_items` - Complete (with foreign key to archived_orders)
- ✅ `archived_medicines` - Complete
- ✅ `archived_suppliers` - Complete

---

### 15. **return_tracking** Table
**Status**: ✅ Present (Not in codebase, but exists in database)
**Primary Key**: `id` (INT(11))

**Purpose**: Tracks returned items/medicines

**Columns**:
- `id` (INT(11), PRIMARY KEY)
- `batch_id` (INT(11))
- `batch_item_id` (INT(11))
- `medicine_id` (INT(11))
- `medicine_name` (VARCHAR(255))
- `quantity_returned` (INT(11), DEFAULT 1)
- `return_reason` (ENUM: 'expired', 'damaged', 'recall', 'customer_return', 'quality_issue', 'overstock', 'other')
- `reason_details` (TEXT)
- `condition_on_return` (ENUM: 'good', 'damaged', 'expired', 'opened', 'sealed')
- `action_taken` (ENUM: 'restocked', 'disposed', 'quarantine', 'returned_to_supplier', 'pending')
- `processed_by` (VARCHAR(255))
- `processed_by_user_id` (INT(11))
- `returned_at` (DATETIME)
- `processed_at` (DATETIME)
- `supplier_credit_issued` (TINYINT(1), DEFAULT 0)
- `credit_amount` (DECIMAL(10,2))
- `notes` (TEXT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Status**: This table exists but is NOT referenced in the current codebase. It appears to be for a return tracking feature that may not be implemented yet.

---

### 16. **sample_tracking** Table
**Status**: ✅ Present (Not in codebase, but exists in database)
**Primary Key**: `id` (INT(11))

**Purpose**: Tracks sample medicines taken for testing

**Columns**:
- `id` (INT(11), PRIMARY KEY)
- `batch_id` (INT(11))
- `batch_item_id` (INT(11))
- `medicine_id` (INT(11))
- `medicine_name` (VARCHAR(255))
- `quantity_taken` (INT(11), DEFAULT 1)
- `reason` (VARCHAR(500))
- `notes` (TEXT)
- `taken_by` (VARCHAR(255))
- `taken_by_user_id` (INT(11))
- `taken_at` (DATETIME)
- `status` (ENUM: 'taken', 'returned', 'disposed', 'tested')
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Status**: This table exists but is NOT referenced in the current codebase. It appears to be for a sample tracking feature that may not be implemented yet.

---

### 17. **tracking_summary** 
**Status**: ✅ Present (VIEW, not a table)
**Type**: VIEW (not a physical table)

**Purpose**: Provides aggregated summary of samples and returns per batch

**Definition**: 
```sql
CREATE VIEW tracking_summary AS 
SELECT 
    b.id AS batch_id,
    b.batch_number,
    b.supplier_id,
    s.name AS supplier_name,
    b.created_date AS batch_date,
    b.status AS batch_status,
    (SELECT COUNT(*) FROM sample_tracking st WHERE st.batch_id = b.id) AS total_samples,
    (SELECT COALESCE(SUM(st.quantity_taken), 0) FROM sample_tracking st WHERE st.batch_id = b.id) AS total_sample_quantity,
    (SELECT COUNT(*) FROM return_tracking rt WHERE rt.batch_id = b.id) AS total_returns,
    (SELECT COALESCE(SUM(rt.quantity_returned), 0) FROM return_tracking rt WHERE rt.batch_id = b.id) AS total_return_quantity
FROM batches b
LEFT JOIN suppliers s ON b.supplier_id = s.id
```

**Status**: This view exists but is NOT referenced in the current codebase. It provides a summary of tracking data.

---

## ⚠️ MISSING COLUMNS IN EXISTING TABLES

### 1. **medicines** Table
- ❌ `unit` - Referenced in `php/get_medicines.php` (line 79-80)
  - Code checks: `SHOW COLUMNS FROM medicines LIKE 'unit'`
  - Used in SELECT queries when present
  - **Impact**: Low - Code handles missing column gracefully

### 2. **suppliers** Table
- ❌ `website` - Referenced in `php/add_supplier.php` and `php/get_suppliers.php`
  - Code checks: `SHOW COLUMNS FROM suppliers WHERE Field = 'website'` (line 85 in get_suppliers.php)
  - **Impact**: Medium - Website field cannot be stored/retrieved
- ❌ `notes` - Referenced in multiple files
  - Code checks for this column
  - **Impact**: Medium - Notes cannot be stored/retrieved

### 3. **orders** Table
- ❌ `total_amount` - Referenced in `php/get_orders.php` and `php/add_order.php`
  - Code checks: `SHOW COLUMNS FROM orders LIKE 'total_amount'` (line 107 in get_orders.php)
  - Code calculates it from order_items if missing
  - **Impact**: Low - Code calculates on-the-fly, but not stored
- ❌ `notes` - Referenced in `php/add_order.php` and `php/get_orders.php`
  - Code checks: `SHOW COLUMNS FROM orders LIKE 'notes'` (line 109 in get_orders.php)
  - **Impact**: Medium - Order notes cannot be stored/retrieved

---

## 🔍 DISCREPANCIES FOUND

### 1. **batches.order_id** Constraint
- **Database**: `order_id INT UNSIGNED NOT NULL`
- **Code Expectation**: Code allows NULL (see `php/check_and_fix_batches.php` line 44)
- **Impact**: Medium - May cause issues when creating batches without orders

### 2. **Data Type Mismatches**
- **suppliers.id**: Database uses `INT(11)` (signed), but some code expects `INT UNSIGNED`
- **batches.id**: Database uses `INT(11)` (signed), but some code expects `INT UNSIGNED`
- **Impact**: Low - Usually works but may cause issues with foreign keys

---

## ✅ FOREIGN KEY CONSTRAINTS

**Present Foreign Keys**:
1. ✅ `archived_order_items.archived_order_id` → `archived_orders.id` (CASCADE DELETE)
2. ✅ `supplier_notifications.supplier_id` → `suppliers.id` (CASCADE DELETE)

**Missing Foreign Keys** (Code doesn't enforce, but would be good to have):
- `orders.supplier_id` → `suppliers.id`
- `order_items.order_id` → `orders.id`
- `order_items.medicine_id` → `medicines.id`
- `batches.supplier_id` → `suppliers.id`
- `batches.order_id` → `orders.id`
- `batch_items.batch_id` → `batches.id`
- `batch_items.medicine_id` → `medicines.id`
- `supplier_medicines.supplier_id` → `suppliers.id`
- `supplier_medicines.medicine_id` → `medicines.id`

**Note**: The code creates tables without foreign keys initially, then tries to add them later. This is why most foreign keys are missing.

---

## 📋 SUMMARY

### ✅ What's Good:
1. All 14 expected tables are present
2. Archive tables are properly structured
3. Core functionality tables are complete
4. Indexes are well-defined
5. Unique constraints are in place

### ⚠️ Issues Found:
1. **Missing Columns** (5):
   - `medicines.unit`
   - `suppliers.website`
   - `suppliers.notes`
   - `orders.total_amount`
   - `orders.notes`

2. **Constraint Discrepancy** (1):
   - `batches.order_id` is NOT NULL in DB but code allows NULL

3. **Missing Foreign Keys** (9):
   - Most relationships don't have foreign key constraints

4. **Unused Tables** (3):
   - `return_tracking` - Not referenced in code
   - `sample_tracking` - Not referenced in code
   - `tracking_summary` (VIEW) - Not referenced in code

---

## 🚀 RECOMMENDATIONS

### High Priority:
1. **Add Missing Columns**:
   ```sql
   ALTER TABLE medicines ADD COLUMN unit VARCHAR(50) NULL AFTER dosage_form;
   ALTER TABLE suppliers ADD COLUMN website VARCHAR(255) NULL AFTER address;
   ALTER TABLE suppliers ADD COLUMN notes TEXT NULL AFTER website;
   ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER status;
   ALTER TABLE orders ADD COLUMN notes TEXT NULL AFTER total_amount;
   ```

2. **Fix batches.order_id Constraint**:
   ```sql
   ALTER TABLE batches MODIFY COLUMN order_id INT UNSIGNED NULL DEFAULT NULL;
   ```

### Medium Priority:
3. **Add Foreign Key Constraints** (for data integrity)
4. **Document or Remove Unused Tables**:
   - If `return_tracking` and `sample_tracking` are for future features, document them
   - If they're legacy, consider removing them

### Low Priority:
5. **Standardize Data Types**:
   - Consider making all ID columns UNSIGNED for consistency

---

## 📝 NOTES

1. **Tracking Tables**: The `return_tracking`, `sample_tracking`, and `tracking_summary` VIEW appear to be for features that may be planned but not yet implemented in the codebase.

2. **Code Resilience**: The codebase is well-written to handle missing columns gracefully (using `SHOW COLUMNS` checks), so the missing columns don't break functionality, but features may be limited.

3. **Database Health**: Overall, the database is in good shape with proper indexes and constraints where they exist.

---

**Last Updated**: Based on SQL dump analysis
**Database Version**: MariaDB 10.4.32
**Total Tables**: 17 (14 expected + 3 tracking tables)
**Total Views**: 1 (tracking_summary)









