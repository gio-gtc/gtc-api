<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'venue_id',
        'ordered_by_id',
        'is_demo',
        'submitted_at',
        'due_date',
        'ticket_outlets',
        'on_same_date',
        'cardholder_times',
        'logos',
        'special_instructions',
    ];

    // Automatically inject these properties into all outgoing JSON serialization blocks
    protected $appends = [
        'item_statuses',
        'is_international',
        'tags',
    ];

    protected $casts = [
        'statuses' => 'array', 
    ];

    /**
     * Boot logic to handle lifecycle event hooks.
     */
    protected static function booted()
    {
        // Automatically generate a secure UUID string whenever a new order is initialized
        static::creating(function ($order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Virtual Accessor: Bubbles up the international tracking flag 
     * from the client's purchasing organization up to the root order layer.
     */
    public function getIsInternationalAttribute(): bool
    {
        return $this->client?->organisation?->is_international ?? false;
    }

    /**
     * Header Icon Guard: Returns true if any active submitted items are missing assets.
     */
    public function getIsAwaitingAssetsAttribute(): bool
    {
        // GUARD 1: If orderItems aren't eager-loaded, abort instantly 
        // to prevent an N+1 query explosion across your accordion loops!
        if (!$this->relationLoaded('orderItems')) {
            return false;
        }

        return $this->orderItems->contains(function ($item) {
            $specs = $item->specifiable ? $item->specifiable->toArray() : [];
            
            // 1. Canonical New Specification Layer: Check explicit JSON asset blocker tags
            if (is_array($specs) && isset($specs['awaiting_assets']) && !empty($specs['awaiting_assets'])) {
                return true;
            }

            // 2. Legacy Pipeline Fallback Layer: Only check status names if pre-loaded
            // ⚡ PROTECTION GUARD 2: Prevents line items from lazy-loading dictionary lookup rows
            if ($item->relationLoaded('statusLookup')) {
                $statusName = $item->statusLookup?->name;
                return in_array($statusName, ['New Order', 'Unassigned', 'Awaiting Assets']);
            }

            return false;
        });
    }

    /**
     * Compute a single primary status string for the order container.
     */
    public function getStatusAttribute(): string
    {
        $statuses = $this->item_statuses;

        if (empty($statuses)) {
            return 'Still In Cart';
        }

        // Precedence Waterfall Matrix
        $priorityWaterfall = [
            'Cancelled',
            'Client Review',
            'In Progress',
            'New Order',
            'Complete'
        ];
        
        foreach ($priorityWaterfall as $statusCandidate) {
            if (in_array($statusCandidate, $statuses)) {
                return $statusCandidate;
            }
        }

        return 'Still In Cart';
    }

    /**
     * Header Badges: Returns a list of all unique Title Case statuses currently active in this order.
     */
    public function getItemStatusesAttribute(): array
    {
        if (!$this->relationLoaded('orderItems') && !$this->orderItems) {
            return [];
        }

        return $this->orderItems
            ->map(function ($item) {
                return $item->statusLookup?->name;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function showDates(): HasMany
    {
        return $this->hasMany(OrderShowDate::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->select([
            'id', 
            'order_id', 
            'order_menu_item_id',
            'order_item_status_id',
            'locked_price',
            'due_date',
            'revision_number',
            'specifiable_id',
            'specifiable_type',
        ]);
    }

    /**
     * Relationship to your order_statuses lookup table via the pivot
     */
    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(
            OrderStatus::class,
            'order_order_status',
            'order_id',
            'order_status_id'
        );
    }

    public function getTagsAttribute(): array
    {
        if (!$this->relationLoaded('orderItems')) {
            return [];
        }

        $tags = [];
        $missingAudio = false;
        $missingArt = false;

        foreach ($this->orderItems as $item) {
            $statusName = $item->statusLookup?->name;
            
            if (in_array($statusName, ['Cancelled', 'Still In Cart'])) {
                continue;
            }

            if (empty($item->asset_url)) {
                $categoryId = $item->orderMenuItem?->order_menu_category_id;
                
                if (in_array($categoryId, [1, 2, 3])) {
                    $missingAudio = true;
                }
                if ($categoryId === 4) {
                    $missingArt = true;
                }
            }
        }

        if ($missingAudio) { $tags[] = 'Audio'; }
        if ($missingArt)   { $tags[] = 'Art'; }

        return $tags;
    }

    /**
     * Synchronize the Order status pivot table and discipline tags dynamically
     */

    public function syncStatusAndTags(): void
    {
        $items = $this->orderItems()
            ->join('order_item_statuses', 'order_items.order_item_status_id', '=', 'order_item_statuses.id')
            ->select('order_item_statuses.name as status_name')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $computedStatuses = [];
        foreach ($items as $item) {
            $status = $item->status_name;
            if ($status === 'In Production' || $status === 'Revision Requested') { $computedStatuses[] = 'In Progress'; }
            if (!in_array('In Progress', $computedStatuses) && $status === 'Client Review') { $computedStatuses[] = 'Client Review'; }
            if ($status === 'Unassigned' || $status === 'Still In Cart') { $computedStatuses[] = 'New Order'; }
            if ($status === 'Complete') { $computedStatuses[] = 'Complete'; }
            if ($status === 'Cancelled') { $computedStatuses[] = 'Cancelled'; }
        }

        $uniqueStatuses = array_values(array_unique($computedStatuses));
        $statusIds = OrderStatus::whereIn('name', $uniqueStatuses)->pluck('id');
        
        $this->statuses()->sync($statusIds);
    }
}