<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venue extends Model
{
    use HasFactory;

    protected $appends = ['is_international'];

    protected $fillable = [
        'name', 'street_address', 'city', 'state', 'postal_code', 'country_id', "capacity"
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getIsInternationalAttribute(): bool
    {
        return $this->country?->iso_code !== 'US';
    }
}