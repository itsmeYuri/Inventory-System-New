# System Testing Guide - Inventory Management System

## Overview
This guide provides step-by-step instructions to test all functionality of the Inventory Management System.

---

## 🔧 PRE-TESTING SETUP

### 1. Verify Database Connection
**Location**: `php/conn.php`

**Test**:
1. Open browser and navigate to: `http://localhost/php/conn.php` (or your server path)
2. **Expected**: Should connect without errors
3. **If Error**: Check database credentials in `php/conn.php`

**Alternative Test** (Create test file):
```php
<?php
require_once 'php/conn.php';
if ($conn) {
    echo "✅ Database connection successful!";
} else {
    echo "❌ Database connection failed!";
}
?>
```

### 2. Verify Database Tables
**Run SQL Query**:
```sql
SHOW TABLES;
```
**Expected**: Should show 17 tables (or at least the 14 core tables)

**Check Critical Tables**:
```sql
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM medicines;
SELECT COUNT(*) FROM suppliers;
SELECT COUNT(*) FROM orders;
```

---

## 🔐 AUTHENTICATION TESTING

### Test 1: Admin/Employee Login
**Steps**:
1. Navigate to: `http://localhost/pages/login.html`
2. Try hardcoded credentials:
   - Username: `admin` or `Admin`
   - Password: `admin12345`
3. **Expected**: Should redirect to `dashboard.html`
4. **Check**: 
   - Session is created
   - User info appears in sidebar/topbar
   - Dashboard loads with data

### Test 2: Database User Login
**Steps**:
1. Use credentials from `users` table
2. Try logging in with email or username
3. **Expected**: Should login successfully
4. **Check**: Correct role is assigned

### Test 3: Supplier Login
**Steps**:
1. Navigate to: `http://localhost/pages/supplier_login.html`
2. Use supplier credentials from `suppliers` table
3. **Expected**: Should redirect to `supplier_dashboard.html`
4. **Check**: Supplier-specific dashboard loads

### Test 4: Invalid Credentials
**Steps**:
1. Try wrong password
2. **Expected**: Error message displayed
3. **Check**: No session created

### Test 5: Password Reset (OTP)
**Steps**:
1. Click "Forgot password?"
2. Enter email from users table
3. **Expected**: OTP sent (if email configured)
4. Enter OTP code
5. **Expected**: Can reset password

### Test 6: Logout
**Steps**:
1. Click logout button
2. **Expected**: 
   - Session cleared
   - Redirected to login page
   - Cannot access protected pages

---

## 📊 DASHBOARD TESTING

### Test 1: Dashboard Loads
**Steps**:
1. Login as admin/employee
2. Navigate to dashboard
3. **Expected**: 
   - Metrics display (Total Medicines, Pending Orders, Total Value)
   - Low Stock Alert shows
   - Expiration Alerts show
   - Recent Activities list
   - Orders Overview
   - Calendar loads

### Test 2: Dashboard Metrics
**Check**:
- Total Medicines count matches database
- Pending Orders count is correct
- Total Value calculation is correct
- Low Stock percentage is accurate

**Verify with SQL**:
```sql
SELECT COUNT(*) FROM medicines;
SELECT COUNT(*) FROM orders WHERE status = 'pending';
SELECT SUM(quantity * price) FROM medicines;
```

### Test 3: Dashboard Calendar
**Check**:
- Calendar displays expiration dates
- Calendar displays order dates
- Clicking events shows details
- Events are color-coded correctly

### Test 4: Quick Actions
**Test Each Button**:
- "Add Medicine" → Should go to inventory management
- "Create Order" → Should go to orders management
- "Add Supplier" → Should go to suppliers management
- "Generate Report" → Should go to reports page

---

## 💊 MEDICINE/INVENTORY MANAGEMENT TESTING

### Test 1: View Medicines
**Steps**:
1. Navigate to: `inventory_management.html`
2. **Expected**: 
   - Medicine list loads
   - Pagination works
   - Search works
   - Filters work (status, category, expiration)

### Test 2: Add Medicine
**Steps**:
1. Click "Add Medicine" button
2. Fill in form:
   - NDC (required, unique)
   - Name (required)
   - Manufacturer
   - Category
   - Quantity
   - Price
   - Expiration Date
   - Dosage Form
3. Submit
4. **Expected**: 
   - Medicine added successfully
   - Appears in list
   - Success message shown

**Verify with SQL**:
```sql
SELECT * FROM medicines WHERE name = '[Medicine Name]';
```

### Test 3: Edit Medicine
**Steps**:
1. Click edit button on a medicine
2. Modify fields
3. Save
4. **Expected**: 
   - Changes saved
   - Updated in list
   - Success message

**Verify with SQL**:
```sql
SELECT * FROM medicines WHERE id = [medicine_id];
```

### Test 4: Delete Medicine
**Steps**:
1. Click delete button
2. Confirm deletion
3. **Expected**: 
   - Medicine removed from list
   - Archived (if archiving enabled)
   - Success message

**Verify with SQL**:
```sql
SELECT * FROM medicines WHERE id = [medicine_id];
SELECT * FROM archived_medicines WHERE original_id = [medicine_id];
```

### Test 5: Search & Filter
**Test**:
- Search by name
- Search by NDC
- Filter by status (in-stock, low-stock, out-of-stock)
- Filter by category
- Filter by expiration (expired, expiring soon)

### Test 6: Low Stock Alert
**Check**:
- Medicines below reorder_level show as "low-stock"
- Dashboard shows low stock count
- Low stock chart displays correctly

---

## 🛒 ORDER MANAGEMENT TESTING

### Test 1: View Orders
**Steps**:
1. Navigate to: `orders_management.html`
2. **Expected**: 
   - Orders list loads
   - Shows supplier name
   - Shows order date
   - Shows status
   - Shows total amount

### Test 2: Create Order
**Steps**:
1. Click "Create Order" or "Add Order"
2. Select supplier
3. Add order items:
   - Select medicine
   - Enter quantity
   - Enter price
4. Set order date
5. Add notes (optional)
6. Submit
7. **Expected**: 
   - Order created
   - Order items saved
   - Batch created (if enabled)
   - Success message
   - Order appears in list

**Verify with SQL**:
```sql
SELECT * FROM orders WHERE id = [order_id];
SELECT * FROM order_items WHERE order_id = [order_id];
SELECT * FROM batches WHERE order_id = [order_id];
```

### Test 3: Edit Order
**Steps**:
1. Click edit on an order
2. Modify status, items, or notes
3. Save
4. **Expected**: 
   - Changes saved
   - Order updated
   - Success message

### Test 4: View Order Details
**Steps**:
1. Click on an order
2. **Expected**: 
   - Modal/Page shows order details
   - Shows all order items
   - Shows supplier info
   - Shows total amount

### Test 5: Cancel Order
**Steps**:
1. Click cancel on a pending order
2. Confirm cancellation
3. **Expected**: 
   - Order status changes to "cancelled"
   - Order archived (if archiving enabled)
   - Success message

**Verify with SQL**:
```sql
SELECT * FROM orders WHERE id = [order_id];
SELECT * FROM archived_orders WHERE original_id = [order_id];
```

### Test 6: Order Status Updates
**Test Each Status**:
- Pending → Shipping
- Shipping → Completed
- Completed → Archived
- Pending → Cancelled

---

## 🏢 SUPPLIER MANAGEMENT TESTING

### Test 1: View Suppliers
**Steps**:
1. Navigate to: `suppliers_management.html`
2. **Expected**: 
   - Supplier list loads
   - Shows supplier details
   - Search works

### Test 2: Add Supplier
**Steps**:
1. Click "Add Supplier"
2. Fill in form:
   - Name (required)
   - Contact Person
   - Phone
   - Email
   - Address
   - Website (if column exists)
   - Notes (if column exists)
3. Submit
4. **Expected**: 
   - Supplier added
   - Appears in list
   - Success message

**Verify with SQL**:
```sql
SELECT * FROM suppliers WHERE name = '[Supplier Name]';
```

### Test 3: Edit Supplier
**Steps**:
1. Click edit on a supplier
2. Modify fields
3. Save
4. **Expected**: 
   - Changes saved
   - Updated in list

### Test 4: Delete/Archive Supplier
**Steps**:
1. Click delete/archive
2. Confirm
3. **Expected**: 
   - Supplier archived
   - Removed from active list
   - Success message

### Test 5: Link Medicines to Supplier
**Steps**:
1. Select supplier
2. Link medicines
3. **Expected**: 
   - Medicines linked
   - Relationship saved in `supplier_medicines` table

**Verify with SQL**:
```sql
SELECT * FROM supplier_medicines WHERE supplier_id = [supplier_id];
```

---

## 👥 USER MANAGEMENT TESTING (Admin Only)

### Test 1: View Users
**Steps**:
1. Login as admin
2. Open Settings → User Management
3. **Expected**: 
   - User list loads
   - Shows roles
   - Shows status

### Test 2: Add User
**Steps**:
1. Click "Add User"
2. Fill in form
3. Submit
4. **Expected**: 
   - User created
   - Can login with new credentials

### Test 3: Edit User
**Steps**:
1. Click edit on a user
2. Modify role, status, etc.
3. Save
4. **Expected**: 
   - Changes saved
   - User permissions updated

### Test 4: Lock/Unlock User
**Steps**:
1. Lock a user account
2. Try to login with that account
3. **Expected**: 
   - Login fails with "Account Locked" message
4. Unlock account
5. **Expected**: 
   - Can login again

---

## 📦 BATCH MANAGEMENT TESTING

### Test 1: View Batches
**Steps**:
1. Navigate to batches page (if exists)
2. **Expected**: 
   - Batch list loads
   - Shows batch numbers
   - Shows expiration dates

### Test 2: Batch Creation (Automatic)
**Steps**:
1. Create an order
2. Complete the order
3. **Expected**: 
   - Batch automatically created
   - Batch items created
   - Batch number generated

**Verify with SQL**:
```sql
SELECT * FROM batches WHERE order_id = [order_id];
SELECT * FROM batch_items WHERE batch_id = [batch_id];
```

### Test 3: Expiration Tracking
**Steps**:
1. Check batch items with expiration dates
2. **Expected**: 
   - Expired items marked
   - Dashboard shows expiration alerts
   - Calendar shows expiration events

---

## 📊 REPORTS & ANALYTICS TESTING

### Test 1: View Reports
**Steps**:
1. Navigate to: `reports_analytics.html`
2. **Expected**: 
   - Reports page loads
   - Charts/graphs display
   - Data is accurate

### Test 2: Generate Reports
**Steps**:
1. Select report type
2. Set date range
3. Generate
4. **Expected**: 
   - Report generated
   - Data is correct
   - Can export (if feature exists)

---

## 🔔 SUPPLIER PORTAL TESTING

### Test 1: Supplier Dashboard
**Steps**:
1. Login as supplier
2. **Expected**: 
   - Supplier-specific dashboard
   - Shows supplier orders
   - Shows notifications
   - Shows metrics

### Test 2: Supplier Orders
**Steps**:
1. View supplier orders
2. **Expected**: 
   - Only shows orders for that supplier
   - Can view order details
   - Can see order status

### Test 3: Supplier Notifications
**Steps**:
1. Check notifications panel
2. **Expected**: 
   - Notifications display
   - Can mark as read
   - Unread count is correct

---

## 🗄️ ARCHIVE TESTING

### Test 1: View Archives
**Steps**:
1. Open Archive modal/page
2. **Expected**: 
   - Archived items list
   - Can filter by type
   - Can view archived data

### Test 2: Archive Functionality
**Steps**:
1. Archive an order/medicine/supplier
2. **Expected**: 
   - Item moved to archive
   - Removed from active list
   - Can be viewed in archive

**Verify with SQL**:
```sql
SELECT * FROM archived_orders;
SELECT * FROM archived_medicines;
SELECT * FROM archived_suppliers;
```

---

## 🔍 ERROR HANDLING TESTING

### Test 1: Invalid Input
**Steps**:
1. Try to submit forms with invalid data
2. **Expected**: 
   - Validation errors shown
   - Form doesn't submit
   - User-friendly error messages

### Test 2: Database Errors
**Steps**:
1. Temporarily break database connection
2. Try to use system
3. **Expected**: 
   - Graceful error handling
   - User-friendly error message
   - System doesn't crash

### Test 3: Missing Data
**Steps**:
1. Delete a supplier that has orders
2. View orders
3. **Expected**: 
   - System handles gracefully
   - Shows "Unknown Supplier" or similar
   - Doesn't crash

### Test 4: Permission Errors
**Steps**:
1. Login as employee (not admin)
2. Try to access admin-only pages
3. **Expected**: 
   - Access denied
   - Redirected appropriately
   - Error message shown

---

## 🌐 BROWSER COMPATIBILITY TESTING

### Test Different Browsers:
- ✅ Chrome
- ✅ Firefox
- ✅ Edge
- ✅ Safari (if on Mac)

### Test Responsive Design:
- ✅ Desktop (1920x1080)
- ✅ Tablet (768x1024)
- ✅ Mobile (375x667)

---

## ⚡ PERFORMANCE TESTING

### Test 1: Page Load Times
**Check**:
- Dashboard loads in < 3 seconds
- Medicine list loads in < 2 seconds
- Orders list loads in < 2 seconds

### Test 2: Large Dataset
**Steps**:
1. Add 100+ medicines
2. Add 50+ orders
3. Test pagination
4. **Expected**: 
   - System handles large datasets
   - Pagination works correctly
   - No performance degradation

### Test 3: Concurrent Users
**Steps**:
1. Open multiple browser tabs
2. Perform operations simultaneously
3. **Expected**: 
   - No conflicts
   - Data consistency maintained

---

## 🧪 AUTOMATED TESTING CHECKLIST

### Quick Test Script (Create test.php):
```php
<?php
require_once 'php/conn.php';

echo "<h1>System Health Check</h1>";

// Test 1: Database Connection
if ($conn) {
    echo "✅ Database connection: OK<br>";
} else {
    echo "❌ Database connection: FAILED<br>";
    exit;
}

// Test 2: Critical Tables
$tables = ['users', 'medicines', 'suppliers', 'orders', 'order_items'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "✅ Table '$table': EXISTS<br>";
    } else {
        echo "❌ Table '$table': MISSING<br>";
    }
}

// Test 3: Sample Data
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
$row = mysqli_fetch_assoc($result);
echo "✅ Users in database: " . $row['cnt'] . "<br>";

$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM medicines");
$row = mysqli_fetch_assoc($result);
echo "✅ Medicines in database: " . $row['cnt'] . "<br>";

// Test 4: Check Missing Columns
$checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
if (mysqli_num_rows($checkUnit) == 0) {
    echo "⚠️ Missing column: medicines.unit<br>";
}

$checkWebsite = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'website'");
if (mysqli_num_rows($checkWebsite) == 0) {
    echo "⚠️ Missing column: suppliers.website<br>";
}

echo "<br><h2>✅ System Health Check Complete!</h2>";
?>
```

**Run**: Navigate to `http://localhost/test.php` (or your path)

---

## 📋 COMPREHENSIVE TEST CHECKLIST

### Pre-Deployment Checklist:
- [ ] Database connection works
- [ ] All tables exist
- [ ] Can login as admin
- [ ] Can login as employee
- [ ] Can login as supplier
- [ ] Dashboard loads with data
- [ ] Can add medicine
- [ ] Can edit medicine
- [ ] Can delete medicine
- [ ] Can create order
- [ ] Can edit order
- [ ] Can cancel order
- [ ] Can add supplier
- [ ] Can edit supplier
- [ ] Can archive supplier
- [ ] Search works
- [ ] Filters work
- [ ] Pagination works
- [ ] Role-based access works
- [ ] Archive functionality works
- [ ] Reports generate
- [ ] No JavaScript errors in console
- [ ] No PHP errors in logs
- [ ] Mobile responsive
- [ ] All forms validate input

---

## 🐛 DEBUGGING TIPS

### Check PHP Error Logs:
**Location**: Usually in `php/error_log` or server error log

### Check Browser Console:
1. Press F12
2. Go to Console tab
3. Look for JavaScript errors

### Check Network Tab:
1. Press F12
2. Go to Network tab
3. Check for failed API calls (red entries)
4. Check response codes (should be 200)

### Common Issues:

1. **"Database connection failed"**
   - Check `php/conn.php` credentials
   - Verify database exists
   - Check MySQL service is running

2. **"Table doesn't exist"**
   - Run table creation scripts
   - Check database name is correct

3. **"Column doesn't exist"**
   - Add missing columns (see DATABASE_COMPLETE_ANALYSIS.md)
   - Or code will handle gracefully

4. **"Access denied"**
   - Check user role
   - Verify session is set
   - Check page protection

5. **"CORS error"**
   - Check CORS headers in PHP files
   - Verify allowed origins

---

## 📝 TESTING REPORT TEMPLATE

**Date**: _______________
**Tester**: _______________
**Environment**: _______________

### Test Results:
- [ ] Authentication: PASS / FAIL
- [ ] Dashboard: PASS / FAIL
- [ ] Medicine Management: PASS / FAIL
- [ ] Order Management: PASS / FAIL
- [ ] Supplier Management: PASS / FAIL
- [ ] User Management: PASS / FAIL
- [ ] Archive: PASS / FAIL
- [ ] Reports: PASS / FAIL

### Issues Found:
1. _______________________
2. _______________________
3. _______________________

### Notes:
_______________________

---

## ✅ SUCCESS CRITERIA

**System is working correctly if**:
1. ✅ All critical features work
2. ✅ No PHP errors in logs
3. ✅ No JavaScript errors in console
4. ✅ Data persists correctly
5. ✅ User permissions work
6. ✅ Search and filters work
7. ✅ Pagination works
8. ✅ Forms validate input
9. ✅ Error messages are user-friendly
10. ✅ System handles edge cases gracefully

---

**Last Updated**: Based on system analysis
**Recommended Testing Frequency**: Before each deployment









