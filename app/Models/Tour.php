<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use HasFactory;

    protected $guarded = ['id']; // Quick fillable shorthand

    public function gtcRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gtc_rep_id');
    }

    public function voiceOver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voice_over_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected static function booted()
    {
        static::created(function ($tour) {
            $tour->orders()->create([
                'venue_id'                => null,
                'ordered_by_id'           => null,
                'is_demo'                 => true,
                'local_deliverable_email' => '',
            ]);
        });
    }
}