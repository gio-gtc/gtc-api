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
        'status',
        'local_deliverable_email',
        'due_date',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function ordered_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function showDates(): HasMany
    {
        return $this->hasMany(OrderShowDate::class);
    }
}