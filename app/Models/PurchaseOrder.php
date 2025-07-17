<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;
    protected $fillable = ['purchase_order_number', 'party_id', 'order_date', 'status', 'customer_name'];
    protected $casts = ['order_date' => 'datetime'];

    // --- BASE RELATIONSHIPS ---
    public function party() { return $this->belongsTo(Party::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
    public function receiptNotes() { return $this->hasMany(ReceiptNote::class); }
    public function purchaseEntries() { return $this->hasMany(PurchaseEntry::class); }

    // --- "THROUGH" RELATIONSHIPS ---

    /**
     * Gets all items received via Receipt Notes for this PO.
     */
    public function receiptNoteItems()
    {
        return $this->hasManyThrough(ReceiptNoteItem::class, ReceiptNote::class);
    }

    /**
     * Gets all items received via direct Purchase Entries for this PO.
     */
    public function purchaseEntryItems()
    {
        return $this->hasManyThrough(PurchaseEntryItem::class, PurchaseEntry::class);
    }

    /**
     * THE DEFINITIVE FIX: This accessor now correctly checks BOTH sources.
     * It will be used by your index and show pages automatically.
     */
    public function getReceiptStatusAttribute(): string
    {
        // Eager load all necessary relationships for high performance
        $this->loadMissing(['items', 'receiptNoteItems', 'purchaseEntryItems']);

        $orderedQuantities = $this->items->pluck('quantity', 'product_id');
        $totalOrdered = $orderedQuantities->sum();

        if ($totalOrdered == 0) {
            return 'Pending';
        }

        // 1. Get quantities from Receipt Notes - FIXED: Use 'quantity' instead of 'quantity_received'
        $receivedViaNote = $this->receiptNoteItems
                                ->where('status', 'received')
                                ->groupBy('product_id')
                                ->map(function ($items) {
                                    // Fixed column name from 'quantity_received' to 'quantity'
                                    return $items->sum('quantity'); 
                                });
        
        // 2. Get quantities from direct Purchase Entries
        $receivedViaEntry = $this->purchaseEntryItems
                                 ->where('status', 'received')
                                 ->groupBy('product_id')
                                 ->map(function ($items) {
                                    // Use the correct column name from purchase_entry_items table
                                     return $items->sum('quantity'); 
                                 });

        // 3. Combine both sources into a single, accurate total for each product
        $totalReceivedQuantities = $orderedQuantities->mapWithKeys(function ($orderedQty, $productId) use ($receivedViaNote, $receivedViaEntry) {
            $fromNote = $receivedViaNote->get($productId, 0);
            $fromEntry = $receivedViaEntry->get($productId, 0);
            return [$productId => $fromNote + $fromEntry];
        });

        $totalReceived = $totalReceivedQuantities->sum();

        if ($totalReceived <= 0) {
            return 'Pending';
        }

        // Use >= in case of over-receipt
        if ($totalReceived >= $totalOrdered) {
            // Final check to ensure every single item is fully received
            foreach ($orderedQuantities as $productId => $orderedQty) {
                if ($totalReceivedQuantities->get($productId, 0) < $orderedQty) {
                    return 'Partial'; // Another item was over-received, but this one is still pending
                }
            }
            return 'Completed';
        }

        // If total received is more than 0 but less than total ordered
        return 'Partial';
    }

    // Your other relationships can remain if you use them elsewhere
    public function product() { return $this->belongsTo(Product::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
}