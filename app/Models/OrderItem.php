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

    protected $fillable = [
        'order_id',
        'order_menu_item_id',
        'order_item_status_id',
        'locked_price',
        'due_date',
        'root_order_item_id',
        'specifiable_id',
        'specifiable_type',
        'revision_number',
        'supersedes_order_item_id',
        'invoice_line_id',
        'asset_path',
    ];

    protected $with = ['specifiable', 'statusLookup'];

    protected $appends = [];

    protected $casts = [
        'locked_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        // Structural Lockdown Rule (Immutability Lock)
        static::updating(function (OrderItem $orderItem) {
            $immutableFields = ['specifiable_type', 'specifiable_id', 'order_menu_item_id'];

            if ($orderItem->isDirty($immutableFields)) {
                throw new \Exception(
                    "Polymorphic type definitions and menu item mappings are immutable once created."
                );
            }
        });

        // Automated System Tracking Code Injection
        static::created(function (OrderItem $item) {
            $specification = $item->specifiable;

            if ($specification && in_array('isci', $specification->getFillable()) && empty($specification->isci)) {
                $paddedId = str_pad($item->id, 6, "0", STR_PAD_LEFT);
                
                $specification->update([
                    'isci' => "GTC" . $paddedId
                ]);
            }
        });

        // Parent status syncing is handled cleanly inside the explicit
        // controller transaction actions now. Removing duplicate recursive pivot saves.
        $syncParentStatuses = function ($item) {
            $order = $item->order;
            if ($order) {
                $order->syncStatusAndTags();
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

    public function revisionInstructions()
    {
        return $this->hasOne(OrderItemRevision::class, 'new_order_item_id');
    }

    // If this is the OLD cancelled item, see why it was rejected:
    public function rejectionReason()
    {
        return $this->hasOne(OrderItemRevision::class, 'old_order_item_id');
    }
}