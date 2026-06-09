<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class OrderItemBroadcastSpecification extends Model
{
    use HasFactory;

    protected $table = 'order_item_broadcast_specifications';

    protected $guarded = [];

    /**
     * Inverse polymorphic mapping relationship back to the master item ledger.
     */
    public function orderItem(): MorphOne
    {
        return $this->morphOne(OrderItem::class, 'specifiable');
    }
}