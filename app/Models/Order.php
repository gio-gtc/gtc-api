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
        'uuid',
        'tour_id',
        'venue_id',
        'ordered_by_id',
        'is_demo',
        'local_deliverable_email',
        'status',
        'submitted_at',
        'due_date'
    ];

    // Automatically inject these properties into all outgoing JSON serialization blocks
    protected $appends = [
        'is_awaiting_assets',
        'item_statuses'
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
     * Maps the order container to the newly built custom 'order_status' pivot table
     */
    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(OrderStatus::class);
    }

    /**
     * Header Icon Guard: Returns true if any active submitted items are missing assets.
     */
    public function getIsAwaitingAssetsAttribute(): bool
    {
        return $this->orderItems->contains(function ($item) {
            return in_array($item->status, ['Unassigned', 'In Production', 'Client Review']);
        });
    }

    /**
     * Header Badges: Returns a list of all unique Title Case statuses currently active in this order.
     */
    public function getItemStatusesAttribute(): array
    {
        return $this->statuses->pluck('name')->toArray();
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
}