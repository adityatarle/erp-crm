# Receipt Note Conversion Testing Guide

## Step-by-Step Testing Process

### 1. First, Check the Receipt Note in Database
```sql
SELECT id, receipt_number, invoice_number, invoice_date, is_converted 
FROM receipt_notes 
WHERE id = [YOUR_RECEIPT_NOTE_ID];
```

### 2. Test from Edit Page

#### A. Fill in the form properly:
1. Go to Receipt Notes → Edit
2. Fill in **Invoice Number**: `TEST-INV-12345`
3. Fill in **Invoice Date**: Today's date
4. For each product, fill in:
   - **Unit Price**: Any positive number (e.g., `100.00`)
   - **CGST Rate**: `9` (if applicable)
   - **SGST Rate**: `9` (if applicable)
   - **IGST Rate**: `0` (or `18` for inter-state)

#### B. Debug the form submission:
1. Open browser Dev Tools (F12)
2. Go to Console tab
3. Click "Convert to Purchase Entry"
4. Check console messages for validation info

### 3. Test from Index Page

1. Go to Receipt Notes → Index
2. Click "Convert to Purchase Entry" on a receipt note
3. Fill in the conversion form completely
4. Submit

### 4. Common Issues to Check

#### Issue 1: Empty Invoice Fields
- **Symptoms**: "Invoice number is required for conversion"
- **Fix**: Make sure both Invoice Number and Invoice Date are filled
- **Debug**: Check browser console for validation messages

#### Issue 2: Missing Unit Prices
- **Symptoms**: Validation errors about unit prices
- **Fix**: Ensure all products have unit prices > 0

#### Issue 3: Form Synchronization
- **Symptoms**: JavaScript alerts about form data not synchronized
- **Fix**: Try manually submitting after a few seconds

#### Issue 4: Route Issues
- **Symptoms**: 404 errors or wrong page redirects
- **Fix**: Check if routes are properly defined

### 5. Debugging Steps

#### Check the Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

Look for entries like:
```
[timestamp] local.INFO: Conversion request started
[timestamp] local.INFO: Receipt note loaded for conversion
[timestamp] local.INFO: Invoice details resolved
```

#### Check Network Tab in Browser
1. Open Dev Tools → Network tab
2. Submit the conversion form
3. Check the POST request to see what data is being sent

#### Check the Form Data
In browser console, run:
```javascript
console.log('Invoice Number:', $('#invoice_number').val());
console.log('Invoice Date:', $('#invoice_date').val());
console.log('Hidden Invoice Number:', $('#convert_invoice_number').val());
console.log('Hidden Invoice Date:', $('#convert_invoice_date').val());
```

### 6. Expected Form Data

The conversion should send data like:
```
invoice_number: "TEST-INV-12345"
invoice_date: "2025-01-15"
purchase_order_id: "123"
party_id: "456"
products[0][product_id]: "789"
products[0][quantity]: "10"
products[0][unit_price]: "100.00"
products[0][cgst_rate]: "9"
products[0][sgst_rate]: "9"
products[0][igst_rate]: "0"
products[0][status]: "received"
```

### 7. Quick Fix Test

If the form isn't working, try this manual approach:

1. Create a receipt note
2. Edit it and save with invoice details (don't convert yet)
3. Go to the database and verify invoice_number and invoice_date are saved
4. Try conversion again

### 8. Success Indicators

You'll know it's working when:
- ✅ No validation errors appear
- ✅ You're redirected to Purchase Entries index
- ✅ A new purchase entry is created
- ✅ The receipt note is marked as `is_converted = 1`
- ✅ You see success message: "Receipt note converted to purchase entry successfully"

### 9. If All Else Fails

Run this quick test in a PHP artisan tinker session:
```php
$rn = ReceiptNote::find(1); // Replace 1 with your receipt note ID
echo "Invoice Number: " . $rn->invoice_number . "\n";
echo "Invoice Date: " . $rn->invoice_date . "\n";
echo "Is Converted: " . $rn->is_converted . "\n";
```