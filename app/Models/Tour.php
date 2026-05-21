<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}