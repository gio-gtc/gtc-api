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

    protected $appends = ['encoding_surcharge', 'estimated_total'];

    protected $casts = [
        'locked_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        // Structural Lockdown Rule (Immutability Lock)
        // Prevents changing an item's core identity or media platform after creation
        static::updating(function (OrderItem $orderItem) {
            $immutableFields = ['specifiable_type', 'specifiable_id', 'order_menu_item_id'];

            if ($orderItem->isDirty($immutableFields)) {
                throw new \Exception(
                    "Polymorphic type definitions and menu item mappings are immutable once created."
                );
            }
        });

        // 1. Automated System Tracking Code Injection
        static::created(function (OrderItem $item) {
            $specification = $item->specifiable;

            // Safe Check: Only stamp an ISCI if the model supports it (keeps Key Art safe!)
            if ($specification && in_array('isci', $specification->getFillable()) && empty($specification->isci)) {
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

    public function revisionInstructions()
    {
        return $this->hasOne(OrderItemRevision::class, 'new_order_item_id');
    }

    // If this is the OLD cancelled item, see why it was rejected:
    public function rejectionReason()
    {
        return $this->hasOne(OrderItemRevision::class, 'old_order_item_id');
    }

    /**
     * Calculate the encoding surcharge for this item as a decimal.
     */
    public function getEncodingSurchargeAttribute(): float
    {
        $spec = $this->specifiable;
        
        if ($spec && isset($spec->encoding) && is_array($spec->encoding)) {
            $pricePerTarget = 50.00; // $50.00 flat per distribution platform
            return count($spec->encoding) * $pricePerTarget;
        }

        return 0.00;
    }

    /**
     * Calculate the true total estimated cost of this line item as a decimal.
     */
    public function getEstimatedTotalAttribute(): float
    {
        return (float)$this->locked_price + $this->encoding_surcharge;
    }
}