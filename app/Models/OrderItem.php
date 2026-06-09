<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['specifiable', 'statusLookup'];

    protected $casts = [
        'locked_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        // 1. Automated System Tracking Code Injection
        static::created(function (OrderItem $item) {
            $specification = $item->specifiable;

            // If a child row exists but hasn't had an ISCI code set, stamp it with the master invoice tracking code
            if ($specification && empty($specification->isci)) {
                $paddedId = str_pad($item->id, 6, "0", STR_PAD_LEFT);
                
                $specification->update([
                    'isci' => "GTC" . $paddedId
                ]);
            }
        });

        // 2. High-Performance Core Order Pipeline Synchronization Status Loop
        $syncParentStatuses = function ($item) {
            $order = $item->order;
            if ($order) {
                $mappedOrderStatusIds = $order->orderItems()
                    ->with('statusLookup')
                    ->get()
                    ->pluck('statusLookup.order_status_id')
                    ->filter()
                    ->unique()
                    ->toArray();

                $order->statuses()->sync($mappedOrderStatusIds);
            }
        };

        static::saved($syncParentStatuses);
        static::deleted($syncParentStatuses);
    }

    /**
     * Polymorphic Mapping Pointer link to the concrete specialized parameters table row.
     */
    public function specifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusLookup(): BelongsTo
    {
        return $this->belongsTo(OrderItemStatus::class, 'order_item_status_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderMenuItem(): BelongsTo 
    {
        return $this->belongsTo(OrderMenuItem::class, 'order_menu_item_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_item_assignee', 'order_item_id', 'user_id')
            ->withTimestamps();
    }
}