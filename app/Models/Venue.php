<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'street_address', 'city', 'state', 'postal_code', 'country_id', 'capacity'
    ];

    protected $appends = ['is_international'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getIsInternationalAttribute(): bool
    {
        return $this->country ? $this->country->code !== 'US' : false;
    }
}