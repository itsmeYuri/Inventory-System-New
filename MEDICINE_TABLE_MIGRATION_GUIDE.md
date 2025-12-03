# Medicine Table Migration Guide - POS Integration

## Overview
The medicines table has been restructured to support Point of Sale (POS) system integration. The new structure simplifies the schema while maintaining backward compatibility.

## New Table Structure

```sql
CREATE TABLE `medicines` (
  `medicine_id` varchar(50) NOT NULL,
  `medicine_group` varchar(100) NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `generic_name` varchar(150) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `form` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Column Mapping

### Old → New Structure

| Old Column | New Column | Notes |
|------------|------------|-------|
| `id` (INT) | `medicine_id` (VARCHAR(50)) | Primary key changed to VARCHAR |
| `name` | `medicine_name` | Renamed for clarity |
| `category` | `medicine_group` | Renamed for POS compatibility |
| `quantity` | `stock` | Renamed for POS terminology |
| `dosage_form` | `dosage` | Simplified name |
| `dosage_form` | `form` | Duplicate field for POS |
| - | `generic_name` | **NEW FIELD** - Required for POS |
| `price` | `price` | Unchanged |

### Removed Fields (Preserved for Reference)
- `ndc` - Not in new structure but kept in database
- `manufacturer` - Not in new structure but kept in database
- `expiration_date` - Not in new structure but kept in database
- `batch_number` - Not in new structure but kept in database
- `status` - Not in new structure but kept in database
- `reorder_level` - Not in new structure but kept in database
- `unit` - Not in new structure but kept in database
- `created_at`, `updated_at` - Not in new structure but kept in database

## Migration Steps

### Step 1: Run Migration Script
Navigate to: `http://localhost/php/migrate_medicines_to_pos_structure.php`

This script will:
1. Create a backup table with timestamp
2. Add new columns to the medicines table
3. Migrate data from old columns to new columns
4. Set up primary key on `medicine_id`
5. Preserve old columns for reference

### Step 2: Verify Migration
After running the migration, verify:
- New columns exist: `medicine_id`, `medicine_name`, `medicine_group`, `generic_name`, `dosage`, `form`, `stock`
- Data has been migrated correctly
- Primary key is set on `medicine_id`

### Step 3: Update Forms
The HTML forms have been updated to include:
- `genericName` field (new)
- `medicineName` field (maps to `medicine_name`)
- `category` field (maps to `medicine_group`)
- `unit` field (maps to both `dosage` and `form`)

## Backend Changes

### Updated PHP Files

1. **`php/medicine_structure_helper.php`** (NEW)
   - Helper functions to detect structure
   - Column mapping functions
   - SELECT field generators

2. **`php/add_medicine.php`**
   - Supports both old and new structures
   - Uses `medicine_id` (VARCHAR) for new structure
   - Uses `id` (INT) for old structure
   - Handles `generic_name` field

3. **`php/edit_medicine.php`**
   - Supports both structures
   - Updates appropriate columns based on structure

4. **`php/get_medicines.php`**
   - Returns data with column aliases for compatibility
   - Maps new columns to old field names in response

### Form Field Names

**Add Medicine Form:**
- `medicineName` → `medicine_name`
- `genericName` → `generic_name` (NEW)
- `category` → `medicine_group`
- `unit` → `dosage` and `form`
- `quantity` → `stock` (in new structure)
- `price` → `price`

**Edit Medicine Form:**
- Same field names as Add form
- `id` → `medicine_id` (in new structure)

## Data Migration Details

### Automatic Mapping
- `id` → `medicine_id`: Converted to string
- `name` → `medicine_name`: Direct copy
- `category` → `medicine_group`: Direct copy (defaults to 'Uncategorized' if NULL)
- `quantity` → `stock`: Direct copy (defaults to 0 if NULL)
- `dosage_form` → `dosage`: Direct copy
- `dosage_form` → `form`: Direct copy
- `generic_name`: Empty string initially (user must populate)

## Backward Compatibility

The system maintains backward compatibility:
- Old columns are preserved in the database
- PHP code detects which structure is in use
- Forms work with both structures
- API responses include aliases for compatibility

## Testing Checklist

- [ ] Run migration script
- [ ] Verify backup table was created
- [ ] Test adding new medicine
- [ ] Test editing existing medicine
- [ ] Test retrieving medicines list
- [ ] Verify generic_name field appears in forms
- [ ] Verify data displays correctly in inventory table
- [ ] Test search functionality
- [ ] Test filtering by category/group

## Notes

1. **Primary Key Change**: The primary key changed from `id` (INT) to `medicine_id` (VARCHAR(50)). This allows for custom ID formats if needed.

2. **Generic Name**: This is a new required field. Existing records will have empty strings initially. Users should populate this field.

3. **Stock vs Quantity**: The field is renamed to `stock` for POS terminology, but the API still returns it as `quantity` for backward compatibility.

4. **Old Columns**: Old columns (ndc, manufacturer, expiration_date, etc.) are preserved but not used in the new structure. They can be removed later after verification.

5. **No Data Loss**: The migration preserves all existing data. Old columns remain accessible if needed.

## Rollback

If you need to rollback:
1. Restore from backup table: `medicines_backup_YYYYMMDDHHMMSS`
2. Remove new columns if needed
3. Restore old primary key structure

## Support

For issues or questions:
- Check migration script output
- Verify database structure
- Review PHP error logs
- Check browser console for JavaScript errors
