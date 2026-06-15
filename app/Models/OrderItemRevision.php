<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemRevision extends Model
{
    // Allow these columns to be written to during database transactions
    protected $fillable = [
        'old_order_item_id',
        'new_order_item_id',
        'user_id',
        'comment'
    ];

    /**
     * Get the historical order item that was rejected.
     */
    public function oldItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'old_order_item_id');
    }

    /**
     * Get the fresh duplicate order item generated for the production team.
     */
    public function newItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'new_order_item_id');
    }

    /**
     * Get the client or staff member who requested the change.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}