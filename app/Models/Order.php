<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'venue_id',
        'ordered_by_id',
        'is_demo',
        'local_deliverable_email',
        'status',
        'due_date'
    ];

    protected $casts = [
        'is_demo' => 'boolean',
    ];

    protected $appends = ['awaiting_assets'];

    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    public function venue(): BelongsTo {
        return $this->belongsTo(Venue::class)->withDefault([
            'name' => 'Demo Template' // Safe fallback for your React props
        ]);
    }

    public function ordered_by(): BelongsTo {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function showDates(): HasMany {
        return $this->hasMany(OrderShowDate::class);
    }

    public function orderItems(): HasMany {
        return $this->hasMany(OrderItem::class);
    }

    public function getAwaitingAssetsAttribute(): array {
        $awaiting = [];

        foreach ($this->orderItems as $item) {
            if (in_array($item->status, ['Still In Cart', 'Unassigned', 'Client Review'])) {
                $categoryTags = $item->orderMenuItem?->category?->required_tags ?? [];
                
                foreach ($categoryTags as $tag) {
                    $awaiting[] = $tag;
                }
            }
        }

        // Return a clean, unique list of active blockers
        return array_values(array_unique($awaiting));
    }

    public function client(): BelongsTo {
        return $this->belongsTo(User::class, 'ordered_by_id')->withDefault([
            'name' => ''
        ]);
    }
}