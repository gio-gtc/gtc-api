<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShowDate extends Model
{
    public $timestamps = false; // Disabled timestamps

    protected $fillable = ['order_id', 'show_date'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}