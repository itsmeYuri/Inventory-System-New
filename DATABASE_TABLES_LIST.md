# Database Tables Analysis - Complete List

## Overview
This document lists all database tables that are referenced, created, or expected by the Inventory Management System codebase.

---

## ✅ CORE TABLES (Required for Basic Functionality)

### 1. **users**
   - **Status**: CRITICAL
   - **Purpose**: Stores user accounts (admin, employee, supplier roles)
   - **References Found In**:
     - `php/login.php`
     - `php/user_management.php`
     - `pages/send_reset_email.php`
     - `php/migrate_user_to_employee.php`
     - Multiple other files
   - **Expected Columns** (from code analysis):
     - `user_id` or `id` (primary key)
     - `email`
     - `username`
     - `password` or `password_hash`
     - `role` (ENUM: 'admin', 'employee', 'supplier', 'user')
     - `status` (ENUM: 'active', 'inactive', 'offline', 'locked')
     - `full_name`
     - `employee_id`
     - `must_change_password`
     - `otp` (for password reset)
     - `otp_expiry` (for password reset)
     - `password_reset_token`
     - `password_reset_expires`
     - `created_at`
     - `updated_at`

### 2. **medicines**
   - **Status**: CRITICAL
   - **Purpose**: Stores medicine inventory information
   - **References Found In**:
     - `php/get_medicines.php`
     - `php/add_medicine.php`
     - `php/edit_medicine.php`
     - `php/delete_medicine.php`
     - `php/get_dashboard.php`
     - Multiple other files
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `ndc` (National Drug Code)
     - `name`
     - `manufacturer`
     - `category`
     - `quantity`
     - `reorder_level`
     - `price`
     - `expiration_date`
     - `batch_number`
     - `status` (ENUM: 'in-stock', 'low-stock', 'out-of-stock')
     - `dosage_form`
     - `unit`
     - `created_at`
     - `updated_at`

### 3. **suppliers**
   - **Status**: CRITICAL
   - **Purpose**: Stores supplier information
   - **References Found In**:
     - `php/add_supplier.php`
     - `php/edit_supplier.php`
     - `php/get_suppliers.php`
     - `php/login.php`
     - `php/add_supplier_auth_fields.php`
     - Multiple other files
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `name`
     - `contact_person`
     - `phone`
     - `email`
     - `username` (for supplier login)
     - `password_hash` (for supplier login)
     - `status` (ENUM: 'active', 'inactive', 'locked')
     - `address`
     - `website`
     - `notes`
     - `created_at`
     - `updated_at`

### 4. **orders**
   - **Status**: CRITICAL
   - **Purpose**: Stores order information
   - **References Found In**:
     - `php/add_order.php`
     - `php/edit_order.php`
     - `php/get_orders.php`
     - `php/get_supplier_orders.php`
     - `php/get_dashboard.php`
     - Multiple other files
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `supplier_id` (foreign key to suppliers)
     - `order_date` or `date`
     - `status` (ENUM: 'pending', 'shipping', 'completed', 'delivered', 'cancelled')
     - `total_amount` (DECIMAL)
     - `notes` (TEXT)
     - `created_at`
     - `updated_at`

### 5. **order_items**
   - **Status**: CRITICAL
   - **Purpose**: Stores individual items within orders
   - **References Found In**:
     - `php/add_order.php`
     - `php/get_order_items.php`
     - `php/get_orders.php`
     - Multiple other files
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `order_id` (foreign key to orders)
     - `medicine_id` (foreign key to medicines)
     - `quantity`
     - `price`
     - `ndc` (optional, for medicine NDC)
     - `medicine_name` (optional, denormalized)
     - `created_at`
     - `updated_at`

---

## ⚠️ BATCH MANAGEMENT TABLES (Required for Batch Tracking)

### 6. **batches**
   - **Status**: IMPORTANT (for batch tracking)
   - **Purpose**: Stores batch information for medicines
   - **References Found In**:
     - `php/check_and_fix_batches.php`
     - `php/order_batch_helper.php`
     - `php/get_batches.php`
     - `php/create_batches_for_existing_orders.php`
     - `php/get_dashboard.php`
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `batch_number` (VARCHAR, UNIQUE)
     - `order_id` (foreign key to orders, nullable)
     - `supplier_id` (foreign key to suppliers)
     - `created_date` (DATE)
     - `status` (ENUM: 'active', 'expired', 'completed')
     - `notes` (TEXT, nullable)
     - `created_at`
     - `updated_at`

### 7. **batch_items**
   - **Status**: IMPORTANT (for batch tracking)
   - **Purpose**: Stores individual items within batches with expiration tracking
   - **References Found In**:
     - `php/check_and_fix_batches.php`
     - `php/order_batch_helper.php`
     - `php/get_batch_items.php`
     - `php/update_batch_items.php`
     - `php/get_dashboard.php`
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `batch_id` (foreign key to batches)
     - `medicine_id` (foreign key to medicines)
     - `quantity` (INT UNSIGNED)
     - `expiration_date` (DATE, nullable)
     - `received_quantity` (INT UNSIGNED)
     - `is_expired` (TINYINT(1))
     - `expired_at` (TIMESTAMP, nullable)
     - `created_at`
     - `updated_at`

---

## 🔗 RELATIONSHIP TABLES (Required for Linking)

### 8. **supplier_medicines**
   - **Status**: IMPORTANT (for supplier-product relationships)
   - **Purpose**: Links suppliers to medicines they provide
   - **References Found In**:
     - `php/link_medicines_to_supplier.php`
     - `php/get_medicines_by_supplier.php`
     - `php/get_supplier_medicines.php`
     - `php/test_link_medicines.php`
     - `php/fix_supplier_medicines_table.php`
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `supplier_id` (foreign key to suppliers)
     - `medicine_id` (foreign key to medicines)
     - `created_at`
     - **Unique Constraint**: (supplier_id, medicine_id)

---

## 📢 NOTIFICATION TABLES (Optional but Recommended)

### 9. **supplier_notifications**
   - **Status**: OPTIONAL (for supplier notifications feature)
   - **Purpose**: Stores notifications for suppliers
   - **References Found In**:
     - `php/create_supplier_notifications_table.php`
     - `php/create_supplier_notification.php`
     - `php/get_supplier_notifications.php`
     - `php/mark_notifications_read.php`
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `supplier_id` (foreign key to suppliers)
     - `title` (VARCHAR(255))
     - `message` (TEXT)
     - `type` (ENUM: 'new_order', 'order_status', 'low_stock', 'system')
     - `read_status` (TINYINT(1), default 0)
     - `created_at`

---

## 📦 ARCHIVE TABLES (Optional but Recommended)

### 10. **archived_expired_items**
   - **Status**: OPTIONAL (for archiving expired items)
   - **Purpose**: Stores archived expired medicine items
   - **References Found In**:
     - `php/get_archives.php`
     - `php/archive_helper.php`
     - `php/process_expired_batches.php`
   - **Note**: Structure not fully defined in code, but table is checked for existence

### 11. **archived_orders**
   - **Status**: OPTIONAL (for archiving completed/cancelled orders)
   - **Purpose**: Stores archived orders
   - **References Found In**:
     - `php/get_archives.php`
     - `php/archive_completed_order.php`
   - **Note**: Structure not fully defined in code, but table is checked for existence

### 12. **archived_order_items**
   - **Status**: OPTIONAL (for archiving order items)
   - **Purpose**: Stores archived order items
   - **References Found In**:
     - `php/get_archives.php`
   - **Note**: Structure not fully defined in code, but table is checked for existence

### 13. **archived_medicines**
   - **Status**: OPTIONAL (for archiving deleted medicines)
   - **Purpose**: Stores archived medicines
   - **References Found In**:
     - `php/get_archives.php`
   - **Note**: Structure not fully defined in code, but table is checked for existence

### 14. **archived_suppliers**
   - **Status**: OPTIONAL (for archiving deleted suppliers)
   - **Purpose**: Stores archived suppliers
   - **References Found In**:
     - `php/get_archives.php`
     - `php/archive_supplier.php`
   - **Expected Columns** (from code analysis):
     - `id` (primary key)
     - `name`
     - `contact_person`
     - `phone`
     - `email`
     - `address`
     - `website`
     - `notes`
     - `archived_at` (TIMESTAMP)
     - `archived_by` (optional, user_id)
     - Other supplier fields

---

## 📊 SUMMARY

### Total Tables: 14

#### Critical Tables (5):
1. ✅ users
2. ✅ medicines
3. ✅ suppliers
4. ✅ orders
5. ✅ order_items

#### Important Tables (3):
6. ⚠️ batches
7. ⚠️ batch_items
8. ⚠️ supplier_medicines

#### Optional Tables (6):
9. 📢 supplier_notifications
10. 📦 archived_expired_items
11. 📦 archived_orders
12. 📦 archived_order_items
13. 📦 archived_medicines
14. 📦 archived_suppliers

---

## 🔍 HOW TO CHECK FOR MISSING TABLES

### Method 1: Run SQL Query
```sql
SHOW TABLES;
```

Compare the result with the list above.

### Method 2: Check via PHP
The system has several test/check scripts:
- `php/check_and_fix_batches.php` - Checks batches and batch_items
- `php/test_link_medicines.php` - Checks supplier_medicines
- `php/create_supplier_notifications_table.php` - Checks supplier_notifications
- `php/get_archives.php` - Checks all archive tables

### Method 3: Check System Logs
The system logs errors when tables are missing. Check PHP error logs for:
- "Table doesn't exist"
- "SHOW TABLES LIKE" queries returning 0 rows

---

## ⚠️ COMMON ISSUES

### 1. **Case Sensitivity**
- MySQL on Linux is case-sensitive for table names
- MySQL on Windows is case-insensitive
- **Recommendation**: Use lowercase table names consistently

### 2. **Auto-Creation**
- Some tables are auto-created by the system (suppliers, batches, etc.)
- Others must be created manually or via migration scripts
- **Recommendation**: Create all tables upfront for better control

### 3. **Foreign Key Constraints**
- Some tables are created without foreign keys initially
- Foreign keys are added later if possible
- **Recommendation**: Add foreign keys during table creation for data integrity

### 4. **Missing Archive Tables**
- Archive tables are optional but referenced in code
- System will work without them, but archiving features won't work
- **Recommendation**: Create archive tables if you plan to use archiving features

---

## 📝 NOTES

1. **Dynamic Table Creation**: The system creates some tables on-the-fly (suppliers, batches, etc.) if they don't exist. This is convenient but can lead to inconsistent schemas.

2. **Column Variations**: Some tables may have different column names or structures depending on when they were created. The code handles this with `SHOW COLUMNS` checks.

3. **Migration Scripts**: There are several migration/creation scripts in the `php/` directory that can help create missing tables.

4. **Database Name**: Ensure the database name is `inventory_system_db` (lowercase) as per the standardized connection file.

---

## 🚀 RECOMMENDED ACTION ITEMS

1. **Create Missing Core Tables**: Ensure all 5 critical tables exist
2. **Create Batch Tables**: If using batch tracking, create batches and batch_items
3. **Create Relationship Tables**: Create supplier_medicines if linking suppliers to medicines
4. **Create Optional Tables**: Create notification and archive tables if needed
5. **Verify Foreign Keys**: Check that foreign key relationships are properly set up
6. **Run Test Scripts**: Use the test scripts to verify table structures
7. **Document Schema**: Create a comprehensive database schema document

---

**Last Updated**: Based on codebase analysis
**Database Name**: `inventory_system_db` (lowercase)









