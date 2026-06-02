<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_menu_item_id',
        'order_item_status_id',
        'locked_price',
        'due_date',
        'specifications',
        'root_order_item_id',
        'revision_number',
        'supersedes_order_item_id',
        'invoice_line_id'
    ];

    protected $casts = [
        'specifications' => 'array',
        'locked_price'   => 'decimal:2',
    ];

    protected $appends = [
        'status'
    ];

    protected static function booted()
    {
        // 1. Generate the automated unique serial tracking code after database insert
        static::created(function (OrderItem $item) {
            $currentSpecs = $item->specifications ?? [];
            
            // Generate GTC + 6-digit zero-padded primary key ID string
            $paddedId = str_pad($item->id, 6, "0", STR_PAD_LEFT);
            $currentSpecs['isci'] = "GTC" . $paddedId;
            
            // Assign back and write silently to the storage cluster
            $item->specifications = $currentSpecs;
            $item->saveQuietly();
        });

        // 2. Automation Sync Loop: Whenever an item updates or is deleted, recalculate parent order states
        $syncParentStatuses = function ($item) {
            $order = $item->order;
            if ($order) {
                // Collect all parent order status ids mapped from current item collection elements
                $mappedOrderStatusIds = $order->orderItems()
                    ->with('statusLookup')
                    ->get()
                    ->pluck('statusLookup.order_status_id')
                    ->filter() // Automatically strips out NULL values (like 'Still In Cart')
                    ->unique()
                    ->toArray();

                // High-performance direct sync to the custom 'order_status' pivot table
                $order->statuses()->sync($mappedOrderStatusIds);
            }
        };

        static::saved($syncParentStatuses);
        static::deleted($syncParentStatuses);
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

    /**
     * Backwards Compatibility: Automatically exposes the text name string 
     * of the status directly on the item's root JSON payload.
     */
    public function getStatusAttribute(): string
    {
        return $this->statusLookup?->name ?? 'Still In Cart';
    }
}