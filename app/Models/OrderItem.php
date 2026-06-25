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
        if ($this->orderMenuItem?->billing_code !== 'Video') {
            return 0.00;
        }

        $stillInCartId = \App\Models\OrderItemStatus::where('name', 'Still In Cart')->first()?->id;
        if (!$stillInCartId) return 0.00;

        $sisterVideoItems = self::where('order_id', $this->order_id)
            ->where('order_item_status_id', $stillInCartId)
            ->with(['specifiable', 'orderMenuItem'])
            ->get();

        // Replicate the exact same pooling rules for the sandbox environment matrix simulation
        $globalEncodingPlatforms = collect();
        foreach ($sisterVideoItems as $item) {
            $isSocial = str_contains(strtolower($item->orderMenuItem?->name ?? ''), 'social');
            
            if ($isSocial) {
                $globalEncodingPlatforms->push("Social-Default-Item-{$item->id}");
            } else {
                if ($item->specifiable && isset($item->specifiable->encoding) && is_array($item->specifiable->encoding)) {
                    foreach ($item->specifiable->encoding as $platform) {
                        $globalEncodingPlatforms->push($platform);
                    }
                }
            }
        }

        $totalEncodingsCount = $globalEncodingPlatforms->count();
        if ($totalEncodingsCount === 0) return 0.00;

        $matrix = $this->orderMenuItem?->pricing_matrix ?? [];
        $baseBundlePrice = (float) ($matrix['base_encoding_bundle'] ?? 250.00);
        $additionalPrice = (float) ($matrix['additional_encoding'] ?? 75.00);

        $totalPoolCost = $totalEncodingsCount <= 2 
            ? $baseBundlePrice 
            : $baseBundlePrice + (($totalEncodingsCount - 2) * $additionalPrice);

        return round($totalPoolCost / $sisterVideoItems->count(), 2);
    }

    /**
     * Calculate the true total estimated cost of this line item as a decimal.
     */
    public function getEstimatedTotalAttribute(): float
    {
        if ($this->orderMenuItem?->billing_code !== 'Video') {
            return (float) $this->locked_price;
        }

        $spec = $this->specifiable;
        if (!$spec) return (float) $this->locked_price;

        $matrix = $this->orderMenuItem?->pricing_matrix ?? [];

        // 1. Evaluate Revision Matrix Parameter
        if (!empty($spec->isci) && preg_match('/R\d+$/i', $spec->isci)) {
            $basePrice = (float) ($matrix['revision_price'] ?? 275.00);
            return $basePrice + $this->encoding_surcharge;
        }

        // 2. Evaluate Unique Cut Variant Parameter
        $stillInCartId = \App\Models\OrderItemStatus::where('name', 'Still In Cart')->first()?->id;
        
        $priorItems = self::where('order_id', $this->order_id)
            ->where('order_item_status_id', $stillInCartId)
            ->where('id', '<', $this->id)
            ->get();

        $isFirstOfKind = true;
        foreach ($priorItems as $prior) {
            $pSpec = $prior->specifiable;
            if ($pSpec && 
                ($pSpec->type ?? 'default') === ($spec->type ?? 'default') &&
                ($pSpec->duration_seconds ?? $pSpec->duration ?? '0') === ($spec->duration_seconds ?? $spec->duration ?? '0') &&
                ($pSpec->language ?? 'English') === ($spec->language ?? 'English') &&
                !( !empty($pSpec->isci) && preg_match('/R\d+$/i', $pSpec->isci) )
            ) {
                $isFirstOfKind = false;
                break;
            }
        }

        $basePrice = $isFirstOfKind 
            ? (float) ($matrix['first_cut_price'] ?? 575.00) 
            : (float) ($matrix['additional_cut_price'] ?? 275.00);

        return $basePrice + $this->encoding_surcharge;
    }
}