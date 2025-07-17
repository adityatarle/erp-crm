<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptNote extends Model
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'party_id',
        'purchase_order_id',
        'note',
        'purchase_order_number',
        'invoice_number',
        'invoice_date',
        'gst_amount',
        'discount',
        'created_at',
        'updated_at',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function items()
    {
        return $this->hasMany(ReceiptNoteItem::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
