<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseEntry extends Model
{
    protected $fillable = ['purchase_number', 'purchase_date', 'invoice_number', 'invoice_date', 'party_id', 'purchase_order_id','note','gst_amount',
        'discount',
        'cgst',
        'sgst',
        'igst',
        'from_receipt_note',];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseEntryItem::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payable()
    {
        return $this->hasOne(Payable::class);
    }
}