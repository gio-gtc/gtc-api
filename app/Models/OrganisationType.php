<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganisationType extends Model
{
    use HasFactory;

    // The inverse relationship back to Organisations
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisations_otypes');
    }
}