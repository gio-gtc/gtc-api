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

    protected $guarded = [];

    // protected $fillable = [
    //     'uuid',
    //     'tour_id',
    //     'venue_id',
    //     'ordered_by_id',
    //     'is_demo',
    //     'local_deliverable_email',
    //     'status',
    //     'submitted_at',
    //     'due_date'
    // ];

    // Automatically inject these properties into all outgoing JSON serialization blocks
    protected $appends = [
        'is_awaiting_assets',
        'item_statuses',
        'status',
        'is_international'
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
        // Removed strict relation loaded guard to allow standard lazy loading during tests
        return $this->orderItems->contains(function ($item) {
            $specs = $item->specifications;
            
            // 1. Canonical New Specification Layer: Check explicit JSON asset blocker tags
            if (is_array($specs) && isset($specs['awaiting_assets']) && !empty($specs['awaiting_assets'])) {
                return true;
            }

            // 2. Legacy Pipeline Fallback Layer: Support existing feature tests 
            // where asset alerts are determined by early workflow status phases.
            $statusName = $item->statusLookup?->name;
            return in_array($statusName, ['New Order', 'Unassigned', 'Awaiting Assets']);
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
            'Canceled',
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
                return $item->statusLookup?->orderStatus?->name;
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
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Maps the order container to the newly built custom 'order_status' pivot table
     */
    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(OrderStatus::class);
    }
}