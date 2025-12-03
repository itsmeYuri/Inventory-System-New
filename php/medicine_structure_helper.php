<?php
/**
 * Helper functions to check which medicine table structure is in use
 * and provide column name mappings
 */

/**
 * Check if the new POS structure exists
 */
function hasNewMedicineStructure($conn) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'medicine_id'");
    return $check && mysqli_num_rows($check) > 0;
}

/**
 * Get column mapping based on structure
 */
function getMedicineColumnMapping($conn) {
    $hasNew = hasNewMedicineStructure($conn);
    
    if ($hasNew) {
        return [
            'id' => 'medicine_id',
            'name' => 'medicine_name',
            'category' => 'medicine_group',
            'quantity' => 'stock',
            'dosage_form' => 'dosage',
            'form' => 'form',
            'generic_name' => 'generic_name',
            'price' => 'price'
        ];
    } else {
        return [
            'id' => 'id',
            'name' => 'name',
            'category' => 'category',
            'quantity' => 'quantity',
            'dosage_form' => 'dosage_form',
            'form' => 'dosage_form',
            'generic_name' => null,
            'price' => 'price'
        ];
    }
}

/**
 * Get SELECT fields for medicines query
 */
function getMedicineSelectFields($conn) {
    $hasNew = hasNewMedicineStructure($conn);
    
    if ($hasNew) {
        return "medicine_id as id, medicine_name as name, medicine_group as category, 
                generic_name, dosage, form, stock as quantity, price";
    } else {
        // Check for optional columns
        $hasUnit = false;
        $checkUnit = mysqli_query($conn, "SHOW COLUMNS FROM medicines WHERE Field = 'unit'");
        if ($checkUnit && mysqli_num_rows($checkUnit) > 0) {
            $hasUnit = true;
        }
        
        $fields = "id, ndc, name, manufacturer, category, dosage_form, quantity, reorder_level, price, expiration_date, batch_number, status";
        if ($hasUnit) {
            $fields .= ", unit";
        }
        return $fields;
    }
}
