<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 
        'order_item_id', 
        'description', 
        'unit_price_cents', 
        'quantity', 
        'total_cents',
        'price'
    ];

    protected $appends = ['price'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Dynamically present 'price' in decimal format for backwards compatibility.
     */
    public function getPriceAttribute()
    {
        return array_key_exists('price', $this->attributes) && !is_null($this->attributes['price'])
            ? $this->attributes['price']
            : ($this->unit_price_cents / 100);
    }
}