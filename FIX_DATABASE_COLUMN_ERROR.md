# Fix Database Column Error

## Error Description
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'from_receipt_note' in 'field list'
```

This error occurs because the `from_receipt_note` column doesn't exist in the `purchase_entries` table.

## Solutions (Choose One)

### Solution 1: Run Laravel Migration (Recommended)

If you have access to Laravel artisan commands:

```bash
php artisan migrate
```

This will run the migration file:
`database/migrations/2025_01_15_000001_add_from_receipt_note_to_purchase_entries_table.php`

### Solution 2: Add Column Manually via SQL

If you can't run Laravel migrations, execute this SQL directly in your MySQL database:

```sql
ALTER TABLE purchase_entries 
ADD COLUMN from_receipt_note BOOLEAN DEFAULT FALSE 
AFTER note;
```

### Solution 3: Use Database Management Tool

If you have phpMyAdmin, MySQL Workbench, or similar:

1. Open your database
2. Navigate to `purchase_entries` table
3. Add new column with these settings:
   - **Name**: `from_receipt_note`
   - **Type**: `BOOLEAN` or `TINYINT(1)`
   - **Default**: `0` (FALSE)
   - **Position**: After `note` column

## Verify the Fix

After adding the column, verify it exists:

```sql
DESCRIBE purchase_entries;
```

You should see `from_receipt_note` in the column list.

## Re-enable the Feature

Once the column is added, uncomment these lines:

### In `app/Http/Controllers/ReceiptNoteController.php`:
```php
// Change this line (around line 265):
// 'from_receipt_note' => true, // Commented out until column is added

// To this:
'from_receipt_note' => true,
```

### In `app/Models/PurchaseEntry.php`:
```php
// Change this in the fillable array:
// 'from_receipt_note', // Commented out until column is added

// To this:
'from_receipt_note',
```

## Test the Conversion

After adding the column:

1. Go to Receipt Notes → Edit any receipt note
2. Fill in Invoice Number and Invoice Date
3. Add unit prices to products
4. Click "Update & Convert to Purchase Entry"
5. Should work without errors!

## Current Status

✅ **Temporarily Fixed**: The conversion will work now, but without the `from_receipt_note` tracking
🔄 **Needs**: Database column to be added
✅ **After Fix**: Full functionality will be restored

## Why This Column is Useful

The `from_receipt_note` column helps track which purchase entries were created from receipt note conversions vs. direct entry. This is useful for:

- Reporting and analytics
- Data integrity checks
- Business process tracking
- Audit trails

Once you add the column, the conversion process will work perfectly and track the source of each purchase entry.