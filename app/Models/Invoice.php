<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 
        'organisation_id', 
        'document_number', 
        'status', 
        'subtotal_cents', 
        'tax_cents', 
        'total_cents', 
        'payment_due'
    ];

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = ucwords(strtolower($value));
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}