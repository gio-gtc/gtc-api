<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class OrderItemSocialSpecs extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'cut',
        'card_holder',
        'duration_seconds',
        'language',
        'isci',
    ];

    /**
     * Inverse polymorphic mapping relationship back to the master item ledger.
     */
    public function orderItem(): MorphOne
    {
        return $this->morphOne(OrderItem::class, 'specifiable');
    }
}