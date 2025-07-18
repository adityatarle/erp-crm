<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptNote extends Model
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'party_id',
        'note',
        'purchase_order_number',
        'invoice_number',
        'invoice_date',
        'gst_amount',
        'discount',
        'is_converted',
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
}
